<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$configFile = $projectRoot . '/config/config.php';
$schemaFile = __DIR__ . '/schema.sql';

function out(string $message): void
{
	if (PHP_SAPI === 'cli') {
		echo $message . PHP_EOL;
		return;
	}

	echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
}

function fail(string $message, int $code = 1): void
{
	if (PHP_SAPI !== 'cli') {
		http_response_code(500);
	}

	out($message);
	exit($code);
}

function splitSqlStatements(string $sql): array
{
	$sql = preg_replace('/^\s*--.*$/m', '', $sql);
	$sql = preg_replace('/^\s*#.*$/m', '', $sql);

	$statements = [];
	$current = '';
	$inSingle = false;
	$inDouble = false;
	$length = strlen($sql);

	for ($i = 0; $i < $length; $i++) {
		$char = $sql[$i];
		$prev = $i > 0 ? $sql[$i - 1] : '';

		if ($char === "'" && !$inDouble && $prev !== '\\') {
			$inSingle = !$inSingle;
		} elseif ($char === '"' && !$inSingle && $prev !== '\\') {
			$inDouble = !$inDouble;
		}

		if ($char === ';' && !$inSingle && !$inDouble) {
			$trimmed = trim($current);
			if ($trimmed !== '') {
				$statements[] = $trimmed;
			}
			$current = '';
			continue;
		}

		$current .= $char;
	}

	$trimmed = trim($current);
	if ($trimmed !== '') {
		$statements[] = $trimmed;
	}

	return $statements;
}

if (!file_exists($configFile)) {
	fail('Missing config/config.php. Copy config/config.php.sample first.');
}

if (!file_exists($schemaFile)) {
	fail('Missing database/schema.sql.');
}

require $configFile;

if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
	fail('Database constants are missing from config/config.php.');
}

$sql = file_get_contents($schemaFile);
if ($sql === false) {
	fail('Unable to read database/schema.sql.');
}

try {
	$pdo = new PDO(
		'mysql:host=' . DB_HOST . ';charset=utf8mb4',
		DB_USER,
		DB_PASS,
		[
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
		]
	);

	$dbName = str_replace('`', '``', DB_NAME);
	$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
	$pdo->exec("USE `{$dbName}`");

	$pdo->beginTransaction();
	foreach (splitSqlStatements($sql) as $statement) {
		$pdo->exec($statement);
	}
	$pdo->commit();
} catch (Throwable $e) {
	if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
		$pdo->rollBack();
	}

	fail('Database install failed: ' . $e->getMessage());
}

if (PHP_SAPI !== 'cli') {
	echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>IPAM Installer</title></head><body>';
}

out('Database install completed successfully.');
out('Database: ' . DB_NAME);
out('Schema file: database/schema.sql');

if (PHP_SAPI !== 'cli') {
	echo '</body></html>';
}
