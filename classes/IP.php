<?php
class IP extends Model {
	private $id;
	private $subnet_id;
	private $ip;
	private $status;
	private $hostname;
	private $owner;
	private $site;
	private $type;
	private $notes;
	private $created_at;
	private $modified_at;
	private $ping_at;
	
	protected $db;
	protected static string $table = 'ips';
	
	public function __construct($id = null) {
		$this->db = Database::getInstance();
	
		if ($id !== null) {
			$this->getOne($id);
		}
	}
	
	public function getOne($id) {
		$query = "SELECT * FROM ips WHERE id = ?";
		$row = $this->db->fetch($query, [$id]);
	
		if ($row) {
			foreach ($row as $key => $value) {
				$this->$key = $value;
			}
		}
	}

	public function exists(): bool {
		return $this->id !== null;
	}
	
	public function id() {
		return $this->id;
	}
	public function subnet_id() {
		return $this->subnet_id;
	}
	public function ip() {
		return $this->ip;
	}
	public function status() {
		return $this->status;
	}
	public function hostname() {
		return $this->hostname;
	}
	public function category() {
		return $this->hostname;
	}
	public function owner() {
		return $this->owner;
	}
	public function site() {
		return $this->site;
	}
	public function type() {
		return $this->type;
	}
	public function notes() {
		return $this->notes;
	}
	public function ping_at() {
		return $this->ping_at;
	}
	
	public function name() {
		return $this->ip;
	}
	
	public function status_badge() {
		global $ipam;
		
		$statuses = $ipam->statuses();
		
		$class = $statuses[$this->status] ?? '';
		
		if ($this->status == 'Available') {
			$class = "text-bg-success";
		} elseif($this->status == 'Allocated') {
			$class = "text-bg-info";
		} elseif($this->status == 'Reserved') {
			$class = "text-bg-primary";
		} else {
			$class = "text-bg-secondary";
		}
		
		return '<span class="badge rounded-pill ' . $class . '">' . $this->status . '</span>';
	}
	
	
	
	/**
	 * Run a safe system ping and return detailed result.
	 *
	 * Returns an array:
	 *  [
	 *    'ran' => bool,            // exec ran successfully (exec didn't fail to call)
	 *    'status' => int|null,     // exit status from ping (0 => success), null if exec didn't run
	 *    'reachable' => bool,      // true iff status === 0
	 *    'output' => array,        // array of output lines from ping
	 *    'latency_ms' => float|null // parsed latency in ms (if present), else null
	 *  ]
	 *
	 * Note: exec() may be disabled on some hosts. This method swallows noxious errors
	 * and reports them via the 'ran' flag and 'output'.
	 */
	public function pingRaw(int $timeoutSeconds = 1): array {
		if (!$this->ip) {
			return [
				'ran' => false,
				'status' => null,
				'reachable' => false,
				'output' => ['IP address is missing or invalid.'],
				'latency_ms' => null,
			];
		}
		
		$hostArg = escapeshellarg($this->ip);
	
		if (stripos(PHP_OS, 'WIN') === 0) {
			// Windows: -n 1 (one request), -w <ms> timeout in milliseconds
			$timeoutMs = max(100, $timeoutSeconds * 1000);
			$cmd = "ping -n 1 -w {$timeoutMs} {$hostArg}";
		} else {
			// Linux: -c 1 (one packet), -W 1 (timeout in seconds) ; macOS uses -W differently,
			// so we try -W (Linux). If broken, fallback to -c 1 (which at least sends one ping).
			$cmd = "ping -c 1 -W {$timeoutSeconds} {$hostArg}";
		}
	
		$output = [];
		$status = null;
	
		// Suppress PHP warnings from exec; we'll inspect status.
		@exec($cmd . ' 2>&1', $output, $status);
	
		// If exec failed to call system command, $status may be null
		if ($status === null) {
			return [
				'ran' => false,
				'status' => null,
				'reachable' => false,
				'output' => $output,
				'latency_ms' => null,
			];
		}
	
		$reachable = ($status === 0);
		$latency = $this->parseLatencyFromPingOutput($output);
		
		// update ping_at timestamp if successful
		if ($reachable == 1) {
			global $db;
			
			$fields = [
				'ping_at' => date('Y-m-d H:i:s')
			];
			
			$where = ['id' => $this->id];
			$db->update('ips', $fields, $where, false);
		}
		
		
		return [
			'ran' => true,
			'status' => (int)$status,
			'reachable' => $reachable,
			'output' => $output,
			'latency_ms' => $latency,
		];
	}
	
	/**
	 * Try to parse latency (ms) from common ping outputs.
	 * Returns float (ms) or null if not parseable.
	 */
	private function parseLatencyFromPingOutput(array $lines): ?float {
		if (empty($lines)) {
			return null;
		}
	
		// Join to one string to run regexes easier
		$text = implode("\n", $lines);
	
		// Common Linux output: "time=12.3 ms"
		if (preg_match('/time[=<]?\s*([0-9]+(?:\.[0-9]+)?)\s*ms/i', $text, $m)) {
			return (float)$m[1];
		}
	
		// Some implementations include "time=12ms" without space
		if (preg_match('/time[=<]?\s*([0-9]+(?:\.[0-9]+)?)ms/i', $text, $m2)) {
			return (float)$m2[1];
		}
	
		// Windows: "Average = 12ms" or reply lines: "time<1ms"
		if (preg_match('/Average\s*=\s*([0-9]+(?:\.[0-9]+)?)ms/i', $text, $m3)) {
			return (float)$m3[1];
		}
		if (preg_match('/time[=<]?\s*<\s*1ms/i', $text)) {
			return 0.5; // approximate for <1ms
		}
	
		return null;
	}
}
