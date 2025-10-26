<?php
/**
 * Starter Templates for TinyDot Template Intelligence (Phase 6)
 *
 * 8 carefully curated templates: 6 active, 2 shadow
 * Designed for high-signal, low-risk matches with ~30-50% hit rate target.
 *
 * @package MSH_Image_Optimizer
 * @since Phase 6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get starter templates array
 *
 * @return array Templates ready for insertion via MSH_Template_Manager.
 */
function msh_get_starter_templates() {
	return array(
		// ============================================================
		// ACTIVE TEMPLATES (6)
		// ============================================================

		array(
			'name'                 => 'Clinic/Office Exterior',
			'site_id'              => '',
			'locale'               => 'en',
			'usage_type'           => 'featured',
			'intent'               => 'on_topic',
			'template_title'       => '{entity} exterior',
			'template_alt'         => 'Exterior view of {entity}',
			'template_caption'     => 'The exterior of {entity}.',
			'template_description' => 'Professional photograph showing the exterior facade and entrance of {entity}.',
			'required_tokens'      => json_encode( array( 'exterior', 'building' ) ),
			'negative_tokens'      => json_encode( array( 'interior', 'room', 'inside' ) ),
			'nice_to_have_tokens'  => json_encode( array( 'clinic', 'office', 'medical', 'entrance' ) ),
			'variables'            => json_encode( array( 'entity', 'post_title' ) ),
			'max_len'              => json_encode( array( 'alt' => 125, 'title' => 60 ) ),
			'notes'                => 'Merged "Building Exterior" + "Clinic/Office Exterior". High confidence for medical/office buildings.',
			'priority'             => 100,
			'is_active'            => 1,
			'mode'                 => 'active',
			'version'              => 1,
		),

		array(
			'name'                 => 'Office/Clinic Interior',
			'site_id'              => '',
			'locale'               => 'en',
			'usage_type'           => 'featured',
			'intent'               => 'on_topic',
			'template_title'       => '{entity} interior',
			'template_alt'         => 'Interior view of {entity}',
			'template_caption'     => 'The interior space at {entity}.',
			'template_description' => 'Professional photograph showing the interior space and facilities at {entity}.',
			'required_tokens'      => json_encode( array( 'interior' ) ),
			'negative_tokens'      => json_encode( array( 'exterior', 'outside', 'facade' ) ),
			'nice_to_have_tokens'  => json_encode( array( 'room', 'office', 'lobby', 'reception', 'clinic', 'waiting' ) ),
			'variables'            => json_encode( array( 'entity', 'post_title' ) ),
			'max_len'              => json_encode( array( 'alt' => 125, 'title' => 60 ) ),
			'notes'                => 'Catches office/clinic interior shots. Safe pattern with clear negative filter.',
			'priority'             => 95,
			'is_active'            => 1,
			'mode'                 => 'active',
			'version'              => 1,
		),

		array(
			'name'                 => 'Team Group Photo',
			'site_id'              => '',
			'locale'               => 'en',
			'usage_type'           => 'featured',
			'intent'               => 'on_topic',
			'template_title'       => '{entity} team',
			'template_alt'         => 'Team members at {entity}',
			'template_caption'     => 'Professional team photo featuring members of {entity}.',
			'template_description' => 'Group photograph of the professional team at {entity}, showcasing the people behind the organization.',
			'required_tokens'      => json_encode( array( 'team', 'people' ) ),
			'negative_tokens'      => json_encode( array( 'logo', 'screenshot', 'portrait', 'headshot', 'individual' ) ),
			'nice_to_have_tokens'  => json_encode( array( 'group', 'staff', 'employees', 'doctors', 'professionals' ) ),
			'variables'            => json_encode( array( 'entity', 'post_title' ) ),
			'max_len'              => json_encode( array( 'alt' => 125, 'title' => 60 ) ),
			'notes'                => 'GROUP photos only. Negative filters prevent individual portraits/headshots.',
			'priority'             => 90,
			'is_active'            => 1,
			'mode'                 => 'active',
			'version'              => 1,
		),

		array(
			'name'                 => 'Product on White Background',
			'site_id'              => '',
			'locale'               => 'en',
			'usage_type'           => 'featured',
			'intent'               => 'on_topic',
			'template_title'       => '{subject}',
			'template_alt'         => '{subject} on white background',
			'template_caption'     => 'Professional product photograph of {subject}.',
			'template_description' => 'Studio product photography showing {subject} on a clean white background for e-commerce or marketing use.',
			'required_tokens'      => json_encode( array( 'product', 'white' ) ),
			'negative_tokens'      => json_encode( array( 'screenshot', 'mockup', 'render', 'ui', 'diagram', 'collage' ) ),
			'nice_to_have_tokens'  => json_encode( array( 'background', 'studio', 'isolated' ) ),
			'variables'            => json_encode( array( 'subject', 'entity' ) ),
			'max_len'              => json_encode( array( 'alt' => 125, 'title' => 60 ) ),
			'notes'                => 'E-commerce product shots. Negative filters prevent screenshots/mockups/renders.',
			'priority'             => 85,
			'is_active'            => 1,
			'mode'                 => 'active',
			'version'              => 1,
		),

		array(
			'name'                 => 'Screenshot/UI',
			'site_id'              => '',
			'locale'               => 'en',
			'usage_type'           => 'featured',
			'intent'               => 'on_topic',
			'template_title'       => '{subject} screenshot',
			'template_alt'         => 'Screenshot showing {subject}',
			'template_caption'     => 'User interface screenshot of {subject}.',
			'template_description' => 'Screenshot capture showing the user interface and functionality of {subject}.',
			'required_tokens'      => json_encode( array( 'screenshot' ) ),
			'negative_tokens'      => json_encode( array( 'photo', 'portrait', 'team', 'person', 'people', 'exterior', 'interior' ) ),
			'nice_to_have_tokens'  => json_encode( array( 'interface', 'ui', 'software', 'app', 'dashboard', 'screen' ) ),
			'variables'            => json_encode( array( 'subject', 'post_title' ) ),
			'max_len'              => json_encode( array( 'alt' => 125, 'title' => 60 ) ),
			'notes'                => 'Software/app screenshots. Strong negatives prevent real photos from matching.',
			'priority'             => 80,
			'is_active'            => 1,
			'mode'                 => 'active',
			'version'              => 1,
		),

		array(
			'name'                 => 'Decorative Texture/Pattern',
			'site_id'              => '',
			'locale'               => 'en',
			'usage_type'           => 'decorative',
			'intent'               => 'off_topic',
			'template_title'       => 'Decorative texture',
			'template_alt'         => 'Abstract texture background',
			'template_caption'     => 'Decorative background texture for visual interest.',
			'template_description' => 'Abstract textured pattern used as a decorative background element to enhance page design.',
			'required_tokens'      => json_encode( array( 'texture' ) ),
			'negative_tokens'      => json_encode( array( 'face', 'person', 'people', 'team', 'logo', 'text', 'screenshot' ) ),
			'nice_to_have_tokens'  => json_encode( array( 'pattern', 'background', 'abstract', 'decorative' ) ),
			'variables'            => json_encode( array() ),
			'max_len'              => json_encode( array( 'alt' => 125, 'title' => 60 ) ),
			'notes'                => 'Decorative backgrounds. No variables since context is off-topic. Strong negatives prevent faces/people.',
			'priority'             => 75,
			'is_active'            => 1,
			'mode'                 => 'active',
			'version'              => 1,
		),

		// ============================================================
		// SHADOW TEMPLATES (2) - Log only, don't apply
		// ============================================================

		array(
			'name'                 => 'Hero Banner (Shadow)',
			'site_id'              => '',
			'locale'               => 'en',
			'usage_type'           => 'featured',
			'intent'               => 'on_topic',
			'template_title'       => '{entity} hero banner',
			'template_alt'         => 'Hero banner image for {entity}',
			'template_caption'     => 'Hero banner showcasing {entity}.',
			'template_description' => 'Wide hero banner image used at the top of the page to showcase {entity} and create visual impact.',
			'required_tokens'      => json_encode( array( 'hero', 'banner' ) ),
			'negative_tokens'      => json_encode( array( 'screenshot', 'logo', 'mockup', 'collage' ) ),
			'nice_to_have_tokens'  => json_encode( array( 'header', 'wide', 'panoramic' ) ),
			'variables'            => json_encode( array( 'entity', 'post_title' ) ),
			'max_len'              => json_encode( array( 'alt' => 125, 'title' => 60 ) ),
			'notes'                => 'SHADOW: Needs validation. Hero/banner terms may be too loose or misidentified.',
			'priority'             => 70,
			'is_active'            => 1,
			'mode'                 => 'shadow',
			'version'              => 1,
		),

		array(
			'name'                 => 'Logo/Branding (Shadow)',
			'site_id'              => '',
			'locale'               => 'en',
			'usage_type'           => 'featured',
			'intent'               => 'on_topic',
			'template_title'       => '{entity} logo',
			'template_alt'         => 'Logo for {entity}',
			'template_caption'     => 'Official logo of {entity}.',
			'template_description' => 'Brand logo for {entity}, used for identification and branding purposes.',
			'required_tokens'      => json_encode( array( 'logo' ) ),
			'negative_tokens'      => json_encode( array( 'watermark', 'signage', 'poster', 'screenshot', 'icon' ) ),
			'nice_to_have_tokens'  => json_encode( array( 'brand', 'branding', 'identity' ) ),
			'variables'            => json_encode( array( 'entity', 'post_title' ) ),
			'max_len'              => json_encode( array( 'alt' => 125, 'title' => 60 ) ),
			'notes'                => 'SHADOW: High risk of confusion with watermarks, icons, or signage. Needs telemetry validation before activation.',
			'priority'             => 65,
			'is_active'            => 1,
			'mode'                 => 'shadow',
			'version'              => 1,
		),
	);
}

