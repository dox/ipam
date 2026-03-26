<?php
function e($value): string {
	return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function isValidCIDR(string $cidr): bool {
	if (strpos($cidr, '/') === false) return false;
	[$ip, $mask] = explode('/', $cidr, 2);
	if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
	$mask = intval($mask);
	return $mask >= 0 && $mask <= 32;
}

function cidrToRange(string $cidr): array {
	[$ip, $mask] = explode('/', $cidr, 2);
	$mask = intval($mask);
	$ipLong = (int)sprintf('%u', ip2long($ip));
	if ($mask === 32) return [$ipLong, $ipLong];
	if ($mask === 0) return [0, 4294967295];
	$hostBits = 32 - $mask;
	$blockSize = (int)pow(2, $hostBits);
	$start = (int)(floor($ipLong / $blockSize) * $blockSize);
	$end = $start + $blockSize - 1;
	return [$start, $end];
}

function expandCIDR(string $cidr): array {
	[$start, $end] = cidrToRange($cidr);
	$ips = [];
	for ($l = $start; $l <= $end; $l++) {
		$ips[] = long2ip($l);
	}
	return $ips;
}

function cidrAddressCount(string $cidr): int {
	[$start, $end] = cidrToRange($cidr);
	return ($end - $start) + 1;
}

function ipInCidr(string $ip, string $cidr): bool {
	if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		return false;
	}

	[$start, $end] = cidrToRange($cidr);
	$ipLong = (int)sprintf('%u', ip2long($ip));
	return $ipLong >= $start && $ipLong <= $end;
}
