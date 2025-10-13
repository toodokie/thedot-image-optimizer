<?php
/**
 * MSH Image Optimizer
 * Optimizes published images for Main Street Health healthcare website
 */

if (!defined('ABSPATH')) {
    exit;
}

class MSH_Contextual_Meta_Generator {
    private $business_name = 'Main Street Health';
    private $location = 'Hamilton';
    private $location_slug = 'hamilton';
    private $active_context = [];
    private $industry = '';
    private $industry_label = '';
    private $brand_voice = '';
    private $cta_preference = '';
    private $target_audience = '';
    private $uvp = '';
    private $pain_points = '';
    private $active_profile_id = 'primary';
    private $active_profile_label = '';

    private $service_keyword_map = [
        'physiotherapy' => [
            'default' => 'WSIB approved. MVA recovery. First responder programs.',
            'assessment' => 'Functional assessments. Return-to-work evaluation.',
            'acute' => 'Immediate injury care. Same-day appointments available.'
        ],
        'chiropractic' => [
            'default' => 'Spinal care. Workplace injury treatment. WSIB claims supported.',
            'assessment' => 'Spinal assessment and posture evaluation services.',
            'acute' => 'Acute back and neck pain management with direct billing.'
        ],
        'massage' => [
            'default' => 'Registered massage therapy. Insurance coverage available.',
            'assessment' => 'Musculoskeletal assessment and soft tissue release.',
            'acute' => 'Pain relief for muscle strain and injury recovery.'
        ],
        'acupuncture' => [
            'default' => 'Evidence-based acupuncture care. WSIB approved provider.',
            'assessment' => 'Assessment-driven acupuncture plans for recovery.',
            'acute' => 'Immediate relief protocols for pain and inflammation.'
        ],
        'rehabilitation' => [
            'default' => 'Return-to-work programs. WSIB approved. Direct billing.',
            'assessment' => 'Functional capacity assessments and workplace evaluations.',
            'acute' => 'Comprehensive rehabilitation for acute injuries.'
        ],
        'motor-vehicle-accident' => [
            'default' => 'MVA rehabilitation with insurance coordination and direct billing.',
            'assessment' => 'Comprehensive post-collision assessments and recovery plans.',
            'acute' => 'Immediate collision injury support with medical-legal documentation.'
        ],
        'workplace-injury' => [
            'default' => 'WSIB workplace injury rehabilitation with return-to-work planning.',
            'assessment' => 'Workplace functional assessments and ergonomic planning.',
            'acute' => 'Rapid workplace injury care with WSIB reporting support.'
        ],
        'first-responder' => [
            'default' => 'Dedicated first responder rehabilitation programs with duty-ready focus.',
            'assessment' => 'Operational fitness assessments for first responders.',
            'acute' => 'Immediate injury care with expedited recovery pathways.'
        ]
    ];

    private $service_keywords = [
        'physiotherapy' => ['physio', 'physiotherapy', 'physical therapy', 'rehab'],
        'chiropractic' => ['chiro', 'chiropractic', 'spinal'],
        'massage' => ['massage', 'rmt'],
        'acupuncture' => ['acupuncture', 'acupucture', 'needling', 'needle'],
        'rehabilitation' => ['rehab', 'recovery', 'rehabilitation'],
        'motor-vehicle-accident' => ['mva', 'motor vehicle', 'collision', 'auto injury', 'car accident'],
        'workplace-injury' => ['wsib', 'workplace', 'work injury', 'return to work', 'occupational'],
        'first-responder' => ['first responder', 'firefighter', 'paramedic', 'police', 'dispatcher']
    ];

    public function __construct() {
        $this->hydrate_active_context();
    }

    private function hydrate_active_context() {
        if (!class_exists('MSH_Image_Optimizer_Context_Helper')) {
            return;
        }

        $profiles = MSH_Image_Optimizer_Context_Helper::get_profiles();
        $active_profile = MSH_Image_Optimizer_Context_Helper::get_active_profile($profiles);
        $context = isset($active_profile['context']) && is_array($active_profile['context'])
            ? $active_profile['context']
            : array();

        $this->active_profile_id = isset($active_profile['id']) ? sanitize_title($active_profile['id']) : 'primary';
        $this->active_profile_label = isset($active_profile['label']) ? $active_profile['label'] : '';
        $this->active_context = $context;

        if (!empty($context['business_name'])) {
            $this->business_name = sanitize_text_field($context['business_name']);
        }

        $city = isset($context['city']) ? sanitize_text_field($context['city']) : '';
        $region = isset($context['region']) ? sanitize_text_field($context['region']) : '';
        $service_area = isset($context['service_area']) ? sanitize_text_field($context['service_area']) : '';

        $location_parts = array_filter([$city, $region], 'strlen');
        if (!empty($location_parts)) {
            $this->location = implode(', ', $location_parts);
        } elseif ($service_area !== '') {
            $this->location = $service_area;
        }

        if (!empty($this->location)) {
            $this->location_slug = $this->slugify($this->location);
        } elseif ($service_area !== '') {
            $this->location_slug = $this->slugify($service_area);
        }

        $this->industry = isset($context['industry']) ? sanitize_text_field($context['industry']) : '';
        $label_map = MSH_Image_Optimizer_Context_Helper::get_label_map();
        $this->industry_label = MSH_Image_Optimizer_Context_Helper::lookup_label('industry', $this->industry, $label_map);
        $this->brand_voice = isset($context['brand_voice']) ? sanitize_text_field($context['brand_voice']) : '';
        $this->cta_preference = isset($context['cta_preference']) ? sanitize_text_field($context['cta_preference']) : '';
        $this->target_audience = isset($context['target_audience']) ? sanitize_text_field($context['target_audience']) : '';
        $this->uvp = isset($context['uvp']) ? sanitize_textarea_field($context['uvp']) : '';
        $this->pain_points = isset($context['pain_points']) ? sanitize_textarea_field($context['pain_points']) : '';
    }

    private function get_default_context_type() {
        if (empty($this->active_context)) {
            return 'clinical';
        }

        if ($this->is_healthcare_industry($this->industry)) {
            return 'clinical';
        }

        return 'business';
    }

    private function is_healthcare_industry($industry) {
        if ($industry === '') {
            return false;
        }

        $health_slugs = array('medical', 'dental', 'therapy', 'wellness');

        return in_array($industry, $health_slugs, true);
    }

    private function get_industry_label_or_default() {
        if ($this->industry_label !== '') {
            return $this->industry_label;
        }

        return __('Business Services', 'msh-image-optimizer');
    }

    private function get_industry_descriptor() {
        $label = $this->get_industry_label_or_default();
        $descriptor = strtolower($label);

        if (!preg_match('/(service|services|solutions|agency|studio|clinic|store|practice)$/', $descriptor)) {
            $descriptor .= ' services';
        }

        return $descriptor;
    }

    private function get_location_phrase($prefix = ' in ') {
        if ($this->location === '') {
            return '';
        }

        return $prefix . $this->location;
    }

    private function get_cta_sentence() {
        switch ($this->cta_preference) {
            case 'direct':
                return __('Contact us today to get started.', 'msh-image-optimizer');
            case 'balanced':
                return __('Reach out for details or a consultation.', 'msh-image-optimizer');
            case 'soft':
                return __('Discover how we can support your goals.', 'msh-image-optimizer');
            default:
                return '';
        }
    }

    private function get_target_audience_sentence() {
        if ($this->target_audience === '') {
            return '';
        }

        return sprintf(__('Serving %s.', 'msh-image-optimizer'), $this->target_audience);
    }

    private function normalize_sentence($text) {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        if (!preg_match('/[.!?]$/', $text)) {
            $text .= '.';
        }

        return $text;
    }

    public function detect_context($attachment_id, $ignore_manual = false) {
        $this->hydrate_active_context();

        $context = [
            'type' => $this->get_default_context_type(),
            'page_type' => null,
            'page_title' => null,
            'service' => $this->is_healthcare_industry($this->industry) ? 'rehabilitation' : '',
            'parent_id' => 0,
            'tags' => [],
            'manual' => false,
            'attachment_id' => (int) $attachment_id,
            'attachment_title' => '',
            'attachment_slug' => '',
            'file_basename' => '',
            'subject_name' => '',
            'active_profile_id' => $this->active_profile_id,
            'active_profile_label' => $this->active_profile_label,
            'industry' => $this->industry,
            'industry_label' => $this->industry_label,
            'brand_voice' => $this->brand_voice,
            'cta_preference' => $this->cta_preference,
            'target_audience' => $this->target_audience,
            'uvp' => $this->uvp,
            'pain_points' => $this->pain_points
        ];

        $manual = get_post_meta($attachment_id, '_msh_context', true);
        $manual = is_string($manual) ? trim($manual) : '';
        $context['manual_value'] = $manual;

        if (!$ignore_manual && $manual !== '') {
            $context['type'] = sanitize_text_field($manual);
            $context['manual'] = true;
        }

        $attachment = get_post($attachment_id);
        $parent_id = $attachment ? (int) $attachment->post_parent : 0;
        $context['parent_id'] = $parent_id;
        if ($attachment) {
            $context['attachment_title'] = $attachment->post_title;
        }

        $attachment_title = $attachment ? strtolower((string) $attachment->post_title) : '';
        $file_meta = get_post_meta($attachment_id, '_wp_attached_file', true);
        $file_name = $file_meta ? basename($file_meta) : '';
        $file_basename = $file_name ? strtolower(pathinfo($file_name, PATHINFO_FILENAME)) : '';
        $context['file_basename'] = $file_basename;
        $context['attachment_slug'] = $this->slugify(!empty($context['attachment_title']) ? $context['attachment_title'] : $file_basename);
        $context['filename'] = $file_name;
        $context['original_filename'] = $file_name; // Add for keyword extraction

        if (!$context['manual']) {
            $meta_sizes = wp_get_attachment_metadata($attachment_id);
            $width = 0;
            $height = 0;
            if (is_array($meta_sizes)) {
                $width = isset($meta_sizes['width']) ? (int) $meta_sizes['width'] : 0;
                $height = isset($meta_sizes['height']) ? (int) $meta_sizes['height'] : 0;
            }

            $icon_context = $this->detect_icon_context($attachment_id, $context, $width, $height);
            if ($icon_context && in_array($context['type'], ['clinical', 'business'], true)) {
                $context = array_merge($context, $icon_context);
            }

            $product_context = $this->detect_product_context($attachment_id, $context);
            if ($product_context && in_array($context['type'], ['clinical', 'business'], true)) {
                $context = array_merge($context, $product_context);
            }
        }


        if ($attachment) {
            $this->apply_attachment_context($context, $attachment, $attachment_title, $file_basename);
        }

        if ($parent_id > 0) {
            $parent_post = get_post($parent_id);
            if ($parent_post) {
                $context['page_type'] = get_post_type($parent_post);
                $context['page_title'] = $parent_post->post_title;
                $this->apply_parent_context($context, $parent_post, $attachment_id, $file_basename);
            }
        }

        // Featured usage (e.g., attached as featured image on other posts)
        $featured_usage = $this->find_featured_usage($attachment_id);
        if (!empty($featured_usage)) {
            $first = $featured_usage[0];
            if (empty($context['page_title'])) {
                $context['page_title'] = $first['post_title'];
                $context['page_type'] = $first['post_type'];
            }
            $this->apply_usage_context($context, $featured_usage, $file_basename);
        }

        // Media categories / taxonomies
        $media_terms = wp_get_object_terms($attachment_id, ['media_category'], ['fields' => 'slugs']);
        if (!is_wp_error($media_terms) && !empty($media_terms)) {
            $context['tags'] = array_merge($context['tags'], $media_terms);
            if (in_array('team', $media_terms, true)) {
                $context['type'] = 'team';
            } elseif (in_array('testimonials', $media_terms, true)) {
                $context['type'] = 'testimonial';
            } elseif (in_array('facility', $media_terms, true)) {
                $context['type'] = 'facility';
            } elseif (in_array('equipment', $media_terms, true)) {
                $context['type'] = 'equipment';
            }
        }

        $combined_indicator = strtolower(trim(($context['attachment_title'] ?? '') . ' ' . $file_basename));
        if (
            !$context['manual']
            && in_array($context['type'], ['clinical', 'business'], true)
            && strpos($combined_indicator, 'icon') !== false
        ) {
            $context['type'] = 'service-icon';
            $context['service'] = $this->extract_service_type($context['page_title'], $context['tags'], [$combined_indicator]);
        }

        // Service extraction for clinical images
        if ($context['type'] === 'clinical') {
            $extra_sources = array_filter([$attachment_title, $file_basename]);
            $context['service'] = $this->extract_service_type($context['page_title'], $context['tags'], $extra_sources);
        }

        if (!$context['manual']) {
            $asset_type = $this->detect_asset_type(strtolower(trim(($context['attachment_title'] ?? '') . ' ' . $file_basename . ' ' . ($context['page_title'] ?? ''))));

            // IMPORTANT: Don't override icon context that was already set by detect_icon_context
            if ($context['type'] === 'service-icon') {
                // Debug disabled for performance
                // Don't apply any asset type overrides - keep as service-icon
            } elseif ($asset_type === 'logo') {
                $context['type'] = 'business';
                $context['asset'] = 'logo';
            } elseif ($asset_type === 'icon') {
                $context['type'] = 'service-icon';
            } elseif ($asset_type === 'frame') {
                $context['type'] = 'business';
                $context['asset'] = 'graphic';
            } elseif ($asset_type === 'product') {
                if ($this->is_healthcare_industry($this->industry)) {
                    $context['type'] = 'equipment';
                } else {
                    $context['type'] = 'business';
                }
                $context['asset'] = 'product';
                $context['product_type'] = $this->extract_product_type($file_basename, $context['attachment_title']);
            } elseif ($asset_type === 'graphic') {
                $context['type'] = 'business';
                $context['asset'] = 'graphic';
            }
        }

        if ($context['type'] === 'testimonial' && empty($context['subject_name'])) {
            $context['subject_name'] = $this->extract_subject_name($context['attachment_title'] ?: str_replace(['-', '_'], ' ', $file_basename));
        }

        $context['source'] = $context['manual'] ? 'manual' : 'auto';

        if (!$context['manual']) {
            update_post_meta($attachment_id, '_msh_auto_context', $context['type']);
        }

        return $context;
    }

    private function apply_parent_context(array &$context, WP_Post $parent_post, $attachment_id, $file_basename = '') {
        if (!empty($context['manual'])) {
            return;
        }

        $title = $parent_post->post_title;
        $post_type = get_post_type($parent_post);

        if (in_array($post_type, ['team', 'staff', 'msh_team_member'], true)) {
            $context['type'] = 'team';
            $context['staff_name'] = $title;
            return;
        }

        $categories = wp_get_post_categories($parent_post->ID, ['fields' => 'slugs']);
        if (!is_wp_error($categories) && !empty($categories)) {
            $context['tags'] = array_merge($context['tags'], $categories);
            if (array_intersect($categories, ['team', 'staff'])) {
                $context['type'] = 'team';
                $context['staff_name'] = $title;
                return;
            }
            if (array_intersect($categories, ['testimonials', 'reviews', 'success-stories'])) {
                $context['type'] = 'testimonial';
                if (empty($context['subject_name'])) {
                    $context['subject_name'] = $this->extract_subject_name($title ?: $file_basename);
                }
                return;
            }
            if (array_intersect($categories, ['facility', 'clinic', 'office'])) {
                $context['type'] = 'facility';
            }
            if (array_intersect($categories, ['equipment'])) {
                $context['type'] = 'equipment';
            }
        }

        $template = get_page_template_slug($parent_post->ID);
        if ($template) {
            if (strpos($template, 'team') !== false) {
                $context['type'] = 'team';
                $context['staff_name'] = $title;
            } elseif (strpos($template, 'testimonial') !== false) {
                $context['type'] = 'testimonial';
                if (empty($context['subject_name'])) {
                    $context['subject_name'] = $this->extract_subject_name($title ?: $file_basename);
                }
            } elseif (strpos($template, 'facility') !== false) {
                $context['type'] = 'facility';
            }
        }

        if ($context['type'] === 'clinical') {
            $extra_sources = array_filter([$title, $file_basename]);
            $context['service'] = $this->extract_service_type($title, $context['tags'], $extra_sources);
        }

        // Gallery detection for reference
        if (!empty($parent_post->post_content) && has_shortcode($parent_post->post_content, 'gallery')) {
            if (strpos($parent_post->post_content, (string) $attachment_id) !== false) {
                $context['in_gallery'] = true;
                $context['gallery_page'] = $title;
            }
        }
    }

    private function extract_service_type($title, array $tags = [], array $extra_sources = []) {
        $sources = [];
        if (!empty($title)) {
            $sources[] = strtolower((string) $title);
        }
        foreach ($tags as $tag) {
            $sources[] = strtolower((string) $tag);
        }
        foreach ($extra_sources as $extra) {
            if (!empty($extra)) {
                $sources[] = strtolower((string) $extra);
            }
        }

        foreach ($sources as $text) {
            foreach ($this->service_keywords as $service => $keywords) {
                foreach ($keywords as $keyword) {
                    if ($keyword !== '' && strpos($text, $keyword) !== false) {
                        return $service;
                    }
                }
            }
        }

        return 'rehabilitation';
    }

