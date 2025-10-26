-- Phase 6: Template Collision Detection
-- Records when multiple templates match the same image
-- Used to identify overly-broad templates and improve specificity

CREATE TABLE IF NOT EXISTS `{wp_prefix}msh_template_collisions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attachment_id` BIGINT UNSIGNED NOT NULL COMMENT 'Image where collision occurred',
    `template_ids` TEXT NOT NULL COMMENT 'JSON array of template IDs that matched',
    `collision_count` TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'Number of templates that matched (2+)',
    `context_hash` VARCHAR(32) NOT NULL COMMENT 'MD5 of context for deduplication',
    `detected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When collision was detected',
    `site_id` VARCHAR(64) DEFAULT '' COMMENT 'Multi-tenant site identifier',
    PRIMARY KEY (`id`),
    KEY `idx_attachment` (`attachment_id`) COMMENT 'Find collisions for specific image',
    KEY `idx_template_involved` (`template_ids`(100)) COMMENT 'Find which templates collide (prefix index)',
    KEY `idx_severity` (`collision_count` DESC, `detected_at` DESC) COMMENT 'Find worst collisions first',
    KEY `idx_cleanup` (`detected_at`) COMMENT 'Efficient old data pruning',
    UNIQUE KEY `idx_dedup` (`attachment_id`, `context_hash`) COMMENT 'Prevent duplicate collision records'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Template collision tracking for overlap detection';
