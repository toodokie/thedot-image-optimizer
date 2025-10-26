-- Phase 6: Template Intelligence - Schema Hardening
-- Add columns for future-proofing and performance
-- Note: This migration is idempotent via Migration Helper's column check

-- Step 1: Add columns
ALTER TABLE `{wp_prefix}msh_optimizer_templates`
ADD COLUMN `name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Human-readable template name' AFTER `id`,
ADD COLUMN `site_id` VARCHAR(64) DEFAULT '' COMMENT 'Multi-tenant site identifier' AFTER `name`,
ADD COLUMN `version` INT DEFAULT 1 COMMENT 'Cache invalidation version' AFTER `updated_at`,
ADD COLUMN `negative_tokens` TEXT NULL COMMENT 'JSON array of tokens that must NOT exist in context' AFTER `required_tokens`,
ADD COLUMN `nice_to_have_tokens` TEXT NULL COMMENT 'JSON array for future fuzzy scoring' AFTER `negative_tokens`,
ADD COLUMN `variables` TEXT NULL COMMENT 'JSON array of expected variables like subject, entity' AFTER `nice_to_have_tokens`,
ADD COLUMN `max_len` TEXT NULL COMMENT 'JSON object with field length caps {"alt":125,"title":60}' AFTER `variables`,
ADD COLUMN `notes` TEXT NULL COMMENT 'Human guidance for template usage' AFTER `max_len`,
ADD COLUMN `mode` ENUM('active', 'shadow', 'inactive') DEFAULT 'active' COMMENT 'Template state: active=production, shadow=telemetry-only, inactive=disabled' AFTER `is_active`,
ADD COLUMN `preferred_format` VARCHAR(10) DEFAULT 'webp' COMMENT 'Target image format (webp, avif) for AVIF compatibility' AFTER `mode`;

-- Step 2: Add covering index for fast template lookup
CREATE INDEX `idx_tpl_match` ON `{wp_prefix}msh_optimizer_templates` (`locale`, `usage_type`, `intent`, `mode`, `priority` DESC);

-- Step 3: Add multi-tenant support index
CREATE INDEX `idx_site_id` ON `{wp_prefix}msh_optimizer_templates` (`site_id`);

-- Backfill defaults for existing templates
UPDATE `{wp_prefix}msh_optimizer_templates`
SET
    `variables` = COALESCE(`variables`, '["subject","entity","post_title"]'),
    `max_len` = COALESCE(`max_len`, '{"alt":125,"title":60}'),
    `version` = COALESCE(`version`, 1),
    `site_id` = COALESCE(`site_id`, ''),
    `mode` = COALESCE(`mode`, 'active'),
    `preferred_format` = COALESCE(`preferred_format`, 'webp')
WHERE `variables` IS NULL
   OR `max_len` IS NULL
   OR `version` IS NULL
   OR `site_id` IS NULL
   OR `mode` IS NULL
   OR `preferred_format` IS NULL;
