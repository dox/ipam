<?php
abstract class Model {
	protected $db;
	protected static string $table;

	public function __construct() {
		$this->db = Database::getInstance();
	}

	public function getOne($id) {
		$query = "SELECT * FROM " . static::$table . " WHERE id = ?";
		$row = $this->db->fetch($query, [$id]);
		
		if ($row) {
			return $row;
		}
	}
	
	/**
	 * Generic INSERT for all subclasses
	 *
	 * @param array $data  Associative array of column => value
	 * @return int|false   Inserted row ID or false on failure
	 */
	public function create(array $data, bool $log = true) {
		if (empty($data)) {
			throw new InvalidArgumentException("Insert data cannot be empty.");
		}
	
		// JSON-encode arrays automatically
		$params = [];
		foreach ($data as $col => $val) {
			if (is_array($val)) {
				$val = json_encode($val); // <-- encode arrays
			}
			$params[":$col"] = $val;
		}
	
		$columns = array_keys($data);
		$placeholders = array_map(fn($c) => ':' . $c, $columns);
	
		$sql = sprintf(
			"INSERT INTO %s (%s) VALUES (%s)",
			static::$table,
			implode(', ', $columns),
			implode(', ', $placeholders)
		);
	
		$stmt = $this->db->query($sql, $params);
		$insertId = $stmt ? $this->db->lastInsertId() : false;
	
		// Optional logging
		if ($log && $insertId !== false && static::$table !== 'new_logs') {
			$this->logInsert($insertId, $data);
		}
	
		return $insertId;
	}
	
	private function logInsert(int $id, array $data): void {
		// We reference Log dynamically to avoid recursion
		$log = new Log();
	
		$summary = sprintf(
			'Inserted into %s (ID %d): %s',
			static::$table,
			$id,
			json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
		);
	
		$log->add($summary, Log::INFO);
	}
}

class Log extends Model {
	protected static string $table = 'logs';
	
	// Define standard log levels
	public const SUCCESS = 'SUCCESS';
	public const INFO    = 'INFO';
	public const WARNING = 'WARNING';
	public const ERROR   = 'ERROR';
	public const DEBUG   = 'DEBUG';
	
	public function add(
		string $description,
		string $category = null,
		?string $result = self::INFO
	): bool {
		global $user;
	
		$sql = "INSERT INTO " . static::$table . " 
				(username, ip, description, category, result, date)
				VALUES (:username, :ip, :description, :category, :result, NOW())";
		
		// Log impersonations
		$original_username = ($_SESSION['impersonation_backup']['samaccountname'] ?? 'Unknown');
		if (isset($_SESSION['impersonating'])) {
			$description = $description . " [Impersonated By: " . $original_username . "]";
		}
		
		$params = [
			':username'    => $user?->getUsername() ?? null,
			':ip'          => ip2long($this->detectIp()),
			':description' => $description,
			':category'    => strtoupper($category),
			':result'      => strtoupper($result),
		];
	
		$stmt = $this->db->query($sql, $params);
		return $stmt !== false;
	}
	
	public function getRecent(int $age = 30): array {
		$sql = "SELECT *
				FROM " . static::$table . "
				WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
				ORDER BY date DESC";
	
		return $this->db->fetchAll($sql, [$age]);
	}
	
	/**
	 * Detect client IP address.
	 *
	 * @return string
	 */
	private function detectIp(): string {
		// Running from CLI
		if (PHP_SAPI === 'cli') {
			return '127.0.0.1';
		}
	
		// Running via web request
		return $_SERVER['REMOTE_ADDR'] 
			?? $_SERVER['HTTP_CLIENT_IP'] 
			?? $_SERVER['HTTP_X_FORWARDED_FOR'] 
			?? 'UNKNOWN';
	}
}