    private function find_featured_usage($attachment_id) {
        global $wpdb;
        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT posts.ID, posts.post_title, posts.post_type \n                 FROM {$wpdb->postmeta} meta \n                 INNER JOIN {$wpdb->posts} posts ON posts.ID = meta.post_id \n                 WHERE meta.meta_key = '_thumbnail_id' AND meta.meta_value = %d AND posts.post_status = 'publish'",
                $attachment_id
            ),
            ARRAY_A
        );

        return $posts ?: [];
    }

    private function apply_usage_context(array &$context, array $usage, $file_basename = '') {
        if (!empty($context['manual'])) {
            return;
        }

        foreach ($usage as $item) {
            $post_type = $item['post_type'];
            $title = $item['post_title'];

            if (empty($context['page_title'])) {
                $context['page_title'] = $title;
                $context['page_type'] = $post_type;
            }

            if (in_array($post_type, ['team', 'staff', 'msh_team_member'], true)) {
                $context['type'] = 'team';
                $context['staff_name'] = $title;
                return;
            }

            if (stripos($title, 'testimonial') !== false || stripos($title, 'review') !== false) {
                $context['type'] = 'testimonial';
                if (empty($context['subject_name'])) {
                    $context['subject_name'] = $this->extract_subject_name($title ?: $file_basename);
                }
                return;
            }

            if ($context['type'] === 'clinical') {
                $service = $this->extract_service_type($title, [], array_filter([$file_basename]));
                if (!empty($service)) {
                    $context['service'] = $service;
                }
            }
        }
    }

    private function apply_attachment_context(array &$context, WP_Post $attachment, $title_lower, $file_basename) {
        if (!empty($context['manual'])) {
            return;
        }

        $combined = trim($title_lower . ' ' . $file_basename);

        if ($context['type'] !== 'team' && $this->text_contains_any($combined, ['team', 'staff', 'doctor', 'dr-', 'physiotherapist', 'therapist', 'rmt', 'chiropractor'])) {
            $context['type'] = 'team';
            $context['staff_name'] = $attachment->post_title;
            return;
        }

        if ($context['type'] !== 'testimonial' && $this->text_contains_any($combined, ['testimonial', 'review', 'patient-story', 'patient_story', 'success', 'case-study', 'before-after'])) {
            $context['type'] = 'testimonial';
            if (empty($context['subject_name'])) {
                $context['subject_name'] = $this->extract_subject_name($attachment->post_title ?: $file_basename);
            }
            return;
        }

        if ($context['type'] !== 'facility' && $this->text_contains_any($combined, ['facility', 'clinic', 'office', 'reception', 'lobby', 'exterior', 'interior', 'waiting-room', 'front-desk'])) {
            $context['type'] = 'facility';
            return;
        }

        if ($context['type'] !== 'equipment' && $this->text_contains_any($combined, ['equipment', 'machine', 'device', 'laser', 'ultrasound', 'tens', 'table', 'traction'])) {
            $context['type'] = 'equipment';
        }
    }

    private function text_contains_any($text, array $needles) {
        if ($text === '') {
            return false;
        }

        foreach ($needles as $needle) {
            if ($needle !== '' && strpos($text, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function extract_subject_name($text) {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\.[a-z0-9]{2,5}$/i', '', $text);
        $text = str_replace(['-', '_'], ' ', $text);
        $text = preg_replace('/\b(review|testimonial|success|story|patient|case\s*study)\b/i', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if ($text === '') {
            return $this->is_healthcare_industry($this->industry) ? 'Patient' : 'Client';
        }

        $normalized = ucwords(strtolower($text));
        if (empty($normalized)) {
            return $this->is_healthcare_industry($this->industry) ? 'Patient' : 'Client';
        }

        return $normalized;
    }

    private function truncate_slug($slug, $max_words = 4) {
        if (empty($slug)) {
            return '';
        }

        $parts = preg_split('/-+/', strtolower($slug));
        $stopwords = ['and', 'with', 'the', 'for', 'a', 'an', 'to', 'of', 'in', 'at', 'on', 'by', 'from', 'about'];

        $filtered = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || in_array($part, $stopwords, true) || is_numeric($part)) {
                continue;
            }
            $filtered[] = $part;
            if (count($filtered) >= max(1, (int) $max_words)) {
                break;
            }
        }

        if (empty($filtered)) {
            $filtered = array_slice(array_filter($parts), 0, 1);
        }

        return implode('-', $filtered);
    }

    public function format_service_label($service) {
        if (empty($service)) {
            return 'Rehabilitation';
        }

        $label = str_replace(['-', '_'], ' ', strtolower($service));
        $label = preg_replace('/\s+/', ' ', $label);

        return ucwords(trim($label));
    }

    private function merge_slug_fragments($base, $extra) {
        if (empty($extra)) {
            return $base;
        }

        $base_parts = array_filter(explode('-', strtolower($base)));
        $extra_parts = array_filter(explode('-', strtolower($extra)));

        $combined = $base_parts;
        foreach ($extra_parts as $part) {
            if (!in_array($part, $combined, true)) {
                $combined[] = $part;
            }
        }

        return implode('-', $combined);
    }

    private function normalize_icon_concept($concept_source) {
        $concept_source = strtolower(trim((string) $concept_source));
        $concept_source = preg_replace('/\.[a-z0-9]+$/', '', $concept_source);
        $concept_source = str_replace(['_', ' '], '-', $concept_source);
        $concept_source = preg_replace('/-+/', '-', $concept_source);
        $concept_source = trim($concept_source, '-');

        $slug = preg_replace('/-?icon$/', '', $concept_source);

        if ($slug === '') {
            $slug = 'service';
        }

        $label = $this->format_service_label($slug);

        return [$slug, $label];
    }


    private function detect_product_context($attachment_id, array $context) {
        $file_meta = get_post_meta($attachment_id, '_wp_attached_file', true);
        $filename = strtolower($file_meta ? basename($file_meta) : '');
        $title = strtolower($context['attachment_title'] ?? '');
        $caption = strtolower((string) get_post_field('post_excerpt', $attachment_id));
        $combined = $filename . ' ' . $title . ' ' . $caption;
        $is_healthcare = $this->is_healthcare_industry($this->industry);

        $patterns = [
            '/mediflow|waterbase|pillow/' => ['product_type' => 'therapeutic-pillow'],
            '/biofreeze|gel|cream/' => ['product_type' => 'pain-relief'],
            '/tens|electrotherapy|stimulator/' => ['product_type' => 'tens-unit'],
            '/frame-?\d+|custom.?sole|orthotic|insole/' => ['product_type' => 'custom-orthotics'],
            '/compression|stocking|sock/' => ['product_type' => 'compression-therapy'],
            '/(ankle|wrist|knee|elbow|shoulder|back|neck|foot|hand).*(brace|support|wrap|sleeve)/' => ['product_type' => 'support-brace'],
            '/brace|support|wrap|sleeve|splint|stabilizer/' => ['product_type' => 'support-product']
        ];

        // Broader business-friendly patterns (marketing/product collateral)
        $business_patterns = [
            '/product|catalog|brochure|sell-sheet|sell sheet|flyer|one-pager|one pager|lookbook|portfolio/' => 'product-collateral',
            '/packaging|label|bottle|jar|box|mockup|prototype/' => 'product-packaging',
            '/device|hardware|equipment|kit|bundle|collection/' => 'product-showcase'
        ];

        foreach ($patterns as $pattern => $data) {
            if (preg_match($pattern, $combined)) {
                if ($is_healthcare) {
                    return [
                        'type' => 'equipment',
                        'service' => 'rehabilitation',
                        'product_type' => $data['product_type'],
                        'asset' => 'product',
                        'source' => 'auto'
                    ];
                }

                return [
                    'type' => 'business',
                    'asset' => 'product',
                    'product_type' => $data['product_type'],
                    'source' => 'auto'
                ];
            }
        }

        if (!$is_healthcare) {
            foreach ($business_patterns as $pattern => $category) {
                if (preg_match($pattern, $combined)) {
                    return [
                        'type' => 'business',
                        'asset' => 'product',
                        'product_category' => $category,
                        'source' => 'auto'
                    ];
                }
            }
        }

        return null;
    }

    private function detect_icon_context($attachment_id, array $context, $width = 0, $height = 0) {
        $file_meta = get_post_meta($attachment_id, '_wp_attached_file', true);
        $filename = strtolower($file_meta ? basename($file_meta) : '');
        $directory = strtolower($file_meta ? dirname($file_meta) : '');
        $title = strtolower($context['attachment_title'] ?? '');
        $caption = strtolower((string) get_post_field('post_excerpt', $attachment_id));
        $combined = $filename . ' ' . $title . ' ' . $caption . ' ' . $directory;

        // Primary icon detection - be more aggressive with SVGs
        $icon_keyword = preg_match('/icon|\.svg$|\/icons\//', $combined);

        // ENHANCED: Auto-detect all SVG files as potential icons (with size check)
        if (strpos($filename, '.svg') !== false) {
            // Most SVGs under 300x300 are likely icons
            if (($width > 0 && $height > 0 && $width <= 300 && $height <= 300) || ($width == 0 && $height == 0)) {
                $icon_keyword = 1;
                error_log("MSH Icon Debug: Auto-detected SVG as icon: '$filename' (size: {$width}x{$height})");
            }
        }
        $concept_keyword = preg_match('/(chronic[-_ ]?pain|sport[-_ ]?injur|work[-_ ]?related[-_ ]?injur|workplace[-_ ]?injur|motor[-_ ]?icon|vehicle[-_ ]?icon|accident|wsib|program)/', $combined);

        // Force healthcare equipment SVGs to be treated as icons
        $healthcare_equipment_pattern = '/(crutches|orthopedic|pillow|compression|stocking|brace|walker|wheelchair|cane|tens|ultrasound|foam|roller|brain|nervous|system|scoliosis|injury|nerve|stimulator|spine|back|neck|joint)/';

        // Also force Noun Project SVGs to be treated as icons (with optional suffixes)
        $noun_project_pattern = '/^noun-(.+)-\d{4,7}-[A-F0-9]{6}(?:-\d+)*\.svg$/i';

        if (strpos($filename, '.svg') !== false &&
            (preg_match($healthcare_equipment_pattern, $combined) || preg_match($noun_project_pattern, $filename))) {
            $icon_keyword = 1; // Force icon detection
            error_log("MSH Icon Debug: Forced SVG to icon: '$filename' (Healthcare: " .
                (preg_match($healthcare_equipment_pattern, $combined) ? 'YES' : 'NO') .
                ", Noun Project: " . (preg_match($noun_project_pattern, $filename) ? 'YES' : 'NO') . ")");
        }

        // Debug SVG icon detection
        if (strpos($filename, '.svg') !== false) {
            // Debug disabled for performance

        }

        if (!$icon_keyword && $concept_keyword) {
            $max_icon_dimension = 600;

            if ($width > 0 && $height > 0 && ($width > $max_icon_dimension || $height > $max_icon_dimension)) {
                return null;
            }
        }

        if (!$icon_keyword && !$concept_keyword) {
            return null;
        }

        $category = 'service';
        if (preg_match('/chronic|pain|injur|condition|mobility|wellness|posture|sport/', $combined)) {
            $category = 'condition';
        } elseif (preg_match('/wsib|work([-_ ]?related)|workplace|mva|vehicle|program|rehab-plan/', $combined)) {
            $category = 'program';
        } elseif (preg_match('/team|staff|doctor|therapist/', $combined)) {
            $category = 'team';
        }

        // First try to extract clean concept from Noun Project filenames
        $concept_source = $context['attachment_slug'] ?? pathinfo($filename, PATHINFO_FILENAME);

        // Check if this is a Noun Project file and extract the clean term
        if (preg_match('/^noun-(.+)-\d{4,7}-[A-F0-9]{6}/i', $filename, $matches)) {
            $concept_source = $matches[1]; // Extract just the concept part (e.g., "foot-bandage")
            error_log("MSH Icon Debug: Extracted Noun Project concept: '$concept_source' from '$filename'");
        }

        // If still no obvious concept, try inferring from service keywords
        if (!$icon_keyword) {
            foreach ($this->service_keywords as $service => $keywords) {
                if ($this->text_contains_any($combined, $keywords)) {
                    $concept_source = $service . '-icon';
                    $category = 'service';
                    break;
                }
            }
        }

        list($concept_slug, $concept_label) = $this->normalize_icon_concept($concept_source);

        return [
            'type' => 'service-icon',
            'icon_type' => $category,
            'icon_concept' => $concept_slug,
            'icon_concept_label' => $concept_label,
            'asset' => 'icon',
            'source' => 'auto'
        ];
    }

    private function detect_asset_type($text) {
        if (empty($text)) {
            return false;
        }

        $patterns = [
            'logo' => '/\b(logo|brandmark|wordmark|seal|badge)\b/i',
            'icon' => '/\b(icon|symbol|glyph|badge|\.svg)\b/i',  // Enhanced: Include .svg extension
            'frame' => '/\b(frame|border|template|layout|mockup)\b/i',
            'product' => '/\b(pillow|brace|support|sleeve|wrap|tens|biofreeze|orthotic|stocking|pillow|equipment|device|gel|cream)\b/i',
            'equipment' => '/\b(machine|table|apparatus|equipment|device|tool)\b/i',
            'graphic' => '/\b(graphic|illustration|diagram|infographic|chart)\b/i'
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $text)) {
                return $type;
            }
        }

        return false;
    }

    private function extract_product_type($basename, $title) {
        $combined = strtolower($basename . ' ' . $title);
        $keywords = ['pillow', 'brace', 'support', 'sleeve', 'wrap', 'gel', 'cream', 'tape', 'orthotic', 'stocking'];

        foreach ($keywords as $keyword) {
            if (strpos($combined, $keyword) !== false) {
                return $keyword;
            }
        }

        return 'rehabilitation-product';
    }

    private function ensure_unique_title($title, $attachment_id) {
        if (!$attachment_id) {
            return $title;
        }

        global $wpdb;

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND ID != %d AND post_type = 'attachment' LIMIT 1",
            $title,
            $attachment_id
        ));

        if (!$existing) {
            return $title;
        }

        $variants = [' Session', ' Treatment', ' Case Study', ' Program', ' Assessment'];
        $index = $attachment_id % count($variants);

        return $title . $variants[$index];
    }

    public function generate_meta_fields($attachment_id, array $context) {
        $this->hydrate_active_context();
        error_log("MSH Meta Generation: Type='{$context['type']}', attachment_id=$attachment_id, title='{$context['attachment_title']}'");

        if (!$this->is_healthcare_industry($this->industry) && $context['type'] === 'clinical') {
            $context['type'] = 'business';
        }

        switch ($context['type']) {
            case 'team':
                return $this->generate_team_meta($context);
            case 'testimonial':
                return $this->generate_testimonial_meta($context);
            case 'icon':
                // Legacy fallback - redirect to service-icon
                return $this->generate_service_icon_meta($context);
            case 'service-icon':
                return $this->generate_service_icon_meta($context);
            case 'facility':
                return $this->generate_facility_meta($context);
            case 'equipment':
                if (!empty($context['asset']) && $context['asset'] === 'product') {
                    return $this->generate_product_meta($context);
                }
                return $this->generate_equipment_meta($context);
            case 'business':
                if (!empty($context['asset'])) {
                    if ($context['asset'] === 'logo') {
                        return $this->generate_logo_meta($context);
                    }
                    if ($context['asset'] === 'product') {
                        return $this->generate_product_meta($context);
                    }
                }
                return $this->generate_business_meta($context);
            case 'clinical':
            default:
                return $this->generate_clinical_meta($context);
        }
    }

    public function generate_filename_slug($attachment_id, array $context, $extension = null) {
        $this->hydrate_active_context();
        // Debug disabled for performance

        if (!$this->is_healthcare_industry($this->industry) && $context['type'] === 'clinical') {
            $context['type'] = 'business';
        }

        switch ($context['type']) {
            case 'team':
                $name = !empty($context['staff_name']) ? $context['staff_name'] : 'team-member';
                return $this->slugify("{$this->business_name}-team-{$name}");
            case 'testimonial':
                $prefix = $this->is_healthcare_industry($this->industry) ? 'patient' : 'client';
                $subject_slug = !empty($context['attachment_slug']) ? $this->truncate_slug($context['attachment_slug'], 3) : $prefix;
                $location_component = $this->location_slug !== '' ? '-' . $this->location_slug : '';
                return $this->slugify($prefix . '-testimonial-' . $subject_slug . $location_component);
            case 'icon':
                // Legacy fallback - reuse service-icon generator with proper arguments
                $normalized_context = $context;
                $normalized_context['type'] = 'service-icon';
                return $this->generate_filename_slug($attachment_id, $normalized_context, $extension);
            case 'service-icon':
                // FIRST: Try filename extraction for high-quality names (same as icon case)
                $original_filename = $context['original_filename'] ?? '';
                $extracted_keywords = $this->extract_filename_keywords($original_filename);

                // Debug disabled for performance

                if (!empty($extracted_keywords)) {
                    $concept_source = $extracted_keywords;
                } else {
                    $concept_source = !empty($context['icon_concept']) ? $this->slugify($context['icon_concept']) : '';
                    if ($concept_source === '') {
                        $concept_source = $context['service'] ?? 'service';
                    }
                }

                $concept = $this->slugify($concept_source);
                if ($concept === '') {
                    $concept = 'service';
                }

                // Debug disabled for performance
                return $this->slugify($concept . '-icon-' . $this->location_slug);
            case 'facility':
                return $this->slugify($this->business_name . '-facility-' . $this->location_slug);
            case 'equipment':
                // Smart filename extraction from original name
                $original_filename = $context['original_filename'] ?? '';
                $extracted_keywords = $this->extract_filename_keywords($original_filename);

                error_log("MSH Debug Equipment Case: Original='$original_filename', Extracted='$extracted_keywords'");

                if (!empty($extracted_keywords)) {
                    return $this->slugify($extracted_keywords . '-equipment-' . $this->location_slug);
                }

                if (!empty($context['asset']) && $context['asset'] === 'product') {
                    $product_map = [
                        'therapeutic-pillow' => 'pillow',
                        'custom-orthotics' => 'orthotics',
                        'support-brace' => 'brace',
                        'support-product' => 'support',
                        'tens-unit' => 'tens-unit',
                        'pain-relief' => 'pain-relief',
                        'compression-therapy' => 'compression'
                    ];
                    $product_type = $context['product_type'] ?? 'support';
                    $product_slug = $this->truncate_slug($product_map[$product_type] ?? $product_type, 2);
                    $components = array_filter([$product_slug, $this->location_slug]);
                    return $this->slugify(implode('-', $components));
                }
                return $this->slugify('rehabilitation-equipment-' . $this->location_slug);
            case 'business':
                $original_filename = strtolower($context['original_filename'] ?? '');
                $brand_keywords = $this->extract_brand_keywords($original_filename);
                $location_component = $this->location_slug !== '' ? '-' . $this->location_slug : '';

                if (!empty($context['asset'])) {
                    if ($context['asset'] === 'logo') {
                        if (!empty($brand_keywords)) {
                            return $this->slugify($brand_keywords . '-logo' . $location_component);
                        }

                        $brand_slug = $this->business_name !== ''
                            ? $this->slugify($this->business_name)
                            : 'brand';

                        return $this->slugify($brand_slug . '-logo' . $location_component);
                    }

                    if ($context['asset'] === 'product') {
                        $product_slug = !empty($context['attachment_slug'])
                            ? $this->truncate_slug($context['attachment_slug'], 4)
                            : 'product';

                        $components = array_filter([
                            $this->business_name !== '' ? $this->slugify($this->business_name) : null,
                            $product_slug,
                            $this->location_slug !== '' ? $this->location_slug : null,
                        ]);

                        return $this->slugify(implode('-', $components));
                    }
                }

                if (!empty($brand_keywords)) {
                    return $this->slugify($brand_keywords . $location_component);
                }

                $business_keywords = $this->extract_business_slug_keywords($context);
                $components = array();

                if ($business_keywords !== '') {
                    $components[] = $business_keywords;
                }

                if ($this->business_name !== '') {
                    $components[] = $this->slugify($this->business_name);
                }

                if ($this->industry_label !== '') {
                    $components[] = $this->slugify($this->industry_label);
                }

                if ($this->location_slug !== '') {
                    $components[] = $this->location_slug;
                }

                if (!empty($context['asset']) && $context['asset'] === 'graphic') {
                    $components[] = 'graphic';
                } elseif (!empty($context['asset']) && $context['asset'] === 'product') {
                    $components[] = 'product';
                } else {
                    $components[] = 'branding';
                }

                $descriptor_phrase = $this->derive_visual_descriptor($context);
                if ($descriptor_phrase !== '') {
                    $components[] = $this->slugify($descriptor_phrase);
                }

                $components = array_values(array_filter(array_unique($components), 'strlen'));

                if (!$this->is_healthcare_industry($this->industry)) {
                    $components = array_filter($components, function ($component) {
                        return !preg_match('/\b(rehabilitation|physiotherapy|therapy|clinical|treatment)\b/', $component);
                    });
                }

                if (empty($components)) {
                    $components[] = 'brand-asset';
                }

                return $this->slugify(implode('-', $components));
            case 'clinical':
            default:
                // SEO-optimized treatment keywords (more specific first, word-boundary safe)
                $treatment_keywords = [
                    'auto-accident' => ['auto accident', 'car accident', 'motor vehicle accident', 'mva'],
                    'workplace-injury' => ['workplace injury', 'work injury', 'ergonomic injury'],
                    'sports-injury' => ['sports injury', 'athletic injury', 'athlete injury'],
                    'concussion' => ['concussion', 'head injury', 'brain injury'],
                    'sciatica' => ['sciatica', 'sciatic nerve', 'leg pain'],
                    'back-pain' => ['back pain', 'spine pain', 'spinal pain', 'lumbar pain'],
                    'neck-pain' => ['neck pain', 'cervical pain', 'whiplash'],
                    'tmj' => ['tmj', 'jaw pain', 'temporal mandibular'],
                    'shoulder-pain' => ['shoulder pain', 'rotator cuff'],
                    'knee-pain' => ['knee pain', 'patella'],
                    'hip-pain' => ['hip pain', 'pelvis pain'],
                    'ankle-pain' => ['ankle pain', 'foot pain'],
                    'arthritis' => ['arthritis', 'joint pain'],
                    'rehabilitation' => ['rehabilitation', 'rehab', 'recovery', 'therapy']
                ];

                // FIRST: Try direct filename keyword extraction for high-quality names
                $original_filename = strtolower($context['original_filename'] ?? '');
                $extracted_keywords = $this->extract_filename_keywords($original_filename);

                if (!empty($extracted_keywords) && $this->is_high_quality_extracted_name($extracted_keywords, $original_filename)) {
                    error_log("MSH Clinical Debug: Using high-quality extracted keywords '$extracted_keywords' from '$original_filename'");
                    return $this->slugify($extracted_keywords . '-' . $this->location_slug);
                }

                // FALLBACK: Extract keywords from context AND original filename for treatment matching
                $service = $context['service'] ?? 'rehabilitation';
                $page_title = strtolower($context['page_title'] ?? '');
                $attachment_title = strtolower($context['attachment_title'] ?? '');
                $search_text = $page_title . ' ' . $attachment_title . ' ' . $service . ' ' . $original_filename;

                // Debug logging for problematic cases
                error_log("MSH Clinical Debug: AttachmentID={$context['attachment_id']}, SearchText='$search_text'");

                // Find best matching treatment (prioritize filename over page context)
                $primary_keyword = 'rehabilitation'; // fallback

                // First, try to extract from filename specifically (with word boundaries)
                if (!empty($original_filename)) {
                    foreach ($treatment_keywords as $keyword => $variations) {
                        foreach ($variations as $variation) {
                            // Use word boundaries to prevent partial matches
                            if (preg_match('/\b' . preg_quote($variation, '/') . '\b/', $original_filename)) {
                                $primary_keyword = $keyword;
                                error_log("MSH Clinical Debug: Found '$variation' in filename -> '$keyword'");
                                break 2; // Exit both loops
                            }
                        }
                    }
                }

                // If no match in filename, try full search text (with word boundaries)
                if ($primary_keyword === 'rehabilitation') {
                    foreach ($treatment_keywords as $keyword => $variations) {
                        foreach ($variations as $variation) {
                            // Use word boundaries to prevent partial matches
                            if (preg_match('/\b' . preg_quote($variation, '/') . '\b/', $search_text)) {
                                $primary_keyword = $keyword;
                                error_log("MSH Clinical Debug: Found '$variation' in search text -> '$keyword'");
                                break 2; // Exit both loops
                            }
                        }
                    }
                }

                // Build SEO-friendly filename: treatment-hamilton-service
                $treatment_type = ($service === 'chiropractic') ? 'chiropractic' : 'physiotherapy';
                $parts = [$primary_keyword];

                // Add Hamilton for local SEO (but not for generic terms)
                if ($primary_keyword !== 'rehabilitation') {
                    $parts[] = $this->location_slug;
                }

                // Add treatment type
                $parts[] = $treatment_type;

                $base_slug = implode('-', array_filter($parts));
                return $this->slugify($base_slug);
        }
    }

    private function generate_clinical_meta(array $context) {
        $service = $context['service'] ?? 'rehabilitation';
        $service_label = $this->format_service_label($service);
        $service_lower = strtolower($service_label);
        $page_title_lower = strtolower((string) ($context['page_title'] ?? ''));

        $variant = 'default';
        if (strpos($page_title_lower, 'assessment') !== false) {
            $variant = 'assessment';
        } elseif (strpos($page_title_lower, 'acute') !== false) {
            $variant = 'acute';
        }

        $keyword_line = $this->get_service_keyword_line($service, $variant);

        $action_word = [
            'default' => 'Treatment',
            'assessment' => 'Assessment',
            'acute' => 'Care'
        ][$variant] ?? 'Treatment';

        $title_focus = trim("{$service_label} {$action_word}");
        $title = $this->ensure_unique_title(
            "{$title_focus} - {$this->business_name} {$this->location}",
            $context['attachment_id'] ?? 0
        );

        $caption_map = [
            'default' => "Professional {$service_label} treatment session",
            'assessment' => "Clinical {$service_label} assessment in progress",
            'acute' => "Immediate {$service_label} care for acute injuries"
        ];

        $description_map = [
            'default' => "Comprehensive {$service_lower} care tailored to patient recovery. {$keyword_line}",
            'assessment' => "Detailed {$service_lower} assessment with measurable progress tracking. {$keyword_line}",
            'acute' => "Rapid-response {$service_lower} care supporting immediate relief. {$keyword_line}"
        ];

        $alt_map = [
            'default' => "{$service_label} treatment at {$this->business_name} {$this->location} rehabilitation clinic",
            'assessment' => "{$service_label} assessment at {$this->business_name} {$this->location} clinic",
            'acute' => "{$service_label} care team providing acute support at {$this->business_name} {$this->location}"
        ];

        return [
            'title' => $this->clean_text($title),
            'alt_text' => $this->clean_text($alt_map[$variant] ?? $alt_map['default']),
            'caption' => $this->clean_text($caption_map[$variant] ?? $caption_map['default']),
            'description' => $this->clean_text($description_map[$variant] ?? $description_map['default'])
        ];
    }

    private function get_service_keyword_line($service, $variant) {
        $service = strtolower($service);
        $variants = $this->service_keyword_map[$service] ?? $this->service_keyword_map['rehabilitation'];
        return $variants[$variant] ?? $variants['default'];
    }

    private function generate_team_meta(array $context) {
        $is_healthcare = $this->is_healthcare_industry($this->industry);
        $default_name = $is_healthcare ? __('Healthcare Professional', 'msh-image-optimizer') : __('Team Member', 'msh-image-optimizer');
        $name = !empty($context['staff_name']) ? $context['staff_name'] : $default_name;

        if ($is_healthcare) {
            return [
                'title' => $this->clean_text("{$name} - {$this->business_name} {$this->location}"),
                'alt_text' => $this->clean_text("{$name}, healthcare professional at {$this->business_name} {$this->location}"),
                'caption' => $this->clean_text("{$name} - Registered rehabilitation provider"),
                'description' => $this->clean_text("{$name} provides expert rehabilitation services at {$this->business_name} in {$this->location}. Specialized in WSIB and MVA recovery programs.")
            ];
        }

        $industry_label = $this->get_industry_label_or_default();
        $title_components = [$name];
        $company_segment = $this->business_name !== '' ? $this->business_name : $industry_label;
        if ($company_segment !== '') {
            $title_components[] = $company_segment;
        }
        if ($this->location !== '') {
            $title_components[] = $this->location;
        }

        $title = $this->clean_text($this->ensure_unique_title(
            implode(' – ', array_filter($title_components)),
            $context['attachment_id'] ?? 0
        ));

        $alt_text = $this->clean_text(sprintf(
            __('%1$s from %2$s%3$s.', 'msh-image-optimizer'),
            $name,
            $this->business_name !== '' ? $this->business_name : $industry_label,
            $this->get_location_phrase(' in ')
        ));

        $caption = $this->clean_text(sprintf(
            __('%1$s – part of the %2$s team', 'msh-image-optimizer'),
            $name,
            strtolower($industry_label)
        ));

        $description_parts = array_filter([
            sprintf(
                __('Meet %1$s from %2$s%3$s.', 'msh-image-optimizer'),
                $name,
                $this->business_name,
                $this->get_location_phrase(' in ')
            ),
            $this->normalize_sentence($this->uvp),
            $this->get_target_audience_sentence(),
            $this->get_cta_sentence(),
        ]);

        return [
            'title' => $title,
            'alt_text' => $alt_text,
            'caption' => $caption,
            'description' => $this->clean_text(implode(' ', $description_parts))
        ];
    }

    private function generate_testimonial_meta(array $context) {
        $is_healthcare = $this->is_healthcare_industry($this->industry);
        $subject = !empty($context['subject_name'])
            ? $context['subject_name']
            : ($is_healthcare ? __('Patient', 'msh-image-optimizer') : __('Client', 'msh-image-optimizer'));

        if ($is_healthcare) {
            $service = $context['service'] ?? 'rehabilitation';
            $service_label = $this->format_service_label($service);
            $service_lower = strtolower($service_label);
            $keywords_line = $this->get_service_keyword_line($service, 'default');

            $caption = sprintf(
                __('%1$s shares %2$s recovery experience at %3$s', 'msh-image-optimizer'),
                $subject,
                $service_lower,
                $this->business_name
            );

            $description = sprintf(
                __('Patient testimonial from %1$s highlighting %2$s recovery at %3$s %4$s. %5$s', 'msh-image-optimizer'),
                $subject,
                $service_lower,
                $this->business_name,
                $this->location,
                $keywords_line
            );

            $title_base = "{$subject} Patient Success Story - {$this->business_name} {$this->location}";
            $final_title = $this->ensure_unique_title($title_base, $context['attachment_id'] ?? 0);

            return [
                'title' => $this->clean_text($final_title),
                'alt_text' => $this->clean_text(sprintf(__('Patient %1$s shares %2$s recovery story at %3$s %4$s', 'msh-image-optimizer'), $subject, $service_lower, $this->business_name, $this->location)),
                'caption' => $this->clean_text($caption),
                'description' => $this->clean_text($description)
            ];
        }

        $industry_label = $this->get_industry_label_or_default();
        $location_phrase = $this->get_location_phrase(' | ');
        $title_base = sprintf(
            __('%1$s Success Story – %2$s%3$s', 'msh-image-optimizer'),
            $subject,
            $this->business_name,
            $location_phrase
        );

        $description_parts = array_filter([
            sprintf(
                __('Testimonial from %1$s showcasing %2$s outcomes with %3$s%4$s.', 'msh-image-optimizer'),
                $subject,
                strtolower($industry_label),
                $this->business_name,
                $this->get_location_phrase(' in ')
            ),
            $this->normalize_sentence($this->uvp),
            $this->normalize_sentence($this->pain_points),
            $this->get_cta_sentence(),
        ]);

        $caption = sprintf(
            __('%1$s shares their experience with %2$s.', 'msh-image-optimizer'),
            $subject,
            $this->business_name
        );

        return [
            'title' => $this->clean_text($this->ensure_unique_title($title_base, $context['attachment_id'] ?? 0)),
            'alt_text' => $this->clean_text(sprintf(__('Client %1$s testimonial for %2$s%3$s.', 'msh-image-optimizer'), $subject, $this->business_name, $this->get_location_phrase(' in '))),
            'caption' => $this->clean_text($caption),
            'description' => $this->clean_text(implode(' ', $description_parts))
        ];
    }

    private function generate_service_icon_meta(array $context) {
        // Try to get specific concept from the icon
        $concept_label = '';

        // First try to extract from original filename (Noun Project files)
        if (!empty($context['original_filename'])) {
            if (preg_match('/^noun-(.+)-\d{4,7}-[A-F0-9]{6}/i', $context['original_filename'], $matches)) {
                $concept_label = $this->format_service_label($matches[1]);
                error_log("MSH Service Icon Meta: Extracted concept '$concept_label' from filename");
            }
        }

        // If no concept found, try icon_concept field
        if (empty($concept_label) && !empty($context['icon_concept'])) {
            $concept = $context['icon_concept'];
            // Clean any Noun Project patterns
            if (preg_match('/^noun-(.+)-\d{4,}/i', $concept, $matches)) {
                $concept = $matches[1];
            }
            $concept_label = $this->format_service_label($concept);
        }

        // Fallback to service if no specific concept
        if (empty($concept_label)) {
            $service = $context['service'] ?? 'rehabilitation';
            $concept_label = $this->format_service_label($service);
        }

        $industry_label = $this->get_industry_label_or_default();
        $title_base = $this->business_name !== ''
            ? sprintf(__('%1$s Icon – %2$s', 'msh-image-optimizer'), $concept_label, $this->business_name)
            : sprintf(__('%s Icon', 'msh-image-optimizer'), $concept_label);

        if ($this->location !== '') {
            $title_base .= ' | ' . $this->location;
        }

        $alt_text = $this->business_name !== ''
            ? sprintf(__('Illustrated %1$s icon for %2$s%3$s.', 'msh-image-optimizer'), $concept_label, $this->business_name, $this->get_location_phrase(' in '))
            : sprintf(__('Illustrated %s icon.', 'msh-image-optimizer'), $concept_label);

        $description_parts = array_filter([
            sprintf(
                __('Custom %1$s icon supporting %2$s digital experience%3$s.', 'msh-image-optimizer'),
                strtolower($concept_label),
                $this->business_name !== '' ? $this->business_name : __('the brand', 'msh-image-optimizer'),
                $this->get_location_phrase(' in ')
            ),
            sprintf(__('Designed for %s navigation.', 'msh-image-optimizer'), strtolower($industry_label)),
            $this->get_cta_sentence(),
        ]);

        return [
            'title' => $this->clean_text($this->ensure_unique_title($title_base, $context['attachment_id'] ?? 0)),
            'alt_text' => $this->clean_text($alt_text),
            'caption' => $this->clean_text(sprintf(__('%1$s icon for %2$s', 'msh-image-optimizer'), $concept_label, strtolower($industry_label))),
            'description' => $this->clean_text(implode(' ', $description_parts))
        ];
    }


    private function generate_logo_meta(array $context) {
        $location = $this->location;
        $industry_label = $this->get_industry_label_or_default();
        $title = $this->business_name !== ''
            ? sprintf(__('%s Logo', 'msh-image-optimizer'), $this->business_name)
            : __('Brand Logo', 'msh-image-optimizer');

        if ($location !== '') {
            $title .= ' | ' . $location;
        }

        $alt_text = $this->business_name !== ''
            ? sprintf(__('Official logo for %s', 'msh-image-optimizer'), $this->business_name)
            : __('Official brand logo', 'msh-image-optimizer');

        $caption = $this->business_name !== ''
            ? sprintf(__('%1$s %2$s branding', 'msh-image-optimizer'), $this->business_name, strtolower($industry_label))
            : sprintf(__('Branding for %s', 'msh-image-optimizer'), strtolower($industry_label));

        $description_parts = array_filter([
            sprintf(
                __('Official %1$s logo showcasing %2$s%3$s.', 'msh-image-optimizer'),
                $this->business_name !== '' ? $this->business_name : __('the business', 'msh-image-optimizer'),
                $this->get_industry_descriptor(),
                $this->get_location_phrase(' in ')
            ),
            $this->normalize_sentence($this->uvp),
            $this->get_cta_sentence(),
        ]);

        return [
            'title' => $this->clean_text($this->ensure_unique_title($title, $context['attachment_id'] ?? 0)),
            'alt_text' => $this->clean_text($alt_text),
            'caption' => $this->clean_text($caption),
            'description' => $this->clean_text(implode(' ', $description_parts))
        ];
    }

    private function generate_product_meta(array $context) {
        if (!$this->is_healthcare_industry($this->industry)) {
            $product_name = !empty($context['attachment_title'])
                ? $context['attachment_title']
                : __('Featured Product', 'msh-image-optimizer');

            $title_components = [$product_name];
            if ($this->business_name !== '') {
                $title_components[] = $this->business_name;
            }

            $title = implode(' – ', array_filter($title_components));
            if ($this->location !== '') {
                $title .= ' | ' . $this->location;
            }

            $alt_text = sprintf(
                __('Product spotlight: %1$s%2$s.', 'msh-image-optimizer'),
                $product_name,
                $this->business_name !== '' ? sprintf(__(' from %s', 'msh-image-optimizer'), $this->business_name) : ''
            );
            if ($this->location !== '') {
                $alt_text = rtrim($alt_text, '.') . sprintf(__(' in %s.', 'msh-image-optimizer'), $this->location);
            }

            $caption_candidates = [
                $this->normalize_sentence($this->uvp),
                $this->business_name !== '' ? sprintf(__('Signature offering from %s.', 'msh-image-optimizer'), $this->business_name) : __('Signature offering.', 'msh-image-optimizer'),
            ];

            $caption = '';
            foreach ($caption_candidates as $candidate) {
                if ($candidate !== '') {
                    $caption = $candidate;
                    break;
                }
            }

            $description_parts = array_filter([
                sprintf(
                __('%1$s provides %2$s solutions like %3$s%4$s.', 'msh-image-optimizer'),
                $this->business_name !== '' ? $this->business_name : __('This business', 'msh-image-optimizer'),
                $this->get_industry_descriptor(),
                $product_name,
                $this->get_location_phrase(' in ')
            ),
                $this->normalize_sentence($this->pain_points),
                $this->get_cta_sentence(),
            ]);

            return [
                'title' => $this->clean_text($this->ensure_unique_title($title, $context['attachment_id'] ?? 0)),
                'alt_text' => $this->clean_text($alt_text),
                'caption' => $this->clean_text($caption),
                'description' => $this->clean_text(implode(' ', $description_parts))
            ];
        }

        $map = [
            'therapeutic-pillow' => [
                'name' => 'Therapeutic Support Pillow',
                'caption' => 'Mediflow water-based pillow for neck support',
                'description' => 'Therapeutic pillow recommended for neck pain and sleep positioning. Available for purchase at our clinic.'
            ],
            'custom-orthotics' => [
                'name' => 'Custom Orthotics',
                'caption' => 'Custom-fitted orthotic insoles',
                'description' => 'Custom orthotics designed for optimal foot support and biomechanical correction. Professional fitting available.'
            ],
            'support-brace' => [
                'name' => 'Support Brace',
                'caption' => 'Medical-grade support brace for injury recovery',
                'description' => 'Support brace for joint stabilization and injury recovery. Multiple sizes available with professional fitting.'
            ],
            'support-product' => [
                'name' => 'Rehabilitation Support Product',
                'caption' => 'Clinical support product for rehabilitation',
                'description' => 'Therapeutic support product recommended by our rehabilitation team. Available for purchase with insurance receipts.'
            ],
            'tens-unit' => [
                'name' => 'TENS Unit',
                'caption' => 'TENS electrotherapy device',
                'description' => 'Transcutaneous electrical nerve stimulation unit for pain relief and muscle activation. Professional guidance provided.'
            ],
            'pain-relief' => [
                'name' => 'Pain Relief Product',
                'caption' => 'Topical pain relief solution',
                'description' => 'Professional pain relief products recommended by our therapists. Available for purchase with usage instructions.'
            ],
            'compression-therapy' => [
                'name' => 'Compression Therapy Garment',
                'caption' => 'Compression stocking for circulation support',
                'description' => 'Compression therapy garment supporting circulation and recovery. Measurements and fittings performed in clinic.'
            ]
        ];

        $product_type = $context['product_type'] ?? 'support-product';
        $product = $map[$product_type] ?? $map['support-product'];

        $title = $product['name'] . " - {$this->business_name} {$this->location}";
        $alt_text = $product['name'] . ' available at ' . $this->business_name;
        $caption = $product['caption'];
        $description = $product['description'];

        return [
            'title' => $this->clean_text($this->ensure_unique_title($title, $context['attachment_id'] ?? 0)),
            'alt_text' => $this->clean_text($alt_text),
            'caption' => $this->clean_text($caption),
            'description' => $this->clean_text($description)
        ];
    }

    private function generate_facility_meta(array $context) {
        if ($this->is_healthcare_industry($this->industry)) {
            return [
                'title' => $this->clean_text("{$this->business_name} Clinic - {$this->location} Rehabilitation Facility"),
                'alt_text' => $this->clean_text("Interior view of {$this->business_name} rehabilitation clinic in {$this->location}"),
                'caption' => $this->clean_text("Modern rehabilitation facility at {$this->business_name} {$this->location}"),
                'description' => $this->clean_text("Modern rehabilitation facility at {$this->business_name} {$this->location}. Professional physiotherapy and chiropractic clinic with specialized treatment rooms and WSIB approved programs.")
            ];
        }

        $industry_label = $this->get_industry_label_or_default();
        $location = $this->location;

        $title = $this->business_name !== ''
            ? sprintf(__('%1$s Workspace – %2$s', 'msh-image-optimizer'), $this->business_name, $location)
            : sprintf(__('Workspace – %s', 'msh-image-optimizer'), $location);

        $alt_text = $this->business_name !== ''
            ? sprintf(__('Interior view of %1$s%2$s.', 'msh-image-optimizer'), $this->business_name, $this->get_location_phrase(' in '))
            : sprintf(__('Interior workspace%1$s.', 'msh-image-optimizer'), $this->get_location_phrase(' in '));

        $caption = sprintf(
            __('Collaborative space for %1$s team members.', 'msh-image-optimizer'),
            strtolower($industry_label)
        );

        $description_parts = array_filter([
            sprintf(
                __('The %1$s workspace%2$s designed for %3$s collaboration.', 'msh-image-optimizer'),
                $this->business_name !== '' ? $this->business_name : __('business', 'msh-image-optimizer'),
                $this->get_location_phrase(' in '),
                strtolower($industry_label)
            ),
            $this->normalize_sentence($this->uvp),
            $this->get_target_audience_sentence(),
            $this->get_cta_sentence(),
        ]);

        return [
            'title' => $this->clean_text($this->ensure_unique_title($title, $context['attachment_id'] ?? 0)),
            'alt_text' => $this->clean_text($alt_text),
            'caption' => $this->clean_text($caption),
            'description' => $this->clean_text(implode(' ', $description_parts))
        ];
    }

    private function generate_equipment_meta(array $context) {
        if ($this->is_healthcare_industry($this->industry)) {
            return [
                'title' => $this->clean_text("Therapeutic Equipment - {$this->business_name} {$this->location}"),
                'alt_text' => $this->clean_text("Therapeutic rehabilitation equipment at {$this->business_name} clinic in {$this->location}"),
                'caption' => $this->clean_text("Advanced therapeutic equipment for rehabilitation at {$this->business_name}"),
                'description' => $this->clean_text("Professional rehabilitation equipment at {$this->business_name} {$this->location}. Advanced therapeutic technology supporting physiotherapy, chiropractic care, and patient recovery.")
            ];
        }

        $industry_label = $this->get_industry_label_or_default();
        $title = __('Operational Equipment', 'msh-image-optimizer');
        $company_segment = $this->business_name !== '' ? $this->business_name : $industry_label;
        if ($company_segment !== '') {
            $title .= ' – ' . $company_segment;
        }
        if ($this->location !== '') {
            $title .= ' | ' . $this->location;
        }

        $alt_text = sprintf(
            __('Operational equipment at %1$s%2$s.', 'msh-image-optimizer'),
            $this->business_name !== '' ? $this->business_name : __('the business', 'msh-image-optimizer'),
            $this->get_location_phrase(' in ')
        );

        $caption = sprintf(
            __('Equipment supporting %s delivery.', 'msh-image-optimizer'),
            strtolower($industry_label)
        );

        $description_parts = array_filter([
            sprintf(
                __('Professional equipment used by %1$s for %2$s.', 'msh-image-optimizer'),
                $this->business_name !== '' ? $this->business_name : __('the business', 'msh-image-optimizer'),
                strtolower($industry_label)
            ),
            $this->normalize_sentence($this->uvp),
            $this->get_cta_sentence(),
        ]);

        return [
            'title' => $this->clean_text($this->ensure_unique_title($title, $context['attachment_id'] ?? 0)),
            'alt_text' => $this->clean_text($alt_text),
            'caption' => $this->clean_text($caption),
            'description' => $this->clean_text(implode(' ', $description_parts))
        ];
    }

    private function generate_business_meta(array $context) {
        $industry_label = $this->get_industry_label_or_default();
        $location = $this->location;
        $descriptor_phrase = $this->derive_visual_descriptor($context);

        $title_components = array();
        if ($descriptor_phrase !== '') {
            $title_components[] = $descriptor_phrase;
        }
        if ($this->business_name !== '') {
            $title_components[] = $this->business_name;
        }
        if ($industry_label !== '') {
            $title_components[] = $industry_label;
        }

        $title = implode(' – ', array_filter($title_components));
        if ($title === '') {
            $title = $this->business_name !== '' ? $this->business_name : __('Business Overview', 'msh-image-optimizer');
        }

        if ($location !== '') {
            $title .= ' | ' . $location;
        }

        if ($this->business_name !== '') {
            $alt_text = $descriptor_phrase !== ''
                ? sprintf(__('Photo of %1$s for %2$s%3$s.', 'msh-image-optimizer'), $descriptor_phrase, $this->business_name, $this->get_location_phrase(' in '))
                : ($location !== ''
                    ? sprintf(__('Brand imagery for %1$s in %2$s.', 'msh-image-optimizer'), $this->business_name, $location)
                    : sprintf(__('Brand imagery for %s.', 'msh-image-optimizer'), $this->business_name));
        } else {
            $alt_text = $descriptor_phrase !== ''
                ? sprintf(__('Photo of %s.', 'msh-image-optimizer'), $descriptor_phrase)
                : ($location !== ''
                    ? sprintf(__('Brand imagery in %s.', 'msh-image-optimizer'), $location)
                    : __('Brand imagery preview.', 'msh-image-optimizer'));
        }

        if ($descriptor_phrase !== '') {
            $caption = sprintf(
                __('%1$s — a highlight for %2$s.', 'msh-image-optimizer'),
                $descriptor_phrase,
                $this->business_name !== '' ? $this->business_name : __('the business', 'msh-image-optimizer')
            );
        } else {
            $caption = $this->business_name !== ''
                ? sprintf(__('Snapshot from %s.', 'msh-image-optimizer'), $this->business_name)
                : __('Snapshot from the business.', 'msh-image-optimizer');
        }

        $provider_name = $this->business_name !== '' ? $this->business_name : __('This business', 'msh-image-optimizer');

        $description_parts = array_filter([
            sprintf(
                __('%1$s%3$s provides %2$s.', 'msh-image-optimizer'),
                $provider_name,
                $this->get_industry_descriptor(),
                $this->get_location_phrase(' in ')
            ),
            $descriptor_phrase !== ''
                ? sprintf(__('Visual focus: %s.', 'msh-image-optimizer'), $descriptor_phrase)
                : '',
            $this->normalize_sentence($this->uvp),
            $this->normalize_sentence($this->pain_points),
            $this->get_target_audience_sentence(),
            $this->get_cta_sentence(),
        ]);

        $description = implode(' ', $description_parts);

        return [
            'title' => $this->clean_text($this->ensure_unique_title($title, $context['attachment_id'] ?? 0)),
            'alt_text' => $this->clean_text($alt_text),
            'caption' => $this->clean_text($caption),
            'description' => $this->clean_text($description)
        ];
    }

    /**
     * Extract meaningful keywords from original filename
     */
    private function extract_filename_keywords($filename) {
        if (empty($filename)) {
            return '';
        }

        // FIRST: Detect source patterns (Noun Project, etc.)
        $source_pattern = $this->detect_source_pattern($filename);
        if ($source_pattern) {
            error_log("MSH Source Pattern: File='$filename', Source='{$source_pattern['source']}', Term='{$source_pattern['extracted_term']}'");
            return $this->normalize_extracted_term($source_pattern['extracted_term']);
        }

        // SECOND: Handle Main Street Health branded files
        if (strpos($filename, 'main-street-health-healthcare-') === 0) {
            $service_part = str_replace('main-street-health-healthcare-', '', $filename);
            $service_part = preg_replace('/\.(jpg|jpeg|png|gif|svg|webp)$/i', '', $service_part);

            error_log("MSH Branded File: Original='$filename', ServicePart='$service_part'");

            // Extract the service keywords and normalize to key service terms
            if (strpos($service_part, 'cardiovascular') !== false) {
                return 'cardiovascular-health-testing';
            } elseif (strpos($service_part, 'massage-therapy') !== false || strpos($service_part, 'professional-massage') !== false) {
                return 'professional-massage-therapy';
            } elseif (strpos($service_part, 'chiropractic') !== false) {
                return 'chiropractic-adjustment-therapy';
            } elseif (strpos($service_part, 'concussion') !== false) {
                return 'concussion-assessment-testing';
            } elseif (strpos($service_part, 'acupuncture') !== false) {
                return 'acupuncture-pain-relief';
            } else {
                // Use first 2-3 meaningful words, excluding 'equipment', 'services', etc.
                $words = explode('-', $service_part);
                $meaningful_words = [];
                $skip_words = ['equipment', 'services', 'techniques', 'and', 'for', 'the', 'with'];

                foreach ($words as $word) {
                    if (strlen($word) > 2 && !in_array($word, $skip_words)) {
                        $meaningful_words[] = $word;
                        if (count($meaningful_words) >= 3) break;
                    }
                }
                return implode('-', $meaningful_words);
            }
        }

        // FALLBACK: Standard processing
        $cleaned = preg_replace('/\.(jpg|jpeg|png|gif|svg|webp)$/i', '', $filename);
        $cleaned = preg_replace('/^(noun-|icon-|img-|image-)/i', '', $cleaned);

        // Remove color codes and IDs
        $cleaned = preg_replace('/-[A-F0-9]{6,8}(-|$)/i', '-', $cleaned);
        $cleaned = preg_replace('/-\d{4,}(-|$)/', '-', $cleaned);

        // Split on separators and clean
        $parts = preg_split('/[-_\s]+/', $cleaned);
        $meaningful_parts = [];

        // Filter out noise words and keep meaningful terms (don't remove svg - it's meaningful)
        $noise_words = ['icon', 'image', 'img', 'pic', 'photo', 'vector', 'png', 'jpg'];

        foreach ($parts as $part) {
            $part = strtolower(trim($part));
            if (strlen($part) > 2 && !in_array($part, $noise_words) && !is_numeric($part)) {
                $meaningful_parts[] = $part;
            }
        }

        // Healthcare-specific keyword mapping (enhanced with variations)
        $healthcare_keywords = [
            'compression-stocking' => ['compression stocking', 'compression sock', 'support stocking'],
            'orthopedic-pillow' => ['orthopedic pillow', 'ortho pillow', 'cervical pillow'],
            'wrist-guards' => ['wristguards', 'wrist guards', 'wrist guard'],
            'knee-brace' => ['knee brace', 'knee support'],
            'support-brace' => ['support brace', 'support'],
            'ankle-brace' => ['ankle brace', 'ankle support'],
            'wrist-brace' => ['wrist brace', 'wrist support'],
            'back-brace' => ['back brace', 'back support'],
            'neck-brace' => ['neck brace', 'neck support'],
            'crutches' => ['crutches', 'crutch'],
            'walker' => ['walker', 'walking aid'],
            'wheelchair' => ['wheelchair', 'wheel chair'],
            'walking-cane' => ['cane', 'walking cane'],
            'exercise-band' => ['exercise band', 'resistance band'],
            'foam-roller' => ['foam roller'],
            'heat-pack' => ['heat pack', 'heating pad'],
            'ice-pack' => ['ice pack', 'cold pack'],
            'kinesiology-tape' => ['kinesiology tape', 'ktape', 'k tape'],
            'posture-corrector' => ['posture corrector'],
            'therapy-ball' => ['therapy ball', 'exercise ball'],
            'tens-unit' => ['tens unit', 'tens'],
            'ultrasound' => ['ultrasound'],
            'massage-table' => ['massage table'],
            'treatment-table' => ['treatment table'],
            'lumbar-support' => ['lumbar support'],
            'ergonomic-cushion' => ['ergonomic cushion'],
            'balance-pad' => ['balance pad'],
            'stability-ball' => ['stability ball'],
            'medicine-ball' => ['medicine ball'],
            'theraband' => ['theraband', 'thera band'],
            'gel-pack' => ['gel pack']
        ];

        // Check for healthcare keyword combinations
        $text = implode(' ', $meaningful_parts);

        // Debug logging
        error_log("MSH Filename Debug: Original='$filename', Cleaned='$cleaned', Parts=" . implode('|', $meaningful_parts) . ", Text='$text'");

        // FIRST: Try consecutive compound matching for multi-word terms
        $parts_string = implode(' ', $meaningful_parts);
        foreach ($healthcare_keywords as $keyword => $variations) {
            foreach ($variations as $variation) {
                // Check for consecutive sequence in the parts string
                if (strpos($parts_string, strtolower($variation)) !== false) {
                    error_log("MSH Filename Debug: Found consecutive compound match '$variation' -> '$keyword'");
                    return $keyword;
                }
            }
        }

        // SECOND: Try single word matching
        foreach ($healthcare_keywords as $keyword => $variations) {
            foreach ($variations as $variation) {
                if (strpos($text, $variation) !== false) {
                    error_log("MSH Filename Debug: Found text match '$variation' -> '$keyword'");
                    return $keyword;
                }
            }
        }

        // THIRD: Try direct part matching
        foreach ($healthcare_keywords as $keyword => $variations) {
            foreach ($variations as $variation) {
                foreach ($meaningful_parts as $part) {
                    if ($part === $variation) {
                        error_log("MSH Filename Debug: Direct part match '$part' -> '$keyword'");
                        return $keyword;
                    }
                }
            }
        }

        // Return best meaningful parts (max 3)
        return implode('-', array_slice($meaningful_parts, 0, 3));
    }

    private function extract_business_slug_keywords(array $context, $limit = 3) {
        $candidates = array();

        if (!empty($context['attachment_title'])) {
            $candidates[] = $context['attachment_title'];
        }

        if (!empty($context['attachment_slug'])) {
            $candidates[] = $context['attachment_slug'];
        }

        if (!empty($context['file_basename'])) {
            $candidates[] = $context['file_basename'];
        }

        if (!empty($context['original_filename'])) {
            $candidates[] = $context['original_filename'];
        }

        if (!empty($context['page_title'])) {
            $candidates[] = $context['page_title'];
        }

        if (!empty($context['tags']) && is_array($context['tags'])) {
            $candidates = array_merge($candidates, $context['tags']);
        }

        $stopwords = array(
            'rehabilitation',
            'physiotherapy',
            'rehabilitation-physiotherapy',
            'physiotherapy-rehabilitation',
            'therapy',
            'clinic',
            'medical',
            'health',
            'wellness',
            'service',
            'services',
            'team',
            'member',
            'testimonial',
            'patient',
            'customer',
            'client',
            'business',
            'branding',
            'brand',
            'graphic',
            'icon',
            'image',
            'photo',
            'picture',
            'stock',
            'default',
            'placeholder',
            'jpeg',
            'jpg',
            'png',
            'svg',
            'gif',
            'rehab',
            'portfolio',
            'marketing',
            'agency',
            'creative'
        );

        if (!empty($this->business_name)) {
            $stopwords = array_merge($stopwords, $this->tokenize_stopwords($this->business_name));
        }

        if (!empty($this->location)) {
            $stopwords = array_merge($stopwords, $this->tokenize_stopwords($this->location));
        }

        if (!empty($this->industry_label)) {
            $stopwords = array_merge($stopwords, $this->tokenize_stopwords($this->industry_label));
        }

        $stopwords = array_unique(array_filter($stopwords));

        $keywords = array();
        foreach ($candidates as $candidate) {
            if (!is_scalar($candidate) || empty($candidate)) {
                continue;
            }

            $slug = strtolower((string) $candidate);
            $slug = str_replace(['_', '/'], '-', $slug);
            $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug);
            $parts = array_filter(explode('-', $slug));

            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '' || is_numeric($part) || strlen($part) < 3) {
                    continue;
                }

                if (in_array($part, $stopwords, true)) {
                    continue;
                }

                if (!in_array($part, $keywords, true)) {
                    $keywords[] = $part;
                }

                if (count($keywords) >= $limit) {
                    break 2;
                }
            }
        }

        if (empty($keywords)) {
            $fallback = array('asset');
            if (!empty($context['attachment_id'])) {
                $fallback[] = (string) $context['attachment_id'];
            }
            if (!empty($this->location_slug)) {
                $fallback[] = $this->location_slug;
            }

            return implode('-', array_filter($fallback));
        }

        return implode('-', array_slice($keywords, 0, $limit));
    }

    private function tokenize_stopwords($source) {
        $tokens = array();
        $source = strtolower((string) $source);
        $source = str_replace(['_', '/'], ' ', $source);
        $source = preg_replace('/[^a-z0-9\s]/', ' ', $source);
        foreach (preg_split('/\s+/', $source) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $tokens[] = $part;
            }
        }

        return $tokens;
    }

    private function collect_visual_keywords(array $context, $limit = 5) {
        $sources = array();

        if (!empty($context['attachment_title'])) {
            $sources[] = $context['attachment_title'];
        }

        if (!empty($context['page_title'])) {
            $sources[] = $context['page_title'];
        }

        if (!empty($context['file_basename'])) {
            $sources[] = str_replace(['-', '_'], ' ', $context['file_basename']);
        }

        if (!empty($context['tags']) && is_array($context['tags'])) {
            foreach ($context['tags'] as $tag) {
                $sources[] = str_replace(['-', '_'], ' ', $tag);
            }
        }

        $stopwords = array(
            'rehabilitation',
            'physiotherapy',
            'treatment',
            'clinic',
            'medical',
            'health',
            'wellness',
            'service',
            'services',
            'agency',
            'marketing',
            'creative',
            'brand',
            'branding',
            'business',
            'default',
            'placeholder',
            'image',
            'photo',
            'picture',
            'stock',
            'jpg',
            'jpeg',
            'png',
            'svg',
            'gif',
            'file',
            'asset',
            'main',
            'street',
            'health'
        );

        if (!empty($this->business_name)) {
            $stopwords = array_merge($stopwords, $this->tokenize_stopwords($this->business_name));
        }

        if (!empty($this->location)) {
            $stopwords = array_merge($stopwords, $this->tokenize_stopwords($this->location));
        }

        $stopwords = array_unique(array_filter($stopwords));

        $keywords = array();

        foreach ($sources as $source) {
            $clean = strtolower((string) $source);
            $clean = preg_replace('/[^a-z0-9\s]/', ' ', $clean);
            $parts = preg_split('/\s+/', $clean);

            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '' || strlen($part) < 3 || is_numeric($part)) {
                    continue;
                }

                if (in_array($part, $stopwords, true)) {
                    continue;
                }

                if (!in_array($part, $keywords, true)) {
                    $keywords[] = $part;
                }

                if (count($keywords) >= $limit) {
                    break 2;
                }
            }
        }

        return $keywords;
    }

    private function derive_visual_descriptor(array $context) {
        $keywords = $this->collect_visual_keywords($context, 4);
        $descriptor = '';

        if (!empty($keywords)) {
            $descriptor = implode(' ', array_slice($keywords, 0, 3));
            $descriptor = ucwords($descriptor);
        }

        $asset_map = array(
            'graphic' => __('Brand Graphic', 'msh-image-optimizer'),
            'product' => __('Product Photo', 'msh-image-optimizer'),
            'logo' => __('Logo Mark', 'msh-image-optimizer'),
            'team' => __('Team Portrait', 'msh-image-optimizer'),
            'facility' => __('Workspace Interior', 'msh-image-optimizer'),
            'service-icon' => __('Service Icon', 'msh-image-optimizer')
        );

        if ($descriptor === '' && !empty($context['asset']) && isset($asset_map[$context['asset']])) {
            $descriptor = $asset_map[$context['asset']];
        } elseif ($descriptor !== '' && !empty($context['asset']) && isset($asset_map[$context['asset']])) {
            $suffix = $asset_map[$context['asset']];
            if (stripos($descriptor, $suffix) === false) {
                $descriptor = $descriptor . ' ' . $suffix;
            }
        }

        if ($descriptor === '' && !empty($context['service'])) {
            $descriptor = $this->humanize_label($context['service']);
        }

        if ($descriptor === '' && !empty($context['attachment_id'])) {
            $descriptor = sprintf(__('Asset %d', 'msh-image-optimizer'), $context['attachment_id']);
        }

        return $descriptor;
    }

    /**
     * Extract brand/insurance company names from filename
     */
    private function extract_brand_keywords($filename) {
        if (empty($filename)) {
            return '';
        }

        // Remove file extension and clean
        $cleaned = preg_replace('/\.(jpg|jpeg|png|gif|svg|webp)$/i', '', $filename);
        $cleaned = preg_replace('/[-_](logo|nobars?)$/i', '', $cleaned);

        // Common insurance/healthcare brands
        $brand_mapping = [
            'bluecross' => ['bluecross', 'blue-cross'],
            'manulife' => ['manulife'],
            'sunlife' => ['sunlife', 'sun-life'],
            'greenshield' => ['greenshield', 'green-shield'],
            'desjardins' => ['desjardins'],
            'chambers' => ['chambers'],
            'benefits' => ['benefits'],
            'wsib' => ['wsib'],
            'mvp' => ['mvp'],
            'rbc' => ['rbc'],
            'td' => ['td'],
            'bmo' => ['bmo'],
            'scotia' => ['scotia'],
            'cigna' => ['cigna'],
            'johnson' => ['johnson'],
            'benefits-plan' => ['benefits', 'plan']
        ];

        $text = strtolower($cleaned);
        foreach ($brand_mapping as $brand => $variations) {
            foreach ($variations as $variation) {
                if (strpos($text, $variation) !== false) {
                    return $brand;
                }
            }
        }

        // If no specific brand, return cleaned filename (max 2 words)
        $parts = preg_split('/[-_\s]+/', $cleaned);
        $clean_parts = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (strlen($part) > 1 && !is_numeric($part)) {
                $clean_parts[] = strtolower($part);
            }
        }

        if (empty($clean_parts)) {
            return '';
        }

        $generic_terms = [
            'rehabilitation',
            'physiotherapy',
            'therapy',
            'treatment',
            'clinical',
            'branding',
            'brand',
            'business',
            'default',
            'placeholder',
            'image',
            'photo',
            'graphic',
            'asset',
            'main',
            'street',
            'health',
            'sample',
            'demo'
        ];

        $filtered = array_values(array_filter($clean_parts, function ($part) use ($generic_terms) {
            return !in_array($part, $generic_terms, true);
        }));

        if (empty($filtered)) {
            return '';
        }

        return implode('-', array_slice($filtered, 0, 2));
    }

    /**
     * Check if extracted keywords represent a high-quality name worth preserving
     */
    private function is_high_quality_extracted_name($extracted_keywords, $original_filename) {
        // High-quality indicators
        $quality_indicators = [
            // Brand names
            'djp', 'bionic', 'footmaxx', 'main-street-health',
            // Specific medical terms
            'gait-scan', 'fullstop', 'cardiovascular', 'professional', 'massage-therapy',
            // Equipment specifics
            'orthopedic-pillow', 'compression-stocking', 'wristguards',
            // MSH service-specific terms
            'cardiovascular-health-testing', 'professional-massage-therapy', 'chiropractic-adjustment-therapy',
            'concussion-assessment-testing', 'acupuncture-pain-relief',
            // Equipment terms
            'bionic-therapy-device', 'support-brace', 'compression-stocking',
            // Descriptive terms
            'injury-care', 'framed', 'services'
        ];

        foreach ($quality_indicators as $indicator) {
            if (strpos($extracted_keywords, $indicator) !== false || strpos($original_filename, $indicator) !== false) {
                return true;
            }
        }

        // If extracted keywords are longer than 2 words and contain specific terms
        $word_count = count(explode('-', $extracted_keywords));
        if ($word_count >= 3) {
            return true;
        }

        return false;
    }

    /**
     * Detect common icon library patterns (Noun Project, etc.)
     */
    private function detect_source_pattern($filename) {
        // Noun Project pattern: noun-compression-stocking-7981375-FFFFFF.svg (with optional suffixes)
        if (preg_match('/^noun-(.+)-\d{4,7}-[A-F0-9]{6}(?:-\d+)*/', $filename, $matches)) {
            return [
                'source' => 'noun_project',
                'extracted_term' => str_replace('-', ' ', $matches[1])
            ];
        }

        // Getty Images pattern: GettyImages-1343539369.png
        if (preg_match('/^gettyimages-(\d+)/i', $filename, $matches)) {
            return [
                'source' => 'getty_images',
                'extracted_term' => 'professional-stock-photo'
            ];
        }

        // Professional equipment patterns: djp-bionic-fullstop-on-skin-1400x1400-1.jpg
        if (preg_match('/^([a-z]+)-([a-z]+)-([a-z-]+)-(\d{3,}x\d{3,}|on-\w+)/', $filename, $matches)) {
            return [
                'source' => 'professional_equipment',
                'extracted_term' => $matches[1] . ' ' . $matches[2] . ' ' . str_replace('-', ' ', $matches[3])
            ];
        }

        // Frame pattern: slide-footmaxx-gait-scan-framed.jpg (but skip generic Frame-123.png)
        if (preg_match('/^(frame|slide)-(.+)/i', $filename, $matches)) {
            $extracted_part = $matches[2];
            // Skip if it's just numbers and extension (like Frame-330.png -> 330.png)
            if (!preg_match('/^\d+\.(jpg|jpeg|png|gif|svg|webp)$/i', $extracted_part)) {
                return [
                    'source' => 'presentation_asset',
                    'extracted_term' => str_replace('-', ' ', $extracted_part)
                ];
            }
        }

        return null;
    }

    /**
     * Normalize extracted terms to healthcare keywords
     */
    private function normalize_extracted_term($term) {
        $term_lower = strtolower($term);

        // FIRST: Remove file extensions if present
        $term_lower = preg_replace('/\.(jpg|jpeg|png|gif|svg|webp)$/i', '', $term_lower);

        // Direct healthcare equipment mapping
        $equipment_mapping = [
            'compression stocking' => 'compression-stocking',
            'compression sock' => 'compression-stocking',
            'support stocking' => 'support-stocking',
            'bionic fullstop' => 'bionic-therapy-device',
            'bionic fullstop on skin' => 'bionic-therapy-device',
            'fullstop' => 'therapy-device',
            'orthopedic pillow' => 'orthopedic-pillow',
            'brain' => 'brain-assessment',
            'central nervous system' => 'nervous-system-assessment',
            'nervous system' => 'nervous-system-assessment',
            'scoliosis' => 'scoliosis-assessment',
            'injury' => 'injury-assessment',
            'portable nerve stimulator' => 'nerve-stimulator-therapy',
            'nerve stimulator' => 'nerve-stimulator-therapy',
            'wristguards' => 'wrist-guards',
            'crutches' => 'crutches',
            'knee brace' => 'knee-brace',
            'ankle brace' => 'ankle-brace',
            'back brace' => 'back-brace',
            'neck brace' => 'neck-brace',
            'brace' => 'support-brace',
            'walker' => 'walker',
            'wheelchair' => 'wheelchair',
            'cane' => 'walking-cane',
            'foam roller' => 'foam-roller',
            'therapy ball' => 'therapy-ball',
            'balance pad' => 'balance-pad',
            'resistance band' => 'resistance-band',
            'heating pad' => 'heating-pad',
            'cold pack' => 'cold-pack',
            'gel pack' => 'gel-pack'
        ];

        // Check for exact matches first
        if (isset($equipment_mapping[$term_lower])) {
            return $equipment_mapping[$term_lower];
        }

        // Check for partial matches
        foreach ($equipment_mapping as $source_term => $normalized) {
            if (strpos($term_lower, $source_term) !== false) {
                return $normalized;
            }
        }

        // Fallback: clean up the term
        return str_replace(' ', '-', $term_lower);
    }

    private function slugify($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }

    private function clean_text($text) {
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}

