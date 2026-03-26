<nav class="navbar navbar-expand-lg bg-body-tertiary d-print-none">
	<div class="container">
		<a class="navbar-brand" href="index.php">
			<i class="bi me-2 bi-view-list" aria-hidden="true"></i> <?= APP_NAME ?>
		</a>
		
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
			<span class="navbar-toggler-icon"></span>
		</button>
		
		<div class="collapse navbar-collapse" id="navbarMain">
			<ul class="navbar-nav me-auto mb-2 mb-lg-0">
				<!--<li>
					<a href="index.php?page=index" class="nav-link"><i class="bi bi-share me-2"></i>Subnets</a>
				</li>
				<li>
					<a href="index.php?page=ips" class="nav-link"><i class="bi bi-hdd-network me-2"></i>IPs</a>
				</li>-->
			</ul>
			
			<div class="d-flex align-items-center gap-3">
				<div class="dropdown">
					<a href="#" id="bd-theme" class="d-block link-body-emphasis text-decoration-none dropdown-toggle"
					   data-bs-toggle="dropdown" aria-expanded="true" aria-label="Toggle theme (auto)">
						<i class="bi bi-circle-half"></i>
						<span class="visually-hidden" id="bd-theme-text">Toggle theme</span>
					</a>
				
					<ul class="dropdown-menu dropdown-menu-end text-small" data-bs-popper="static">
						<li>
							<button type="button"
								class="dropdown-item position-relative"
								data-bs-theme-value="light" aria-pressed="false">
								<span><i class="bi bi-sun me-2"></i> Light</span>
								<i class="bi bi-check2 d-none"></i>
							</button>
						</li>
						<li>
							<button type="button"
								class="dropdown-item position-relative"
								data-bs-theme-value="dark" aria-pressed="false">
								<span><i class="bi bi-moon-stars-fill me-2"></i> Dark</span>
								<i class="bi bi-check2 d-none"></i>
							</button>
						</li>
						<li>
							<button type="button"
								class="dropdown-item position-relative active"
								data-bs-theme-value="auto" aria-pressed="true">
								<span><i class="bi bi-circle-half me-2"></i> Auto</span>
								<i class="bi bi-check2"></i>
							</button>
						</li>
					</ul>
				</div>
				
				<div class="dropdown">
					<a href="#" class="d-block link-body-emphasis text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><?= $user->getUsername(); ?></a>
					<ul class="dropdown-menu dropdown-menu-end text-small">
						
						<li><hr class="dropdown-divider"></li>
						<li><a class="dropdown-item" href="logout.php">Sign out</a></li>
					</ul>
				</div>
	  </div>
	</div>
  </div>
</nav>
