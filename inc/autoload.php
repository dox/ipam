<?php
// Start session
session_start();

// Load configuration
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
	http_response_code(500);
	die('<h1>Missing configuration</h1><p>Copy <code>config/config.php.sample</code> to <code>config/config.php</code> and fill in your local settings.</p>');
}

require_once $configFile;
require_once __DIR__ . '/../inc/global.php';

// Set debugging
if (APP_DEBUG) {
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1');
	error_reporting(E_ALL);

	set_error_handler(function ($errno, $errstr, $errfile, $errline) {
		echo "<div class=\"alert alert-danger\" role=\"alert\">";
		echo "<strong>PHP ERROR:</strong> [$errno] $errstr<br>";
		echo "In <strong>$errfile</strong> on line <strong>$errline</strong>";
		echo "</div>";
		return false;
	});

	set_exception_handler(function ($e) {
		echo "<div class=\"alert alert-warning\" role=\"alert\">";
		echo "<strong>UNCAUGHT EXCEPTION:</strong> " . get_class($e) . "<br>";
		echo $e->getMessage() . "<br><br>" . $e->getTraceAsString();
		echo "</div>";
	});
} else {
	ini_set('display_errors', '0');
	ini_set('display_startup_errors', '0');
	error_reporting(0);

	ini_set('log_errors', '1');
	ini_set('error_log', __DIR__ . '/php-error.log');
}

// Register autoloader
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($composerAutoload)) {
	http_response_code(500);
	die('<h1>Missing dependencies</h1><p>Run <code>composer install</code> to install the required PHP packages.</p>');
}

require_once $composerAutoload;

// Load classes
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Model.php';
require_once __DIR__ . '/../classes/IPAM.php';
require_once __DIR__ . '/../classes/Subnet.php';
require_once __DIR__ . '/../classes/IP.php';

// Initialise shared database instance
try {
	global $db;
	$db = Database::getInstance();
} catch (Throwable $e) {
	error_log("Database connection failed: " . $e->getMessage());
	die('<h1>Database connection error: ' . htmlspecialchars($e->getMessage()) . '</h1>');
}

// Create shared objects
//$log      = new Log();
//$terms    = new Terms();
//$meals    = new Meals();
$user     = new User();
$ipam     = new IPAM();
//$settings = new Settings();