class MSH_Image_Optimizer {
    private static $instance = null;

    const ANALYSIS_CACHE_VERSION = '2';

    private $batch_size = 10;
    private $processed_count = 0;
    private $current_attachment_id = null;
    private $contextual_meta_generator;
    private $healthcare_contexts = [
        'homepage_hero' => ['max_width' => 1200, 'max_height' => 600, 'quality' => 85],
        'service_page' => ['max_width' => 800, 'max_height' => 600, 'quality' => 80],
        'team_photo' => ['max_width' => 400, 'max_height' => 600, 'quality' => 85],
        'blog_featured' => ['max_width' => 800, 'max_height' => 450, 'quality' => 80],
        'testimonial' => ['max_width' => 200, 'max_height' => 200, 'quality' => 75],
        'facility' => ['max_width' => 800, 'max_height' => 600, 'quality' => 80]
    ];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct() {
        if (null !== self::$instance) {
            return;
        }

        self::$instance = $this;

        add_action('wp_ajax_msh_analyze_images', array($this, 'ajax_analyze_images'));
        add_action('wp_ajax_msh_optimize_batch', array($this, 'ajax_optimize_batch'));
        add_action('wp_ajax_msh_get_progress', array($this, 'ajax_get_progress'));
        add_action('wp_ajax_msh_reset_optimization', array($this, 'ajax_reset_optimization'));
        add_action('wp_ajax_msh_apply_filename_suggestions', array($this, 'ajax_apply_filename_suggestions'));
        add_action('wp_ajax_msh_save_filename_suggestion', array($this, 'ajax_save_filename_suggestion'));
        add_action('wp_ajax_msh_remove_filename_suggestion', array($this, 'ajax_remove_filename_suggestion'));
        add_action('wp_ajax_msh_accept_filename_suggestion', array($this, 'ajax_accept_filename_suggestion'));
        add_action('wp_ajax_msh_toggle_file_rename', array($this, 'ajax_toggle_file_rename'));
        add_action('wp_ajax_msh_reject_filename_suggestion', array($this, 'ajax_reject_filename_suggestion'));
        add_action('wp_ajax_msh_preview_meta_text', array($this, 'ajax_preview_meta_text'));
        add_action('wp_ajax_msh_save_edited_meta', array($this, 'ajax_save_edited_meta'));
        add_action('wp_ajax_msh_update_context', array($this, 'ajax_update_context'));
        add_action('wp_ajax_msh_build_usage_index', array($this, 'ajax_build_usage_index'));
        add_action('wp_ajax_msh_clear_bad_suggestions', array($this, 'ajax_clear_bad_suggestions'));
        add_action('wp_ajax_msh_optimize_high_priority', array($this, 'ajax_optimize_high_priority'));
        add_action('wp_ajax_msh_optimize_medium_priority', array($this, 'ajax_optimize_medium_priority'));
        add_action('wp_ajax_msh_optimize_all_remaining', array($this, 'ajax_optimize_all_remaining'));
        add_action('wp_ajax_msh_verify_webp_status', array($this, 'ajax_verify_webp_status'));
        add_action('wp_ajax_msh_get_attachment_count', array($this, 'ajax_get_attachment_count'));
        add_action('wp_ajax_msh_get_remaining_count', array($this, 'ajax_get_remaining_count'));

        $this->contextual_meta_generator = new MSH_Contextual_Meta_Generator();

        // Auto-generate suggestions for new uploads
        add_action('add_attachment', array($this, 'generate_suggestion_for_new_upload'), 10, 1);

        add_filter('attachment_fields_to_edit', array($this, 'add_context_attachment_field'), 10, 2);
        add_filter('attachment_fields_to_save', array($this, 'save_context_attachment_field'), 10, 2);
    }