/**
 * Install starter templates (safe to run multiple times)
 *
 * @return array Results with created/skipped counts.
 */
function msh_install_starter_templates() {
	$manager = MSH_Template_Manager::get_instance();
	$templates = msh_get_starter_templates();

	$results = array(
		'created' => 0,
		'skipped' => 0,
		'errors'  => array(),
	);

	foreach ( $templates as $template ) {
		// Check if template already exists (by name + locale + usage_type + intent)
		$existing = $manager->get_templates( array(
			'locale'     => $template['locale'],
			'usage_type' => $template['usage_type'],
			'intent'     => $template['intent'],
		) );

		$found = false;
		foreach ( $existing as $existing_template ) {
			if ( $existing_template['name'] === $template['name'] ) {
				$found = true;
				break;
			}
		}

		if ( $found ) {
			$results['skipped']++;
			continue;
		}

		// Create template
		$result = $manager->create_template( $template );

		if ( is_wp_error( $result ) ) {
			$results['errors'][] = sprintf(
				'Failed to create "%s": %s',
				$template['name'],
				$result->get_error_message()
			);
		} else {
			$results['created']++;
		}
	}

	return $results;
}

/**
 * Export templates to JSON (for sharing/backup)
 *
 * @param array $filters Optional filters (locale, usage_type, mode).
 * @return string JSON string.
 */
