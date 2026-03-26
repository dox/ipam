<!DOCTYPE html>
<?php
require_once "inc/autoload.php";

if (!$user->isLoggedIn()) {
	header("Location: login.php");
	exit;
}
?>
<html lang="en" data-bs-theme="auto">
<head>
	<?php require_once "layout/html_head.php"; ?>
</head>
<body>
	<?php require_once "layout/header.php"; ?>
	
	<div class="container">
		<?php
		// Determine which page to show
		$allowed = ['404','index','subnet','ip','test'];
		
		$page = $_GET['page'] ?? 'index';
		
		if (!in_array($page, $allowed, true)) {
			$page = '404';
		}
		
		require_once __DIR__ . "/pages/{$page}.php";
		?>
	</div>
	
	<?php require_once "layout/footer.php"; ?>
</body>
</html>
