<?php
require_once '../inc/autoload.php';

if (!$user->isLoggedIn()) {
	http_response_code(401);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode([
		'ok' => false,
		'error' => 'Authentication required.'
	]);
	exit;
}

header('Content-Type: application/json; charset=utf-8');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

try {
	if (!$id) {
		throw new InvalidArgumentException('A valid IP id is required.');
	}

	// Validate & instantiate IP object
	$ipObj = new IP($id);
	if (!$ipObj->exists()) {
		throw new InvalidArgumentException('IP not found.');
	}

	// Run ping once (get full details)
	$detail = $ipObj->pingRaw(1); // 1 second timeout

	if (!$detail['ran']) {
		http_response_code(500);
		echo json_encode([
			'ok' => false,
			'error' => 'Ping command could not be executed on server.',
			'details' => $detail['output']
		]);
		exit;
	}

	echo json_encode([
		'ok'        => true,
		'ip'        => $ipObj->ip(),
		'reachable' => $detail['reachable'],
		'status'    => $detail['status'],
		'latency'   => $detail['latency_ms'],  // may be null
	]);

} catch (InvalidArgumentException $e) {

	http_response_code(400);
	echo json_encode([
		'ok' => false,
		'error' => $e->getMessage()
	]);

} catch (Throwable $e) {

	http_response_code(500);
	echo json_encode([
		'ok' => false,
		'error' => 'Server error'
	]);

	// Log real error privately
	error_log($e->getMessage());
}
