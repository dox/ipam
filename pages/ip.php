<?php
$ipId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$ip = new IP($ipId);

if (!$ipId || !$ip->exists()) {
	http_response_code(404);
	?>
	<div class="alert alert-warning mt-4" role="alert">
		IP not found. <a href="index.php" class="alert-link">Return to the subnet list</a>.
	</div>
	<?php
	return;
}

$subnet = new Subnet($ip->subnet_id());

$errors = [];
$success = null;




// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	// Collect and sanitize incoming data (keep raw values for repopulation)
	$status   = trim((string)($_POST['status'] ?? ''));
	$type     = trim((string)($_POST['type'] ?? ''));
	$hostname = trim((string)($_POST['hostname'] ?? ''));
	$site     = trim((string)($_POST['site'] ?? ''));
	$owner    = trim((string)($_POST['owner'] ?? ''));
	$notes    = trim((string)($_POST['notes'] ?? ''));

	// Basic validation
	if ($status === '' || !in_array($status, array_keys($ipam->statuses()), true)) {
		$errors[] = "Please select a valid status.";
	}

	if ($type !== '' && mb_strlen($type) > 64) {
		$errors[] = "Type is too long (max 64 characters).";
	}
	if ($hostname !== '' && mb_strlen($hostname) > 255) {
		$errors[] = "Hostname is too long (max 255 characters).";
	}
	if ($owner !== '' && mb_strlen($owner) > 128) {
		$errors[] = "Owner is too long (max 128 characters).";
	}
	if ($site === '' || !in_array($site, $ipam->sites(), true)) {
		$errors[] = "Please select a valid site.";
	}

	// If no errors, persist
	if (empty($errors)) {
		try {
			// Begin transaction with global $db
			$db->beginTransaction();

			$fields = [
				'status'      => $status,
				'type'        => $type !== '' ? $type : null,
				'hostname'    => $hostname !== '' ? $hostname : null,
				'site'        => $site !== '' ? $site : null,
				'owner'       => $owner !== '' ? $owner : null,
				'notes'       => $notes !== '' ? $notes : null,
				'modified_at' => date('Y-m-d H:i:s'),
			];

			// Adjust table / pk name if your schema uses different names
			$where = ['id' => $ip->id()];

			$affected = $db->update('ips', $fields, $where, true);

			$db->commit();
			$ip = new IP($ipId);
			$success = "IP saved successfully.";
		} catch (Exception $e) {
			// Try to roll back, but suppress any rollBack errors
			try { $db->rollBack(); } catch (Exception $_) {}
			$errors[] = "Failed to save IP: " . $e->getMessage();
		}
	}
}

// Helper: value to show in form fields (prefer POST values when validation failed)
function old($field, $default = '') {
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		return isset($_POST[$field]) ? $_POST[$field] : $default;
	}
	return $default;
}
?>


<!-- Alerts -->
<?php if (!empty($errors)): ?>
	<div class="alert alert-danger">
		<ul class="mb-0">
			<?php foreach ($errors as $err): ?>
				<li><?= e($err) ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<?php if ($success): ?>
	<div class="alert alert-success">
		<?= e($success) ?>
	</div>
<?php endif; ?>

<!-- Page Header -->

<h1 class="display-1"><?= $subnet->name() ?></h1>

<nav aria-label="breadcrumb">
	<ol class="breadcrumb">
		<li class="breadcrumb-item"><a href="index.php">Home</a></li>
		<li class="breadcrumb-item"><a href="index.php?page=subnet&id=<?= $subnet->id() ?>"><?= $subnet->name() ?></a></li>
		<li class="breadcrumb-item active" aria-current="page"><?= $ip->name() ?></li>
	</ol>
</nav>

<!-- IP Summary Card -->
<div class="card shadow-sm mb-4">
	<div class="card-body">
		<div class="row align-items-center">

			<div class="col-md-6">
				<label class="form-label text-muted small">IP Address</label>
				<div class="fs-5 fw-semibold"><?= e($ip->ip()) ?></div>
			</div>

			<div class="col-md-6 text-md-end mt-3 mt-md-0">
				<label class="form-label text-muted small d-block">Live Ping</label>

				<div class="d-inline-flex align-items-center gap-2">
					<div id="ping-container"
						 class="ping-status"
						 data-id="<?= $ip->id() ?>"
						 aria-live="polite"></div>

					<button id="ping-refresh"
							class="btn btn-sm btn-outline-primary">
						<i class="bi bi-arrow-clockwise"></i>
					</button>
				</div>
			</div>

		</div>
	</div>
</div>

<hr>

<!-- Edit Form Card -->
<form method="post">

	<div class="row g-4">

		<div class="col-md-6">
			<label class="form-label">Hostname</label>
			<input type="text"
				   class="form-control"
				   name="hostname"
				   value="<?= e($_SERVER['REQUEST_METHOD'] === 'POST' ? old('hostname', '') : $ip->hostname()) ?>"
				   placeholder="server01.domain.local">
		</div>

		<div class="col-md-6">
			<label class="form-label">Owner</label>
			<input type="text"
				   class="form-control"
				   name="owner"
				   value="<?= e($_SERVER['REQUEST_METHOD'] === 'POST' ? old('owner', '') : $ip->owner()) ?>"
				   placeholder="IT Department">
		</div>

		<div class="col-md-4">
			<label class="form-label">Status</label>
			<select class="form-select" name="status">
				<?php foreach (array_keys($ipam->statuses()) as $value): ?>
					<option value="<?= e($value) ?>" <?= (old('status', $ip->status()) === $value) ? 'selected' : '' ?>>
						<?= $value ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="col-md-4">
			<label class="form-label">Type</label>
			<select class="form-select" name="type">
				<?php foreach ($ipam->types() as $value): ?>
					<option value="<?= e($value) ?>" <?= (old('type', $ip->type()) === $value) ? 'selected' : '' ?>>
						<?= $value ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="col-md-4">
			<label class="form-label">Site</label>
			<select class="form-select" name="site">
				<option value="">Select Site</option>
				<?php foreach ($ipam->sites() as $value): ?>
					<option value="<?= e($value) ?>" <?= (old('site', $ip->site()) === $value) ? 'selected' : '' ?>>
						<?= $value ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="col-12">
			<label class="form-label">Notes</label>
			<textarea class="form-control"
					  name="notes"
					  rows="4"
					  placeholder="Additional information..."><?= e($_SERVER['REQUEST_METHOD'] === 'POST' ? old('notes', '') : $ip->notes()) ?></textarea>
		</div>

	</div>

	<!-- Actions -->
	<div class="mt-4 d-flex justify-content-end gap-2">
		<button type="submit" class="btn btn-primary">
			<i class="bi bi-save me-1"></i> Save Changes
		</button>
	</div>

</form>
  
<!-- Our loader (non-module fallback) -->
  <script type="module">
	import { loadPingStatus, autoLoadAll } from '/assets/js/ping-loader.js';

	// auto-load on page ready
	document.addEventListener('DOMContentLoaded', () => {
	  autoLoadAll();

	  // manual refresh example
	  const btn = document.getElementById('ping-refresh');
	  const container = document.getElementById('ping-container');
	  if (btn && container) {
		btn.addEventListener('click', () => loadPingStatus(container));
	  }
	});
  </script>
