<?php
$lookupErrors = [];
$lookupSuccess = null;

function lookupTableConfig(string $entity): ?array
{
	$config = [
		'status' => [
			'table' => 'statuses',
			'value_column' => 'name',
			'label' => 'Status',
			'usage_column' => 'status',
			'extra_fields' => ['class'],
		],
		'type' => [
			'table' => 'types',
			'value_column' => 'name',
			'label' => 'Type',
			'usage_column' => 'type',
			'extra_fields' => [],
		],
		'site' => [
			'table' => 'sites',
			'value_column' => 'name',
			'label' => 'Site',
			'usage_column' => 'site',
			'extra_fields' => [],
		],
	];

	return $config[$entity] ?? null;
}

function lookupRows(Database $db, array $config): array
{
	return $db->fetchAll(
		sprintf(
			'SELECT t.*, (SELECT COUNT(*) FROM ips i WHERE i.%s = t.%s) AS usage_count FROM %s t ORDER BY t.%s ASC',
			$config['usage_column'],
			$config['value_column'],
			$config['table'],
			$config['value_column']
		)
	);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'lookup_manage') {
	$entity = $_POST['entity'] ?? '';
	$operation = $_POST['operation'] ?? '';
	$config = lookupTableConfig($entity);

	if (!$config) {
		$lookupErrors[] = 'Invalid lookup entity.';
	} else {
		$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
		$name = trim((string)($_POST['name'] ?? ''));
		$class = trim((string)($_POST['class'] ?? ''));

		if (in_array($operation, ['create', 'update'], true)) {
			if ($name === '') {
				$lookupErrors[] = $config['label'] . ' name is required.';
			} elseif (mb_strlen($name) > 100) {
				$lookupErrors[] = $config['label'] . ' name is too long.';
			}

			if ($entity === 'status' && $class === '') {
				$lookupErrors[] = 'Status badge class is required.';
			} elseif ($entity === 'status' && mb_strlen($class) > 50) {
				$lookupErrors[] = 'Status badge class is too long.';
			}
		}

		if ($operation === 'create' && empty($lookupErrors)) {
			$existing = $db->fetch(
				sprintf('SELECT id FROM %s WHERE %s = ?', $config['table'], $config['value_column']),
				[$name]
			);
			if ($existing) {
				$lookupErrors[] = $config['label'] . ' already exists.';
			} else {
				$fields = [$config['value_column'] => $name];
				if ($entity === 'status') {
					$fields['class'] = $class;
				}
				$db->create($config['table'], $fields, false);
				$lookupSuccess = $config['label'] . ' created.';
			}
		}

		if ($operation === 'update' && empty($lookupErrors)) {
			if (!$id) {
				$lookupErrors[] = 'A valid record id is required.';
			} else {
				$existing = $db->fetch(
					sprintf('SELECT * FROM %s WHERE id = ?', $config['table']),
					[$id]
				);

				if (!$existing) {
					$lookupErrors[] = $config['label'] . ' not found.';
				} else {
					$duplicate = $db->fetch(
						sprintf('SELECT id FROM %s WHERE %s = ? AND id <> ?', $config['table'], $config['value_column']),
						[$name, $id]
					);

					if ($duplicate) {
						$lookupErrors[] = $config['label'] . ' already exists.';
					} else {
						try {
							$db->beginTransaction();

							$oldName = $existing[$config['value_column']];
							if ($oldName !== $name) {
								$db->query(
									sprintf('UPDATE ips SET %s = ? WHERE %s = ?', $config['usage_column'], $config['usage_column']),
									[$name, $oldName]
								);
							}

							$fields = [$config['value_column'] => $name];
							if ($entity === 'status') {
								$fields['class'] = $class;
							}

							$db->update($config['table'], $fields, ['id' => $id], false);
							$db->commit();
							$lookupSuccess = $config['label'] . ' updated.';
						} catch (Throwable $e) {
							try { $db->rollBack(); } catch (Throwable $_) {}
							$lookupErrors[] = 'Update failed: ' . $e->getMessage();
						}
					}
				}
			}
		}

		if ($operation === 'delete' && empty($lookupErrors)) {
			if (!$id) {
				$lookupErrors[] = 'A valid record id is required.';
			} else {
				$existing = $db->fetch(
					sprintf('SELECT * FROM %s WHERE id = ?', $config['table']),
					[$id]
				);

				if (!$existing) {
					$lookupErrors[] = $config['label'] . ' not found.';
				} else {
					$usageCount = (int)$db->fetch(
						sprintf('SELECT COUNT(*) AS total FROM ips WHERE %s = ?', $config['usage_column']),
						[$existing[$config['value_column']]]
					)['total'];

					if ($usageCount > 0) {
						$lookupErrors[] = sprintf(
							'Cannot delete %s "%s" because it is used by %d IP%s.',
							strtolower($config['label']),
							$existing[$config['value_column']],
							$usageCount,
							$usageCount === 1 ? '' : 's'
						);
					} else {
						$db->delete($config['table'], ['id' => $id], false);
						$lookupSuccess = $config['label'] . ' deleted.';
					}
				}
			}
		}
	}
}

