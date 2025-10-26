-- Phase 6: Shadow Precision Tracking
-- Records every template evaluation for calculating shadow precision
-- Used to determine when templates are safe to promote from shadow → active

CREATE TABLE IF NOT EXISTS `{wp_prefix}msh_shadow_stats` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_id` INT UNSIGNED NOT NULL COMMENT 'Template that was evaluated',
    `attachment_id` BIGINT UNSIGNED NOT NULL COMMENT 'Image being evaluated',
    `matched` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if template matched, 0 if no match',
    `expected_match` TINYINT(1) DEFAULT NULL COMMENT 'Expected outcome for precision calculation (NULL = unknown)',
    `duration_ms` DECIMAL(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Evaluation duration in milliseconds',
    `context_hash` VARCHAR(32) NOT NULL COMMENT 'MD5 of context for deduplication',
    `evaluated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When evaluation occurred',
    `site_id` VARCHAR(64) DEFAULT '' COMMENT 'Multi-tenant site identifier',
    PRIMARY KEY (`id`),
    KEY `idx_template_perf` (`template_id`, `evaluated_at` DESC) COMMENT 'Fast template stats queries',
    KEY `idx_precision` (`template_id`, `matched`, `expected_match`) COMMENT 'Shadow precision calculation',
    KEY `idx_cleanup` (`evaluated_at`) COMMENT 'Efficient old data pruning'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Shadow mode evaluation tracking for precision metrics';

-- Add index for finding recent evaluations by attachment (debugging)
CREATE INDEX `idx_attachment_recent` ON `{wp_prefix}msh_shadow_stats` (`attachment_id`, `evaluated_at` DESC);
