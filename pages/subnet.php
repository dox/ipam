<?php
$subnetId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$subnet = new Subnet($subnetId);

if (!$subnetId || !$subnet->exists()) {
	http_response_code(404);
	?>
	<div class="alert alert-warning mt-4" role="alert">
		Subnet not found. <a href="index.php" class="alert-link">Return to the subnet list</a>.
	</div>
	<?php
	return;
}

$bulkErrors = [];
$bulkSuccess = null;
$selectedIds = [];
$clearFields = [
	'hostname' => false,
	'owner' => false,
	'notes' => false,
];
$bulkInput = [
	'status' => '',
	'type' => '',
	'site' => '',
	'owner' => '',
	'hostname' => '',
	'notes' => '',
];
$createIpErrors = [];
$createIpSuccess = null;
$createIpInput = [
	'ip' => '',
	'status' => 'Available',
	'hostname' => '',
	'type' => '',
	'site' => '',
	'owner' => '',
	'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'create_ip') {
	$createIpInput = [
		'ip' => trim((string)($_POST['ip'] ?? '')),
		'status' => trim((string)($_POST['status'] ?? 'Available')),
		'hostname' => trim((string)($_POST['hostname'] ?? '')),
		'type' => trim((string)($_POST['type'] ?? '')),
		'site' => trim((string)($_POST['site'] ?? '')),
		'owner' => trim((string)($_POST['owner'] ?? '')),
		'notes' => trim((string)($_POST['notes'] ?? '')),
	];

	if (!filter_var($createIpInput['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		$createIpErrors[] = 'Please enter a valid IPv4 address.';
	} elseif (!ipInCidr($createIpInput['ip'], $subnet->cidr())) {
		$createIpErrors[] = 'The IP address must fall within this subnet.';
	}

	if (!in_array($createIpInput['status'], array_keys($ipam->statuses()), true)) {
		$createIpErrors[] = 'Please choose a valid status.';
	}

	if ($createIpInput['type'] !== '' && !in_array($createIpInput['type'], $ipam->types(), true)) {
		$createIpErrors[] = 'Please choose a valid type.';
	}

	if ($createIpInput['site'] !== '' && !in_array($createIpInput['site'], $ipam->sites(), true)) {
		$createIpErrors[] = 'Please choose a valid site.';
	}

	if ($createIpInput['hostname'] !== '' && mb_strlen($createIpInput['hostname']) > 255) {
		$createIpErrors[] = 'Hostname is too long (max 255 characters).';
	}

	if ($createIpInput['owner'] !== '' && mb_strlen($createIpInput['owner']) > 128) {
		$createIpErrors[] = 'Owner is too long (max 128 characters).';
	}

	if (empty($createIpErrors)) {
		$existingIp = $db->fetch('SELECT id FROM ips WHERE subnet_id = ? AND ip = ?', [$subnetId, $createIpInput['ip']]);
		if ($existingIp) {
			$createIpErrors[] = 'That IP already exists in this subnet.';
		}
	}

	if (empty($createIpErrors)) {
		try {
			$ip = new IP();
			$ip->create([
				'subnet_id' => $subnetId,
				'ip' => $createIpInput['ip'],
				'status' => $createIpInput['status'],
				'hostname' => $createIpInput['hostname'] !== '' ? $createIpInput['hostname'] : null,
				'owner' => $createIpInput['owner'] !== '' ? $createIpInput['owner'] : null,
				'site' => $createIpInput['site'] !== '' ? $createIpInput['site'] : null,
				'type' => $createIpInput['type'] !== '' ? $createIpInput['type'] : null,
				'notes' => $createIpInput['notes'] !== '' ? $createIpInput['notes'] : null,
				'created_at' => date('Y-m-d H:i:s'),
				'modified_at' => date('Y-m-d H:i:s'),
			], false);
			$createIpSuccess = 'IP address created.';
			$createIpInput = [
				'ip' => '',
				'status' => 'Available',
				'hostname' => '',
				'type' => '',
				'site' => '',
				'owner' => '',
				'notes' => '',
			];
		} catch (Throwable $e) {
			$createIpErrors[] = 'Failed to create IP: ' . $e->getMessage();
		}
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'bulk_update_ips') {
	$selectedIds = array_values(array_unique(array_filter(
		array_map('intval', $_POST['selected_ips'] ?? []),
		fn ($id) => $id > 0
	)));

	$allowedStatuses = array_keys($ipam->statuses());
	$allowedTypes = $ipam->types();
	$allowedSites = $ipam->sites();

	$bulkInput = [
		'status' => trim((string)($_POST['bulk_status'] ?? '')),
		'type' => trim((string)($_POST['bulk_type'] ?? '')),
		'site' => trim((string)($_POST['bulk_site'] ?? '')),
		'owner' => trim((string)($_POST['bulk_owner'] ?? '')),
		'hostname' => trim((string)($_POST['bulk_hostname'] ?? '')),
		'notes' => trim((string)($_POST['bulk_notes'] ?? '')),
	];

	$clearFields = [
		'hostname' => isset($_POST['clear_hostname']),
		'owner' => isset($_POST['clear_owner']),
		'notes' => isset($_POST['clear_notes']),
	];

	if (empty($selectedIds)) {
		$bulkErrors[] = 'Select at least one IP to update.';
	}

	if ($bulkInput['status'] !== '' && !in_array($bulkInput['status'], $allowedStatuses, true)) {
		$bulkErrors[] = 'Please choose a valid status.';
	}

	if ($bulkInput['type'] !== '' && !in_array($bulkInput['type'], $allowedTypes, true)) {
		$bulkErrors[] = 'Please choose a valid type.';
	}

	if ($bulkInput['site'] !== '' && !in_array($bulkInput['site'], $allowedSites, true)) {
		$bulkErrors[] = 'Please choose a valid site.';
	}

	if ($bulkInput['hostname'] !== '' && mb_strlen($bulkInput['hostname']) > 255) {
		$bulkErrors[] = 'Hostname is too long (max 255 characters).';
	}

	if ($bulkInput['owner'] !== '' && mb_strlen($bulkInput['owner']) > 128) {
		$bulkErrors[] = 'Owner is too long (max 128 characters).';
	}

	$hasUpdates = false;
	foreach ($bulkInput as $value) {
		if ($value !== '') {
			$hasUpdates = true;
			break;
		}
	}
	if (!$hasUpdates && !in_array(true, $clearFields, true)) {
		$bulkErrors[] = 'Choose at least one field to change or clear.';
	}

	if (empty($bulkErrors)) {
		$placeholders = implode(', ', array_fill(0, count($selectedIds), '?'));
		$params = array_merge([$subnetId], $selectedIds);
		$validIds = array_map(
			'intval',
			$db->fetchColumn(
				"SELECT id FROM ips WHERE subnet_id = ? AND id IN ($placeholders)",
				$params
			)
		);

		if (count($validIds) !== count($selectedIds)) {
			$bulkErrors[] = 'One or more selected IPs are invalid for this subnet.';
		} else {
			$fields = [];
			if ($bulkInput['status'] !== '') {
				$fields['status'] = $bulkInput['status'];
			}
			if ($bulkInput['type'] !== '') {
				$fields['type'] = $bulkInput['type'];
			}
			if ($bulkInput['site'] !== '') {
				$fields['site'] = $bulkInput['site'];
			}
			if ($bulkInput['hostname'] !== '') {
				$fields['hostname'] = $bulkInput['hostname'];
			} elseif ($clearFields['hostname']) {
				$fields['hostname'] = null;
			}
			if ($bulkInput['owner'] !== '') {
				$fields['owner'] = $bulkInput['owner'];
			} elseif ($clearFields['owner']) {
				$fields['owner'] = null;
			}
			if ($bulkInput['notes'] !== '') {
				$fields['notes'] = $bulkInput['notes'];
			} elseif ($clearFields['notes']) {
				$fields['notes'] = null;
			}

			$updated = 0;

			try {
				$db->beginTransaction();
				foreach ($validIds as $ipId) {
					$ipFields = $fields;
					$ipFields['modified_at'] = date('Y-m-d H:i:s');
					$db->update('ips', $ipFields, ['id' => $ipId], false);
					$updated++;
				}
				$db->commit();
				$bulkSuccess = sprintf('Updated %d IP%s.', $updated, $updated === 1 ? '' : 's');
				$bulkInput = [
					'status' => '',
					'type' => '',
					'site' => '',
					'owner' => '',
					'hostname' => '',
					'notes' => '',
				];
			} catch (Throwable $e) {
				try { $db->rollBack(); } catch (Throwable $_) {}
				$bulkErrors[] = 'Bulk update failed: ' . $e->getMessage();
			}
		}
	}
}

$ips = $subnet->ips();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
	<h1 class="display-1 mb-0"><?= $subnet->name() ?></h1>
	<button
		type="button"
		class="btn btn-primary"
		data-bs-toggle="modal"
		data-bs-target="#create-ip-modal"
	>
		<i class="bi bi-plus-lg me-1"></i> Add IP
	</button>
</div>

<nav aria-label="breadcrumb">
	<ol class="breadcrumb">
		<li class="breadcrumb-item"><a href="index.php">Home</a></li>
		<li class="breadcrumb-item active" aria-current="page"><?= $subnet->name() ?></li>
	</ol>
</nav>

<div class="row mb-3">
	<div class="col">
		<input type="text" class="form-control form-control-lg" id="tableSearch" placeholder="Quick search" autocomplete="off" spellcheck="false">
	</div>
</div>

<?php if (!empty($createIpErrors)): ?>
	<div class="alert alert-danger" role="alert">
		<ul class="mb-0">
			<?php foreach ($createIpErrors as $error): ?>
				<li><?= e($error) ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<?php if ($createIpSuccess): ?>
	<div class="alert alert-success" role="alert"><?= e($createIpSuccess) ?></div>
<?php endif; ?>

<?php if (!empty($bulkErrors)): ?>
	<div class="alert alert-danger" role="alert">
		<ul class="mb-0">
			<?php foreach ($bulkErrors as $error): ?>
				<li><?= e($error) ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<?php if ($bulkSuccess): ?>
	<div class="alert alert-success" role="alert"><?= e($bulkSuccess) ?></div>
<?php endif; ?>

<form method="post" id="bulk-edit-form">
	<input type="hidden" name="form_action" value="bulk_update_ips">

	<div class="accordion mb-4" id="bulk-edit-accordion">
		<div class="accordion-item shadow-sm">
			<h2 class="accordion-header">
				<button
					class="accordion-button collapsed"
					type="button"
					id="bulk-edit-toggle"
					data-bs-toggle="collapse"
					data-bs-target="#bulk-edit-collapse"
					aria-expanded="false"
					aria-controls="bulk-edit-collapse"
				>
					<span>Bulk Edit</span>
					<span class="ms-3 text-body-secondary small"><span id="selected-count">0</span> selected</span>
				</button>
			</h2>
			<div id="bulk-edit-collapse" class="accordion-collapse collapse<?= (!empty($bulkErrors) || $bulkSuccess || !empty($selectedIds)) ? ' show' : '' ?>" data-bs-parent="#bulk-edit-accordion">
				<div class="accordion-body">
					<p class="text-body-secondary mb-3">Select IPs below, then set only the fields you want to change.</p>

					<div class="row g-3">
						<div class="col-md-4">
							<label class="form-label" for="bulk-status">Status</label>
							<select class="form-select" id="bulk-status" name="bulk_status">
								<option value="">Leave unchanged</option>
								<?php foreach (array_keys($ipam->statuses()) as $value): ?>
									<option value="<?= e($value) ?>" <?= $bulkInput['status'] === $value ? 'selected' : '' ?>><?= e($value) ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-4">
							<label class="form-label" for="bulk-type">Type</label>
							<select class="form-select" id="bulk-type" name="bulk_type">
								<option value="">Leave unchanged</option>
								<?php foreach ($ipam->types() as $value): ?>
									<option value="<?= e($value) ?>" <?= $bulkInput['type'] === $value ? 'selected' : '' ?>><?= e($value) ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-4">
							<label class="form-label" for="bulk-site">Site</label>
							<select class="form-select" id="bulk-site" name="bulk_site">
								<option value="">Leave unchanged</option>
								<?php foreach ($ipam->sites() as $value): ?>
									<option value="<?= e($value) ?>" <?= $bulkInput['site'] === $value ? 'selected' : '' ?>><?= e($value) ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="col-md-6">
							<label class="form-label" for="bulk-hostname">Hostname</label>
							<input type="text" class="form-control" id="bulk-hostname" name="bulk_hostname" value="<?= e($bulkInput['hostname']) ?>" placeholder="Set the same hostname on all selected IPs">
							<div class="form-check mt-2">
								<input class="form-check-input" type="checkbox" value="1" id="clear-hostname" name="clear_hostname" <?= $clearFields['hostname'] ? 'checked' : '' ?>>
								<label class="form-check-label" for="clear-hostname">Clear hostname</label>
							</div>
						</div>
						<div class="col-md-6">
							<label class="form-label" for="bulk-owner">Owner</label>
							<input type="text" class="form-control" id="bulk-owner" name="bulk_owner" value="<?= e($bulkInput['owner']) ?>" placeholder="Set the same owner on all selected IPs">
							<div class="form-check mt-2">
								<input class="form-check-input" type="checkbox" value="1" id="clear-owner" name="clear_owner" <?= $clearFields['owner'] ? 'checked' : '' ?>>
								<label class="form-check-label" for="clear-owner">Clear owner</label>
							</div>
						</div>

						<div class="col-12">
							<label class="form-label" for="bulk-notes">Notes</label>
							<textarea class="form-control" id="bulk-notes" name="bulk_notes" rows="3" placeholder="Replace notes on all selected IPs"><?= e($bulkInput['notes']) ?></textarea>
							<div class="form-check mt-2">
								<input class="form-check-input" type="checkbox" value="1" id="clear-notes" name="clear_notes" <?= $clearFields['notes'] ? 'checked' : '' ?>>
								<label class="form-check-label" for="clear-notes">Clear notes</label>
							</div>
						</div>
					</div>

					<div class="d-flex flex-wrap gap-2 mt-4">
						<button type="submit" class="btn btn-primary" id="bulk-submit" disabled>Update Selected IPs</button>
						<button type="button" class="btn btn-outline-secondary" id="select-visible">Select Visible</button>
						<button type="button" class="btn btn-outline-secondary" id="clear-selection">Clear Selection</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<table class="table table-striped table-hover" id="ip_table">
		<thead>
			<tr>
				<th class="bulk-select-col">
					<input type="checkbox" class="form-check-input" id="select-all-ips" aria-label="Select all visible IPs">
				</th>
				<th>IP</th>
				<th>Status</th>
				<th>Hostname</th>
				<th>Type</th>
				<th>Site</th>
				<th>Owner</th>
				<th>Notes</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($ips AS $ip) {
				if ($ip->type() == 'Special') {
					$class = "table-warning";
				} else {
					$class = "";
				}
				echo '<tr class="' . $class . '">
						<td class="bulk-select-col align-middle"><input class="form-check-input ip-select" type="checkbox" name="selected_ips[]" value="' . $ip->id() . '" ' . (in_array($ip->id(), $selectedIds, true) ? 'checked' : '') . ' aria-label="Select IP ' . e($ip->ip()) . '"></td>
					<td><a href="index.php?page=ip&id=' . $ip->id() . '">' . e($ip->ip()) . '</a></td>
					<td>' . $ip->status_badge() . '</td>
					<td>' . e($ip->hostname()) . '</td>
					<td>' . e($ip->type()) . '</td>
					<td>' . e($ip->site()) . '</td>
					<td>' . e($ip->owner()) . '</td>
					<td>' . e($ip->notes()) . '</td>
				</tr>';
			}
			?>
		</tbody>
	</table>
</form>

<div class="modal fade" id="create-ip-modal" tabindex="-1" aria-labelledby="create-ip-modal-label" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-centered">
		<div class="modal-content">
			<form method="post">
				<div class="modal-header">
					<h2 class="modal-title fs-5" id="create-ip-modal-label">Add IP to <?= e($subnet->name()) ?></h2>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" name="form_action" value="create_ip">
					<div class="row g-3">
						<div class="col-md-3">
							<label class="form-label" for="create-ip-address">IP Address</label>
							<input type="text" class="form-control" id="create-ip-address" name="ip" value="<?= e($createIpInput['ip']) ?>" placeholder="192.168.10.15" required>
						</div>
						<div class="col-md-3">
							<label class="form-label" for="create-ip-status">Status</label>
							<select class="form-select" id="create-ip-status" name="status">
								<?php foreach (array_keys($ipam->statuses()) as $value): ?>
									<option value="<?= e($value) ?>" <?= $createIpInput['status'] === $value ? 'selected' : '' ?>><?= e($value) ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-3">
							<label class="form-label" for="create-ip-type">Type</label>
							<select class="form-select" id="create-ip-type" name="type">
								<option value="">None</option>
								<?php foreach ($ipam->types() as $value): ?>
									<option value="<?= e($value) ?>" <?= $createIpInput['type'] === $value ? 'selected' : '' ?>><?= e($value) ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-3">
							<label class="form-label" for="create-ip-site">Site</label>
							<select class="form-select" id="create-ip-site" name="site">
								<option value="">None</option>
								<?php foreach ($ipam->sites() as $value): ?>
									<option value="<?= e($value) ?>" <?= $createIpInput['site'] === $value ? 'selected' : '' ?>><?= e($value) ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-4">
							<label class="form-label" for="create-ip-hostname">Hostname</label>
							<input type="text" class="form-control" id="create-ip-hostname" name="hostname" value="<?= e($createIpInput['hostname']) ?>">
						</div>
						<div class="col-md-4">
							<label class="form-label" for="create-ip-owner">Owner</label>
							<input type="text" class="form-control" id="create-ip-owner" name="owner" value="<?= e($createIpInput['owner']) ?>">
						</div>
						<div class="col-md-4">
							<label class="form-label" for="create-ip-notes">Notes</label>
							<input type="text" class="form-control" id="create-ip-notes" name="notes" value="<?= e($createIpInput['notes']) ?>">
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Create IP</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
function debounce(func, delay) {
	let timeout;
	return function () {
		clearTimeout(timeout);
		timeout = setTimeout(() => func.apply(this, arguments), delay);
	};
}

const searchInput = document.getElementById("tableSearch");
const selectAll = document.getElementById("select-all-ips");
const selectVisibleButton = document.getElementById("select-visible");
const clearSelectionButton = document.getElementById("clear-selection");
const selectedCount = document.getElementById("selected-count");
const bulkSubmit = document.getElementById("bulk-submit");
const bulkCollapseElement = document.getElementById("bulk-edit-collapse");
const bulkCollapse = bootstrap.Collapse.getOrCreateInstance(bulkCollapseElement, { toggle: false });
const ipCheckboxes = () => Array.from(document.querySelectorAll(".ip-select"));

function visibleRows() {
	return Array.from(document.querySelectorAll("#ip_table tbody tr")).filter(row => row.style.display !== "none");
}

function updateBulkSelectionState() {
	const selected = ipCheckboxes().filter(input => input.checked).length;
	selectedCount.textContent = selected;
	bulkSubmit.disabled = selected === 0;

	if (selected > 0 && !bulkCollapseElement.classList.contains("show")) {
		bulkCollapse.show();
	}

	const visibleCheckboxes = visibleRows()
		.map(row => row.querySelector(".ip-select"))
		.filter(Boolean);

	if (visibleCheckboxes.length === 0) {
		selectAll.checked = false;
		selectAll.indeterminate = false;
		return;
	}

	const checkedVisible = visibleCheckboxes.filter(input => input.checked).length;
	selectAll.checked = checkedVisible === visibleCheckboxes.length;
	selectAll.indeterminate = checkedVisible > 0 && checkedVisible < visibleCheckboxes.length;
}

searchInput.addEventListener("keyup", debounce(function () {
	const filter = this.value.toLowerCase();
	const rows = document.querySelectorAll("#ip_table tbody tr");

	rows.forEach(row => {
		const text = row.textContent.toLowerCase();
		row.style.display = text.includes(filter) ? "" : "none";
	});
	updateBulkSelectionState();
}, 150));

selectAll.addEventListener("change", function () {
	visibleRows().forEach(row => {
		const checkbox = row.querySelector(".ip-select");
		if (checkbox) {
			checkbox.checked = this.checked;
		}
	});
	updateBulkSelectionState();
});

selectVisibleButton.addEventListener("click", function () {
	visibleRows().forEach(row => {
		const checkbox = row.querySelector(".ip-select");
		if (checkbox) {
			checkbox.checked = true;
		}
	});
	updateBulkSelectionState();
});

clearSelectionButton.addEventListener("click", function () {
	ipCheckboxes().forEach(input => {
		input.checked = false;
	});
	updateBulkSelectionState();
});

document.addEventListener("change", function (event) {
	if (event.target.classList.contains("ip-select")) {
		updateBulkSelectionState();
	}
});

updateBulkSelectionState();
</script>

<?php if (!empty($createIpErrors)): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
	bootstrap.Modal.getOrCreateInstance(document.getElementById('create-ip-modal')).show();
});
</script>
<?php endif; ?>
