<?php

use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\ActiveDirectory\User as AdUser;

class User {
	protected $userData = [];
	protected $loggedIn = false;

	// kept for compatibility if you want to enable cookie/token later — currently unused.
	const COOKIE_NAME     = 'scr_user_token';
	const COOKIE_LIFETIME = 2592000; // 30 days

	public function __construct() {
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}

		// Existing session?
		if (!empty($_SESSION['user'])) {
			$this->userData = $_SESSION['user'];
			$this->loggedIn = true;
			return;
		}

		$this->setupLdap();
		// Token restore / DB-based member checks removed on purpose for LDAP-only auth.
	}

	protected function setupLdap(): void {
		$connection = new Connection([
			'hosts'    => [LDAP_HOST],
			'base_dn'  => LDAP_BASE_DN,
			'username' => LDAP_BIND_USER,
			'password' => LDAP_BIND_PASS,
			'use_tls'  => LDAP_USE_TLS,
		]);

		Container::addConnection($connection);
	}

	/**
	 * Finalize login using the LDAP user object.
	 *
	 * @param AdUser $adUser
	 * @param bool $remember  optional, currently unused (no local tokens)
	 * @param bool $viaCookie optional, currently unused
	 */
	private function finalizeLogin(AdUser $adUser, bool $remember = false, bool $viaCookie = false): void {
		global $log, $user;

		// Make this instance the global $user immediately
		$user = $this;

		// Extract attributes safely
		$sam = $adUser->getFirstAttribute('samaccountname') ?: null;
		$display = $adUser->getFirstAttribute('displayName') ?: null;
		$mail = $adUser->getFirstAttribute('mail') ?: null;
		$memberOf = $adUser->getAttribute('memberOf') ?: []; // could be array or string depending on LDAP server

		// Normalize memberOf to array of strings
		if (is_string($memberOf) && $memberOf !== '') {
			$memberOf = [$memberOf];
		} elseif (!is_array($memberOf)) {
			$memberOf = [];
		}

		$_SESSION['user'] = [
			'samaccountname' => $sam,
			'displayName'    => $display,
			'email'          => $mail,
			'memberOf'       => $memberOf,
		];

		$this->userData = $_SESSION['user'];
		$this->loggedIn = true;

		// Logging (best effort: some attributes may be null)
		if (isset($log) && is_object($log)) {
			$who = $sam ?? ($display ?? 'unknown');
			$log->add("{$who} authenticated" . ($viaCookie ? ' (with cookie)' : ''), 'auth', Log::SUCCESS);
		}
	}

	/**
	 * Authenticate user against LDAP and optionally remember (placeholder).
	 *
	 * @param string $username
	 * @param string $password
	 * @param bool $remember  currently unused; token system removed
	 * @return bool
	 */
	public function authenticate(string $username, string $password, bool $remember = false): bool {
		global $log;
	
		try {
			$adUser = AdUser::whereEquals('samaccountname', $username)->firstOrFail();
		} catch (\Exception $e) {
			error_log("SECURITY ALERT: Failed login attempt for username: {$username} from {$_SERVER['REMOTE_ADDR']}");
			$this->logout();
			return false;
		}
	
		$connection = $adUser->getConnection();
	
		// Validate password
		if (!$connection->auth()->attempt($adUser->getDn(), $password)) {
			error_log("SECURITY ALERT: Invalid credentials for: {$username} from {$_SERVER['REMOTE_ADDR']}");
			$this->logout();
			return false;
		}
	
		// 🔐 NEW: Check allowed login groups
		if (!$this->isUserInAllowedGroups($adUser)) {
			error_log("SECURITY ALERT: Unauthorized group login attempt by {$username} from {$_SERVER['REMOTE_ADDR']}");
			$this->logout();
			return false;
		}
	
		$this->finalizeLogin($adUser, $remember);
		return true;
	}
	
	private function isUserInAllowedGroups(AdUser $adUser): bool {
	
		if (!defined('LDAP_ALLOWED_LOGIN_GROUPS') || empty(LDAP_ALLOWED_LOGIN_GROUPS)) {
			// If nothing defined, allow all authenticated users
			return true;
		}
	
		$memberOf = $adUser->getAttribute('memberOf') ?: [];
	
		if (is_string($memberOf)) {
			$memberOf = [$memberOf];
		}
	
		// Normalize user group DNs
		$userGroups = array_map(
			fn($g) => mb_strtolower(trim($g)),
			$memberOf
		);
	
		foreach (LDAP_ALLOWED_LOGIN_GROUPS as $allowedGroup) {
			if (in_array(mb_strtolower(trim($allowedGroup)), $userGroups, true)) {
				return true;
			}
		}
	
		return false;
	}

	// Token persistence and restoration removed for LDAP-only setup.
	// If you later want remember-me tokens, reintroduce using samaccountname as identifier
	// or create a dedicated tokens table tied to LDAP usernames.

	/**
	 * Check if the currently logged-in user is a member of the supplied LDAP group.
	 * Accepts either a full DN or a common name (CN=GroupName,...). Case-insensitive.
	 *
	 * @param string $group Full DN or CN (e.g. 'CN=MyGroup,OU=Groups,DC=example,DC=com' or 'MyGroup')
	 * @return bool
	 */
	public function isMemberOfLdapGroup(string $group): bool {
		if (!$this->isLoggedIn()) {
			return false;
		}

		$memberOf = $this->userData['memberOf'] ?? [];
		if (!is_array($memberOf)) {
			$memberOf = [$memberOf];
		}

		$needle = trim($group);
		if ($needle === '') {
			return false;
		}

		// If input looks like a DN (contains '=' or ','), match full DN (case-insensitive)
		$isDn = (strpos($needle, '=') !== false || strpos($needle, ',') !== false);
		$needleLower = mb_strtolower($needle);

		foreach ($memberOf as $entry) {
			if (!is_string($entry)) continue;
			$entryTrim = trim($entry);
			$entryLower = mb_strtolower($entryTrim);

			if ($isDn) {
				// compare DN to DN
				if ($entryLower === $needleLower) {
					return true;
				}
			} else {
				// Compare by CN part: CN=GroupName,... OR the whole DN contains CN=<needle>
				// Try to extract CN from DN, otherwise fallback to substring match
				if (preg_match('/\bcn=([^,]+)/i', $entryTrim, $m)) {
					if (mb_strtolower($m[1]) === $needleLower) {
						return true;
					}
				}
				// Fallback: check if DN contains the group as a chunk (case-insensitive)
				if (stripos($entryLower, 'cn=' . $needleLower) !== false) {
					return true;
				}
			}
		}

		return false;
	}

	public function logout(): void {
		global $log, $user;

		// Optional logging
		if (isset($log) && is_object($log) && isset($this->userData['samaccountname'])) {
			$log->add(strtoupper($this->userData['samaccountname']) . " logged out", 'auth', Log::SUCCESS);
		}

		unset($_SESSION['user']);
		unset($_SESSION['impersonating']);
		unset($_SESSION['impersonation_backup']);
		$this->loggedIn = false;
		$this->userData = [];
	}

	public function isLoggedIn(): bool {
		return $this->loggedIn;
	}

	public function getUsername(): ?string {
		return isset($this->userData['samaccountname'])
			? strtoupper($this->userData['samaccountname'])
			: null;
	}

	public function getMemberType(): ?string {
		return isset($this->userData['type'])
			? $this->userData['type']
			: null;
	}
}