$statuses = lookupRows($db, lookupTableConfig('status'));
$types = lookupRows($db, lookupTableConfig('type'));
$sites = lookupRows($db, lookupTableConfig('site'));

$statusClassOptions = [
	'text-bg-success',
	'text-bg-info',
	'text-bg-primary',
	'text-bg-warning',
	'text-bg-danger',
	'text-bg-secondary',
	'text-bg-dark',
];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
	<h1 class="display-1 mb-0">Lookup Data</h1>
</div>

<p class="lead text-body-secondary">Manage the status, type, and site values used throughout the app.</p>

<?php if (!empty($lookupErrors)): ?>
	<div class="alert alert-danger" role="alert">
		<ul class="mb-0">
			<?php foreach ($lookupErrors as $error): ?>
				<li><?= e($error) ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<?php if ($lookupSuccess): ?>
	<div class="alert alert-success" role="alert"><?= e($lookupSuccess) ?></div>
<?php endif; ?>

<div class="row g-4">
	<div class="col-12">
		<div class="card shadow-sm">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h2 class="h4 mb-0">Statuses</h2>
					<span class="text-body-secondary small"><?= count($statuses) ?> total</span>
				</div>

				<form method="post" class="row g-3 align-items-end mb-4">
					<input type="hidden" name="form_action" value="lookup_manage">
					<input type="hidden" name="entity" value="status">
					<input type="hidden" name="operation" value="create">
					<div class="col-md-4">
						<label class="form-label" for="new-status-name">New status</label>
						<input type="text" class="form-control" id="new-status-name" name="name" placeholder="Maintenance" required>
					</div>
					<div class="col-md-4">
						<label class="form-label" for="new-status-class">Badge class</label>
						<select class="form-select" id="new-status-class" name="class" required>
							<?php foreach ($statusClassOptions as $option): ?>
								<option value="<?= e($option) ?>"><?= e($option) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-4">
						<button type="submit" class="btn btn-primary">Add Status</button>
					</div>
				</form>

				<div class="table-responsive">
					<table class="table align-middle">
						<thead>
							<tr>
								<th>Name</th>
								<th>Preview</th>
								<th>Badge class</th>
								<th>Used by</th>
								<th class="text-end">Actions</th>
							</tr>
						</thead>
							<tbody>
								<?php foreach ($statuses as $row): ?>
									<tr>
										<td colspan="4">
											<form method="post" class="row g-2 align-items-center">
												<input type="hidden" name="form_action" value="lookup_manage">
												<input type="hidden" name="entity" value="status">
												<input type="hidden" name="operation" value="update">
												<input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
												<div class="col-md-4">
													<input type="text" class="form-control" name="name" value="<?= e($row['name']) ?>" required>
												</div>
												<div class="col-md-3">
													<span class="badge rounded-pill <?= e($row['class']) ?>"><?= e($row['name']) ?></span>
												</div>
												<div class="col-md-3">
													<select class="form-select" name="class" required>
														<?php foreach ($statusClassOptions as $option): ?>
															<option value="<?= e($option) ?>" <?= $row['class'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
														<?php endforeach; ?>
													</select>
												</div>
												<div class="col-md-2 d-flex justify-content-end">
													<button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
												</div>
											</form>
										</td>
										<td class="text-end">
											<div class="mb-2 text-body-secondary small"><?= (int)$row['usage_count'] ?> IPs</div>
											<form method="post" onsubmit="return confirm('Delete this status?');">
												<input type="hidden" name="form_action" value="lookup_manage">
												<input type="hidden" name="entity" value="status">
												<input type="hidden" name="operation" value="delete">
												<input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
												<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
											</form>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<div class="col-lg-6">
		<div class="card shadow-sm h-100">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h2 class="h4 mb-0">Types</h2>
					<span class="text-body-secondary small"><?= count($types) ?> total</span>
				</div>

				<form method="post" class="row g-3 align-items-end mb-4">
					<input type="hidden" name="form_action" value="lookup_manage">
					<input type="hidden" name="entity" value="type">
					<input type="hidden" name="operation" value="create">
					<div class="col-sm-8">
						<label class="form-label" for="new-type-name">New type</label>
						<input type="text" class="form-control" id="new-type-name" name="name" placeholder="Access Point" required>
					</div>
					<div class="col-sm-4">
						<button type="submit" class="btn btn-primary">Add Type</button>
					</div>
				</form>

				<div class="table-responsive">
					<table class="table align-middle">
						<thead>
							<tr>
								<th>Name</th>
								<th>Used by</th>
								<th class="text-end">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($types as $row): ?>
								<tr>
									<td colspan="3">
										<div class="row g-2 align-items-center">
											<div class="col-md-6">
												<form method="post" class="d-flex gap-2">
													<input type="hidden" name="form_action" value="lookup_manage">
													<input type="hidden" name="entity" value="type">
													<input type="hidden" name="operation" value="update">
													<input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
													<input type="text" class="form-control" name="name" value="<?= e($row['name']) ?>" required>
													<button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
												</form>
											</div>
											<div class="col-md-3">
												<span class="text-body-secondary small"><?= (int)$row['usage_count'] ?> IPs</span>
											</div>
											<div class="col-md-3 d-flex justify-content-end">
												<form method="post" onsubmit="return confirm('Delete this type?');">
													<input type="hidden" name="form_action" value="lookup_manage">
													<input type="hidden" name="entity" value="type">
													<input type="hidden" name="operation" value="delete">
													<input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
													<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
												</form>
											</div>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<div class="col-lg-6">
		<div class="card shadow-sm h-100">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h2 class="h4 mb-0">Sites</h2>
					<span class="text-body-secondary small"><?= count($sites) ?> total</span>
				</div>

				<form method="post" class="row g-3 align-items-end mb-4">
					<input type="hidden" name="form_action" value="lookup_manage">
					<input type="hidden" name="entity" value="site">
					<input type="hidden" name="operation" value="create">
					<div class="col-sm-8">
						<label class="form-label" for="new-site-name">New site</label>
						<input type="text" class="form-control" id="new-site-name" name="name" placeholder="Queens Lane" required>
					</div>
					<div class="col-sm-4">
						<button type="submit" class="btn btn-primary">Add Site</button>
					</div>
				</form>

				<div class="table-responsive">
					<table class="table align-middle">
						<thead>
							<tr>
								<th>Name</th>
								<th>Used by</th>
								<th class="text-end">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($sites as $row): ?>
								<tr>
									<td colspan="3">
										<div class="row g-2 align-items-center">
											<div class="col-md-6">
												<form method="post" class="d-flex gap-2">
													<input type="hidden" name="form_action" value="lookup_manage">
													<input type="hidden" name="entity" value="site">
													<input type="hidden" name="operation" value="update">
													<input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
													<input type="text" class="form-control" name="name" value="<?= e($row['name']) ?>" required>
													<button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
												</form>
											</div>
											<div class="col-md-3">
												<span class="text-body-secondary small"><?= (int)$row['usage_count'] ?> IPs</span>
											</div>
											<div class="col-md-3 d-flex justify-content-end">
												<form method="post" onsubmit="return confirm('Delete this site?');">
													<input type="hidden" name="form_action" value="lookup_manage">
													<input type="hidden" name="entity" value="site">
													<input type="hidden" name="operation" value="delete">
													<input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
													<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
												</form>
											</div>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>