function msh_export_templates_json( $filters = array() ) {
	$manager = MSH_Template_Manager::get_instance();
	$templates = $manager->get_templates( $filters );

	// Remove database-specific fields
	foreach ( $templates as &$template ) {
		unset( $template['id'] );
		unset( $template['created_at'] );
		unset( $template['updated_at'] );
	}

	return wp_json_encode( $templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
}

/**
 * Import templates from JSON
 *
 * @param string $json   JSON string.
 * @param string $mode   Import mode: 'merge' (default) or 'replace'.
 * @param bool   $dry_run Test mode (don't actually import).
 * @return array Results with created/updated/skipped counts.
 */
function msh_import_templates_json( $json, $mode = 'merge', $dry_run = false ) {
	$manager = MSH_Template_Manager::get_instance();
	$templates = json_decode( $json, true );

	if ( null === $templates ) {
		return new WP_Error( 'invalid_json', 'Invalid JSON format' );
	}

	$results = array(
		'created' => 0,
		'updated' => 0,
		'skipped' => 0,
		'deleted' => 0,
		'errors'  => array(),
	);

	// Replace mode: delete all existing templates first
	if ( 'replace' === $mode && ! $dry_run ) {
		$existing = $manager->get_templates();
		foreach ( $existing as $template ) {
			$manager->delete_template( $template['id'] );
			$results['deleted']++;
		}
	}

	// Import templates
	foreach ( $templates as $template ) {
		// Check if exists (by name + locale + usage_type + intent)
		$existing = $manager->get_templates( array(
			'locale'     => $template['locale'],
			'usage_type' => $template['usage_type'],
			'intent'     => $template['intent'],
		) );

		$existing_id = null;
		foreach ( $existing as $existing_template ) {
			if ( $existing_template['name'] === $template['name'] ) {
				$existing_id = $existing_template['id'];
				break;
			}
		}

		if ( $existing_id && 'merge' === $mode ) {
			// Update existing
			if ( ! $dry_run ) {
				$result = $manager->update_template( $existing_id, $template );
				if ( is_wp_error( $result ) ) {
					$results['errors'][] = sprintf(
						'Failed to update "%s": %s',
						$template['name'],
						$result->get_error_message()
					);
				} else {
					$results['updated']++;
				}
			} else {
				$results['updated']++;
			}
		} elseif ( ! $existing_id ) {
			// Create new
			if ( ! $dry_run ) {
				$result = $manager->create_template( $template );
				if ( is_wp_error( $result ) ) {
					$results['errors'][] = sprintf(
						'Failed to create "%s": %s',
						$template['name'],
						$result->get_error_message()
					);
				} else {
					$results['created']++;
				}
			} else {
				$results['created']++;
			}
		} else {
			$results['skipped']++;
		}
	}

	return $results;
}
