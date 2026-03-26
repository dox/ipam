<?php
$createSubnetErrors = [];
$createSubnetSuccess = null;
$createSubnetInput = [
	'cidr' => '',
	'description' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'create_subnet') {
	$createSubnetInput = [
		'cidr' => trim((string)($_POST['cidr'] ?? '')),
		'description' => trim((string)($_POST['description'] ?? '')),
	];

	if (!isValidCIDR($createSubnetInput['cidr'])) {
		$createSubnetErrors[] = 'Please enter a valid IPv4 CIDR block.';
	}

	if ($createSubnetInput['description'] !== '' && mb_strlen($createSubnetInput['description']) > 255) {
		$createSubnetErrors[] = 'Description is too long (max 255 characters).';
	}

	if (empty($createSubnetErrors) && cidrAddressCount($createSubnetInput['cidr']) > 4096) {
		$createSubnetErrors[] = 'Subnet is too large to create in one go. Please use /20 or smaller.';
	}

	if (empty($createSubnetErrors)) {
		$existingSubnet = $db->fetch('SELECT id FROM subnets WHERE cidr = ?', [$createSubnetInput['cidr']]);
		if ($existingSubnet) {
			$createSubnetErrors[] = 'That subnet already exists.';
		}
	}

	if (empty($createSubnetErrors)) {
		try {
			$subnet = new Subnet();
			$createSubnetSuccess = $subnet->generateIPs($createSubnetInput['cidr'], $createSubnetInput['description']);
			$createSubnetInput = [
				'cidr' => '',
				'description' => '',
			];
		} catch (Throwable $e) {
			$createSubnetErrors[] = $e->getMessage();
		}
	}
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
	<h1 class="display-1 mb-0">Subnets</h1>
	<button
		type="button"
		class="btn btn-primary"
		data-bs-toggle="modal"
		data-bs-target="#create-subnet-modal"
	>
		<i class="bi bi-plus-lg me-1"></i> Add Subnet
	</button>
</div>

<?php if (!empty($createSubnetErrors)): ?>
	<div class="alert alert-danger" role="alert">
		<ul class="mb-0">
			<?php foreach ($createSubnetErrors as $error): ?>
				<li><?= e($error) ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<?php if ($createSubnetSuccess): ?>
	<div class="alert alert-success" role="alert"><?= e($createSubnetSuccess) ?></div>
<?php endif; ?>

<table class="table table-striped">
	<thead>
		<tr>
			<th style="width:15%;">CIDR</th>
			<th style="width:20%;">Description</th>
			<th>Stats</th>
			<th style="width:15%;">Actions</th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach ($ipam->subnets() AS $subnet) {
			echo '<tr>
				<td><a href="index.php?page=subnet&id=' . $subnet->id() . '">' . e($subnet->cidr()) . '</a> (' . $subnet->count_ips() . ')</td>
				<td>' . e($subnet->description()) . '</td>
				<td> ' . $subnet->renderProgressBar() . '</td>
				<td>
					<a class="btn" href="index.php?page=subnet&id=' . $subnet->id() . '"><i class="bi bi-eye"></i></a>
				</td>
			</tr>';
		}
		?>
		
	</tbody>
</table>

<div class="modal fade" id="create-subnet-modal" tabindex="-1" aria-labelledby="create-subnet-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered">
		<div class="modal-content">
			<form method="post">
				<div class="modal-header">
					<h2 class="modal-title fs-5" id="create-subnet-modal-label">Add Subnet</h2>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" name="form_action" value="create_subnet">
					<div class="row g-3">
						<div class="col-md-5">
							<label class="form-label" for="cidr">CIDR</label>
							<input type="text" class="form-control" id="cidr" name="cidr" value="<?= e($createSubnetInput['cidr']) ?>" placeholder="192.168.10.0/24" required>
							<div class="form-text">Creating a subnet also creates its IP address records.</div>
						</div>
						<div class="col-md-7">
							<label class="form-label" for="description">Description</label>
							<input type="text" class="form-control" id="description" name="description" value="<?= e($createSubnetInput['description']) ?>" placeholder="Office LAN">
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Create Subnet</button>
				</div>
			</form>
		</div>
	</div>
</div>

<?php if (!empty($createSubnetErrors)): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
	bootstrap.Modal.getOrCreateInstance(document.getElementById('create-subnet-modal')).show();
});
</script>
<?php endif; ?>
