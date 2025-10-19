<?php
/**
 * Locale Profile Management - WP-CLI Commands
 *
 * CLI commands for managing locale profiles and glossary entries.
 *
 * @package MSH_Image_Optimizer
 * @subpackage AI_Translation
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage locale profiles and glossary for AI translation
 *
 * ## EXAMPLES
 *
 *     # List all locale profiles
 *     $ wp msh locale profile-list
 *
 *     # Create a new locale profile
 *     $ wp msh locale profile-set --locale=fr_FR --tone=formal --formality=4
 *
 *     # Add glossary entry
 *     $ wp msh locale glossary-add --locale=fr_FR --term="WordPress" --protected=1
 *
 *     # List glossary entries
 *     $ wp msh locale glossary-list --locale=fr_FR
 *
 * @when after_wp_load
 */
class MSH_Locale_CLI {

	/**
	 * List all locale profiles
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh locale profile-list
	 *     wp msh locale profile-list --format=json
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function profile_list( $args, $assoc_args ) {
		$manager = MSH_Locale_Profile_Manager::get_instance();

		$profiles = $manager->get_all_profiles();

		if ( empty( $profiles ) ) {
			WP_CLI::warning( 'No locale profiles found' );
			return;
		}

		$table_data = array();

		foreach ( $profiles as $profile ) {
			$table_data[] = array(
				'Locale'      => $profile['locale'],
				'Tone'        => $profile['tone'],
				'CTA Style'   => $profile['cta_style'],
				'Formality'   => $profile['formality_level'],
				'Threshold'   => $profile['confidence_threshold'],
				'Updated'     => $profile['updated_at'],
			);
		}

		$format = $assoc_args['format'] ?? 'table';

		\WP_CLI\Utils\format_items( $format, $table_data, array( 'Locale', 'Tone', 'CTA Style', 'Formality', 'Threshold', 'Updated' ) );
	}

	/**
	 * Show a specific locale profile
	 *
	 * ## OPTIONS
	 *
	 * --locale=<locale>
	 * : Locale code (e.g., en_US, fr_FR)
	 *
	 * [--fallback]
	 * : Use fallback chain if locale not found
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh locale profile-show --locale=fr_FR
	 *     wp msh locale profile-show --locale=de_DE --fallback
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function profile_show( $args, $assoc_args ) {
		$locale   = $assoc_args['locale'] ?? null;
		$fallback = isset( $assoc_args['fallback'] );

		if ( ! $locale ) {
			WP_CLI::error( 'You must specify --locale' );
		}

		$manager = MSH_Locale_Profile_Manager::get_instance();

		if ( $fallback ) {
			$profile = $manager->get_profile_with_fallback( $locale );
		} else {
			$profile = $manager->get_profile( $locale );
		}

		if ( ! $profile ) {
			WP_CLI::error( "Profile not found for locale: {$locale}" );
		}

		WP_CLI::line( WP_CLI::colorize( '%G' . str_repeat( '=', 40 ) . '%n' ) );
		WP_CLI::line( WP_CLI::colorize( "%Y  LOCALE PROFILE: {$locale}%n" ) );
		WP_CLI::line( WP_CLI::colorize( '%G' . str_repeat( '=', 40 ) . '%n' ) );
		WP_CLI::line( '' );

		WP_CLI::line( 'Locale: ' . $profile['locale'] );
		WP_CLI::line( 'Tone: ' . $profile['tone'] );
		WP_CLI::line( 'CTA Style: ' . $profile['cta_style'] );
		WP_CLI::line( 'Formality Level: ' . $profile['formality_level'] . '/5' );
		WP_CLI::line( 'Confidence Threshold: ' . $profile['confidence_threshold'] );

		if ( ! empty( $profile['special_instructions'] ) ) {
			WP_CLI::line( '' );
			WP_CLI::line( 'Special Instructions:' );
			WP_CLI::line( '  ' . $profile['special_instructions'] );
		}

		if ( ! empty( $profile['forbidden_terms'] ) ) {
			WP_CLI::line( '' );
			WP_CLI::line( 'Forbidden Terms:' );
			WP_CLI::line( '  ' . $profile['forbidden_terms'] );
		}

		if ( isset( $profile['_source'] ) ) {
			WP_CLI::line( '' );
			WP_CLI::line( 'Source: ' . $profile['_source'] );
		}

		WP_CLI::success( 'Profile displayed' );
	}

	/**
	 * Create or update a locale profile
	 *
	 * ## OPTIONS
	 *
	 * --locale=<locale>
	 * : Locale code (e.g., en_US, fr_FR)
	 *
	 * [--tone=<tone>]
	 * : Tone (formal, friendly, professional, casual)
	 * ---
	 * default: professional
	 * ---
	 *
	 * [--cta-style=<style>]
	 * : CTA style (direct, subtle, none)
	 * ---
	 * default: subtle
	 * ---
	 *
	 * [--formality=<level>]
	 * : Formality level (1-5, where 1=casual, 5=formal)
	 * ---
	 * default: 3
	 * ---
	 *
	 * [--instructions=<text>]
	 * : Special instructions for this locale
	 *
	 * [--forbidden=<terms>]
	 * : Comma-separated forbidden terms
	 *
	 * [--threshold=<float>]
	 * : Confidence threshold (0.0-1.0)
	 * ---
	 * default: 0.70
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh locale profile-set --locale=fr_FR --tone=formal --formality=4
	 *     wp msh locale profile-set --locale=en_US --instructions="Use American spelling"
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function profile_set( $args, $assoc_args ) {
		$locale = $assoc_args['locale'] ?? null;

		if ( ! $locale ) {
			WP_CLI::error( 'You must specify --locale' );
		}

		$manager = MSH_Locale_Profile_Manager::get_instance();

		$profile_data = array(
			'tone'                 => $assoc_args['tone'] ?? 'professional',
			'cta_style'            => $assoc_args['cta-style'] ?? 'subtle',
			'formality_level'      => isset( $assoc_args['formality'] ) ? (int) $assoc_args['formality'] : 3,
			'special_instructions' => $assoc_args['instructions'] ?? '',
			'forbidden_terms'      => $assoc_args['forbidden'] ?? '',
			'confidence_threshold' => isset( $assoc_args['threshold'] ) ? (float) $assoc_args['threshold'] : 0.70,
		);

		$profile_id = $manager->save_profile( $locale, $profile_data );

		if ( ! $profile_id ) {
			WP_CLI::error( 'Failed to save profile' );
		}

		WP_CLI::success( "Profile saved for locale: {$locale}" );
	}

	/**
	 * Delete a locale profile
	 *
	 * ## OPTIONS
	 *
	 * --locale=<locale>
	 * : Locale code to delete
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh locale profile-delete --locale=fr_FR
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function profile_delete( $args, $assoc_args ) {
		$locale = $assoc_args['locale'] ?? null;

		if ( ! $locale ) {
			WP_CLI::error( 'You must specify --locale' );
		}

		$manager = MSH_Locale_Profile_Manager::get_instance();

		$result = $manager->delete_profile( $locale );

		if ( ! $result ) {
			WP_CLI::error( "Failed to delete profile: {$locale}" );
		}

		WP_CLI::success( "Profile deleted: {$locale}" );
	}

	/**
	 * List glossary entries
	 *
	 * ## OPTIONS
	 *
	 * --locale=<locale>
	 * : Locale code
	 *
	 * [--category=<category>]
	 * : Filter by category
	 *
	 * [--protected]
	 * : Show only protected terms
	 *
	 * [--format=<format>]
	 * : Render output format
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh locale glossary-list --locale=fr_FR
	 *     wp msh locale glossary-list --locale=fr_FR --protected
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function glossary_list( $args, $assoc_args ) {
		$locale = $assoc_args['locale'] ?? null;

		if ( ! $locale ) {
			WP_CLI::error( 'You must specify --locale' );
		}

		$manager = MSH_Locale_Profile_Manager::get_instance();

		$filters = array();

		if ( isset( $assoc_args['category'] ) ) {
			$filters['category'] = $assoc_args['category'];
		}

		if ( isset( $assoc_args['protected'] ) ) {
			$filters['protected'] = 1;
		}

		$entries = $manager->get_glossary_entries( $locale, $filters );

		if ( empty( $entries ) ) {
			WP_CLI::warning( 'No glossary entries found' );
			return;
		}

		$table_data = array();

		foreach ( $entries as $entry ) {
			$table_data[] = array(
				'ID'          => $entry['id'],
				'Term'        => $entry['term'],
				'Translation' => $entry['translation'] ?? '(keep original)',
				'Category'    => $entry['category'],
				'Protected'   => $entry['protected'] ? 'Yes' : 'No',
				'Case'        => $entry['case_sensitive'] ? 'Sensitive' : 'Insensitive',
			);
		}

		$format = $assoc_args['format'] ?? 'table';

		\WP_CLI\Utils\format_items( $format, $table_data, array( 'ID', 'Term', 'Translation', 'Category', 'Protected', 'Case' ) );
	}

	/**
	 * Add a glossary entry
	 *
	 * ## OPTIONS
	 *
	 * --locale=<locale>
	 * : Locale code
	 *
	 * --term=<term>
	 * : Term to add
	 *
	 * [--translation=<translation>]
	 * : Preferred translation (leave empty to keep original)
	 *
	 * [--category=<category>]
	 * : Category (brand, city, product, sku, technical, general)
	 * ---
	 * default: general
	 * ---
	 *
	 * [--protected]
	 * : Mark as protected (never translate)
	 *
	 * [--case-sensitive]
	 * : Enable case-sensitive matching
	 *
	 * [--context=<context>]
	 * : Optional context description
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh locale glossary-add --locale=fr_FR --term="WordPress" --protected
	 *     wp msh locale glossary-add --locale=fr_FR --term="color" --translation="couleur"
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function glossary_add( $args, $assoc_args ) {
		$locale = $assoc_args['locale'] ?? null;
		$term   = $assoc_args['term'] ?? null;

		if ( ! $locale || ! $term ) {
			WP_CLI::error( 'You must specify --locale and --term' );
		}

		$manager = MSH_Locale_Profile_Manager::get_instance();

		$glossary_data = array(
			'term'           => $term,
			'translation'    => $assoc_args['translation'] ?? null,
			'category'       => $assoc_args['category'] ?? 'general',
			'protected'      => isset( $assoc_args['protected'] ) ? 1 : 0,
			'case_sensitive' => isset( $assoc_args['case-sensitive'] ) ? 1 : 0,
			'context'        => $assoc_args['context'] ?? null,
		);

		$entry_id = $manager->add_glossary_entry( $locale, $glossary_data );

		if ( ! $entry_id ) {
			WP_CLI::error( 'Failed to add glossary entry' );
		}

		WP_CLI::success( "Glossary entry added (ID: {$entry_id})" );
	}

	/**
	 * Delete a glossary entry
	 *
	 * ## OPTIONS
	 *
	 * --id=<id>
	 * : Entry ID to delete
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh locale glossary-delete --id=5
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function glossary_delete( $args, $assoc_args ) {
		$entry_id = $assoc_args['id'] ?? null;

		if ( ! $entry_id ) {
			WP_CLI::error( 'You must specify --id' );
		}

		$manager = MSH_Locale_Profile_Manager::get_instance();

		$result = $manager->delete_glossary_entry( $entry_id );

		if ( ! $result ) {
			WP_CLI::error( "Failed to delete entry: {$entry_id}" );
		}

		WP_CLI::success( "Entry deleted: {$entry_id}" );
	}

	/**
	 * Test prompt generation
	 *
	 * ## OPTIONS
	 *
	 * --media-id=<id>
	 * : Media attachment ID
	 *
	 * --locale=<locale>
	 * : Locale code
	 *
	 * [--usage=<type>]
	 * : Usage type (featured, inline, thumbnail, hero)
	 * ---
	 * default: featured
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh locale test-prompt --media-id=123 --locale=fr_FR
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function test_prompt( $args, $assoc_args ) {
		$media_id = $assoc_args['media-id'] ?? null;
		$locale   = $assoc_args['locale'] ?? null;

		if ( ! $media_id || ! $locale ) {
			WP_CLI::error( 'You must specify --media-id and --locale' );
		}

		$template = MSH_Prompt_Template::get_instance();

		$usage_type = $assoc_args['usage'] ?? 'featured';

		$prompt = $template->build_metadata_prompt( $media_id, $locale, array( 'usage_type' => $usage_type ) );

		WP_CLI::line( WP_CLI::colorize( '%G' . str_repeat( '=', 60 ) . '%n' ) );
		WP_CLI::line( WP_CLI::colorize( '%Y  SYSTEM PROMPT%n' ) );
		WP_CLI::line( WP_CLI::colorize( '%G' . str_repeat( '=', 60 ) . '%n' ) );
		WP_CLI::line( '' );
		WP_CLI::line( $prompt['system'] );
		WP_CLI::line( '' );

		WP_CLI::line( WP_CLI::colorize( '%G' . str_repeat( '=', 60 ) . '%n' ) );
		WP_CLI::line( WP_CLI::colorize( '%Y  USER PROMPT%n' ) );
		WP_CLI::line( WP_CLI::colorize( '%G' . str_repeat( '=', 60 ) . '%n' ) );
		WP_CLI::line( '' );
		WP_CLI::line( $prompt['user'] );
		WP_CLI::line( '' );

		WP_CLI::success( 'Prompt generated' );
	}

	/**
	 * Validate metadata
	 *
	 * ## OPTIONS
	 *
	 * --alt=<text>
	 * : Alt text to validate
	 *
	 * --locale=<locale>
	 * : Locale code
	 *
	 * [--title=<text>]
	 * : Title text
	 *
	 * [--description=<text>]
	 * : Description text
	 *
	 * [--caption=<text>]
	 * : Caption text
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh locale validate --alt="Image of a sunset" --locale=en_US
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function validate( $args, $assoc_args ) {
		$alt_text = $assoc_args['alt'] ?? null;
		$locale   = $assoc_args['locale'] ?? null;

		if ( ! $alt_text || ! $locale ) {
			WP_CLI::error( 'You must specify --alt and --locale' );
		}

		$validator = MSH_Metadata_Validator::get_instance();

		$metadata = array(
			'alt_text'    => $alt_text,
			'title'       => $assoc_args['title'] ?? '',
			'description' => $assoc_args['description'] ?? '',
			'caption'     => $assoc_args['caption'] ?? '',
		);

		$result = $validator->validate( $metadata, $locale );

		WP_CLI::line( '' );
		WP_CLI::line( $validator->get_validation_summary( $result ) );

		if ( $result['valid'] ) {
			WP_CLI::success( 'Validation passed' );
		} else {
			WP_CLI::error( 'Validation failed' );
		}
	}
}
