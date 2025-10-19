<?php
/**
 * Unit Tests for Manual Edit Protection System
 *
 * @package MSH_Image_Optimizer
 * @since 2.0.0
 */

class Test_MSH_Manual_Edit_Protection extends WP_UnitTestCase {

    private $protection;
    private $versioning;
    private $test_media_id;

    public function setUp() {
        parent::setUp();

        $this->protection = MSH_Manual_Edit_Protection::get_instance();
        $this->versioning = MSH_Metadata_Versioning::get_instance();

        // Create a test attachment
        $this->test_media_id = $this->factory->attachment->create_upload_object(__DIR__ . '/fixtures/test-image.jpg');
    }

    public function tearDown() {
        parent::tearDown();

        if ($this->test_media_id) {
            wp_delete_attachment($this->test_media_id, true);
        }
    }

    /**
     * Test: Manual title edit creates version
     */
    public function test_manual_title_edit_creates_version() {
        $media_id = $this->test_media_id;

        // Simulate manual title edit
        wp_update_post(array(
            'ID' => $media_id,
            'post_title' => 'Manually Edited Title'
        ));

        // Give hooks time to run
        do_action('edit_attachment', $media_id);

        $active = $this->versioning->get_active_version($media_id, 'en', 'title');

        $this->assertNotNull($active, 'Title version should exist');
        $this->assertEquals('Manually Edited Title', $active['value'], 'Title value should match');
        $this->assertEquals('manual', $active['source'], 'Source should be manual');
    }

    /**
     * Test: Manual ALT text edit creates version
     */
    public function test_manual_alt_edit_creates_version() {
        $media_id = $this->test_media_id;

        // Simulate manual ALT text edit
        update_post_meta($media_id, '_wp_attachment_image_alt', 'Manually Edited ALT');

        $active = $this->versioning->get_active_version($media_id, 'en', 'alt');

        $this->assertNotNull($active, 'ALT version should exist');
        $this->assertEquals('Manually Edited ALT', $active['value'], 'ALT value should match');
        $this->assertEquals('manual', $active['source'], 'Source should be manual');
    }

    /**
     * Test: Has manual edit detection
     */
    public function test_has_manual_edit_detection() {
        $media_id = $this->test_media_id;
        $locale = 'en';

        // No manual edit yet
        $has_manual = $this->protection->has_manual_edit($media_id, 'title', $locale);
        $this->assertFalse($has_manual, 'Should not have manual edit initially');

        // Save AI version
        $this->versioning->save_version($media_id, $locale, 'title', 'AI Title', 'ai');
        $has_manual = $this->protection->has_manual_edit($media_id, 'title', $locale);
        $this->assertFalse($has_manual, 'Should not have manual edit after AI version');

        // Save manual version
        $this->versioning->save_version($media_id, $locale, 'title', 'Manual Title', 'manual');
        $has_manual = $this->protection->has_manual_edit($media_id, 'title', $locale);
        $this->assertTrue($has_manual, 'Should have manual edit after manual version');
    }

    /**
     * Test: AI can write when no manual edits exist
     */
    public function test_ai_can_write_without_manual_edits() {
        $media_id = $this->test_media_id;
        $locale = 'en';

        // Save AI version
        $this->versioning->save_version($media_id, $locale, 'title', 'AI Title', 'ai');

        $can_write = $this->protection->can_ai_write($media_id, 'title', $locale);
        $this->assertTrue($can_write, 'AI should be able to write when no manual edits');
    }

    /**
     * Test: AI cannot overwrite manual edits by default
     */
    public function test_ai_cannot_overwrite_manual_edits() {
        $media_id = $this->test_media_id;
        $locale = 'en';

        // Save manual version
        $this->versioning->save_version($media_id, $locale, 'title', 'Manual Title', 'manual');

        $can_write = $this->protection->can_ai_write($media_id, 'title', $locale);
        $this->assertFalse($can_write, 'AI should not be able to overwrite manual edits');
    }

    /**
     * Test: AI can overwrite with force_replace flag
     */
    public function test_ai_can_overwrite_with_force() {
        $media_id = $this->test_media_id;
        $locale = 'en';

        // Save manual version
        $this->versioning->save_version($media_id, $locale, 'title', 'Manual Title', 'manual');

        $can_write_forced = $this->protection->can_ai_write($media_id, 'title', $locale, true);
        $this->assertTrue($can_write_forced, 'AI should be able to write with force_replace=true');
    }

    /**
     * Test: Bulk operations don't trigger manual edit protection
     */
    public function test_bulk_operations_bypass_protection() {
        $media_id = $this->test_media_id;

        // Define bulk operation flag
        define('MSH_AI_REGENERATION_RUNNING', true);

        // Update title during bulk operation
        wp_update_post(array(
            'ID' => $media_id,
            'post_title' => 'Bulk Updated Title'
        ));

        do_action('edit_attachment', $media_id);

        $active = $this->versioning->get_active_version($media_id, 'en', 'title');

        // Version should NOT be created during bulk operation
        $this->assertNull($active, 'No version should be created during bulk operation');
    }

    /**
     * Test: Duplicate values don't create new versions
     */
    public function test_duplicate_values_skipped() {
        $media_id = $this->test_media_id;
        $locale = 'en';

        // Save first version
        $this->versioning->save_version($media_id, $locale, 'title', 'Same Title', 'manual');
        $version_1 = $this->versioning->get_latest_version_number($media_id, $locale, 'title');

        // Try to save same value again
        $this->versioning->save_version($media_id, $locale, 'title', 'Same Title', 'manual');
        $version_2 = $this->versioning->get_latest_version_number($media_id, $locale, 'title');

        $this->assertEquals($version_1, $version_2, 'Version number should not increment for duplicate values');
    }

    /**
     * Test: Empty values are not saved
     */
    public function test_empty_values_not_saved() {
        $media_id = $this->test_media_id;

        // Try to update with empty title
        wp_update_post(array(
            'ID' => $media_id,
            'post_title' => ''
        ));

        do_action('edit_attachment', $media_id);

        $active = $this->versioning->get_active_version($media_id, 'en', 'title');

        $this->assertNull($active, 'Empty values should not create versions');
    }

    /**
     * Test: Metadata source markers are set
     */
    public function test_metadata_source_markers() {
        $media_id = $this->test_media_id;

        wp_update_post(array(
            'ID' => $media_id,
            'post_title' => 'Test Title'
        ));

        do_action('edit_attachment', $media_id);

        $source_marker = get_post_meta($media_id, '_msh_title_source', true);

        $this->assertEquals('manual', $source_marker, 'Source marker should be set to manual');
    }
}