    /**
     * Check if recompression is needed with safe file checks
     */
    private function needs_recompression($attachment_id) {
        $source_file = get_attached_file($attachment_id);
        if (!$source_file || !file_exists($source_file)) {
            return 'needs_attention'; // File missing or invalid
        }
        
        $source_mtime = @filemtime($source_file);
        if ($source_mtime === false) {
            return 'needs_attention'; // Can't read file time
        }
        
        $last_webp = (int)get_post_meta($attachment_id, 'msh_webp_last_converted', true);
        $last_metadata = (int)get_post_meta($attachment_id, 'msh_metadata_last_updated', true);
        
        // If no optimization timestamps exist, this isn't a recompression case
        if (!$last_webp && !$last_metadata) {
            return false;
        }
        
        // Needs recompression if source is newer than optimization
        return $source_mtime > max($last_webp, $last_metadata);
    }
    
    /**
     * Validate optimization status and provide fallback for unexpected values
     */
    private function validate_status($status) {
        $valid_statuses = [
            'ready_for_optimization',
            'optimized',
            'metadata_missing',
            'needs_recompression',
            'webp_missing',
            'metadata_current',
            'needs_webp_conversion',
            'webp_timestamp_missing',
            'needs_attention'
        ];
        
        if (!in_array($status, $valid_statuses)) {
            error_log("MSH Optimizer: Invalid status '$status' returned, defaulting to needs_attention");
            return 'needs_attention';
        }
        
        return $status;
    }

    /**
     * Get optimization status with enhanced logic and validation
     */
    private function get_optimization_status($attachment_id) {
        $webp_time = (int)get_post_meta($attachment_id, 'msh_webp_last_converted', true);
        $meta_time = (int)get_post_meta($attachment_id, 'msh_metadata_last_updated', true);
        $optimized_date = get_post_meta($attachment_id, 'msh_optimized_date', true);
        $version = get_post_meta($attachment_id, 'msh_optimization_version', true);
        $webp_status = get_post_meta($attachment_id, 'msh_webp_status', true);

        $source_file = get_attached_file($attachment_id);
        if (!$source_file || !file_exists($source_file)) {
            // Missing files are marked as "needs_attention" and excluded from optimization
            return $this->validate_status('needs_attention');
        }

        // Skip WebP conversion logic for files that don't need it
        $file_extension = strtolower(pathinfo($source_file, PATHINFO_EXTENSION));
        $is_webp_convertible = in_array($file_extension, ['jpg', 'jpeg', 'png']);

        if ($is_webp_convertible && in_array($webp_status, ['unsupported', 'failed'], true)) {
            if (!$meta_time) {
                return $this->validate_status('metadata_missing');
            }
            return $this->validate_status('optimized');
        }

        // For SVG, WebP, and other non-convertible formats, only check metadata
        if (!$is_webp_convertible) {
            if (!$meta_time) {
                return $this->validate_status('metadata_missing');
            }
            return $this->validate_status('optimized'); // These files don't need WebP conversion
        }

        // Check for missing metadata FIRST (before recompression test)
        if (!$meta_time && !$webp_time) {
            return $this->validate_status('ready_for_optimization');
        }

        if (!$meta_time) {
            return $this->validate_status('metadata_missing');
        }

        // CHECK: If image has been marked as optimized via msh_optimized_date, consider it optimized
        // This handles cases where metadata was applied but WebP conversion failed
        if ($optimized_date && $meta_time) {
            return $this->validate_status('optimized');
        }

        $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $source_file);
        $webp_exists = file_exists($webp_path);

        // Now check recompression (only for images that have been optimized)
        $recompression_check = $this->needs_recompression($attachment_id);
        if ($recompression_check === 'needs_attention') {
            return $this->validate_status('needs_attention');
        } elseif ($recompression_check === true) {
            return $this->validate_status('needs_recompression');
        }

