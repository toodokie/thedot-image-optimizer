-- Phase 6: Template Intelligence
-- EXPAND phase: Add wp_msh_optimizer_templates table

CREATE TABLE IF NOT EXISTS `{wp_prefix}msh_optimizer_templates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `locale` VARCHAR(10) NOT NULL,
    `usage_type` VARCHAR(50) NOT NULL COMMENT 'featured, inline, gallery, acf_field, block',
    `intent` ENUM('on_topic', 'off_topic', 'unknown') NOT NULL DEFAULT 'unknown',
    `template_title` TEXT NULL,
    `template_alt` TEXT NULL,
    `template_caption` TEXT NULL,
    `template_description` TEXT NULL,
    `required_tokens` TEXT NULL COMMENT 'JSON array of tokens that must exist in context',
    `priority` INT NOT NULL DEFAULT 50 COMMENT 'Template matching priority (higher = first)',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_locale_usage_intent` (`locale`, `usage_type`, `intent`),
    INDEX `idx_priority` (`priority` DESC),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
