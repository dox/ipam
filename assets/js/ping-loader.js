// ping-loader.js
// Usage: call loadPingStatus(containerElement) or add attribute data-id on container and call autoLoad(container)
export async function loadPingStatus(container, opts = {}) {
  const id = container.dataset.id || opts.id;
  if (!id) {
	container.innerHTML = '<div class="text-muted">No ID specified</div>';
	return;
  }

  // Show spinner (Bootstrap spinner-border + visually-hidden text for screen readers)
  container.innerHTML = `
	<div class="d-inline-flex align-items-center">
	  <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
	  <span class="small text-muted">Checking ${escapeHtml(id)}…</span>
	</div>
  `;

  try {
	const resp = await fetch(`/ajax/ping.php?id=${encodeURIComponent(id)}`, { cache: 'no-store' });
	if (!resp.ok) {
	  const txt = await resp.text();
	  container.innerHTML = `<span class="text-warning">Error: ${escapeHtml(resp.status + ' ' + resp.statusText)}</span>`;
	  console.error('ping error response body:', txt);
	  return;
	}
	const json = await resp.json();
	if (!json.ok) {
	  container.innerHTML = `<span class="text-warning">Error: ${escapeHtml(json.error || 'unknown')}</span>`;
	  return;
	}

	if (json.reachable) {
	  container.innerHTML = `
		<span class="text-success">
		  <i class="bi bi-broadcast-fill me-1" aria-hidden="true"></i>
		  Host is reachable
		</span>
	  `;
	} else {
	  container.innerHTML = `
		<span class="text-danger">
		  <i class="bi bi-broadcast-fill me-1" aria-hidden="true"></i>
		  Host is not reachable
		</span>
	  `;
	}
  } catch (err) {
	console.error(err);
	container.innerHTML = `<span class="text-danger">Network error</span>`;
  }
}

// optional helper for escaping text inserted into HTML
function escapeHtml(s) {
  return String(s)
	.replace(/&/g, '&amp;')
	.replace(/</g, '&lt;')
	.replace(/>/g, '&gt;')
	.replace(/"/g, '&quot;')
	.replace(/'/g, '&#39;');
}

// Auto-load all elements with class .ping-status
export function autoLoadAll() {
  document.querySelectorAll('.ping-status[data-id]').forEach(el => {
	loadPingStatus(el);
  });
}