        if ($webp_time && $meta_time && $webp_exists) {
            return $this->validate_status('optimized');
        } elseif ($webp_time && !$webp_exists) {
            return $this->validate_status('webp_missing');
        } elseif ($meta_time && !$webp_time && !$webp_exists) {
            // Has metadata but missing WebP - needs WebP conversion
            return $this->validate_status('needs_webp_conversion');
        } elseif ($meta_time && !$webp_time && $webp_exists) {
            // Has metadata and WebP file exists but no timestamp - update timestamp
            return $this->validate_status('webp_timestamp_missing');
        } else {
            return $this->validate_status('ready_for_optimization');
        }
    }

    /**
     * Get all published images that need optimization
     */
    public function get_published_images() {
        // TEMP: Disable caching to debug file analysis issues
        // static $cached_results = null;
        // if ($cached_results !== null) {
        //     return $cached_results;
        // }

        global $wpdb;

        $attachments = $wpdb->get_results(
            "SELECT ID, post_title, post_name, post_mime_type
             FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
             AND post_mime_type LIKE 'image/%'
             ORDER BY ID",
            ARRAY_A
        );

        // Debug: Count SVG attachments found (reduced logging for performance)
        $svg_count = 0;
        foreach ($attachments as $attachment) {
            if (strpos($attachment['post_mime_type'], 'svg') !== false) {
                $svg_count++;
            }
        }
        // Debug logging removed for production

        if (empty($attachments)) {
            // $cached_results = [];
            return [];
        }

        $attachment_map = [];
        $attachment_ids = [];

        foreach ($attachments as $attachment) {
            $attachment['file_path'] = '';
            $attachment['alt_text'] = '';
            $attachment['used_in'] = [];
            $attachment_map[$attachment['ID']] = $attachment;
            $attachment_ids[] = (int) $attachment['ID'];
        }

        // Gather attachment meta in chunks to avoid oversized IN clauses
        $meta_keys = [
            '_wp_attached_file',
            '_wp_attachment_image_alt',
        ];

        $meta_rows = [];
        $chunk_size = 200;

        foreach (array_chunk($attachment_ids, $chunk_size) as $chunk) {
            $id_placeholders = implode(',', array_fill(0, count($chunk), '%d'));
            $meta_placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
            $meta_sql = "
                SELECT post_id, meta_key, meta_value
                FROM {$wpdb->postmeta}
                WHERE post_id IN ($id_placeholders)
                AND meta_key IN ($meta_placeholders)
            ";
            $prepared = $wpdb->prepare($meta_sql, array_merge($chunk, $meta_keys));
            $meta_rows = array_merge($meta_rows, $wpdb->get_results($prepared, ARRAY_A));
        }

        foreach ($meta_rows as $meta_row) {
            $post_id = (int) $meta_row['post_id'];

            if (!isset($attachment_map[$post_id])) {
                continue;
            }

            if ($meta_row['meta_key'] === '_wp_attached_file') {
                $file_path = ltrim((string) $meta_row['meta_value'], '/');
                $uploads_dir = wp_upload_dir();
                $full_path = $uploads_dir['basedir'] . '/' . $file_path;

                // Only set file_path if file actually exists on disk
                if (file_exists($full_path)) {
                    $attachment_map[$post_id]['file_path'] = $file_path;
                } else {
                    // Debug logging removed for production
                    // Leave file_path empty so it gets filtered out later
                }
            }

            if ($meta_row['meta_key'] === '_wp_attachment_image_alt') {
                $attachment_map[$post_id]['alt_text'] = (string) $meta_row['meta_value'];
            }
        }

        $upload_dir = wp_get_upload_dir();
        $uploads_baseurl = isset($upload_dir['baseurl']) ? $upload_dir['baseurl'] : '';
        $uploads_baseurl = rtrim($uploads_baseurl, '/');

        $file_map = [];
        $basename_map = [];

        foreach ($attachment_map as $attachment_id => $attachment) {
            if (!empty($attachment['file_path'])) {
                $relative_path = ltrim($attachment['file_path'], '/');
                $file_map[strtolower($relative_path)] = $attachment_id;

                $basename = strtolower(basename($relative_path));
                $clean_basename = preg_replace('/-\d+x\d+(?=\.[^.]+$)/', '', $basename);
                $clean_basename = str_replace(['-scaled', '-rotated', '-edited'], '', $clean_basename);

                if (!isset($basename_map[$basename])) {
                    $basename_map[$basename] = [];
                }
                $basename_map[$basename][$attachment_id] = true;

                if (!isset($basename_map[$clean_basename])) {
                    $basename_map[$clean_basename] = [];
                }
                $basename_map[$clean_basename][$attachment_id] = true;
            }
        }

        $register_usage = static function (&$map, $attachment_id, $post_title, $post_type) {
            if (!isset($map[$attachment_id])) {
                return;
            }

            $title = trim((string) $post_title);

            if ($title === '') {
                $title = 'Untitled';
            }

            $label = $title . ' (' . $post_type . ')';
            $map[$attachment_id]['used_in'][$label] = true;
        };

        // Featured images (single query)
        $featured_rows = $wpdb->get_results(
            "SELECT meta.meta_value AS attachment_id, posts.post_title, posts.post_type
             FROM {$wpdb->postmeta} meta
             INNER JOIN {$wpdb->posts} posts ON posts.ID = meta.post_id
             WHERE meta.meta_key = '_thumbnail_id'
             AND posts.post_status = 'publish'",
            ARRAY_A
        );

        foreach ($featured_rows as $row) {
            $attachment_id = (int) $row['attachment_id'];

            if (!isset($attachment_map[$attachment_id])) {
                continue;
            }

            $register_usage(
                $attachment_map,
                $attachment_id,
                $row['post_title'],
                $row['post_type']
            );
        }

        // Published posts/pages content scan
        $content_rows = $wpdb->get_results(
            "SELECT ID, post_title, post_type, post_content
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND post_type NOT IN ('attachment','revision','nav_menu_item','customize_changeset','oembed_cache','user_request')",
            ARRAY_A
        );

        foreach ($content_rows as $post_row) {
            $content = (string) $post_row['post_content'];

            if ($content === '') {
                continue;
            }

            // Match Gutenberg and classic editor image references by attachment ID
            if (preg_match_all('/wp-image-(\d+)/', $content, $id_matches)) {
                $matched_ids = array_unique(array_map('intval', $id_matches[1]));

                foreach ($matched_ids as $attachment_id) {
                    if (!isset($attachment_map[$attachment_id])) {
                        continue;
                    }

                    $register_usage(
                        $attachment_map,
                        $attachment_id,
                        $post_row['post_title'],
                        $post_row['post_type']
                    );
                }
            }

            // Match direct file references
            if (preg_match_all('#wp-content/uploads/[^"\'\s>]+#i', $content, $path_matches)) {
                $paths = array_unique($path_matches[0]);

                foreach ($paths as $path) {
                    $normalized = strtolower($path);
                    $normalized = preg_replace('#^' . preg_quote(strtolower($uploads_baseurl), '#') . '\/?#', '', $normalized);
                    $normalized = preg_replace('#^.*wp-content\/uploads\/+#', '', $normalized);
                    $normalized = strtok($normalized, '?'); // remove query strings
                    $normalized = ltrim((string) $normalized, '/');

                    if ($normalized === '') {
                        continue;
                    }

                    if (isset($file_map[$normalized])) {
                        $attachment_id = $file_map[$normalized];
                        $register_usage(
                            $attachment_map,
                            $attachment_id,
                            $post_row['post_title'],
                            $post_row['post_type']
                        );
                        continue;
                    }

                    $basename = strtolower(basename($normalized));

                    if (isset($basename_map[$basename])) {
                        foreach (array_keys($basename_map[$basename]) as $attachment_id) {
                            $register_usage(
                                $attachment_map,
                                $attachment_id,
                                $post_row['post_title'],
                                $post_row['post_type']
                            );
                        }
                        continue;
                    }

                    $basename_clean = preg_replace('/-\d+x\d+(?=\.[^.]+$)/', '', $basename);
                    $basename_clean = str_replace(['-scaled', '-rotated', '-edited'], '', $basename_clean);

                    if (isset($basename_map[$basename_clean])) {
                        foreach (array_keys($basename_map[$basename_clean]) as $attachment_id) {
                            $register_usage(
                                $attachment_map,
                                $attachment_id,
                                $post_row['post_title'],
                                $post_row['post_type']
                            );
                        }
                    }
                }
            }
        }

        $published_images = [];
        $svg_excluded_count = 0;
        $svg_included_count = 0;

        foreach ($attachment_map as $attachment) {
            $is_svg = isset($attachment['post_mime_type']) && strpos($attachment['post_mime_type'], 'svg') !== false;

            // TEMPORARY: Limit SVG auto-include to prevent performance issues
            // Only auto-include SVGs with IDs > 14500 (newer condition icons) to focus on relevant ones
            $should_auto_include_svg = $is_svg && (int)$attachment['ID'] > 14500;

            // Include SVGs regardless of usage detection (limited to newer ones for performance)
            if (empty($attachment['used_in']) && !$should_auto_include_svg) {
                if ($is_svg && (int)$attachment['ID'] <= 14500) {
                    // Skip older duplicate SVGs for performance
                    continue;
                }
                continue; // Exclude non-SVG unused images, but keep newer SVGs
            }

            if (empty($attachment['used_in']) && $should_auto_include_svg) {
                // SVGs without detected usage - add them with a default usage note
                $attachment['used_in'] = ['SVG Icon (auto-included)' => true];
                $svg_excluded_count++; // Count as "rescued" SVGs
                // Debug logging removed for production
            }

            if ($is_svg) {
                $svg_included_count++;
                // Debug logging removed for production
            }

            // Skip images with missing files (empty file_path)
            if (empty($attachment['file_path'])) {
                // Debug logging removed for production
                continue;
            }

            $attachment['used_in'] = implode(', ', array_keys($attachment['used_in']));
            $published_images[] = $attachment;
        }

        // Debug logging removed for production

        usort($published_images, static function ($a, $b) {
            return $a['ID'] <=> $b['ID'];
        });

        // $cached_results = $published_images;
        // return $cached_results;
        return $published_images;
    }

    /**
     * Calculate healthcare-specific priority for image optimization
     */
    private function calculate_healthcare_priority($image) {
        $priority = 1;
        $used_in = strtolower($image['used_in']);
        
        // Healthcare-specific high-priority pages
        if (strpos($used_in, 'home') !== false) {
            $priority += 15; // Homepage hero images critical for trust
        }
        
        // Medical services pages (highest conversion)
        if (strpos($used_in, 'services') !== false || 
            strpos($used_in, 'treatment') !== false ||
            strpos($used_in, 'conditions') !== false) {
            $priority += 12;
        }
        
        // Team/doctor photos (trust & credibility)
        if (strpos($used_in, 'team') !== false || 
            strpos($used_in, 'doctor') !== false ||
            strpos($used_in, 'staff') !== false) {
            $priority += 10;
        }
        
        // Patient testimonials/success stories
        if (strpos($used_in, 'testimonial') !== false || 
            strpos($used_in, 'patient') !== false) {
            $priority += 8;
        }
        
        // CRITICAL: Missing alt text in healthcare = accessibility violation
        if (empty($image['alt_text'])) {
            $priority += 20; // Healthcare accessibility is legal requirement
        }
        
        return $priority;
    }

    /**
     * Analyze single image for optimization potential
     */
    public function analyze_single_image($attachment_id) {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!is_array($metadata)) {
            $metadata = [];
        }
        $upload_dir = wp_upload_dir();

        $relative_file = is_array($metadata) && !empty($metadata['file'])
            ? $metadata['file']
            : get_post_meta($attachment_id, '_wp_attached_file', true);

        if (empty($relative_file)) {
            return ['error' => 'No file metadata found'];
        }

        $file_path = $upload_dir['basedir'] . '/' . ltrim($relative_file, '/');
        
        if (!file_exists($file_path)) {
            return ['error' => 'File not found: ' . $file_path];
        }
        
        $file_size = filesize($file_path);
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $is_svg = ($extension === 'svg');

        $image_info = $is_svg ? [0 => 0, 1 => 0, 'mime' => 'image/svg+xml'] : @getimagesize($file_path);
        if (!$image_info) {
            $image_info = [0 => 0, 1 => 0, 'mime' => $metadata['mime_type'] ?? 'image'];
        }

        $webp_exists = false;
        $webp_savings = null;
        if (!$is_svg) {
            $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $file_path);
            $webp_exists = file_exists($webp_path);
            $webp_savings = $this->estimate_webp_savings($file_size, $image_info['mime']);
        }
        
        // Determine legacy resizing context and new contextual information
        $legacy_context = $this->determine_image_context($attachment_id);
        $context_info = $this->contextual_meta_generator->detect_context($attachment_id);
        $manual_context_value = get_post_meta($attachment_id, '_msh_context', true);
        $manual_context_value = is_string($manual_context_value) ? trim($manual_context_value) : '';
        $auto_context_value = get_post_meta($attachment_id, '_msh_auto_context', true);
        $auto_context_value = is_string($auto_context_value) ? trim($auto_context_value) : '';
        $context_source = !empty($context_info['manual']) ? 'manual' : 'auto';
        $active_context_slug = $manual_context_value !== ''
            ? $manual_context_value
            : ($context_info['type'] ?? $auto_context_value);
        $generated_meta = $this->contextual_meta_generator->generate_meta_fields($attachment_id, $context_info);

        // Check if file already has SEO-optimized name FIRST
        $current_file = $file_path; // Use the correct variable name
        $path_info = pathinfo($current_file);
        $extension = isset($path_info['extension']) ? strtolower($path_info['extension']) : '';
        $current_basename = strtolower($path_info['basename']);
        $current_slug = strtolower(isset($path_info['filename']) ? $path_info['filename'] : '');

        // Generate target slug for comparison (context-aware)
        $expected_slug = '';
        if (!empty($extension)) {
            $expected_slug = $this->contextual_meta_generator->generate_filename_slug($attachment_id, $context_info, $extension);
        }

        // If file already has good name, clear any existing suggestion and don't generate new one
        if (!empty($expected_slug)) {
            $has_good_name = ($current_slug === strtolower($expected_slug));
        } else {
            $has_good_name = (strpos($current_basename, 'msh') !== false ||
                             strpos($current_basename, 'hamilton') !== false ||
                             strpos($current_basename, 'main-street-health') !== false ||
                             // Also detect common SEO patterns our system generates
                             preg_match('/^(rehabilitation|physiotherapy|chiropractic|acupuncture|massage|orthotics|chronic-pain|work-related|sport-injuries|motor-vehicle|patient-testimonial|bluecross|canada-life|manulife)-/', $current_basename) ||
                             // Or files that end with OUR attachment ID pattern (more specific to avoid false matches)
                             preg_match('/-' . $attachment_id . '\.(jpg|jpeg|png|gif|svg|webp)$/', $current_basename));
        }

        // Debug filename suggestion logic for SVGs
        $is_svg = (strtolower(pathinfo($current_file, PATHINFO_EXTENSION)) === 'svg');
        if ($is_svg && (int)$attachment_id > 14500) {
            // Debug disabled for performance
        }

        if ($has_good_name) {
            // Remove any existing suggestion for this already-optimized file
            delete_post_meta($attachment_id, '_msh_suggested_filename');
            $suggested_filename = ''; // No suggestion needed
        } else {
            // Get or generate suggestion for files that need renaming
            $suggested_filename = get_post_meta($attachment_id, '_msh_suggested_filename', true);

            $expected_filename_full = '';
            if (!empty($expected_slug) && !empty($extension)) {
                $expected_filename_full = $this->ensure_unique_filename($expected_slug, $extension, $attachment_id);
            }

            if (!empty($suggested_filename) && !empty($expected_filename_full)) {
                $current_suggested_base = strtolower(pathinfo($suggested_filename, PATHINFO_FILENAME));
                $expected_suggested_base = strtolower(pathinfo($expected_filename_full, PATHINFO_FILENAME));

                if ($current_suggested_base !== $expected_suggested_base) {
                    $suggested_filename = $expected_filename_full;
                    update_post_meta($attachment_id, '_msh_suggested_filename', $suggested_filename);
                    update_post_meta($attachment_id, 'msh_filename_last_suggested', time());
                }
            }

            // Generate suggestion if it doesn't exist yet
            if (empty($suggested_filename) && !empty($extension)) {
                // Debug disabled for performance

                $slug = !empty($expected_slug)
                    ? $expected_slug
                    : $this->contextual_meta_generator->generate_filename_slug($attachment_id, $context_info, $extension);

                if (!empty($slug)) {
                    if (!empty($expected_filename_full) && $slug === $expected_slug) {
                        $suggested_filename = $expected_filename_full;
                    } else {
                        $suggested_filename = $this->ensure_unique_filename($slug, $extension, $attachment_id);
                    }
                    update_post_meta($attachment_id, '_msh_suggested_filename', $suggested_filename);
                    update_post_meta($attachment_id, 'msh_filename_last_suggested', time());

                    // Debug disabled for performance
                } else {
                    // Debug disabled for performance
                }
            }
        }

        $quality_note = get_post_meta($attachment_id, '_msh_filename_quality_note', true);

        // Gather optimization metadata
        $optimized_date = get_post_meta($attachment_id, 'msh_optimized_date', true);
        $optimization_status = $this->get_optimization_status($attachment_id);
        $webp_last_converted = (int) get_post_meta($attachment_id, 'msh_webp_last_converted', true);
        $metadata_last_updated = (int) get_post_meta($attachment_id, 'msh_metadata_last_updated', true);
        $source_last_compressed = (int) get_post_meta($attachment_id, 'msh_source_last_compressed', true);

        // Analysis only reads existing suggestions - no writing to database during analysis

        if ($is_svg) {
            $optimization_potential = [
                'needs_resize' => false,
                'current_size' => $file_size,
                'recommended_dimensions' => null,
                'estimated_optimal_size' => $file_size,
                'estimated_savings_bytes' => 0,
                'estimated_savings_percent' => 0
            ];
        } else {
            $optimization_potential = $this->calculate_optimization_potential($file_path, $metadata, $legacy_context);
        }

        return [
            'current_size_bytes' => $file_size,
            'current_size_mb' => round($file_size / 1048576, 2),
            'current_dimensions' => $is_svg ? 'vector' : ($image_info[0] . 'x' . $image_info[1]),
            'current_format' => $image_info['mime'],
            'webp_exists' => $webp_exists,
            'webp_savings_estimate' => $webp_savings,
            'context' => $legacy_context,
            'context_details' => $context_info,
            'context_source' => $context_source,
            'manual_context' => $manual_context_value,
            'auto_context' => $auto_context_value,
            'context_active_label' => $this->format_context_label($active_context_slug),
            'context_auto_label' => $auto_context_value !== '' ? $this->format_context_label($auto_context_value) : '',
            'generated_meta' => $generated_meta,
            'optimization_potential' => $optimization_potential,
            'suggested_filename' => $suggested_filename,
            'filename_quality_note' => $quality_note,
            'optimized_date' => $optimized_date,
            'optimization_status' => $optimization_status,
            'webp_last_converted' => $webp_last_converted,
            'metadata_last_updated' => $metadata_last_updated,
            'source_last_compressed' => $source_last_compressed
        ];
    }

    /**
     * Estimate potential WebP savings when conversion hasn't run yet
     */
    private function estimate_webp_savings($file_size, $mime_type) {
        $file_size = (int) $file_size;

        if ($file_size <= 0) {
            return [
                'source_size' => 0,
                'estimated_webp_size' => 0,
                'estimated_savings_bytes' => 0,
                'estimated_savings_percent' => 0,
            ];
        }

        $mime_type = strtolower((string) $mime_type);

        // Average compression ratios based on format benchmarking
        $compression_map = [
            'image/jpeg' => 0.35,
            'image/jpg' => 0.35,
            'image/png' => 0.45,
            'image/gif' => 0.55,
            'image/webp' => 1.00,
        ];

        $ratio = isset($compression_map[$mime_type]) ? (float) $compression_map[$mime_type] : 0.40;
        $ratio = max(0.05, min(1.0, $ratio));

        $estimated_webp_size = (int) round($file_size * $ratio);
        $estimated_webp_size = max(0, min($file_size, $estimated_webp_size));

        $estimated_savings_bytes = max(0, $file_size - $estimated_webp_size);
        $estimated_savings_percent = $file_size > 0
            ? (int) round(($estimated_savings_bytes / $file_size) * 100)
            : 0;

        return [
            'source_size' => $file_size,
            'estimated_webp_size' => $estimated_webp_size,
            'estimated_savings_bytes' => $estimated_savings_bytes,
            'estimated_savings_percent' => max(0, min(100, $estimated_savings_percent)),
        ];
    }

    /**
     * Recalculate optimization potential with healthcare-aware sizes
     */
    private function calculate_optimization_potential($file_path, $metadata, $context_slug = null) {
        $current_size = @filesize($file_path);
        $current_size = $current_size !== false ? (int) $current_size : 0;

        if ($current_size <= 0 || empty($metadata)) {
            return [
                'needs_resize' => false,
                'current_size' => $current_size,
                'recommended_dimensions' => null,
                'estimated_savings_bytes' => 0,
                'estimated_savings_percent' => 0,
            ];
        }

        $dimensions = [
            'width' => $metadata['width'] ?? 0,
            'height' => $metadata['height'] ?? 0,
        ];

        $recommended = $this->get_recommended_dimensions($context_slug, $dimensions);
        $needs_resize = $this->needs_resize($dimensions, $recommended);

        if (!$needs_resize) {
            return [
                'needs_resize' => false,
                'current_size' => $current_size,
                'recommended_dimensions' => $recommended,
                'estimated_savings_bytes' => 0,
                'estimated_savings_percent' => 0,
            ];
        }

        $estimated_optimal_size = $this->estimate_optimal_filesize($current_size, $recommended, $dimensions);
        $estimated_optimal_size = max(0, min($current_size, $estimated_optimal_size));
        $estimated_savings_bytes = max(0, $current_size - $estimated_optimal_size);
        $estimated_savings_percent = $current_size > 0
            ? (int) round(($estimated_savings_bytes / $current_size) * 100)
            : 0;

        return [
            'needs_resize' => true,
            'current_size' => $current_size,
            'recommended_dimensions' => $recommended,
            'estimated_optimal_size' => $estimated_optimal_size,
            'estimated_savings_bytes' => $estimated_savings_bytes,
            'estimated_savings_percent' => max(0, min(100, $estimated_savings_percent)),
        ];
    }

    private function get_recommended_dimensions($context_slug, array $dimensions) {
        $defaults = ['width' => 1200, 'height' => 800];

        if (!$context_slug) {
            return $defaults;
        }

        $recommendations = [
            'homepage_hero' => ['width' => 1400, 'height' => 750],
            'service_page' => ['width' => 900, 'height' => 600],
            'team_photo' => ['width' => 600, 'height' => 800],
            'testimonial' => ['width' => 600, 'height' => 600],
            'facility' => ['width' => 1200, 'height' => 800],
            'equipment' => ['width' => 900, 'height' => 600],
            'blog_featured' => ['width' => 1200, 'height' => 675],
        ];

        if (!isset($recommendations[$context_slug])) {
            return $defaults;
        }

        $recommended = $recommendations[$context_slug];

        // Ensure we don't suggest an upscale
        $recommended['width'] = min($recommended['width'], (int) ($dimensions['width'] ?? $recommended['width']));
        $recommended['height'] = min($recommended['height'], (int) ($dimensions['height'] ?? $recommended['height']));

        return $recommended;
    }

    private function needs_resize(array $dimensions, array $recommended) {
        $width = (int) ($dimensions['width'] ?? 0);
        $height = (int) ($dimensions['height'] ?? 0);

        if ($width === 0 || $height === 0) {
            return false;
        }

        return $width > $recommended['width'] + 40 || $height > $recommended['height'] + 40;
    }

    private function estimate_optimal_filesize($current_size, array $recommended, array $dimensions) {
        $width = (int) ($dimensions['width'] ?? 1);
        $height = (int) ($dimensions['height'] ?? 1);

        if ($width <= 0 || $height <= 0) {
            return $current_size;
        }

        $current_pixels = $width * $height;
        $target_pixels = max(1, $recommended['width'] * $recommended['height']);
        $scale_factor = $target_pixels / $current_pixels;

        $estimated = $current_size * $scale_factor;
        $estimated = $estimated * 1.1; // include buffer for quality retention

        return (int) round($estimated);
    }

    /**
     * Determine image context based on usage
     */
    private function determine_image_context($attachment_id) {
        global $wpdb;
        
        // Check if it's a featured image
        $featured_posts = $wpdb->get_results($wpdb->prepare("
            SELECT posts.post_type, posts.post_title 
            FROM {$wpdb->postmeta} meta 
            JOIN {$wpdb->posts} posts ON posts.ID = meta.post_id 
            WHERE meta.meta_key = '_thumbnail_id' 
            AND meta.meta_value = %d 
            AND posts.post_status = 'publish'
        ", $attachment_id));
        
        if ($featured_posts) {
            foreach ($featured_posts as $post) {
                if (strpos(strtolower($post->post_title), 'home') !== false) {
                    return 'homepage_hero';
                }
                if ($post->post_type === 'msh_service') {
                    return 'service_page';
                }
                if ($post->post_type === 'msh_team_member') {
                    return 'team_photo';
                }
                if ($post->post_type === 'post') {
                    return 'blog_featured';
                }
            }
        }
        
        // Check content usage
        $file_path = get_post_meta($attachment_id, '_wp_attached_file', true);
        if ($file_path) {
            $posts_using = $wpdb->get_results($wpdb->prepare("
                SELECT post_type, post_title 
                FROM {$wpdb->posts} 
                WHERE post_content LIKE %s 
                AND post_status = 'publish'
            ", '%' . $file_path . '%'));
            
            foreach ($posts_using as $post) {
                $title_lower = strtolower($post->post_title);
                if (strpos($title_lower, 'testimonial') !== false || 
                    strpos($title_lower, 'patient') !== false) {
                    return 'testimonial';
                }
                if (strpos($title_lower, 'facility') !== false || 
                    strpos($title_lower, 'office') !== false ||
                    strpos($title_lower, 'clinic') !== false) {
                    return 'facility';
                }
            }
        }
        
        return 'blog_featured'; // Default context
    }

    /**
     * Generate business-focused filename
     */
    private function ensure_unique_filename($base_name, $extension, $attachment_id) {
        $filename = $base_name . '.' . $extension;

        // Check if this exact filename already exists in WordPress
        $existing_attachment = $this->get_attachment_by_filename($filename);

        // Also check for base name conflicts across different extensions
        $base_conflicts = $this->get_attachments_with_base_name($base_name);

        if (($existing_attachment && $existing_attachment !== $attachment_id) || !empty($base_conflicts)) {
            // Also check if this suggestion is already suggested for another file
            $suggestion_conflicts = $this->get_attachments_with_suggestion($filename);

            // Try with short attachment ID suffix first
            $short_id = $attachment_id;
            $filename = $base_name . '-' . $short_id . '.' . $extension;

            // If still conflicts, add timestamp
            $existing_check = $this->get_attachment_by_filename($filename);
            if ($existing_check && $existing_check !== $attachment_id) {
                $timestamp = substr(time(), -4); // Last 4 digits of timestamp
                $filename = $base_name . '-' . $timestamp . '.' . $extension;
            }
        } else {
            // Even if filename doesn't exist yet, check for duplicate suggestions
            $suggestion_conflicts = $this->get_attachments_with_suggestion($filename);
            if (!empty($suggestion_conflicts) && !in_array($attachment_id, $suggestion_conflicts)) {
                $short_id = $attachment_id;
                $filename = $base_name . '-' . $short_id . '.' . $extension;
            }
        }

        error_log("MSH Uniqueness: AttachmentID=$attachment_id, BaseName='$base_name', FinalFilename='$filename'");
        return $filename;
    }

    /**
     * Get attachments that have the same base filename (ignoring extension)
     */
    private function get_attachments_with_base_name($base_name) {
        global $wpdb;

        $like_pattern = '%' . $wpdb->esc_like($base_name) . '.%';
        $results = $wpdb->get_col($wpdb->prepare("
            SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE (meta_key = '_wp_attached_file' OR meta_key = '_msh_suggested_filename')
            AND meta_value LIKE %s
        ", $like_pattern));

        return array_map('intval', $results);
    }

    /**
     * Get attachments that already have this filename as a suggestion
     */
    private function get_attachments_with_suggestion($filename) {
        global $wpdb;

        $results = $wpdb->get_col($wpdb->prepare("
            SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_msh_suggested_filename'
            AND meta_value = %s
        ", $filename));

        return array_map('intval', $results);
    }

    /**
     * Check if suggested filename is worse than current filename
     */
    private function is_filename_worse($current, $suggested) {
        // Remove extensions for comparison
        $current_base = pathinfo($current, PATHINFO_FILENAME);
        $suggested_base = pathinfo($suggested, PATHINFO_FILENAME);

        // Score filenames (higher = better)
        $current_score = $this->score_filename_quality($current_base);
        $suggested_score = $this->score_filename_quality($suggested_base);

        error_log("MSH Quality Check: Current='$current_base' (score: $current_score), Suggested='$suggested_base' (score: $suggested_score)");

        // Don't suggest if it's significantly worse (threshold: 4 points)
        return ($current_score - $suggested_score) >= 4;
    }

    /**
     * Score filename quality (higher = better SEO/descriptiveness)
     */
    private function score_filename_quality($filename) {
        $score = 0;
        $filename_lower = strtolower($filename);

        // Positive points for SEO-friendly elements (expanded)
        $seo_keywords = [
            'hamilton' => 2,
            'main-street-health' => 3,
            'physiotherapy' => 2,
            'chiropractic' => 2,
            'treatment' => 1,
            'therapy' => 1,
            'rehabilitation' => 1,
            'chronic-pain' => 3,
            'back-pain' => 3,
            'neck-pain' => 3,
            'sciatica' => 3,
            'tmj' => 3,
            'concussion' => 3,
            'workplace-injury' => 3,
            'auto-accident' => 3,
            'cardiovascular' => 3,
            'equipment' => 1,
            'testing' => 2,
            'health' => 1,
            'medical' => 1,
            'clinical' => 1,
            // Equipment/product specific
            'djp' => 2,
            'bionic' => 2,
            'fullstop' => 2,
            'skin' => 1,
            'orthopedic' => 3,
            'pillow' => 2,
            'compression' => 2,
            'stocking' => 2,
            'brace' => 2,
            'crutches' => 2,
            'knee' => 2,
            'ankle' => 2,
            'wrist' => 2,
            'back' => 2,
            'neck' => 2,
            // Brand/quality indicators
            'gettyimages' => 2,
            'professional' => 1,
            'premium' => 1,
            'advanced' => 1,
            'brand' => 2,
            'branding' => 2,
            'logo' => 2,
            'team' => 2,
            'culture' => 2,
            'workspace' => 2,
            'office' => 1,
            'studio' => 1,
            'campaign' => 2,
            'marketing' => 2,
            'services' => 1,
            'portfolio' => 2,
            'product' => 2,
            'catalog' => 2,
            'brochure' => 2,
            'flyer' => 1,
            'testimonial' => 2,
            'case-study' => 2,
            'success-story' => 2,
            'client' => 2,
            'customer' => 2,
            'business' => 1,
            // Dimensions (indicate professional stock)
            '1400x1400' => 1,
            '1200x800' => 1
        ];

        foreach ($seo_keywords as $keyword => $points) {
            if (strpos($filename_lower, $keyword) !== false) {
                $score += $points;
            }
        }

        // Bonus for longer, descriptive filenames (up to 6 words)
        $word_count = count(explode('-', $filename_lower));
        if ($word_count >= 3 && $word_count <= 6) {
            $score += $word_count;
        }

        // Dynamic brand signals
        if (!empty($this->business_name)) {
            $brand_slug = $this->slugify($this->business_name);
            if ($brand_slug !== '' && strpos($filename_lower, $brand_slug) !== false) {
                $score += 3;
            }
        }

        if (!empty($this->location_slug) && strpos($filename_lower, $this->location_slug) !== false) {
            $score += 2;
        }

        if (!empty($this->industry_label)) {
            $industry_slug = $this->slugify($this->industry_label);
            if ($industry_slug !== '' && strpos($filename_lower, $industry_slug) !== false) {
                $score += 2;
            }
        }

        // Penalty for generic terms (healthcare + placeholder slugs)
        $generic_terms = [
            'rehabilitation-physiotherapy',
            'rehabilitation-equipment',
            'service-icon',
            'img',
            'image',
            'photo',
            'picture',
            'stock-photo'
        ];
        foreach ($generic_terms as $term) {
            if (strpos($filename_lower, $term) !== false) {
                $score -= 5; // Heavy penalty for generic names
            }
        }

        // Penalty for numbered suffixes (indicates generated filename)
        if (preg_match('/-\d{4,}$/', $filename_lower)) {
            $score -= 2;
        }

        return max(0, $score);
    }

    /**
     * Find attachment by filename
     */
    private function get_attachment_by_filename($filename) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
            '%' . $wpdb->esc_like($filename)
        ));
        
        return $result ? (int) $result : null;
    }

    private function generate_business_filename($attachment_id, $context) {
        $file_path = get_post_meta($attachment_id, '_wp_attached_file', true);

        if (empty($file_path) || !is_string($file_path)) {
            error_log("MSH Optimizer: Empty or invalid file path for attachment ID: $attachment_id");
            return false;
        }

        $extension = pathinfo($file_path, PATHINFO_EXTENSION);
        if (empty($extension)) {
            error_log("MSH Optimizer: No file extension found for attachment ID: $attachment_id, file: $file_path");
            return false;
        }

        $extension = strtolower($extension);
        $context_details = $this->contextual_meta_generator->detect_context($attachment_id);

        // Legacy callers may still provide a context slug – honour it if type missing
        if (!empty($context) && empty($context_details['type'])) {
            $context_details['type'] = $context;
        }

        $slug = $this->contextual_meta_generator->generate_filename_slug($attachment_id, $context_details, $extension);
        if (empty($slug)) {
            $slug = sanitize_title($context ?: basename($file_path, '.' . $extension));
        }

        return $this->ensure_unique_filename($slug, $extension, $attachment_id);
    }

    /**
     * Check if meta should be regenerated (protect manual edits)
     */
    private function should_regenerate_meta($attachment_id, $field = null) {
        $metadata_source = get_post_meta($attachment_id, 'msh_metadata_source', true);

        // Never overwrite manual edits
        if ($metadata_source === 'manual_edit') {
            return false;
        }

        return true;
    }

    private function get_context_choices() {
        if (class_exists('MSH_Image_Optimizer_Context_Helper')) {
            $active_context = MSH_Image_Optimizer_Context_Helper::get_active_context();
            $industry = isset($active_context['industry']) ? $active_context['industry'] : '';

            return MSH_Image_Optimizer_Context_Helper::get_context_choice_map($industry);
        }

        return [
            '' => __('Auto-detect (default)', 'msh-image-optimizer'),
            'business' => __('Business / General', 'msh-image-optimizer'),
            'team' => __('Team Member', 'msh-image-optimizer'),
            'testimonial' => __('Customer Testimonial', 'msh-image-optimizer'),
            'service-icon' => __('Icon / Graphic', 'msh-image-optimizer'),
            'facility' => __('Workspace / Office', 'msh-image-optimizer'),
            'equipment' => __('Product / Equipment', 'msh-image-optimizer'),
            'clinical' => __('Service Highlight', 'msh-image-optimizer'),
        ];
    }

    private function format_context_label($slug) {
        $slug = (string) $slug;

        if ($slug === '') {
            return __('Auto-detect (default)', 'msh-image-optimizer');
        }

        $choices = $this->get_context_choices();
        if (isset($choices[$slug])) {
            return $choices[$slug];
        }

        return $this->humanize_label($slug, __('Unknown', 'msh-image-optimizer'));
    }

    private function humanize_label($value, $fallback = '') {
        if (!is_string($value) || trim($value) === '') {
            return $fallback;
        }

        $label = str_replace(['-', '_'], ' ', strtolower($value));
        $label = preg_replace('/\s+/', ' ', $label);

        $label = trim($label);
        if ($label === '') {
            return $fallback;
        }

        return ucwords($label);
    }

    /**
     * Attachment field for manual context selection
     */
    public function add_context_attachment_field($form_fields, $post) {
        if (strpos($post->post_mime_type, 'image/') !== 0) {
            return $form_fields;
        }

        $choices = $this->get_context_choices();
        $manual_value = get_post_meta($post->ID, '_msh_context', true);
        $manual_value = is_string($manual_value) ? trim($manual_value) : '';
        $auto_value = get_post_meta($post->ID, '_msh_auto_context', true);
        $context_details = $this->contextual_meta_generator->detect_context($post->ID);
        $context_source = !empty($context_details['manual']) ? 'manual' : 'auto';

        $select = '<select class="msh-context-select" name="attachments[' . esc_attr($post->ID) . '][msh_context]" style="width:100%">';
        foreach ($choices as $key => $label) {
            $selected = selected($manual_value, $key, false);
            $select .= '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        $select .= '</select>';

        $active_label = $manual_value !== ''
            ? $this->format_context_label($manual_value)
            : $this->format_context_label($context_details['type'] ?? $auto_value);

        $auto_label_value = $auto_value !== '' ? $this->format_context_label($auto_value) : '';

        $chips = [];
        $chips[] = '<span class="msh-context-chip ' . ($context_source === 'manual' ? 'manual' : 'auto') . '">' .
            esc_html($context_source === 'manual' ? __('Manual override', 'msh-image-optimizer') : __('Auto-detected', 'msh-image-optimizer')) . '</span>';

        if ($active_label) {
            $chips[] = '<span class="msh-context-chip context">' . esc_html($active_label) . '</span>';
        }

        if ($context_source === 'manual' && $auto_label_value && $manual_value !== $auto_value) {
            $chips[] = '<span class="msh-context-chip auto-note">' .
                esc_html(sprintf(__('Auto suggestion: %s', 'msh-image-optimizer'), $auto_label_value)) . '</span>';
        }

        $detail_items = [];
        if (!empty($context_details['service'])) {
            $detail_items[] = esc_html(sprintf(
                __('Service focus: %s', 'msh-image-optimizer'),
                $this->contextual_meta_generator->format_service_label($context_details['service'])
            ));
        }
        if (!empty($context_details['asset'])) {
            $detail_items[] = esc_html(sprintf(
                __('Asset type: %s', 'msh-image-optimizer'),
                $this->humanize_label($context_details['asset'], __('General', 'msh-image-optimizer'))
            ));
        }
        if (!empty($context_details['product_type'])) {
            $detail_items[] = esc_html(sprintf(
                __('Product indicator: %s', 'msh-image-optimizer'),
                $this->humanize_label($context_details['product_type'], __('Medical', 'msh-image-optimizer'))
            ));
        }
        if (!empty($context_details['icon_type'])) {
            $detail_items[] = esc_html(sprintf(
                __('Icon category: %s', 'msh-image-optimizer'),
                $this->humanize_label($context_details['icon_type'], __('Clinical', 'msh-image-optimizer'))
            ));
        }
        if (!empty($context_details['page_title'])) {
            $detail_items[] = esc_html(sprintf(
                __('Appears on: %s', 'msh-image-optimizer'),
                $context_details['page_title']
            ));
        }

        $details_html = '';
        if (!empty($detail_items)) {
            $details_html = '<ul class="msh-context-details"><li>' . implode('</li><li>', $detail_items) . '</li></ul>';
        }

        $primary_description = $context_source === 'manual'
            ? __('The optimizer will honour this manual context until you switch back to Auto-detect.', 'msh-image-optimizer')
            : __('Auto-detect uses usage data, taxonomies, and filenames to pick the best context. Select a manual option to lock it in.', 'msh-image-optimizer');

        $auto_description = '';
        if ($context_source === 'manual') {
            $auto_description = $auto_label_value
                ? sprintf(__('Last auto-detected context: %s', 'msh-image-optimizer'), $auto_label_value)
                : __('Run the analyzer to record the latest auto-detected context for comparison.', 'msh-image-optimizer');
            $auto_description = '<p class="description">' . esc_html($auto_description) . '</p>';
        }

        static $styles_injected = false;
        $style_block = '';
        if (!$styles_injected) {
            $styles_injected = true;
            $style_block = '<style id="msh-context-field-styles">'
                . '.msh-context-field{margin-top:8px;padding:12px;border:1px solid #dcdcde;border-radius:6px;background:#f8f9fb;}'
                . '.msh-context-chips{margin-bottom:8px;}'
                . '.msh-context-chip{display:inline-block;margin:0 6px 6px 0;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600;border:1px solid #c3c4c7;background:#ffffff;color:#1d2327;}'
                . '.msh-context-chip.manual{background:#fde8e6;border-color:#f0b8af;color:#a4281f;}'
                . '.msh-context-chip.auto{background:#ecfbea;border-color:#b4e1b1;color:#116b25;}'
                . '.msh-context-chip.context{background:#fff;border-color:#c3c4c7;color:#1d2327;}'
                . '.msh-context-chip.auto-note{background:#eef2ff;border-color:#c0c7f8;color:#1b3f91;}'
                . '.msh-context-chip.pending{background:#fef7e5;border-color:#f7d48b;color:#7a4b00;}'
                . '.msh-context-details{margin:8px 0 0 0;padding-left:18px;font-size:12px;color:#1d2327;}'
                . '.msh-context-details li{margin-bottom:4px;}'
                . '.msh-context-select{margin-bottom:6px;}'
                . '</style>';
        }

        $html = $style_block . '<div class="msh-context-field">'
            . '<div class="msh-context-chips">' . implode('', $chips) . '</div>'
            . '<label class="screen-reader-text" for="msh-context-' . esc_attr($post->ID) . '">' . esc_html__('Image Context', 'msh-image-optimizer') . '</label>'
            . str_replace('<select', '<select id="msh-context-' . esc_attr($post->ID) . '"', $select)
            . '<p class="description">' . esc_html($primary_description) . '</p>'
            . $auto_description
            . $details_html
            . '</div>';

        $form_fields['msh_context'] = [
            'label' => __('Image Context', 'msh-image-optimizer'),
            'input' => 'html',
            'helps' => '',
            'html' => $html
        ];

        return $form_fields;
    }

    /**
     * Save manual context selection
     */
    public function save_context_attachment_field($post, $attachment) {
        if (isset($attachment['msh_context'])) {
            $choices = $this->get_context_choices();
            $value = sanitize_text_field($attachment['msh_context']);
            if (!array_key_exists($value, $choices)) {
                $value = '';
            }

            if ($value !== '') {
                update_post_meta($post['ID'], '_msh_context', $value);
            } else {
                delete_post_meta($post['ID'], '_msh_context');
            }

            // Remove deprecated metadata keys introduced in earlier batches
            delete_post_meta($post['ID'], '_msh_manual_edit');
            delete_post_meta($post['ID'], 'msh_context_last_manual_update');
        }

        return $post;
    }

    /**
     * Generate clinical meta using templates
     */
    private function validate_and_truncate_meta($meta_data) {
        $limits = ['title' => 60, 'caption' => 155, 'alt_text' => 125, 'description' => 250];
        $validated = [];
        
        foreach ($meta_data as $field => $content) {
            if (strlen($content) > $limits[$field]) {
                $content = $this->smart_truncate($content, $limits[$field]);
            }
            
            $quality_score = $this->score_meta_quality($content);
            if ($quality_score < 70) {
                error_log("MSH Optimizer: Low quality meta generated for $field: $content (score: $quality_score)");
            }
            
            $validated[$field] = $content;
        }
        
        return $validated;
    }

    /**
     * Smart truncation preserving clinical terms
     */
    private function smart_truncate($text, $limit) {
        if (strlen($text) <= $limit) return $text;
        
        $truncated = substr($text, 0, $limit);
        $last_space = strrpos($truncated, ' ');
        
        if ($last_space !== false) {
            $truncated = substr($truncated, 0, $last_space);
        }
        
        // Preserve essential terms
        $essential_terms = ['WSIB', 'Hamilton', 'physiotherapy', 'rehabilitation'];
        foreach ($essential_terms as $term) {
            if (strpos($text, $term) !== false && strpos($truncated, $term) === false) {
                $term_pos = strpos($text, $term);
                if ($term_pos + strlen($term) <= $limit) {
                    $truncated = substr($text, 0, $term_pos + strlen($term));
                }
            }
        }
        
        return trim($truncated);
    }

    /**
     * Score meta quality
     */
    private function score_meta_quality($content) {
        $score = 100;
        
        $blacklist = ['trusted partner', 'comprehensive care', 'healthcare services'];
        foreach ($blacklist as $phrase) {
            if (stripos($content, $phrase) !== false) {
                $score -= 25;
            }
        }

        $priority_terms = ['physiotherapy', 'rehabilitation', 'WSIB', 'Hamilton', 'chiropractic', 'clinic'];
        foreach ($priority_terms as $term) {
            if (stripos($content, $term) !== false) {
                $score += 8;
            }
        }

        return max(0, min(100, $score));
    }

    /**
     * Generate clinical caption
     */
    private function generate_title($attachment_id, $legacy_context = null) {
        $context_info = $this->contextual_meta_generator->detect_context($attachment_id);
        $meta = $this->contextual_meta_generator->generate_meta_fields($attachment_id, $context_info);

        if (!empty($meta['title'])) {
            return $meta['title'];
        }

        return 'Main Street Health - Rehabilitation Services';
    }
    
    private function generate_caption($attachment_id, $legacy_context = null) {
        $context_info = $this->contextual_meta_generator->detect_context($attachment_id);
        $meta = $this->contextual_meta_generator->generate_meta_fields($attachment_id, $context_info);

        if (!empty($meta['caption'])) {
            return $meta['caption'];
        }

        return 'Professional rehabilitation therapy for injury recovery in Hamilton.';
    }


    private function generate_alt_text($attachment_id, $legacy_context = null) {
        $context_info = $this->contextual_meta_generator->detect_context($attachment_id);
        $meta = $this->contextual_meta_generator->generate_meta_fields($attachment_id, $context_info);

        if (!empty($meta['alt_text'])) {
            return $meta['alt_text'];
        }

        return 'Main Street Health rehabilitation clinic in Hamilton Ontario';
    }


    private function generate_description($attachment_id, $legacy_context = null) {
        $context_info = $this->contextual_meta_generator->detect_context($attachment_id);
        $meta = $this->contextual_meta_generator->generate_meta_fields($attachment_id, $context_info);

        if (!empty($meta['description'])) {
            return $meta['description'];
        }

        return 'Professional rehabilitation services at Main Street Health in Hamilton with WSIB approved programs and direct billing.';
    }


    /**
     * AJAX handler for image analysis
     */
    public function ajax_analyze_images() {
        check_ajax_referer('msh_image_optimizer', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Check for force refresh parameter
        $force_refresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true';

        // URGENT: Quick cache to stop analysis time waste
        $cache_key = 'msh_analysis_cache_v' . self::ANALYSIS_CACHE_VERSION . '_' . md5('latest_analysis');

        if ($force_refresh) {
            delete_transient($cache_key);
            error_log("MSH: Cache cleared - performing fresh analysis");
        }

        $cached_result = get_transient($cache_key);

        if ($cached_result !== false && !$force_refresh) {
            $cache_age = time() - $cached_result['timestamp'];
            error_log("MSH: Using cached analysis from " . human_time_diff($cached_result['timestamp']) . " ago");

            // Use cache if less than 30 minutes old
            if ($cache_age < 1800) {
                wp_send_json_success($cached_result['data']);
                return;
            }
        }

        $start_time = microtime(true);
        error_log('MSH: Starting fresh analysis (no valid cache found)');
        
        // Debug: First check total images
        global $wpdb;
        $total_images = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'");

        $images = $this->get_published_images();

        if ($total_images === 0) {
            $total_images = count($images);
        }
        $analysis_results = [];
        
        try {
            foreach ($images as $image) {
            // Fix missing file_path for images that don't have _wp_attached_file meta
            if (empty($image['file_path'])) {
                $attached_file = get_attached_file($image['ID']);
                if ($attached_file) {
                    $upload_dir = wp_get_upload_dir();
                    $uploads_basedir = $upload_dir['basedir'];
                    if (strpos($attached_file, $uploads_basedir) === 0) {
                        $image['file_path'] = str_replace($uploads_basedir . '/', '', $attached_file);
                        error_log("MSH File Path Fix: ID {$image['ID']} - recovered file_path: {$image['file_path']}");
                    }
                }
            }

                $analysis = $this->analyze_single_image($image['ID']);
            $priority = $this->calculate_healthcare_priority($image);

            // Map current_size_bytes to file_size for frontend compatibility
            if (isset($analysis['current_size_bytes'])) {
                $analysis['file_size'] = $analysis['current_size_bytes'];
            }

            $analysis_results[] = array_merge($image, $analysis, ['priority' => $priority]);
        }
        } catch (Throwable $throwable) {
            error_log(sprintf('MSH Analyzer Fatal: ID %d - %s in %s:%d',
                isset($image['ID']) ? (int) $image['ID'] : 0,
                $throwable->getMessage(),
                $throwable->getFile(),
                $throwable->getLine()
            ));

            wp_send_json_error($throwable->getMessage());
        }

        // Sort by priority (highest first)
        usort($analysis_results, function($a, $b) {
            return $b['priority'] - $a['priority'];
        });
        
        // Include minimal debug info in response
        $duration_ms = round((microtime(true) - $start_time) * 1000, 2);

        $response_data = [
            'images' => $analysis_results,
            'total_images' => intval($total_images),
            'debug' => [
                'total_images_in_db' => intval($total_images),
                'published_images_found' => count($images),
                'analysis_duration_ms' => $duration_ms
            ]
        ];

        // URGENT: Cache the results to prevent time waste
        set_transient($cache_key, [
            'data' => $response_data,
            'timestamp' => time()
        ], 1800); // Cache for 30 minutes

        error_log("MSH: Analysis complete in {$duration_ms}ms, cached for 30 minutes");

        update_option('msh_last_analyzer_run', current_time('mysql'));

        wp_send_json_success($response_data);
    }

    /**
     * AJAX handler for batch optimization
     */
    public function ajax_optimize_batch() {
        check_ajax_referer('msh_image_optimizer', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $image_ids = $_POST['image_ids'] ?? [];
        $results = [];
        
        foreach ($image_ids as $attachment_id) {
            $result = $this->optimize_single_image(intval($attachment_id));
            $results[] = [
                'id' => $attachment_id,
                'result' => $result
            ];
        }

        if (!empty($image_ids)) {
            update_option('msh_last_optimization_run', current_time('mysql'));
        }

        wp_send_json_success($results);
    }

    /**
     * AJAX handler for High Priority optimization (15+)
     */
    public function ajax_optimize_high_priority() {
        check_ajax_referer('msh_image_optimizer', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Get all published images with priority 15+
        $images = $this->get_published_images();
        $high_priority_images = array_filter($images, function($image) {
            return $image['priority_score'] >= 15;
        });

        $image_ids = array_column($high_priority_images, 'ID');
        $results = [];

        foreach ($image_ids as $attachment_id) {
            $result = $this->optimize_single_image(intval($attachment_id));
            $results[] = [
                'id' => $attachment_id,
                'result' => $result
            ];
        }

        // Clear analysis cache after optimization
        $cache_key = 'msh_analysis_cache_' . md5('latest_analysis');
        delete_transient($cache_key);

        if (!empty($image_ids)) {
            update_option('msh_last_optimization_run', current_time('mysql'));
        }

        wp_send_json_success([
            'results' => $results,
            'total_processed' => count($image_ids),
            'message' => sprintf(__('Optimized %d high priority images (15+)', 'msh-image-optimizer'), count($image_ids))
        ]);
    }

    /**
     * AJAX handler for Medium Priority optimization (10-14)
     */
    public function ajax_optimize_medium_priority() {
        check_ajax_referer('msh_image_optimizer', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Get all published images with priority 10-14
        $images = $this->get_published_images();
        $medium_priority_images = array_filter($images, function($image) {
            return $image['priority_score'] >= 10 && $image['priority_score'] < 15;
        });

        $image_ids = array_column($medium_priority_images, 'ID');
        $results = [];

        foreach ($image_ids as $attachment_id) {
            $result = $this->optimize_single_image(intval($attachment_id));
            $results[] = [
                'id' => $attachment_id,
                'result' => $result
            ];
        }

        // Clear analysis cache after optimization
        $cache_key = 'msh_analysis_cache_' . md5('latest_analysis');
        delete_transient($cache_key);

        if (!empty($image_ids)) {
            update_option('msh_last_optimization_run', current_time('mysql'));
        }

        wp_send_json_success([
            'results' => $results,
            'total_processed' => count($image_ids),
            'message' => sprintf(__('Optimized %d medium priority images (10-14)', 'msh-image-optimizer'), count($image_ids))
        ]);
    }

    /**
     * AJAX handler for All Remaining optimization
     */
    public function ajax_optimize_all_remaining() {
        check_ajax_referer('msh_image_optimizer', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Get all published images that need optimization (excluding needs_attention)
        $images = $this->get_published_images();
        $unoptimized_images = array_filter($images, function($image) {
            return $image['optimization_status'] !== 'optimized' &&
                   $image['optimization_status'] !== 'needs_attention';
        });

        $image_ids = array_column($unoptimized_images, 'ID');
        $results = [];

        foreach ($image_ids as $attachment_id) {
            $result = $this->optimize_single_image(intval($attachment_id));
            $results[] = [
                'id' => $attachment_id,
                'result' => $result
            ];
        }

        // Clear analysis cache after optimization
        $cache_key = 'msh_analysis_cache_' . md5('latest_analysis');
        delete_transient($cache_key);

        if (!empty($image_ids)) {
            update_option('msh_last_optimization_run', current_time('mysql'));
        }

        wp_send_json_success([
            'results' => $results,
            'total_processed' => count($image_ids),
            'message' => sprintf(__('Optimized %d remaining images', 'msh-image-optimizer'), count($image_ids))
        ]);
    }

    /**
     * Run single image optimization (Batch 2: apply contextual metadata & filename suggestion)
     */
    private function optimize_single_image($attachment_id) {
        $attachment_id = intval($attachment_id);

        $result = [
            'status' => 'skipped',
            'actions' => [],
        ];

        if ($attachment_id <= 0) {
            $result['status'] = 'error';
            $result['actions'][] = 'Invalid attachment ID';
            return $result;
        }

        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            $result['status'] = 'error';
            $result['actions'][] = 'Attachment not found';
            return $result;
        }

        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            $result['status'] = 'error';
            $result['actions'][] = 'Original file missing';
            return $result;
        }

        $legacy_context = $this->determine_image_context($attachment_id);
        $context_details = $this->contextual_meta_generator->detect_context($attachment_id);
        $manual_context_value = get_post_meta($attachment_id, '_msh_context', true);
        $manual_context_value = is_string($manual_context_value) ? trim($manual_context_value) : '';
        $auto_context_value = get_post_meta($attachment_id, '_msh_auto_context', true);
        $auto_context_value = is_string($auto_context_value) ? trim($auto_context_value) : '';
        $context_source = !empty($context_details['manual']) ? 'manual' : 'auto';
        $active_context_slug = $manual_context_value !== ''
            ? $manual_context_value
            : ($context_details['type'] ?? $auto_context_value);
        $active_context_label = $this->format_context_label($active_context_slug);
        $auto_context_label = $auto_context_value !== '' ? $this->format_context_label($auto_context_value) : '';
        $meta_preview = $this->contextual_meta_generator->generate_meta_fields($attachment_id, $context_details);
        $meta_preview = $this->validate_and_truncate_meta($meta_preview);

        $context_message = $context_source === 'manual'
            ? sprintf(__('Manual override in effect: %s', 'msh-image-optimizer'), $active_context_label)
            : sprintf(__('Auto-detected context: %s', 'msh-image-optimizer'), $active_context_label);

        if ($context_source === 'manual' && $auto_context_label && $manual_context_value !== $auto_context_value) {
            $context_message .= ' ' . sprintf(__('(Auto suggestion: %s)', 'msh-image-optimizer'), $auto_context_label);
        }

        $timestamp = time();
        $meta_applied = [];
        $meta_skipped = [];
        $metadata_timestamp_applied = false;
        $filename_refreshed = false;

        // Title
        if (!empty($meta_preview['title'])) {
            if ($this->should_regenerate_meta($attachment_id, 'title')) {
                wp_update_post([
                    'ID' => $attachment_id,
                    'post_title' => sanitize_text_field($meta_preview['title']),
                    'post_name' => sanitize_title($meta_preview['title'])
                ]);
                $result['actions'][] = 'Title updated from contextual generator';
                $meta_applied['title'] = $meta_preview['title'];
            } else {
                $meta_skipped[] = 'title';
            }
        }

        // Caption
        if (!empty($meta_preview['caption'])) {
            if ($this->should_regenerate_meta($attachment_id, 'caption')) {
                wp_update_post([
                    'ID' => $attachment_id,
                    'post_excerpt' => sanitize_textarea_field($meta_preview['caption'])
                ]);
                $result['actions'][] = 'Caption updated from contextual generator';
                $meta_applied['caption'] = $meta_preview['caption'];
            } else {
                $meta_skipped[] = 'caption';
            }
        }

        // Description
        if (!empty($meta_preview['description'])) {
            if ($this->should_regenerate_meta($attachment_id, 'description')) {
                wp_update_post([
                    'ID' => $attachment_id,
                    'post_content' => sanitize_textarea_field($meta_preview['description'])
                ]);
                $result['actions'][] = 'Description updated from contextual generator';
                $meta_applied['description'] = $meta_preview['description'];
            } else {
                $meta_skipped[] = 'description';
            }
        }

        // Alt text
        if (!empty($meta_preview['alt_text'])) {
            if ($this->should_regenerate_meta($attachment_id, 'alt_text')) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($meta_preview['alt_text']));
                $result['actions'][] = 'ALT text updated from contextual generator';
                $meta_applied['alt_text'] = $meta_preview['alt_text'];
            } else {
                $meta_skipped[] = 'alt_text';
            }
        }

        // Convert to WebP if applicable
        $webp_converted = false;
        $webp_timestamp_updated = false;
        $image_info = wp_get_image_editor($file_path);
        if (!is_wp_error($image_info)) {
            $mime_type = get_post_mime_type($attachment_id);
            if (in_array($mime_type, ['image/jpeg', 'image/png'])) {
                $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $file_path);
                $webp_supported = function_exists('imagewebp');

                if (!$webp_supported) {
                    update_post_meta($attachment_id, 'msh_webp_status', 'unsupported');
                    $result['actions'][] = 'WebP conversion skipped (unsupported)';
                    $webp_timestamp_updated = true;
                } else {
                    delete_post_meta($attachment_id, 'msh_webp_status');

                    // Convert if WebP doesn't exist or source is newer
                    if (!file_exists($webp_path) || filemtime($file_path) > filemtime($webp_path)) {
                        $webp_result = $this->convert_to_webp($file_path, $webp_path);
                        if ($webp_result) {
                            update_post_meta($attachment_id, 'msh_webp_last_converted', $timestamp);
                            delete_post_meta($attachment_id, 'msh_webp_status');
                            $result['actions'][] = 'WebP version created';
                            $webp_converted = true;
                        } else {
                            update_post_meta($attachment_id, 'msh_webp_status', 'failed');
                            $result['actions'][] = 'WebP conversion failed';
                        }
                    } elseif (file_exists($webp_path) && !get_post_meta($attachment_id, 'msh_webp_last_converted', true)) {
                        // WebP exists but timestamp missing - update timestamp
                        update_post_meta($attachment_id, 'msh_webp_last_converted', $timestamp);
                        delete_post_meta($attachment_id, 'msh_webp_status');
                        $result['actions'][] = 'WebP timestamp updated';
                        $webp_timestamp_updated = true;
                    }
                }
            }
        }

        // Always refresh metadata timestamp so status reflects the latest context application
        if (!empty($meta_applied)) {
            update_post_meta($attachment_id, 'msh_metadata_last_updated', (int) $timestamp);
            delete_post_meta($attachment_id, 'msh_metadata_source');
            $metadata_timestamp_applied = true;
        } else {
            update_post_meta($attachment_id, 'msh_metadata_last_updated', (int) $timestamp);
            $metadata_timestamp_applied = true;
        }

        foreach ($meta_skipped as $field) {
            $result['actions'][] = ucfirst(str_replace('_', ' ', $field)) . ' preserved (manual edit)';
        }

        // Refresh filename suggestion using contextual slug helper
        $suggested_filename = '';
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if (!empty($extension)) {
            $slug = $this->contextual_meta_generator->generate_filename_slug($attachment_id, $context_details, $extension);
            if (!empty($slug)) {
                $suggested_filename = $this->ensure_unique_filename($slug, $extension, $attachment_id);
                update_post_meta($attachment_id, '_msh_suggested_filename', $suggested_filename);
                update_post_meta($attachment_id, 'msh_filename_last_suggested', (int) $timestamp);
                $result['actions'][] = 'Filename suggestion refreshed';
                $filename_refreshed = true;
            }
        }

        if ($metadata_timestamp_applied || $webp_converted || $webp_timestamp_updated || $filename_refreshed) {
            update_post_meta($attachment_id, 'msh_optimized_date', date('Y-m-d H:i:s', $timestamp));
        }

        $result['status'] = $this->get_optimization_status($attachment_id);
        $result['actions'][] = $context_message;
        $result['context'] = [
            'legacy' => $legacy_context,
            'detected' => $context_details,
            'source' => $context_source,
            'manual_override' => $manual_context_value,
            'auto' => $auto_context_value,
            'active_label' => $active_context_label,
            'auto_label' => $auto_context_label,
        ];
        $result['meta_preview'] = $meta_preview;
        $result['meta_applied'] = $meta_applied;
        $result['suggested_filename'] = $suggested_filename;

        return $result;
    }

    /**
     * AJAX: Update manual context selection directly from analyzer UI
     */
    public function ajax_update_context() {
        check_ajax_referer('msh_image_optimizer', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        if ($attachment_id <= 0) {
            wp_send_json_error(__('Invalid attachment ID.', 'msh-image-optimizer'));
        }

        $raw_context = isset($_POST['context']) ? wp_unslash($_POST['context']) : '';
        $new_context = sanitize_text_field($raw_context);

        // Handle legacy underscore format by converting to hyphen
        if ($new_context === 'service_icon') {
            $new_context = 'service-icon';
        }

        // Debug logging removed for production

        $choices = $this->get_context_choices();
        if ($new_context !== '' && !array_key_exists($new_context, $choices)) {
            wp_send_json_error(__('Invalid context selection.', 'msh-image-optimizer'));
        }

        if ($new_context !== '') {
            update_post_meta($attachment_id, '_msh_context', $new_context);
        } else {
            delete_post_meta($attachment_id, '_msh_context');
        }

        // Clean up deprecated keys retained for backwards compatibility.
        delete_post_meta($attachment_id, '_msh_manual_edit');
        delete_post_meta($attachment_id, 'msh_context_last_manual_update');

        // Refresh auto-detected context for comparison badges.
        try {
            $auto_context = $this->contextual_meta_generator->detect_context($attachment_id, true);
            if (!empty($auto_context['type'])) {
                update_post_meta($attachment_id, '_msh_auto_context', $auto_context['type']);
            } else {
                delete_post_meta($attachment_id, '_msh_auto_context');
            }
        } catch (Exception $e) {
            // Debug logging removed for production
        }

        try {
            $image_data = $this->analyze_single_image($attachment_id);
            if (!is_array($image_data) || isset($image_data['error'])) {
                $error_message = is_array($image_data) && isset($image_data['error'])
                    ? $image_data['error']
                    : __('Unable to refresh analyzer data.', 'msh-image-optimizer');
                // Debug logging removed for production
                wp_send_json_error($error_message);
            }

            wp_send_json_success([
                'image' => $image_data,
            ]);
        } catch (Exception $e) {
            // Debug logging removed for production
            wp_send_json_error('Error updating context: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for progress tracking
     */
    public function ajax_get_progress() {
        check_ajax_referer('msh_image_optimizer', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        // Get total published images (simplified for performance)
        $total_images = count($this->get_published_images());
        
        // Get optimized count (only from published images)
        $optimized_count = $wpdb->get_var("
            SELECT COUNT(DISTINCT pm.post_id) 
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = 'msh_optimized_date'
            AND p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
        ");
        
        $remaining = max(0, $total_images - $optimized_count);
        $percentage = $total_images > 0 ? min(100, round(($optimized_count / $total_images) * 100, 2)) : 0;
        
        $progress = [
            'total' => intval($total_images),
            'optimized' => intval($optimized_count),
            'percentage' => $percentage,
            'remaining' => $remaining
        ];
        
        wp_send_json_success($progress);
    }

    /**
     * AJAX handler to reset optimization flags (allows re-optimization with improved logic)
     */
    public function ajax_reset_optimization() {
        check_ajax_referer('msh_image_optimizer', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        // Remove optimization flags to allow re-processing with improved metadata preservation
        $reset_count = $wpdb->query("
            DELETE FROM {$wpdb->postmeta} 
            WHERE meta_key IN ('msh_optimized_date', '_msh_suggested_filename')
        ");
        
        wp_send_json_success([
            'reset_count' => $reset_count,
            'message' => "Reset {$reset_count} optimization flags. Images can now be re-optimized with improved metadata preservation."
        ]);
    }
    
    /**
     * Apply filename suggestions in batch with automatic batch processing
     */
    public function ajax_apply_filename_suggestions() {
        error_log('MSH Safe Rename: Batch apply function called');
        check_ajax_referer('msh_image_optimizer', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Production-ready batch processing
        $batch_size = 25; // Safe batch size to prevent timeouts
        $batch_number = isset($_POST['batch_number']) ? intval($_POST['batch_number']) : 1;
        $total_files = isset($_POST['total_files']) ? intval($_POST['total_files']) : 0;

        $mode = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'full';
        $limit = isset($_POST['limit']) ? max(0, intval($_POST['limit'])) : 0;

        $image_ids = isset($_POST['image_ids']) ? array_map('intval', $_POST['image_ids']) : [];
        error_log('MSH Safe Rename: Received image_ids from JavaScript: ' . print_r($image_ids, true));

        if (empty($image_ids)) {
            global $wpdb;
            $image_ids = $wpdb->get_col("
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_msh_suggested_filename'
                AND meta_value != ''
            ");

            // Debug: Log how many suggestions were found
            error_log('MSH Safe Rename: Found ' . count($image_ids) . ' images with filename suggestions');
        }

        if ($mode === 'test' && $limit > 0) {
            $image_ids = array_slice($image_ids, 0, $limit);
        }

        // Calculate batch boundaries
        $total_count = count($image_ids);
        $start_index = ($batch_number - 1) * $batch_size;
        $current_batch = array_slice($image_ids, $start_index, $batch_size);
        $total_batches = ceil($total_count / $batch_size);

        error_log("MSH Safe Rename: Processing batch {$batch_number}/{$total_batches} - " . count($current_batch) . " files (of {$total_count} total)");

        if (empty($current_batch)) {
            // All batches processed or no files to process
            wp_send_json_success([
                'results' => [],
                'summary' => [
                    'total' => $total_count,
                    'success' => 0,
                    'errors' => 0,
                    'skipped' => 0,
                    'mode' => $mode,
                    'batch_complete' => true,
                    'all_batches_complete' => true
                ]
            ]);
        }

        // SMART APPROACH: Index ONLY the files in current batch
        error_log("MSH Safe Rename: Smart indexing batch {$batch_number}/{$total_batches} - indexing " . count($current_batch) . " files");
        $usage_index = MSH_Image_Usage_Index::get_instance();

        // Quick index for just current batch files
        $indexed_count = 0;
        $skipped_count = 0;
        foreach ($current_batch as $attachment_id) {
            try {
                // Check if index already exists - don't rebuild existing indexes!
                global $wpdb;
                $index_table = $wpdb->prefix . 'msh_image_usage_index';
                $existing_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$index_table} WHERE attachment_id = %d",
                    $attachment_id
                ));

                if ($existing_count > 0) {
                    $skipped_count++;
                    error_log("MSH Safe Rename: ✅ SKIPPING $attachment_id - already indexed ($existing_count entries)");
                    continue;
                }

                $usage_index->index_attachment_usage($attachment_id);
                $indexed_count++;
                if ($indexed_count % 5 === 0) {
                    error_log("MSH Safe Rename: Just-in-time indexed $indexed_count files in batch {$batch_number}...");
                }
            } catch (Exception $e) {
                error_log("MSH Safe Rename: Failed to index $attachment_id: " . $e->getMessage());
            }
        }
        error_log("MSH Safe Rename: Batch {$batch_number} indexing complete - indexed $indexed_count files, skipped $skipped_count files");

        error_log('MSH Safe Rename: About to get instance of MSH_Safe_Rename_System');
        $renamer = MSH_Safe_Rename_System::get_instance();
        error_log('MSH Safe Rename: Successfully got instance of MSH_Safe_Rename_System');

        // Record start time for performance tracking
        $batch_start_time = microtime(true);

        error_log("MSH Safe Rename: ===============================================");
        error_log("MSH Safe Rename: 🚀 STARTING BATCH {$batch_number}/{$total_batches} FILENAME APPLICATION");
        error_log("MSH Safe Rename: ===============================================");
        error_log("MSH Safe Rename: 📊 BATCH OVERVIEW:");
        error_log("MSH Safe Rename: 📊   Batch: {$batch_number} of {$total_batches}");
        error_log("MSH Safe Rename: 📊   Files in this batch: " . count($current_batch));
        error_log("MSH Safe Rename: 📊   Total files overall: {$total_count}");
        error_log("MSH Safe Rename: 📊   Processing mode: " . ($mode === 'test' ? 'TEST MODE' : 'LIVE MODE'));
        error_log("MSH Safe Rename: 📊   Start time: " . date('Y-m-d H:i:s'));
        error_log("MSH Safe Rename: ===============================================");

        $results = [];
        $success_count = 0;
        $error_count = 0;
        $skipped_count = 0;
        $test_count = 0;

        $batch_file_count = count($current_batch);
        $processed = 0;
        $start_time = time();
        $max_execution_time = 600; // 10 minutes max per batch

        foreach ($current_batch as $attachment_id) {
            $processed++;

            // Enhanced progress logging for batch
            $batch_percentage = round(($processed / $batch_file_count) * 100, 1);
            error_log("MSH Safe Rename: 📊 BATCH {$batch_number} PROGRESS: File {$processed}/{$batch_file_count} ({$batch_percentage}%)");

            // Check execution time to prevent timeout
            if ((time() - $start_time) > $max_execution_time) {
                error_log("MSH Safe Rename: ⚠️ BATCH TIMEOUT - Stopping batch {$batch_number} at {$processed} of {$batch_file_count} files");
                break;
            }

            // Enhanced logging for every file with comprehensive debugging
            error_log("MSH Safe Rename: ===============================================");
            error_log("MSH Safe Rename: [Stage 1/4] 🔍 BATCH PROCESSING FILE $processed/$total_count");
            error_log("MSH Safe Rename: [Stage 1/4] 📎 Attachment ID: $attachment_id");

            // Get current attachment data with detailed logging
            $attachment = get_post($attachment_id);
            if (!$attachment || $attachment->post_type !== 'attachment') {
                error_log("MSH Safe Rename: [Stage 1/4] ❌ Invalid attachment ID: $attachment_id");
                error_log("MSH Safe Rename: [Stage 1/4] ❌ Attachment type: " . ($attachment ? $attachment->post_type : 'null'));
                continue;
            }

            // Log current attachment details
            $current_file = get_attached_file($attachment_id);
            error_log("MSH Safe Rename: [Stage 1/4] 📁 Current file: $current_file");
            error_log("MSH Safe Rename: [Stage 1/4] 📋 Attachment title: '{$attachment->post_title}'");

            // Get the suggested filename with validation
            $suggested_filename = get_post_meta($attachment_id, '_msh_suggested_filename', true);

            if (!$suggested_filename) {
                error_log("MSH Safe Rename: [Stage 1/4] ⚠️ No suggested filename for attachment: $attachment_id");
                error_log("MSH Safe Rename: [Stage 1/4] ⚠️ Skipping this file - no rename suggestion available");
                $results[] = [
                    'id' => $attachment_id,
                    'status' => 'skipped',
                    'message' => 'No filename suggestion available'
                ];
                $skipped_count++;
                continue;
            }

            error_log("MSH Safe Rename: [Stage 1/4] ✅ Found suggested filename: '$suggested_filename'");
            error_log("MSH Safe Rename: [Stage 1/4] 🚀 Initiating safe rename process...");

            $suggested_filename = sanitize_file_name($suggested_filename);
            error_log("MSH Safe Rename: [Stage 1/4] 🧹 Sanitized filename: '$suggested_filename'");

            // Record timing for performance analysis
            $start_time = microtime(true);
            error_log("MSH Safe Rename: [Stage 2/4] 🚀 Calling rename_attachment() with mode: " . ($mode === 'test' ? 'TEST' : 'LIVE'));

            $result = $renamer->rename_attachment($attachment_id, basename($suggested_filename), $mode === 'test');

            $end_time = microtime(true);
            $duration = round(($end_time - $start_time), 2);
            error_log("MSH Safe Rename: [Stage 4/4] 🎉 Rename operation completed in {$duration}s");

            if (is_wp_error($result)) {
                error_log("MSH Safe Rename: [Stage 4/4] ❌ Rename failed with WP_Error: " . $result->get_error_message());
                error_log("MSH Safe Rename: [Stage 4/4] ❌ Error code: " . $result->get_error_code());
                $error_data = $result->get_error_data();
                if (!empty($error_data)) {
                    error_log('MSH Safe Rename: [Stage 4/4] ❌ Error data: ' . print_r($error_data, true));
                }
                error_log("MSH Safe Rename: [Stage 4/4] ❌ File processing COMPLETE - error!");
                $results[] = [
                    'id' => $attachment_id,
                    'status' => 'error',
                    'message' => $result->get_error_message()
                ];
                $error_count++;
                continue;
            }

            if (!empty($result['test_mode'])) {
                error_log("MSH Safe Rename: [Stage 4/4] 🧪 Test mode - no filesystem changes were applied");
                $results[] = [
                    'id' => $attachment_id,
                    'status' => 'test',
                    'old_url' => $result['old_url'],
                    'new_url' => $result['new_url'],
                    'references_updated' => $result['replaced'],
                    'message' => __('Test mode - rename simulated only.', 'msh-image-optimizer')
                ];
                $test_count++;
                $success_count++;
                continue;
            }

            if (!empty($result['skipped'])) {
                error_log("MSH Safe Rename: [Stage 4/4] ⚠️ File skipped - filename already optimized");
                delete_post_meta($attachment_id, '_msh_suggested_filename');
                error_log("MSH Safe Rename: [Stage 4/4] 🗑️ Cleared suggested filename meta for skipped file: $attachment_id");
                error_log("MSH Safe Rename: [Stage 4/4] ⚠️ File processing COMPLETE - skipped!");

                $results[] = [
                    'id' => $attachment_id,
                    'status' => 'skipped',
                    'message' => __('Filename already optimized', 'msh-image-optimizer')
                ];
                $skipped_count++;
                continue;
            }

            error_log("MSH Safe Rename: [Stage 4/4] ✅ Successfully renamed attachment $attachment_id to '$suggested_filename' in {$duration}s");
            error_log("MSH Safe Rename: [Stage 4/4] 🔄 Database references updated: " . $result['replaced']);
            error_log("MSH Safe Rename: [Stage 4/4] 🔗 Old URL: " . $result['old_url']);
            error_log("MSH Safe Rename: [Stage 4/4] 🔗 New URL: " . $result['new_url']);

            delete_post_meta($attachment_id, '_msh_suggested_filename');
            error_log("MSH Safe Rename: [Stage 4/4] 🗑️ Cleared suggested filename meta for attachment: $attachment_id");
            error_log("MSH Safe Rename: [Stage 4/4] 🎉 File processing COMPLETE - success!");

            $results[] = [
                'id' => $attachment_id,
                'status' => 'success',
                'old_url' => $result['old_url'],
                'new_url' => $result['new_url'],
                'references_updated' => $result['replaced'],
                'message' => sprintf(__('References updated: %d', 'msh-image-optimizer'), $result['replaced'])
            ];
            $success_count++;
        }

        // Check if there are more files to process
        $has_more = false;
        $remaining_count = 0;

        if (count($image_ids) === 20 && $mode !== 'test') {
            // Check for more files with suggestions
            global $wpdb;
            $all_ids = $wpdb->get_col("
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_msh_suggested_filename'
                AND meta_value != ''
            ");
            $remaining_count = count($all_ids) - 20;
            $has_more = $remaining_count > 0;
        }

        // Calculate final statistics
        $success_rate = $processed > 0 ? round(($success_count / $processed) * 100, 1) : 0;
        $end_time = microtime(true);
        $total_duration = round(($end_time - $batch_start_time), 2);
        $avg_per_file = $processed > 0 ? round($total_duration / $processed, 2) : 0;

        error_log("MSH Safe Rename: ===============================================");
        error_log("MSH Safe Rename: 🎉 BATCH RENAME PROCESS COMPLETE!");
        error_log("MSH Safe Rename: ===============================================");
        error_log("MSH Safe Rename: 📊 FINAL STATISTICS:");
        error_log("MSH Safe Rename: 📊   Total files processed: $processed");
        error_log("MSH Safe Rename: 📊   ✅ Successful renames: $success_count");
        error_log("MSH Safe Rename: 📊   ❌ Failed renames: $error_count");
        error_log("MSH Safe Rename: 📊   ⚠️ Skipped files: $skipped_count");
        if ($test_count > 0) {
            error_log("MSH Safe Rename: 📊   🧪 Test simulations: $test_count");
        }
        error_log("MSH Safe Rename: 📊   🎯 Success rate: {$success_rate}%");
        error_log("MSH Safe Rename: 📊   ⏱️ Total duration: {$total_duration}s");
        error_log("MSH Safe Rename: 📊   ⚡ Average per file: {$avg_per_file}s");
        error_log("MSH Safe Rename: 📊   🚀 Has more files: " . ($has_more ? 'YES' : 'NO'));
        if ($has_more) {
            error_log("MSH Safe Rename: 📊   📝 Remaining files: $remaining_count");
        }
        error_log("MSH Safe Rename: ===============================================");

        // Calculate if there are more batches to process
        $has_more_batches = $batch_number < $total_batches;
        $next_batch_number = $has_more_batches ? $batch_number + 1 : 0;

        wp_send_json_success([
            'results' => $results,
            'summary' => [
                'total' => $batch_file_count,
                'success' => $success_count,
                'errors' => $error_count,
                'skipped' => $skipped_count,
                'test' => $test_count,
                'mode' => $mode,
                'batch_complete' => true
            ],
            'batch_info' => [
                'current_batch' => $batch_number,
                'total_batches' => $total_batches,
                'has_more_batches' => $has_more_batches,
                'next_batch_number' => $next_batch_number,
                'batch_size' => $batch_size,
                'total_files' => $total_count,
                'files_processed_so_far' => (($batch_number - 1) * $batch_size) + $processed,
                'overall_progress' => round(((($batch_number - 1) * $batch_size) + $processed) / $total_count * 100, 1)
            ],
            'performance' => [
                'duration' => $total_duration,
                'avg_per_file' => $avg_per_file
            ]
        ]);
    }
    
    /**
     * AJAX handler to save individual filename suggestion
     */
    public function ajax_save_filename_suggestion() {
        check_ajax_referer('msh_image_optimizer', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $image_id = intval($_POST['image_id'] ?? 0);
        $suggested_filename = sanitize_file_name($_POST['suggested_filename'] ?? '');
        
        if (!$image_id || !$suggested_filename) {
            wp_send_json_error('Missing image ID or filename suggestion');
            return;
        }
        
        // Validate the attachment exists
        if (!get_post($image_id) || get_post_type($image_id) !== 'attachment') {
            wp_send_json_error('Invalid attachment ID');
            return;
        }
        
        // Ensure the filename has an extension
        if (pathinfo($suggested_filename, PATHINFO_EXTENSION) === '') {
            // Get the original file extension
            $current_file = get_attached_file($image_id);
            if ($current_file) {
                $original_extension = pathinfo($current_file, PATHINFO_EXTENSION);
                $suggested_filename .= '.' . $original_extension;
            } else {
                $suggested_filename .= '.jpg'; // Default fallback
            }
        }
        
        // Save the suggestion with timestamp
        update_post_meta($image_id, '_msh_suggested_filename', $suggested_filename);
        update_post_meta($image_id, 'msh_filename_last_suggested', (int)time());
        
        wp_send_json_success([
            'message' => 'Filename suggestion saved successfully',
            'image_id' => $image_id,
            'suggested_filename' => $suggested_filename
        ]);
    }
    
    /**
     * AJAX handler to remove filename suggestion (keep current name)
     */
    public function ajax_remove_filename_suggestion() {
        check_ajax_referer('msh_image_optimizer', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $image_id = intval($_POST['image_id'] ?? 0);
        
        if (!$image_id) {
            wp_send_json_error('Missing image ID');
            return;
        }
        
        // Validate the attachment exists
        if (!get_post($image_id) || get_post_type($image_id) !== 'attachment') {
            wp_send_json_error('Invalid attachment ID');
            return;
        }
        
        // Remove the suggestion
        delete_post_meta($image_id, '_msh_suggested_filename');
        
        wp_send_json_success([
            'message' => 'Filename suggestion removed - current name will be kept',
            'image_id' => $image_id
        ]);
    }
    
    /**
     * AJAX handler to preview meta text generation
     */
    public function ajax_preview_meta_text() {
        check_ajax_referer('msh_image_optimizer', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $image_id = intval($_POST['image_id'] ?? 0);
        
        if (!$image_id) {
            wp_send_json_error('Missing image ID');
            return;
        }
        
        // Validate the attachment exists
        if (!get_post($image_id) || get_post_type($image_id) !== 'attachment') {
            wp_send_json_error('Invalid attachment ID');
            return;
        }
        
        // Generate meta text preview using the same logic as optimization
        $context = $this->determine_image_context($image_id);
        
        $preview = [
            'title' => $this->generate_title($image_id, $context),
            'caption' => $this->generate_caption($image_id, $context),
            'alt_text' => $this->generate_alt_text($image_id, $context),
            'description' => $this->generate_description($image_id, $context)
        ];
        
        wp_send_json_success($preview);
    }
    
    /**
     * AJAX handler to save edited meta text
     */
    public function ajax_save_edited_meta() {
        check_ajax_referer('msh_image_optimizer', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $image_id = intval($_POST['image_id'] ?? 0);
        $meta_data = wp_unslash($_POST['meta_data'] ?? []);
        
        if (!$image_id || empty($meta_data)) {
            wp_send_json_error('Missing image ID or meta data');
            return;
        }
        
        // Validate the attachment exists
        if (!get_post($image_id) || get_post_type($image_id) !== 'attachment') {
            wp_send_json_error('Invalid attachment ID');
            return;
        }
        
        $updates_made = [];
        
        // Update Title
        if (!empty($meta_data['title'])) {
            wp_update_post([
                'ID' => $image_id,
                'post_title' => sanitize_text_field($meta_data['title']),
                'post_name' => sanitize_title($meta_data['title'])
            ]);
            $updates_made[] = 'title';
        }
        
        // Update Caption
        if (!empty($meta_data['caption'])) {
            wp_update_post([
                'ID' => $image_id,
                'post_excerpt' => sanitize_textarea_field($meta_data['caption'])
            ]);
            $updates_made[] = 'caption';
        }
        
        // Update ALT text
        if (!empty($meta_data['alt_text'])) {
            update_post_meta($image_id, '_wp_attachment_image_alt', sanitize_text_field($meta_data['alt_text']));
            $updates_made[] = 'alt_text';
        }
        
        // Update Description
        if (!empty($meta_data['description'])) {
            wp_update_post([
                'ID' => $image_id,
                'post_content' => sanitize_textarea_field($meta_data['description'])
            ]);
            $updates_made[] = 'description';
        }
        
        // Update metadata timestamp to reflect manual edit
        update_post_meta($image_id, 'msh_metadata_last_updated', (int)time());
        update_post_meta($image_id, 'msh_metadata_source', 'manual_edit');
        
        wp_send_json_success([
            'message' => 'Meta text updated successfully',
            'updates_made' => $updates_made,
            'image_id' => $image_id
        ]);
    }

    /**
     * Clear bad suggestions (missing extensions or for already-renamed files)
     */
    public function ajax_clear_bad_suggestions() {
        check_ajax_referer('msh_image_optimizer', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        global $wpdb;

        // NUCLEAR OPTION: Remove ALL suggestions to start fresh
        $total_count = $wpdb->query("
            DELETE FROM {$wpdb->postmeta}
            WHERE meta_key = '_msh_suggested_filename'
        ");

        wp_send_json_success([
            'message' => "Cleared ALL {$total_count} filename suggestions. Run 'Analyze Published Images' to regenerate.",
            'total_removed' => $total_count
        ]);
    }

    /**
     * Auto-generate filename suggestion when new image is uploaded
     */
    public function generate_suggestion_for_new_upload($attachment_id) {
        // Only process images
        if (!wp_attachment_is_image($attachment_id)) {
            return;
        }

        // Get current filename and extension
        $current_file = get_attached_file($attachment_id);
        if (!$current_file) {
            return;
        }

        $path_info = pathinfo($current_file);
        $extension = isset($path_info['extension']) ? strtolower($path_info['extension']) : '';
        $current_basename = strtolower($path_info['basename']);

        // Skip if already has an SEO-optimized name
        $has_good_name = (strpos($current_basename, 'msh') !== false ||
                         strpos($current_basename, 'hamilton') !== false ||
                         strpos($current_basename, 'main-street-health') !== false ||
                         // Also detect common SEO patterns our system generates
                         preg_match('/^(rehabilitation|physiotherapy|chiropractic|acupuncture|massage|orthotics|chronic-pain|work-related|sport-injuries|motor-vehicle|patient-testimonial|bluecross|canada-life|manulife)-/', $current_basename) ||
                         // Or files that end with attachment ID pattern (our system's signature)
                         preg_match('/-\d{4,5}\.(jpg|jpeg|png|gif|svg|webp)$/', $current_basename));

        if ($has_good_name || empty($extension)) {
            return; // Don't generate suggestions for good files or files without extensions
        }

        // Skip if already has a suggestion
        $existing_suggestion = get_post_meta($attachment_id, '_msh_suggested_filename', true);
        if (!empty($existing_suggestion)) {
            return;
        }

        // Auto-detect context from filename and path
        $context_details = $this->contextual_meta_generator->detect_context($attachment_id);

        // Generate suggestion using the contextual generator
        $slug = $this->contextual_meta_generator->generate_filename_slug($attachment_id, $context_details, $extension);

        if (!empty($slug)) {
            $suggested_filename = $this->ensure_unique_filename($slug, $extension, $attachment_id);
            update_post_meta($attachment_id, '_msh_suggested_filename', $suggested_filename);
            update_post_meta($attachment_id, 'msh_filename_last_suggested', time());

            // Log for debugging
            error_log("MSH Auto-Suggestion: Generated '{$suggested_filename}' for new upload '{$current_basename}' (ID: {$attachment_id})");
        }
    }

    /**
     * AJAX handler to build the image usage index
     */
    public function ajax_build_usage_index() {
        check_ajax_referer('msh_image_optimizer', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        @set_time_limit(900); // 15 minutes for complete rebuild
        @ini_set('memory_limit', '512M');

        // Create tables first
        $this->ensure_safe_rename_tables();

        // Get usage index instance
        $usage_index = MSH_Image_Usage_Index::get_instance();

        // Check if force rebuild is requested
        $force_rebuild = !empty($_POST['force_rebuild']) && $_POST['force_rebuild'] === 'true';

        // Check current index status first
        $stats = $usage_index->get_index_stats();
        $summary_payload = $this->format_index_summary($stats);
        $current_entries = isset($summary_payload['total_entries']) ? (int) $summary_payload['total_entries'] : 0;

        if ($current_entries > 0 && !$force_rebuild) {
            // Index already has data, return current stats (unless force rebuild)
            wp_send_json_success([
                'message' => 'Usage index already exists with ' . $current_entries . ' entries.',
                'processed_attachments' => $summary_payload['indexed_attachments'] ?? 0,
                'stats' => array(
                    'summary' => $summary_payload,
                    'by_context' => $stats['by_context'] ?? array(),
                ),
                'status' => 'already_built'
            ]);
            return;
        }

        if ($force_rebuild) {
            error_log('MSH Index Build: Force rebuild requested - clearing existing index');
            // Clear the existing index for complete rebuild
            global $wpdb;
            $table_name = $wpdb->prefix . 'msh_image_usage_index';
            $wpdb->query("TRUNCATE TABLE $table_name");
            error_log('MSH Index Build: Existing index cleared');
        }

        // Build the index with small batch size for better performance
        error_log('MSH Index Build: Starting index build process');
        $processed = $usage_index->build_complete_index(25); // Small batches

        // Get final stats
        $final_stats = $usage_index->get_index_stats();
        $final_summary = $this->format_index_summary($final_stats);

        // Mark system as ready
        update_option('msh_safe_rename_enabled', '1');

        wp_send_json_success([
            'message' => 'Usage index built successfully! Processed ' . $processed . ' attachments.',
            'processed_attachments' => (int) $processed,
            'stats' => array(
                'summary' => $final_summary,
                'by_context' => $final_stats['by_context'] ?? array(),
            ),
            'status' => 'newly_built'
        ]);
    }

    private function ensure_safe_rename_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Create tables if they don't exist
        $tables = [
            'wp_msh_image_usage_index' => "CREATE TABLE IF NOT EXISTS wp_msh_image_usage_index (
                id int(11) NOT NULL AUTO_INCREMENT,
                attachment_id int(11) NOT NULL,
                url_variation text NOT NULL,
                table_name varchar(64) NOT NULL,
                row_id int(11) NOT NULL,
                column_name varchar(64) NOT NULL,
                context_type varchar(50) DEFAULT 'content',
                post_type varchar(20) DEFAULT NULL,
                last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY attachment_id (attachment_id),
                KEY table_row (table_name, row_id),
                KEY url_variation (url_variation(191)),
                KEY context_type (context_type)
            ) $charset_collate",
            'wp_msh_rename_backups' => "CREATE TABLE IF NOT EXISTS wp_msh_rename_backups (
                id int(11) NOT NULL AUTO_INCREMENT,
                operation_id varchar(32) NOT NULL,
                attachment_id int(11) NOT NULL,
                table_name varchar(64) NOT NULL,
                row_id int(11) NOT NULL,
                column_name varchar(64) NOT NULL,
                original_value longtext NOT NULL,
                backup_date datetime DEFAULT CURRENT_TIMESTAMP,
                status varchar(20) DEFAULT 'active',
                PRIMARY KEY (id),
                KEY operation_id (operation_id),
                KEY attachment_id (attachment_id),
                KEY backup_date (backup_date)
            ) $charset_collate",
            'wp_msh_rename_verification' => "CREATE TABLE IF NOT EXISTS wp_msh_rename_verification (
                id int(11) NOT NULL AUTO_INCREMENT,
                operation_id varchar(32) NOT NULL,
                attachment_id int(11) NOT NULL,
                check_type varchar(50) NOT NULL,
                expected_value text NOT NULL,
                actual_value text NOT NULL,
                status varchar(20) NOT NULL,
                error_message text NULL,
                check_date datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY operation_id (operation_id),
                KEY attachment_id (attachment_id),
                KEY status (status)
            ) $charset_collate"
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ($tables as $table_name => $sql) {
            dbDelta($sql);
        }

        // Set options to mark tables as created
        update_option('msh_usage_index_table_version', '1');
        update_option('msh_backup_tables_version', '1');
    }

    private function format_index_summary($stats) {
        if (empty($stats)) {
            return null;
        }

        $usage_index = MSH_Image_Usage_Index::get_instance();

        if (is_array($stats)) {
            $formatted = $usage_index->format_stats_for_ui($stats);
            if ($formatted !== null) {
                return $formatted;
            }
            $summary = $stats['summary'] ?? null;
        } else {
            $summary = $stats;
        }

        if (!$summary) {
            return null;
        }

        $base = array(
            'total_entries'       => isset($summary->total_entries) ? (int) $summary->total_entries : 0,
            'indexed_attachments' => isset($summary->indexed_attachments) ? (int) $summary->indexed_attachments : 0,
            'unique_locations'    => isset($summary->unique_locations) ? (int) $summary->unique_locations : 0,
            'last_update_raw'     => $summary->last_update,
            'last_update_display' => $summary->last_update ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $summary->last_update, false) : null,
            'by_context'          => array(),
            'orphaned_entries'    => 0,
            'orphan_preview'      => array(),
        );

        return $base;
    }

    /**
     * Get attachment count for UI display
     */
    public function ajax_get_attachment_count() {
        check_ajax_referer('msh_image_optimizer', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        global $wpdb;

        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
             AND post_mime_type LIKE 'image/%'"
        );

        wp_send_json_success([
            'count' => (int) $count
        ]);
    }

    /**
     * Get remaining unindexed attachment count
     */
    public function ajax_get_remaining_count() {
        check_ajax_referer('msh_image_optimizer', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        global $wpdb;

        // Get total attachments
        $total = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
             AND post_mime_type LIKE 'image/%'"
        );

        // Get already indexed count
        $indexed = $wpdb->get_var(
            "SELECT COUNT(DISTINCT attachment_id)
             FROM {$wpdb->prefix}msh_image_usage_index"
        );

        $remaining = max(0, $total - $indexed);

        wp_send_json_success([
            'total' => (int) $total,
            'indexed' => (int) $indexed,
            'remaining' => (int) $remaining,
            'percent_complete' => $total > 0 ? round(($indexed / $total) * 100, 1) : 0
        ]);
    }

    /**
     * Convert image to WebP format
     */
    private function convert_to_webp($source_path, $webp_path) {
        if (!function_exists('imagewebp')) {
            return false; // WebP not supported
        }

        // Get image info
        $image_info = getimagesize($source_path);
        if (!$image_info) {
            return false;
        }

        $mime_type = $image_info['mime'];
        $image = null;

        // Create image resource based on type
        switch ($mime_type) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($source_path);
                // Preserve transparency
                imagealphablending($image, false);
                imagesavealpha($image, true);
                break;
            default:
                return false;
        }

        if (!$image) {
            return false;
        }

        // Convert to WebP with 85% quality (good balance of quality/size)
        $result = imagewebp($image, $webp_path, 85);

        // Clean up memory
        imagedestroy($image);

        return $result;
    }

    public function ajax_toggle_file_rename() {
        check_ajax_referer('msh_toggle_file_rename', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'msh-image-optimizer')], 403);
        }

        $enabled = isset($_POST['enabled']) && (string) $_POST['enabled'] === '1' ? '1' : '0';
        update_option('msh_enable_file_rename', $enabled);

        wp_send_json_success([
            'enabled' => $enabled
        ]);
    }

    /**
     * AJAX handler to accept and apply a filename suggestion for a single image
     */
    public function ajax_accept_filename_suggestion() {
        error_log("MSH Accept Debug: Starting ajax_accept_filename_suggestion");

        check_ajax_referer('msh_image_optimizer', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $image_id = intval($_POST['image_id'] ?? 0);
        error_log("MSH Accept Debug: Image ID = $image_id");

        if (!$image_id) {
            wp_send_json_error('Missing image ID');
            return;
        }

        // Validate the attachment exists
        if (!get_post($image_id) || get_post_type($image_id) !== 'attachment') {
            wp_send_json_error('Invalid attachment ID');
            return;
        }

        // Get the suggested filename
        $suggested_filename = get_post_meta($image_id, '_msh_suggested_filename', true);

        if (!$suggested_filename) {
            wp_send_json_error('No filename suggestion found for this image');
            return;
        }

        error_log("MSH Accept Debug: Using Safe Rename System for individual apply");

        // Use the same Safe Rename System as the batch process
        try {
            if (!class_exists('MSH_Safe_Rename_System')) {
                wp_send_json_error('Safe Rename System not available');
                return;
            }

            $renamer = MSH_Safe_Rename_System::get_instance();
            if (!$renamer) {
                wp_send_json_error('Failed to get Safe Rename System instance');
                return;
            }

            // Build a simple array with just this image for processing
            $image_data = [];
            $attachment_post = get_post($image_id);
            if ($attachment_post) {
                $current_file = get_attached_file($image_id);
                $filename = $current_file ? basename($current_file) : '';

                $image_data[] = [
                    'ID' => $image_id,
                    'filename' => $filename,
                    'suggested_filename' => $suggested_filename
                ];
            }

            if (empty($image_data)) {
                wp_send_json_error('Failed to prepare image data for renaming');
                return;
            }

            // Use the same processing logic as batch apply
            $results = $renamer->process_batch_renames($image_data);

            if (!empty($results['success'])) {
                $success = $results['success'][0]; // Get first (and only) success result
                wp_send_json_success([
                    'message' => 'Filename updated successfully using Safe Rename System',
                    'new_filename' => $success['new_filename'] ?? $suggested_filename,
                    'image_id' => $image_id,
                    'database_updates' => $success['database_updates'] ?? 0
                ]);
            } elseif (!empty($results['errors'])) {
                $error = $results['errors'][0]; // Get first error
                wp_send_json_error('Safe Rename failed: ' . ($error['message'] ?? 'Unknown error'));
            } else {
                wp_send_json_error('Safe Rename System returned no results');
            }

        } catch (Exception $e) {
            error_log("MSH Accept Debug: Exception in Safe Rename System: " . $e->getMessage());
            wp_send_json_error('Safe Rename System error: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler to reject/dismiss a filename suggestion for a single image
     */
    public function ajax_reject_filename_suggestion() {
        check_ajax_referer('msh_image_optimizer', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $image_id = intval($_POST['image_id'] ?? 0);

        if (!$image_id) {
            wp_send_json_error('Missing image ID');
            return;
        }

        // Validate the attachment exists
        if (!get_post($image_id) || get_post_type($image_id) !== 'attachment') {
            wp_send_json_error('Invalid attachment ID');
            return;
        }

        // Remove the suggestion (same as existing ajax_remove_filename_suggestion)
        delete_post_meta($image_id, '_msh_suggested_filename');

        wp_send_json_success([
            'message' => 'Filename suggestion dismissed - current name will be kept',
            'image_id' => $image_id
        ]);
    }

    /**
     * AJAX: Verify WebP status for all optimized images
     */
    public function ajax_verify_webp_status() {
        // Debug logging removed for production

        if (!current_user_can('manage_options')) {
            // Debug logging removed for production
            wp_send_json_error('Insufficient permissions');
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'], 'msh_image_optimizer')) {
            // Debug logging removed for production
            wp_send_json_error('Invalid nonce');
            return;
        }

        try {
            // Debug logging removed for production
            $verification_results = $this->perform_webp_verification();
            // Debug logging removed for production
            wp_send_json_success($verification_results);
        } catch (Exception $e) {
            // Debug logging removed for production
            // Debug logging removed for production
            wp_send_json_error('Verification failed: ' . $e->getMessage());
        } catch (Error $e) {
            // Debug logging removed for production
            // Debug logging removed for production
            wp_send_json_error('Fatal error during verification: ' . $e->getMessage());
        }
    }

    /**
     * Perform comprehensive WebP verification audit
     */
    private function perform_webp_verification() {
        // Debug logging removed for production

        try {
            $published_images = $this->get_published_images();
            // Debug logging removed for production
        } catch (Exception $e) {
            // Debug logging removed for production
            throw $e;
        }

        $results = [
            'total_images' => count($published_images),
            'total_optimized' => 0,
            'webp_compatible_optimized' => 0,
            'webp_missing' => 0,
            'svg_files' => 0,
            'issues_found' => [],
            'summary' => '',
            'stats' => []
        ];

        foreach ($published_images as $image) {
            $attachment_id = $image['ID'];
            $status = $this->get_optimization_status($attachment_id);

            // Only check optimized images
            if ($status !== 'optimized') {
                continue;
            }

            $results['total_optimized']++;

            // Get file extension
            $file_path = $image['file_path'] ?? '';
            $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

            // Debug logging removed for production

            // Handle SVG files separately
            if ($file_extension === 'svg') {
                $results['svg_files']++;
                continue;
            }

            // For WebP-compatible formats (JPG, JPEG, PNG)
            if (in_array($file_extension, ['jpg', 'jpeg', 'png'])) {
                $results['webp_compatible_optimized']++;

                // Check if WebP version exists
                if (!empty($file_path) && file_exists($file_path)) {
                    $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $file_path);

                    if (!file_exists($webp_path)) {
                        $results['webp_missing']++;
                        $results['issues_found'][] = [
                            'attachment_id' => $attachment_id,
                            'filename' => basename($file_path),
                            'issue' => 'webp_missing',
                            'message' => 'Optimized but missing WebP version'
                        ];

                        // Debug logging removed for production
                    } else {
                        // Debug logging removed for production
                    }
                } else {
                    $results['issues_found'][] = [
                        'attachment_id' => $attachment_id,
                        'filename' => 'File missing',
                        'issue' => 'source_missing',
                        'message' => 'Source file missing from disk'
                    ];
                }
            }
        }

        // Calculate stats
        $webp_success_rate = $results['webp_compatible_optimized'] > 0
            ? (($results['webp_compatible_optimized'] - $results['webp_missing']) / $results['webp_compatible_optimized']) * 100
            : 100;

        // Generate summary
        $results['summary'] = sprintf(
            "WebP Verification Complete: %d optimized images checked. " .
            "%d WebP-compatible images found, %d missing WebP versions (%.1f%% success rate). " .
            "%d SVG files (WebP not needed).",
            $results['total_optimized'],
            $results['webp_compatible_optimized'],
            $results['webp_missing'],
            $webp_success_rate,
            $results['svg_files']
        );

        $results['stats'] = [
            'webp_success_rate' => round($webp_success_rate, 1),
            'issues_count' => count($results['issues_found'])
        ];

        // Debug logging removed for production

        return $results;
    }

    public function optimize_attachments_cli(array $attachment_ids, array $args = []) {
        $summary = [
            'processed' => 0,
            'optimized' => [],
            'errors' => [],
            'skipped' => []
        ];

        foreach ($attachment_ids as $attachment_id) {
            $attachment_id = intval($attachment_id);
            if ($attachment_id <= 0) {
                $summary['errors'][] = [
                    'id' => $attachment_id,
                    'message' => __('Invalid attachment ID.', 'msh-image-optimizer')
                ];
                continue;
            }

            $result = $this->optimize_single_image($attachment_id);

            if ($result['status'] === 'error') {
                $summary['errors'][] = [
                    'id' => $attachment_id,
                    'message' => implode('; ', $result['actions'])
                ];
                continue;
            }

            $webp_path = $this->get_webp_path_for_attachment($attachment_id);
            $webp_exists = $webp_path ? file_exists($webp_path) : false;

            if (!empty($result['meta_applied']) || $webp_exists) {
                $summary['optimized'][] = [
                    'id' => $attachment_id,
                    'status' => $result['status'],
                    'meta_updated' => array_keys($result['meta_applied']),
                    'webp_generated' => $webp_exists
                ];
            } else {
                $summary['skipped'][] = [
                    'id' => $attachment_id,
                    'message' => implode('; ', $result['actions'])
                ];
            }

            $summary['processed']++;
        }

        if ($summary['processed'] > 0) {
            update_option('msh_last_cli_optimization', current_time('mysql'));
        }

        return $summary;
    }

    private function get_webp_path_for_attachment($attachment_id) {
        $file_path = get_attached_file($attachment_id);
        if (!$file_path) {
            return '';
        }

        if (!preg_match('/\.(jpg|jpeg|png)$/i', $file_path)) {
            return '';
        }

        return preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $file_path);
    }
}

// Initialize the optimizer
MSH_Image_Optimizer::get_instance();
