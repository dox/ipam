<?php
class IPAM {
	public function sites() {
		global $db;
		
		$query = "SELECT * FROM sites ORDER BY name ASC";
		$rows = $db->fetchAll($query);
		
		$sites = array();
		foreach ($rows as $row) {
			$sites[] = $row['name'];
		}
		
		return $sites;
	}
	
	public function subnets() {
		global $db;
		
		$query = "SELECT * FROM subnets ORDER BY cidr ASC";
		$rows = $db->fetchAll($query);
		
		$subnets = array();
		foreach ($rows as $row) {
			$subnets[] = new Subnet($row['id']);
		}
		
		return $subnets;
	}
	
	public function statuses() {
		global $db;
		
		$query = "SELECT * FROM statuses ORDER BY name ASC";
		$rows = $db->fetchAll($query);
		
		$statuses = array();
		foreach ($rows as $row) {
			$statuses[$row['name']] = $row['class'];
		}
		
		return $statuses;
	}
	
	public function types() {
		global $db;
		
		$query = "SELECT * FROM types ORDER BY name ASC";
		$rows = $db->fetchAll($query);
		
		$types = array();
		foreach ($rows as $row) {
			$types[] = $row['name'];
		}
		
		return $types;
	}
}
?>