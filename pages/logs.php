<?php
$age = filter_input(INPUT_GET, 'days', FILTER_VALIDATE_INT);
if (!$age || $age < 1) {
	$age = 30;
}
if ($age > 365) {
	$age = 365;
}

$log = new Log();
$entries = $log->getRecent($age);

function logIpToString($value): string
{
	if ($value === null || $value === '') {
		return '';
	}

	return long2ip((int)$value) ?: (string)$value;
}

function logResultBadgeClass(?string $result): string
{
	$result = strtoupper((string)$result);

	return match ($result) {
		'SUCCESS' => 'text-bg-success',
		'WARNING' => 'text-bg-warning',
		'ERROR' => 'text-bg-danger',
		'DEBUG' => 'text-bg-secondary',
		default => 'text-bg-info',
	};
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
	<h1 class="display-1 mb-0">Logs</h1>
</div>

<p class="lead text-body-secondary">Recent application activity and audit events.</p>

<form method="get" class="row g-3 align-items-end mb-4">
	<input type="hidden" name="page" value="logs">
	<div class="col-sm-4 col-md-3">
		<label class="form-label" for="log-days">Show logs from the last</label>
		<select class="form-select" id="log-days" name="days">
			<?php foreach ([1, 7, 14, 30, 90, 365] as $option): ?>
				<option value="<?= $option ?>" <?= $age === $option ? 'selected' : '' ?>><?= $option ?> day<?= $option === 1 ? '' : 's' ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="col-sm-4 col-md-2">
		<button type="submit" class="btn btn-primary">Apply</button>
	</div>
	<div class="col-sm-4 col-md-7 text-md-end text-body-secondary small">
		<?= count($entries) ?> entr<?= count($entries) === 1 ? 'y' : 'ies' ?> shown
	</div>
</form>

<div class="card shadow-sm">
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-striped table-hover align-middle mb-0">
				<thead>
					<tr>
						<th style="width: 12rem;">Date</th>
						<th style="width: 10rem;">User</th>
						<th>Description</th>
						<th style="width: 10rem;">Category</th>
						<th style="width: 9rem;">Result</th>
						<th style="width: 10rem;">IP</th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($entries)): ?>
						<tr>
							<td colspan="6" class="text-center py-5 text-body-secondary">No logs found for this time range.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($entries as $entry): ?>
							<tr>
								<td><?= e($entry['date']) ?></td>
								<td><?= e($entry['username'] ?: 'System') ?></td>
								<td><?= e($entry['description']) ?></td>
								<td><?= e($entry['category'] ?: '-') ?></td>
								<td>
									<span class="badge rounded-pill <?= logResultBadgeClass($entry['result'] ?? null) ?>">
										<?= e($entry['result'] ?: 'INFO') ?>
									</span>
								</td>
								<td><?= e(logIpToString($entry['ip'] ?? null) ?: '-') ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
