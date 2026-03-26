CREATE TABLE IF NOT EXISTS `subnets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cidr` VARCHAR(18) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_subnets_cidr` (`cidr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `statuses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `class` VARCHAR(50) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_statuses_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `types` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_types_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sites` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_sites_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ips` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subnet_id` INT UNSIGNED NOT NULL,
    `ip` VARCHAR(15) NOT NULL,
    `status` VARCHAR(50) NOT NULL,
    `hostname` VARCHAR(255) NULL,
    `owner` VARCHAR(128) NULL,
    `site` VARCHAR(100) NULL,
    `type` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `modified_at` DATETIME NOT NULL,
    `ping_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ips_subnet_ip` (`subnet_id`, `ip`),
    KEY `idx_ips_subnet` (`subnet_id`),
    KEY `idx_ips_status` (`status`),
    KEY `idx_ips_site` (`site`),
    CONSTRAINT `fk_ips_subnet`
        FOREIGN KEY (`subnet_id`) REFERENCES `subnets` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(255) NULL,
    `ip` INT UNSIGNED NULL,
    `description` TEXT NOT NULL,
    `category` VARCHAR(50) NULL,
    `result` VARCHAR(50) NULL,
    `date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_logs_date` (`date`),
    KEY `idx_logs_username` (`username`),
    KEY `idx_logs_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `statuses` (`name`, `class`)
VALUES
    ('Available', 'text-bg-success'),
    ('Allocated', 'text-bg-info'),
    ('Reserved', 'text-bg-primary')
ON DUPLICATE KEY UPDATE `class` = VALUES(`class`);

INSERT INTO `types` (`name`)
VALUES
    ('Unknown'),
    ('Special'),
    ('Server'),
    ('Printer'),
    ('Network Device'),
    ('Workstation')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `sites` (`name`)
VALUES
    ('Unassigned')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
