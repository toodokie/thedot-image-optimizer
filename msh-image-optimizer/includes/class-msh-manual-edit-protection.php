<?php
/**
 * Manual Edit Protection System
 *
 * Detects when users manually edit image metadata and creates versioned records
 * to preserve manual edits and prevent AI overwrite.
 *
 * @package MSH_Image_Optimizer
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class MSH_Manual_Edit_Protection {

    /**
     * Singleton instance
     *
     * @var MSH_Manual_Edit_Protection|null
     */
    private static $instance = null;

    /**
     * Metadata versioning instance
     *
     * @var MSH_Metadata_Versioning
     */
    private $versioning;

    /**
     * Get singleton instance
     *
     * @since 2.0.0
     * @return MSH_Manual_Edit_Protection
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Register hooks
     *
     * @since 2.0.0
     */
    private function __construct() {
        $this->versioning = MSH_Metadata_Versioning::get_instance();

        // Hook into attachment metadata updates
        add_filter('wp_update_attachment_metadata', array($this, 'detect_manual_edits'), 10, 2);

        // Hook into post updates (for title changes)
        add_action('edit_attachment', array($this, 'detect_title_change'), 10, 1);

        // Hook into alt text updates
        add_action('updated_post_meta', array($this, 'detect_alt_text_change'), 10, 4);
    }

    /**
     * Detect manual edits to attachment metadata
     *
     * @since 2.0.0
     * @param array $data    Attachment metadata
     * @param int   $post_id Attachment ID
     * @return array Unchanged metadata
     */
    public function detect_manual_edits($data, $post_id) {
        // Only track if this is a manual edit (not during bulk operations)
        if ($this->is_bulk_operation()) {
            return $data;
        }

        // Get current locale (default to site locale)
        $locale = $this->get_current_locale();

        // Check caption and description from image meta
        if (isset($data['image_meta'])) {
            if (isset($data['image_meta']['caption'])) {
                $this->maybe_save_manual_version(
                    $post_id,
                    $locale,
                    'caption',
                    $data['image_meta']['caption']
                );
            }
        }

        return $data;
    }

    /**
     * Detect manual title changes
     *
     * @since 2.0.0
     * @param int $post_id Attachment ID
     */
    public function detect_title_change($post_id) {
        // Only for attachments
        if (get_post_type($post_id) !== 'attachment') {
            return;
        }

        // Skip bulk operations
        if ($this->is_bulk_operation()) {
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        $locale = $this->get_current_locale();

        // Save title version
        $this->maybe_save_manual_version(
            $post_id,
            $locale,
            'title',
            $post->post_title
        );

        // Save description version (post_content in WP is the description)
        $this->maybe_save_manual_version(
            $post_id,
            $locale,
            'description',
            $post->post_content
        );
    }

    /**
     * Detect ALT text changes
     *
     * @since 2.0.0
     * @param int    $meta_id    Meta ID
     * @param int    $object_id  Post ID
     * @param string $meta_key   Meta key
     * @param mixed  $meta_value Meta value
     */
    public function detect_alt_text_change($meta_id, $object_id, $meta_key, $meta_value) {
        // Only track ALT text changes
        if ($meta_key !== '_wp_attachment_image_alt') {
            return;
        }

        // Only for attachments
        if (get_post_type($object_id) !== 'attachment') {
            return;
        }

        // Skip bulk operations
        if ($this->is_bulk_operation()) {
            return;
        }

        $locale = $this->get_current_locale();

        $this->maybe_save_manual_version(
            $object_id,
            $locale,
            'alt',
            $meta_value
        );
    }

    /**
     * Save a manual edit version if the value has changed
     *
     * @since 2.0.0
     * @param int    $media_id Attachment ID
     * @param string $locale   Locale code
     * @param string $field    Metadata field
     * @param string $value    New value
     * @return bool True if version was saved, false otherwise
     */
    private function maybe_save_manual_version($media_id, $locale, $field, $value) {
        // Don't save empty values
        if (empty($value)) {
            return false;
        }

        // Check if this value already exists (prevent duplicate versions)
        if ($this->versioning->value_exists($media_id, $locale, $field, $value)) {
            return false;
        }

        // Save as manual edit
        $version_id = $this->versioning->save_version(
            $media_id,
            $locale,
            $field,
            $value,
            'manual'
        );

        if ($version_id) {
            // Update metadata source marker
            update_post_meta($media_id, '_msh_' . $field . '_source', 'manual');
            update_post_meta($media_id, '_msh_' . $field . '_version_id', $version_id);

            error_log(sprintf(
                '[MSH Manual Edit] Saved manual edit for media_id=%d, field=%s, locale=%s',
                $media_id,
                $field,
                $locale
            ));

            return true;
        }

        return false;
    }

    /**
     * Check if we're in a bulk operation
     *
     * @since 2.0.0
     * @return bool True if bulk operation is running
     */
    private function is_bulk_operation() {
        // Check for AI regeneration flag
        if (defined('MSH_AI_REGENERATION_RUNNING') && MSH_AI_REGENERATION_RUNNING) {
            return true;
        }

        // Check for bulk optimize flag
        if (defined('MSH_BULK_OPTIMIZE_RUNNING') && MSH_BULK_OPTIMIZE_RUNNING) {
            return true;
        }

        // Check for WP-CLI
        if (defined('WP_CLI') && WP_CLI) {
            return true;
        }

        return false;
    }

    /**
     * Get current locale
     *
     * @since 2.0.0
     * @return string Locale code
     */
    private function get_current_locale() {
        // Try to get active profile locale
        if (class_exists('MSH_Image_Optimizer_Context_Helper')) {
            $active_profile = MSH_Image_Optimizer_Context_Helper::get_active_profile();
            if (!empty($active_profile['locale'])) {
                return $active_profile['locale'];
            }

            $active_context = MSH_Image_Optimizer_Context_Helper::get_active_context();
            if (!empty($active_context['locale'])) {
                return $active_context['locale'];
            }
        }

        // Fall back to WordPress locale
        $wp_locale = get_locale();

        // Normalize to lowercase with hyphen (en-US -> en-us)
        return strtolower(str_replace('_', '-', $wp_locale));
    }

    /**
     * Check if a field has been manually edited
     *
     * @since 2.0.0
     * @param int    $media_id Attachment ID
     * @param string $field    Metadata field
     * @param string $locale   Locale code (optional)
     * @return bool True if field has manual edits
     */
    public function has_manual_edit($media_id, $field, $locale = null) {
        if (!$locale) {
            $locale = $this->get_current_locale();
        }

        $active = $this->versioning->get_active_version($media_id, $locale, $field);

        return $active && $active['source'] === 'manual';
    }

    /**
     * Check if AI can overwrite this field
     *
     * Manual edits win unless user explicitly allows AI to replace
     *
     * @since 2.0.0
     * @param int    $media_id     Attachment ID
     * @param string $field        Metadata field
     * @param string $locale       Locale code (optional)
     * @param bool   $force_replace Allow AI to replace manual edits
     * @return bool True if AI can write to this field
     */
    public function can_ai_write($media_id, $field, $locale = null, $force_replace = false) {
        if ($force_replace) {
            return true; // User explicitly allowed replacement
        }

        return !$this->has_manual_edit($media_id, $field, $locale);
    }
}
