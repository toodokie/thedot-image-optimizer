<?php
/**
 * Phase 4 Metadata Versioning & Manual Edit Protection tests.
 *
 * Run with:
 *   wp eval-file tests/test-phase4-metadata-versioning.php
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Ensure our primary services are bootstrapped. */
$versioning         = MSH_Metadata_Versioning::get_instance();
$manual_protection  = MSH_Manual_Edit_Protection::get_instance();
$versioning->maybe_create_table();

$phase4_tests          = array();
$phase4_created_posts  = array();

/**
 * Register a new test.
 *
 * @param string   $group    Test group.
 * @param string   $name     Test name.
 * @param callable $callback Test callback.
 */
function phase4_register_test( $group, $name, $callback ) {
	global $phase4_tests;
	$phase4_tests[] = array(
		'group'    => $group,
		'name'     => $name,
		'callback' => $callback,
	);
}

/**
 * Reset metadata versioning table before each test.
 */
function phase4_reset_versioning_table() {
	$versioning = MSH_Metadata_Versioning::get_instance();
	$versioning->maybe_create_table();

	global $wpdb;
	$table = $wpdb->prefix . MSH_Metadata_Versioning::TABLE_NAME;
	$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Create a disposable attachment for testing.
 *
 * @param array $overrides Optional post data overrides.
 * @return int Attachment ID.
 */
function phase4_create_attachment( $overrides = array() ) {
	global $phase4_created_posts;

	$uploads = wp_get_upload_dir();
	$defaults = array(
		'post_title'     => 'Phase4 Test Attachment ' . wp_generate_uuid4(),
		'post_content'   => 'Initial test description.',
		'post_status'    => 'inherit',
		'post_type'      => 'attachment',
		'post_mime_type' => 'image/jpeg',
		'guid'           => trailingslashit( $uploads['baseurl'] ) . uniqid( 'phase4-test-', true ) . '.jpg',
	);

	$post_data = wp_parse_args( $overrides, $defaults );
	$post_id   = wp_insert_post( $post_data );

	if ( ! $post_id || is_wp_error( $post_id ) ) {
		throw new Exception( 'Failed to create test attachment.' );
	}

	$phase4_created_posts[] = $post_id;
	return $post_id;
}

/**
 * Create a disposable post record (non-attachment).
 *
 * @param array $overrides Optional data overrides.
 * @return int Post ID.
 */
function phase4_create_post( $overrides = array() ) {
	global $phase4_created_posts;

	$defaults = array(
		'post_title'   => 'Phase4 Test Post ' . wp_generate_uuid4(),
		'post_content' => 'Test content.',
		'post_status'  => 'publish',
		'post_type'    => 'post',
	);

	$post_id = wp_insert_post( wp_parse_args( $overrides, $defaults ) );

	if ( ! $post_id || is_wp_error( $post_id ) ) {
		throw new Exception( 'Failed to create test post.' );
	}

	$phase4_created_posts[] = $post_id;
	return $post_id;
}

/**
 * Clean up created posts between tests.
 */
function phase4_cleanup_posts() {
	global $phase4_created_posts;

	if ( empty( $phase4_created_posts ) ) {
		return;
	}

	foreach ( $phase4_created_posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}

	$phase4_created_posts = array();
}

/**
 * Helper assert: strict equality.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $message  Optional message.
 * @throws Exception When assertion fails.
 */
function phase4_assert_equals( $expected, $actual, $message = '' ) {
	if ( $expected !== $actual ) {
		if ( '' === $message ) {
			$message = sprintf(
				'Assertion failed: expected (%s) but found (%s).',
				var_export( $expected, true ),
				var_export( $actual, true )
			);
		}
		throw new Exception( $message );
	}
}

/**
 * Helper assert: truthy.
 *
 * @param bool   $condition Condition to evaluate.
 * @param string $message   Optional message.
 * @throws Exception When assertion fails.
 */
function phase4_assert_true( $condition, $message = 'Expected condition to be true.' ) {
	if ( ! $condition ) {
		throw new Exception( $message );
	}
}

/**
 * Helper assert: falsy.
 *
 * @param bool   $condition Condition to evaluate.
 * @param string $message   Optional message.
 * @throws Exception When assertion fails.
 */
function phase4_assert_false( $condition, $message = 'Expected condition to be false.' ) {
	if ( $condition ) {
		throw new Exception( $message );
	}
}

/**
 * Helper assert: value is not null.
 *
 * @param mixed  $value   Value to inspect.
 * @param string $message Optional message.
 * @throws Exception When assertion fails.
 */
function phase4_assert_not_null( $value, $message = 'Expected value to be non-null.' ) {
	if ( null === $value ) {
		throw new Exception( $message );
	}
}

/**
 * Calculate the default locale used by manual edit protection.
 *
 * @return string Default locale slug.
 */
function phase4_default_locale() {
	return strtolower( str_replace( '_', '-', get_locale() ) );
}

/**
 * Convenience helper to retrieve version history for assertions.
 *
 * @param int    $media_id Attachment ID.
 * @param string $locale   Locale code.
 * @param string $field    Metadata field.
 * @return array
 */
function phase4_get_history( $media_id, $locale, $field ) {
	$versioning = MSH_Metadata_Versioning::get_instance();
	return $versioning->get_version_history( $media_id, $locale, $field );
}


/* ------------------------------------------------------------------------- */
/* Versioning tests                                                          */
/* ------------------------------------------------------------------------- */

phase4_register_test(
	'Versioning',
	'Creates table and updates schema option',
	function() {
		phase4_reset_versioning_table();

		delete_option( 'msh_metadata_versioning_schema_version' );

		$versioning = MSH_Metadata_Versioning::get_instance();
		$result     = $versioning->maybe_create_table();

		phase4_assert_true( $result, 'maybe_create_table() should return true.' );

		global $wpdb;
		$table = $wpdb->prefix . MSH_Metadata_Versioning::TABLE_NAME;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		phase4_assert_equals( $table, $found, 'Versioning table was not created.' );
		phase4_assert_equals( MSH_Metadata_Versioning::SCHEMA_VERSION, (int) get_option( 'msh_metadata_versioning_schema_version' ), 'Schema version option not updated.' );
	}
);

phase4_register_test(
	'Versioning',
	'Auto increments version numbers per media/field',
	function() {
		phase4_reset_versioning_table();

		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		phase4_assert_true( false !== $versioning->save_version( $media_id, 'en', 'title', 'First Title', 'ai' ), 'First save failed.' );
		phase4_assert_true( false !== $versioning->save_version( $media_id, 'en', 'title', 'Second Title', 'ai' ), 'Second save failed.' );

		$latest = $versioning->get_latest_version_number( $media_id, 'en', 'title' );
		phase4_assert_equals( 2, $latest, 'Version number should increment sequentially.' );
	}
);

phase4_register_test(
	'Versioning',
	'Rejects invalid media IDs',
	function() {
		phase4_reset_versioning_table();

		$versioning = MSH_Metadata_Versioning::get_instance();
		phase4_assert_false( $versioning->save_version( 0, 'en', 'title', 'Invalid', 'ai' ), 'Invalid media ID should fail.' );
	}
);

phase4_register_test(
	'Versioning',
	'Rejects invalid field keys',
	function() {
		phase4_reset_versioning_table();

		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		phase4_assert_false( $versioning->save_version( $media_id, 'en', 'summary', 'Invalid Field', 'ai' ), 'Unexpected field should be rejected.' );
	}
);

phase4_register_test(
	'Versioning',
	'Rejects invalid sources',
	function() {
		phase4_reset_versioning_table();

		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		phase4_assert_false( $versioning->save_version( $media_id, 'en', 'title', 'Invalid Source', 'unknown' ), 'Unexpected source should be rejected.' );
	}
);

phase4_register_test(
	'Versioning',
	'Tracks separate locales independently',
	function() {
		phase4_reset_versioning_table();

		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		$versioning->save_version( $media_id, 'en', 'title', 'English', 'ai' );
		$versioning->save_version( $media_id, 'es', 'title', 'Español', 'ai' );

		phase4_assert_equals( 1, $versioning->get_latest_version_number( $media_id, 'en', 'title' ) );
		phase4_assert_equals( 1, $versioning->get_latest_version_number( $media_id, 'es', 'title' ) );
	}
);

phase4_register_test(
	'Versioning',
	'get_active_version returns latest record',
	function() {
		phase4_reset_versioning_table();

		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		$versioning->save_version( $media_id, 'en', 'title', 'First Version', 'ai' );
		$versioning->save_version( $media_id, 'en', 'title', 'Second Version', 'template' );

		$active = $versioning->get_active_version( $media_id, 'en', 'title' );
		phase4_assert_equals( 'Second Version', $active['value'], 'Active version should match most recent value.' );
		phase4_assert_equals( 'template', $active['source'], 'Source should reflect latest entry.' );
		phase4_assert_equals( 2, (int) $active['version'], 'Version counter should be 2.' );
	}
);

phase4_register_test(
	'Versioning',
	'get_version_history returns newest first',
	function() {
		phase4_reset_versioning_table();

		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		$versioning->save_version( $media_id, 'en', 'title', 'Alpha', 'ai' );
		$versioning->save_version( $media_id, 'en', 'title', 'Beta', 'manual' );

		$history = $versioning->get_version_history( $media_id, 'en', 'title' );
		phase4_assert_equals( 2, count( $history ), 'Two versions expected.' );
		phase4_assert_equals( 'Beta', $history[0]['value'], 'Latest version should appear first.' );
		phase4_assert_equals( 'manual', $history[0]['source'], 'Latest source mismatch.' );
	}
);

phase4_register_test(
	'Versioning',
	'get_version fetches specific version number',
	function() {
		phase4_reset_versioning_table();

		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		$versioning->save_version( $media_id, 'en', 'title', 'V1', 'ai' );
		$versioning->save_version( $media_id, 'en', 'title', 'V2', 'ai' );

		$record = $versioning->get_version( $media_id, 'en', 'title', 1 );
		phase4_assert_not_null( $record, 'Expected version 1 record.' );
		phase4_assert_equals( 'V1', $record['value'], 'Version 1 value mismatch.' );
	}
);

phase4_register_test(
	'Versioning',
	'compare_versions returns diff payload',
	function() {
		phase4_reset_versioning_table();

		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		$versioning->save_version( $media_id, 'en', 'caption', 'Caption A', 'ai' );
		$versioning->save_version( $media_id, 'en', 'caption', 'Caption B', 'manual' );

		$diff = $versioning->compare_versions( $media_id, 'en', 'caption', 1, 2 );
		phase4_assert_not_null( $diff, 'Diff should not be null.' );
		phase4_assert_false( $diff['values_match'], 'Values should differ.' );
		phase4_assert_equals( 'Caption A', $diff['value_diff']['from'], 'Diff "from" value mismatch.' );
		phase4_assert_equals( 'Caption B', $diff['value_diff']['to'], 'Diff "to" value mismatch.' );
		phase4_assert_true( $diff['source_changed'], 'Sources should differ between versions.' );
	}
);

phase4_register_test(
	'Versioning',
	'compare_versions returns null when versions missing',
	function() {
		phase4_reset_versioning_table();

		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		$versioning->save_version( $media_id, 'en', 'description', 'Only Version', 'ai' );

		$diff = $versioning->compare_versions( $media_id, 'en', 'description', 1, 2 );
		phase4_assert_equals( null, $diff, 'Diff should be null when version missing.' );
	}
);

phase4_register_test(
	'Versioning',
	'get_ai_vs_manual_diff highlights manual overrides',
	function() {
		phase4_reset_versioning_table();

		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		$versioning->save_version( $media_id, 'en', 'title', 'AI Title', 'ai' );
		$versioning->save_version( $media_id, 'en', 'title', 'Manual Title', 'manual' );

		$diffs = $versioning->get_ai_vs_manual_diff( $media_id, 'en' );
		phase4_assert_true( isset( $diffs['title'] ), 'Title diff should exist.' );
		phase4_assert_true( $diffs['title']['has_manual'], 'Manual flag expected.' );
		phase4_assert_true( $diffs['title']['manual_is_active'], 'Manual version should be active.' );
		phase4_assert_equals( 'Manual Title', $diffs['title']['active']['value'], 'Active value mismatch.' );
	}
);

phase4_register_test(
	'Versioning',
	'get_ai_vs_manual_diff returns empty for untouched media',
	function() {
		phase4_reset_versioning_table();

		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		$diffs = $versioning->get_ai_vs_manual_diff( $media_id, 'en' );
		phase4_assert_equals( array(), $diffs, 'Diffs should be empty when no versions exist.' );
	}
);

phase4_register_test(
	'Versioning',
	'value_exists detects identical checksum',
	function() {
		phase4_reset_versioning_table();

		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		$versioning->save_version( $media_id, 'en', 'description', 'Matching Value', 'ai' );
		phase4_assert_true( $versioning->value_exists( $media_id, 'en', 'description', 'Matching Value' ), 'Existing value should be detected.' );
	}
);

phase4_register_test(
	'Versioning',
	'value_exists returns false for new values',
	function() {
		phase4_reset_versioning_table();

		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		$versioning->save_version( $media_id, 'en', 'description', 'Stored Value', 'ai' );
		phase4_assert_false( $versioning->value_exists( $media_id, 'en', 'description', 'Different Value' ), 'Different value should not be flagged as existing.' );
	}
);

phase4_register_test(
	'Versioning',
	'Locales are sanitized before storage',
	function() {
		phase4_reset_versioning_table();

		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		$versioning->save_version( $media_id, ' es_ES ', 'title', 'Hola', 'template' );

		$active = $versioning->get_active_version( $media_id, 'es_ES', 'title' );
		phase4_assert_not_null( $active, 'Sanitized locale lookup failed.' );
		phase4_assert_equals( 'es_ES', $active['locale'], 'Locale should be trimmed and sanitized.' );
	}
);

phase4_register_test(
	'Versioning',
	'Empty value comparison does not block new content',
	function() {
		phase4_reset_versioning_table();

		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		$versioning->save_version( $media_id, 'en', 'caption', '', 'ai' );
		phase4_assert_true( $versioning->value_exists( $media_id, 'en', 'caption', '' ), 'Empty string checksum should be recognised.' );
		phase4_assert_false( $versioning->value_exists( $media_id, 'en', 'caption', 'Non-empty' ), 'Different value should still be writable.' );
	}
);


/* ------------------------------------------------------------------------- */
/* Manual edit protection tests                                              */
/* ------------------------------------------------------------------------- */

phase4_register_test(
	'Manual Protection',
	'has_manual_edit defaults to false when no versions',
	function() {
		phase4_reset_versioning_table();

		$manual  = MSH_Manual_Edit_Protection::get_instance();
		$media_id = phase4_create_attachment();

		phase4_assert_false( $manual->has_manual_edit( $media_id, 'title', 'en' ), 'No manual edits expected.' );
	}
);

phase4_register_test(
	'Manual Protection',
	'has_manual_edit recognises manual version',
	function() {
		phase4_reset_versioning_table();

		$manual     = MSH_Manual_Edit_Protection::get_instance();
		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		$versioning->save_version( $media_id, 'en', 'title', 'Manual Title', 'manual' );
		phase4_assert_true( $manual->has_manual_edit( $media_id, 'title', 'en' ), 'Manual edit should be detected.' );
	}
);

phase4_register_test(
	'Manual Protection',
	'can_ai_write blocks AI when manual edit exists',
	function() {
		phase4_reset_versioning_table();

		$manual     = MSH_Manual_Edit_Protection::get_instance();
		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		$versioning->save_version( $media_id, 'en', 'description', 'Manual Description', 'manual' );
		phase4_assert_false( $manual->can_ai_write( $media_id, 'description', 'en' ), 'AI should be blocked when manual version active.' );
	}
);

phase4_register_test(
	'Manual Protection',
	'can_ai_write honours force flag',
	function() {
		phase4_reset_versioning_table();

		$manual     = MSH_Manual_Edit_Protection::get_instance();
		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		$versioning->save_version( $media_id, 'en', 'description', 'Manual Description', 'manual' );
		phase4_assert_true( $manual->can_ai_write( $media_id, 'description', 'en', true ), 'Force flag should override manual edit protection.' );
	}
);

phase4_register_test(
	'Manual Protection',
	'Manual edits are locale-aware',
	function() {
		phase4_reset_versioning_table();

		$manual     = MSH_Manual_Edit_Protection::get_instance();
		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		$versioning->save_version( $media_id, 'es', 'title', 'Título Manual', 'manual' );
		phase4_assert_true( $manual->can_ai_write( $media_id, 'title', 'en' ), 'Different locale should not block AI writes.' );
	}
);

phase4_register_test(
	'Manual Protection',
	'AI overwrite clears manual flag',
	function() {
		phase4_reset_versioning_table();

		$manual     = MSH_Manual_Edit_Protection::get_instance();
		$versioning = MSH_Metadata_Versioning::get_instance();
		$media_id   = phase4_create_attachment();

		$versioning->save_version( $media_id, 'en', 'title', 'Manual Title', 'manual' );
		$versioning->save_version( $media_id, 'en', 'title', 'AI Title', 'ai' );

		phase4_assert_false( $manual->has_manual_edit( $media_id, 'title', 'en' ), 'Newest AI version should supersede manual flag.' );
		phase4_assert_true( $manual->can_ai_write( $media_id, 'title', 'en' ), 'AI should be allowed after overwrite.' );
	}
);

phase4_register_test(
	'Manual Protection',
	'detect_alt_text_change saves manual version',
	function() {
		phase4_reset_versioning_table();

		$manual   = MSH_Manual_Edit_Protection::get_instance();
		$media_id = phase4_create_attachment();

		$manual->detect_alt_text_change( 0, $media_id, '_wp_attachment_image_alt', 'Accessible Alt Text' );

		$history = phase4_get_history( $media_id, phase4_default_locale(), 'alt' );
		phase4_assert_equals( 1, count( $history ), 'Alt text manual version should be stored.' );
		phase4_assert_equals( 'manual', $history[0]['source'], 'Alt version should be marked manual.' );
	}
);

phase4_register_test(
	'Manual Protection',
	'detect_alt_text_change ignores non-attachments',
	function() {
		phase4_reset_versioning_table();

		$manual   = MSH_Manual_Edit_Protection::get_instance();
		$post_id  = phase4_create_post();

		$manual->detect_alt_text_change( 0, $post_id, '_wp_attachment_image_alt', 'Should Ignore' );

		$history = phase4_get_history( $post_id, phase4_default_locale(), 'alt' );
		phase4_assert_equals( array(), $history, 'Non-attachments should not generate versions.' );
	}
);

phase4_register_test(
	'Manual Protection',
	'detect_manual_edits stores caption only once per value',
	function() {
		phase4_reset_versioning_table();

		$manual   = MSH_Manual_Edit_Protection::get_instance();
		$media_id = phase4_create_attachment();

		$data = array(
			'image_meta' => array(
				'caption' => 'Manual Caption',
			),
		);

		$manual->detect_manual_edits( $data, $media_id );
		$manual->detect_manual_edits( $data, $media_id );

		$history = phase4_get_history( $media_id, phase4_default_locale(), 'caption' );
		phase4_assert_equals( 1, count( $history ), 'Duplicate captions should not create extra versions.' );
	}
);

phase4_register_test(
	'Manual Protection',
	'detect_title_change captures title and description',
	function() {
		phase4_reset_versioning_table();

		$manual   = MSH_Manual_Edit_Protection::get_instance();
		$media_id = phase4_create_attachment(
			array(
				'post_title'   => 'Original Title',
				'post_content' => 'Original Description',
			)
		);

		wp_update_post(
			array(
				'ID'           => $media_id,
				'post_title'   => 'Manual Title',
				'post_content' => 'Manual Description',
			)
		);

		$manual->detect_title_change( $media_id );

		$title_history       = phase4_get_history( $media_id, phase4_default_locale(), 'title' );
		$description_history = phase4_get_history( $media_id, phase4_default_locale(), 'description' );

		phase4_assert_equals( 1, count( $title_history ), 'Title version missing.' );
		phase4_assert_equals( 'Manual Title', $title_history[0]['value'], 'Stored title mismatch.' );

		phase4_assert_equals( 1, count( $description_history ), 'Description version missing.' );
		phase4_assert_equals( 'Manual Description', $description_history[0]['value'], 'Stored description mismatch.' );
	}
);


/* ------------------------------------------------------------------------- */
/* Test Runner                                                               */
/* ------------------------------------------------------------------------- */

$passed = 0;
$failed = 0;
$results = array();

foreach ( $phase4_tests as $test ) {
	phase4_reset_versioning_table();
	phase4_cleanup_posts();

	try {
		call_user_func( $test['callback'] );
		$results[] = sprintf( '[PASS] %s: %s', $test['group'], $test['name'] );
		$passed++;
	} catch ( Exception $exception ) {
		$results[] = sprintf( '[FAIL] %s: %s -> %s', $test['group'], $test['name'], $exception->getMessage() );
		$failed++;
	}

	phase4_cleanup_posts();
}

$summary = sprintf(
	"Phase 4 Tests Complete: %d passed, %d failed (total %d)\n",
	$passed,
	$failed,
	count( $phase4_tests )
);

echo implode( "\n", $results ) . "\n";
echo str_repeat( '-', 70 ) . "\n";
echo $summary;

if ( $failed > 0 ) {
	exit( 1 );
}

