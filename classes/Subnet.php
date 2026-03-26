<?php
class Subnet extends Model {
	private $id;
	private $cidr;
	private $description;
	private $created_at;
	
	protected $db;
	protected static string $table = 'subnets';
	
	public function __construct($id = null) {
		$this->db = Database::getInstance();
	
		if ($id !== null) {
			$this->getOne($id);
		}
	}
	
	public function getOne($id) {
		$query = "SELECT * FROM subnets WHERE id = ?";
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
	
	public function generateIPs($cidr, $description = null) {
		global $db;
		
		$cidr = trim($cidr ?? '');
		$description = trim((string)($description ?? ''));
		if (!isValidCIDR($cidr)) {
			throw new Exception("Invalid CIDR: $cidr");
		}

		if (cidrAddressCount($cidr) > 4096) {
			throw new Exception("Subnet is too large to create in one go. Please use /20 or smaller.");
		}
		
		// insert subnet
		$data = [
			'cidr' => $cidr,
			'description' => $description !== '' ? $description : null,
			'created_at' => date('Y-m-d H:i:s')
		];
		$this->create($data, false);
		$subnet_id = (int)$db->lastInsertId();
		
		// expand and insert IPs
		$ips = expandCIDR($cidr);
		
		foreach ($ips as $new_ip) {
			$ip = new IP();
			
			$data = [
				'subnet_id' => $subnet_id,
				'ip' => $new_ip,
				'status' => 'Available',
				'hostname' => null,
				'owner' => null,
				'site' => null,
				'type' => 'Unknown',
				'created_at' => date('Y-m-d H:i:s'),
				'modified_at' => date('Y-m-d H:i:s')
			];
			
			$ip->create($data, false);
		}
		
		return "Subnet {$cidr} added with " . count($ips) . " addresses.";
	}
	
	public function id() {
		return $this->id;
	}
	public function cidr() {
		return $this->cidr;
	}
	public function description() {
		return $this->description;
	}
	public function created_at() {
		return $this->created_at;
	}
	
	public function name() {
		return $this->description . ' (' . $this->cidr . ')';
	}
	
	public function ips() {
		$query = "SELECT * FROM ips WHERE subnet_id = ? ORDER BY INET_ATON(ip)";
		$rows = $this->db->fetchAll($query, [$this->id]);
		
		$ips = array();
		foreach ($rows as $row) {
			$ips[] = new IP($row['id']);
		}
		
		return $ips;
	}
	
	public function count_ips() {
		$query = "SELECT COUNT(*) as total FROM ips WHERE subnet_id = ? ORDER BY INET_ATON(ip)";
		$row = $this->db->fetch($query, [$this->id]);
		
		return $row['total'];
	}
	
	public function count_ips_by_status($status) {
		$query = "SELECT COUNT(*) as total FROM ips WHERE subnet_id = ? AND status = ? ORDER BY INET_ATON(ip)";
		$row = $this->db->fetch($query, [$this->id, $status]);
		
		return $row['total'];
	}
	
	public function renderProgressBar(): string
	{
		$total     = $this->count_ips();
		$allocated = $this->count_ips_by_status('Allocated');
		$reserved  = $this->count_ips_by_status('Reserved');
		$unused    = max(0, $total - $allocated - $reserved);
	
		if ($total <= 0) {
			return '<div class="progress"><div class="progress-bar w-100">No IPs</div></div>';
		}
	
		// Percentages (integer, guaranteed to total 100)
		$allocatedPct = (int) floor(($allocated / $total) * 100);
		$reservedPct  = (int) floor(($reserved  / $total) * 100);
		$unusedPct    = 100 - $allocatedPct - $reservedPct;
	
		$segments = [];
	
		if ($allocatedPct > 0) {
			$segments[] = sprintf(
				'<div class="progress" role="progressbar" aria-label="Allocated" aria-valuenow="%d" aria-valuemin="0" aria-valuemax="100" style="width: %d%%">
					<div class="progress-bar bg-info">%s</div>
				</div>',
				$allocatedPct,
				$allocatedPct,
				($allocatedPct >= 8 ? $allocated : '')
			);
		}
	
		if ($reservedPct > 0) {
			$segments[] = sprintf(
				'<div class="progress" role="progressbar" aria-label="Reserved" aria-valuenow="%d" aria-valuemin="0" aria-valuemax="100" style="width: %d%%">
					<div class="progress-bar bg-warning">%s</div>
				</div>',
				$reservedPct,
				$reservedPct,
				($reservedPct >= 8 ? $reserved : '')
			);
		}
	
		if ($unusedPct > 0) {
			$segments[] = sprintf(
				'<div class="progress" role="progressbar" aria-label="Unused" aria-valuenow="%d" aria-valuemin="0" aria-valuemax="100" style="width: %d%%">
					<div class="progress-bar bg-success">%s</div>
				</div>',
				$unusedPct,
				$unusedPct,
				($unusedPct >= 8 ? $unused : '')
			);
		}
	
		return '<div class="progress-stacked">'
			. implode("\n", $segments)
			. '</div>';
	}
}
