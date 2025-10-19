<?php
/**
 * Unit Tests for Metadata Versioning System
 *
 * @package MSH_Image_Optimizer
 * @since 2.0.0
 */

class Test_MSH_Metadata_Versioning extends WP_UnitTestCase {

    private $versioning;
    private $test_media_id;

    public function setUp() {
        parent::setUp();

        $this->versioning = MSH_Metadata_Versioning::get_instance();

        // Create a test attachment
        $this->test_media_id = $this->factory->attachment->create_upload_object(__DIR__ . '/fixtures/test-image.jpg');
    }

    public function tearDown() {
        parent::tearDown();

        // Clean up test data
        if ($this->test_media_id) {
            wp_delete_attachment($this->test_media_id, true);
        }
    }

    /**
     * Test: Database table exists
     */
    public function test_database_table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'msh_optimizer_metadata';

        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        )) === $table_name;

        $this->assertTrue($table_exists, 'Metadata versioning table should exist');
    }

    /**
     * Test: Save version increments correctly
     */
    public function test_save_version_increments() {
        $media_id = $this->test_media_id;
        $locale = 'en';
        $field = 'title';

        // Save version 1
        $v1 = $this->versioning->save_version($media_id, $locale, $field, 'Test Title v1', 'ai');
        $this->assertNotFalse($v1, 'First version should save successfully');

        $version_num_1 = $this->versioning->get_latest_version_number($media_id, $locale, $field);
        $this->assertEquals(1, $version_num_1, 'First version number should be 1');

        // Save version 2
        $v2 = $this->versioning->save_version($media_id, $locale, $field, 'Test Title v2', 'manual');
        $this->assertNotFalse($v2, 'Second version should save successfully');

        $version_num_2 = $this->versioning->get_latest_version_number($media_id, $locale, $field);
        $this->assertEquals(2, $version_num_2, 'Second version number should be 2');
    }

    /**
     * Test: Get active version returns latest
     */
    public function test_get_active_version_returns_latest() {
        $media_id = $this->test_media_id;
        $locale = 'en';
        $field = 'alt';

        $this->versioning->save_version($media_id, $locale, $field, 'Old ALT text', 'ai');
        $this->versioning->save_version($media_id, $locale, $field, 'New ALT text', 'manual');

        $active = $this->versioning->get_active_version($media_id, $locale, $field);

        $this->assertNotNull($active, 'Active version should exist');
        $this->assertEquals('New ALT text', $active['value'], 'Active version should be the latest');
        $this->assertEquals('manual', $active['source'], 'Active version source should be manual');
        $this->assertEquals(2, $active['version'], 'Active version number should be 2');
    }

    /**
     * Test: Version history returns all versions in correct order
     */
    public function test_version_history_order() {
        $media_id = $this->test_media_id;
        $locale = 'en';
        $field = 'description';

        $this->versioning->save_version($media_id, $locale, $field, 'Description v1', 'ai');
        $this->versioning->save_version($media_id, $locale, $field, 'Description v2', 'manual');
        $this->versioning->save_version($media_id, $locale, $field, 'Description v3', 'ai');

        $history = $this->versioning->get_version_history($media_id, $locale, $field);

        $this->assertCount(3, $history, 'History should contain 3 versions');
        $this->assertEquals(3, $history[0]['version'], 'First item should be version 3 (newest)');
        $this->assertEquals(2, $history[1]['version'], 'Second item should be version 2');
        $this->assertEquals(1, $history[2]['version'], 'Third item should be version 1 (oldest)');
    }

    /**
     * Test: Compare versions shows differences
     */
    public function test_compare_versions() {
        $media_id = $this->test_media_id;
        $locale = 'en';
        $field = 'caption';

        $this->versioning->save_version($media_id, $locale, $field, 'Caption A', 'ai');
        $this->versioning->save_version($media_id, $locale, $field, 'Caption B', 'manual');

        $diff = $this->versioning->compare_versions($media_id, $locale, $field, 1, 2);

        $this->assertNotNull($diff, 'Diff should not be null');
        $this->assertFalse($diff['values_match'], 'Values should not match');
        $this->assertEquals('Caption A', $diff['value_diff']['from'], 'From value should be Caption A');
        $this->assertEquals('Caption B', $diff['value_diff']['to'], 'To value should be Caption B');
        $this->assertTrue($diff['source_changed'], 'Source should have changed from ai to manual');
    }

    /**
     * Test: AI vs Manual diff identifies manual edits
     */
    public function test_ai_vs_manual_diff() {
        $media_id = $this->test_media_id;
        $locale = 'en';

        // Save AI versions
        $this->versioning->save_version($media_id, $locale, 'title', 'AI Generated Title', 'ai');
        $this->versioning->save_version($media_id, $locale, 'alt', 'AI Generated ALT', 'ai');

        // Save manual edit on title
        $this->versioning->save_version($media_id, $locale, 'title', 'Manually Edited Title', 'manual');

        $diffs = $this->versioning->get_ai_vs_manual_diff($media_id, $locale);

        $this->assertTrue($diffs['title']['has_manual'], 'Title should have manual edit');
        $this->assertTrue($diffs['title']['manual_is_active'], 'Manual edit should be active');
        $this->assertFalse($diffs['alt']['has_manual'], 'ALT should not have manual edit');
    }

    /**
     * Test: Value exists prevents duplicate versions
     */
    public function test_value_exists_prevents_duplicates() {
        $media_id = $this->test_media_id;
        $locale = 'en';
        $field = 'title';
        $value = 'Same Title';

        $this->versioning->save_version($media_id, $locale, $field, $value, 'ai');

        $exists = $this->versioning->value_exists($media_id, $locale, $field, $value);
        $this->assertTrue($exists, 'Value should exist');

        $different_exists = $this->versioning->value_exists($media_id, $locale, $field, 'Different Title');
        $this->assertFalse($different_exists, 'Different value should not exist');
    }

    /**
     * Test: Multiple locales for same media
     */
    public function test_multiple_locales() {
        $media_id = $this->test_media_id;

        $this->versioning->save_version($media_id, 'en', 'title', 'English Title', 'ai');
        $this->versioning->save_version($media_id, 'es', 'title', 'Título en Español', 'ai');
        $this->versioning->save_version($media_id, 'fr', 'title', 'Titre en Français', 'ai');

        $en_version = $this->versioning->get_active_version($media_id, 'en', 'title');
        $es_version = $this->versioning->get_active_version($media_id, 'es', 'title');
        $fr_version = $this->versioning->get_active_version($media_id, 'fr', 'title');

        $this->assertEquals('English Title', $en_version['value'], 'English version should be correct');
        $this->assertEquals('Título en Español', $es_version['value'], 'Spanish version should be correct');
        $this->assertEquals('Titre en Français', $fr_version['value'], 'French version should be correct');
    }

    /**
     * Test: Checksum generation is consistent
     */
    public function test_checksum_consistency() {
        $media_id = $this->test_media_id;
        $locale = 'en';
        $field = 'title';
        $value = 'Test Title for Checksum';

        $this->versioning->save_version($media_id, $locale, $field, $value, 'ai');

        $version = $this->versioning->get_active_version($media_id, $locale, $field);
        $expected_checksum = hash('sha256', $value);

        $this->assertEquals($expected_checksum, $version['checksum'], 'Checksum should match SHA256 hash');
    }

    /**
     * Test: Invalid field name is rejected
     */
    public function test_invalid_field_rejected() {
        $media_id = $this->test_media_id;
        $locale = 'en';
        $invalid_field = 'invalid_field_name';

        $result = $this->versioning->save_version($media_id, $locale, $invalid_field, 'Value', 'ai');

        $this->assertFalse($result, 'Invalid field name should be rejected');
    }

    /**
     * Test: Invalid source type is rejected
     */
    public function test_invalid_source_rejected() {
        $media_id = $this->test_media_id;
        $locale = 'en';
        $field = 'title';
        $invalid_source = 'invalid_source';

        $result = $this->versioning->save_version($media_id, $locale, $field, 'Value', $invalid_source);

        $this->assertFalse($result, 'Invalid source type should be rejected');
    }

    /**
     * Test: Get specific version by number
     */
    public function test_get_specific_version() {
        $media_id = $this->test_media_id;
        $locale = 'en';
        $field = 'title';

        $this->versioning->save_version($media_id, $locale, $field, 'Version 1', 'ai');
        $this->versioning->save_version($media_id, $locale, $field, 'Version 2', 'manual');
        $this->versioning->save_version($media_id, $locale, $field, 'Version 3', 'ai');

        $v2 = $this->versioning->get_version($media_id, $locale, $field, 2);

        $this->assertNotNull($v2, 'Version 2 should exist');
        $this->assertEquals('Version 2', $v2['value'], 'Version 2 value should be correct');
        $this->assertEquals('manual', $v2['source'], 'Version 2 source should be manual');
    }
}
