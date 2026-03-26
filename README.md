# IPAM

Internal IP address management app (written in PHP) for tracking subnets and IP allocations.

It provides:

- LDAP-backed sign-in
- subnet and IP browsing
- single-record IP editing
- bulk editing for multiple IPs in a subnet
- subnet creation from a CIDR block
- manual IP creation inside an existing subnet
- live ping checks for individual IPs

## Stack

- PHP
- MySQL / MariaDB
- Bootstrap 5
- [LdapRecord](https://ldaprecord.com/) via Composer

## Current Features

### Subnets

- list all subnets
- view subnet utilisation
- create a new subnet from CIDR
- automatically generate IP rows for the created subnet

### IPs

- browse all IPs in a subnet
- edit an individual IP
- bulk edit selected IPs within a subnet
- manually add a single IP to an existing subnet
- ping an IP from the detail page

### Auth

- LDAP bind and user lookup
- optional restriction to specific LDAP groups

## Repository Layout

```text
ajax/         AJAX endpoints
assets/       CSS and JavaScript
classes/      PHP classes and models
config/       Local config and sample config
inc/          Bootstrap / shared helpers
layout/       Shared page fragments
pages/        Application page views
```

## Requirements

- a web server capable of serving PHP (PHP 8.x recommended)
- Composer
- MySQL or MariaDB
- LDAP connectivity to your directory

## Getting Started

### 1. Install dependencies

```bash
composer install
```

### 2. Create your local config

Copy the sample config and fill in your real values:

```bash
cp config/config.php.sample config/config.php
```

`config/config.php` is gitignored. Keep secrets there only.

- database credentials
- LDAP host, bind account, and base DN
- allowed LDAP login groups (group-based login restrictions are supported through `LDAP_ALLOWED_LOGIN_GROUPS`)
- application URL and name
- password reset URL
- cookie salt

### 3. Serve the app

Point your web server at the project root.

The main entry point is [index.php](index.php).

### 4. Install the database schema

After `config/config.php` is in place, run:

```bash
php database/install.php
```

This will:

- create the configured database if it does not already exist
- create the required tables
- seed default `statuses`, `types`, and an `Unassigned` site

## License

This project is distributed under the MIT License. See [LICENSE](LICENSE).
