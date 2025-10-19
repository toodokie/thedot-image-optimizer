<?php
/**
 * Locale Profiles Admin Page
 *
 * Visual interface for managing locale-specific AI prompt profiles.
 *
 * @package    MSH_Image_Optimizer
 * @subpackage Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get locale manager
$locale_manager = MSH_Locale_Profile_Manager::get_instance();
$site_locale    = get_locale();

// Handle form submissions
if ( isset( $_POST['msh_save_locale_profile'] ) && check_admin_referer( 'msh_locale_profile' ) ) {
	$locale = sanitize_text_field( $_POST['locale'] ?? $site_locale );

	$profile_data = array(
		'tone'                  => sanitize_text_field( $_POST['tone'] ?? 'professional' ),
		'formality_level'       => intval( $_POST['formality_level'] ?? 3 ),
		'cta_style'             => sanitize_text_field( $_POST['cta_style'] ?? 'subtle' ),
		'special_instructions'  => sanitize_textarea_field( $_POST['special_instructions'] ?? '' ),
		'forbidden_terms'       => sanitize_textarea_field( $_POST['forbidden_terms'] ?? '' ),
		'confidence_threshold'  => floatval( $_POST['confidence_threshold'] ?? 0.7 ),
	);

	$result = $locale_manager->set_profile( $locale, $profile_data );

	if ( $result ) {
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Locale profile saved successfully.', 'msh-image-optimizer' ) . '</p></div>';
	} else {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Error saving locale profile.', 'msh-image-optimizer' ) . '</p></div>';
	}
}

// Get current profile
$current_profile = $locale_manager->get_profile( $site_locale ) ?: array(
	'tone'                 => 'professional',
	'formality_level'      => 3,
	'cta_style'            => 'subtle',
	'special_instructions' => '',
	'forbidden_terms'      => '',
	'confidence_threshold' => 0.7,
);

// Enqueue brand fonts
wp_enqueue_style( 'msh-brand-fonts', 'https://use.typekit.net/gac6jnd.css', array(), null );
?>

<link rel="stylesheet" href="<?php echo esc_url( MSH_IO_ASSETS_URL . 'css/image-optimizer-settings.css' ); ?>">

<style>
/* Additional overrides for locale profiles page */
#wpcontent, #wpbody, #wpbody-content {
	background-color: #FAF9F6;
}

.msh-locale-wrap {
	background-color: #FAF9F6;
	font-family: 'ff-real-text-pro', Arial, sans-serif;
	color: #35332f;
	padding: 20px;
	max-width: 1200px;
}

.msh-locale-wrap h1,
.msh-locale-wrap h2 {
	font-family: 'futura-pt', Arial, sans-serif !important;
	text-transform: uppercase !important;
	letter-spacing: 0.08em !important;
	font-weight: 400 !important;
	color: #35332f !important;
	margin-bottom: 24px;
}

.form-table input[type="range"] {
	width: 200px;
	accent-color: #35332f;
}

.form-table textarea {
	width: 100%;
	max-width: 600px;
	min-height: 100px;
	border: 1px solid #ccc;
	border-radius: 6px;
	padding: 12px 16px;
	font-family: 'ff-real-text-pro', Arial, sans-serif;
	font-size: 14px;
	color: #35332f;
	background-color: #fff;
	resize: vertical;
}

.form-table textarea:hover {
	border-color: #daff00;
	box-shadow: 0 0 8px rgba(218, 255, 0, 0.3);
}

.form-table textarea:focus {
	outline: none !important;
	border-color: #35332f !important;
	box-shadow: 0 0 0 4px rgba(53, 51, 47, 0.15) !important;
}

.form-table input[type="number"] {
	width: 120px;
	height: 44px;
	border: 1px solid #ccc;
	border-radius: 6px;
	padding: 0 16px;
	font-family: 'futura-pt', Arial, sans-serif;
	font-size: 14px;
	color: #35332f;
	background-color: #fff;
}

.form-table input[type="number"]:hover {
	border-color: #daff00;
	box-shadow: 0 0 8px rgba(218, 255, 0, 0.3);
}

.form-table input[type="number"]:focus {
	outline: none !important;
	border-color: #35332f !important;
	box-shadow: 0 0 0 4px rgba(53, 51, 47, 0.15) !important;
}
</style>

<div class="wrap msh-settings-wrap msh-locale-wrap">
	<h1><?php esc_html_e( 'Locale Profiles', 'msh-image-optimizer' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Configure locale-specific settings for AI-generated metadata. These settings control tone, formality, and cultural adaptation for each language.', 'msh-image-optimizer' ); ?>
	</p>

	<div class="msh-settings-card">
		<form method="post" action="">
			<?php wp_nonce_field( 'msh_locale_profile' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="locale"><?php esc_html_e( 'Locale', 'msh-image-optimizer' ); ?></label>
					</th>
					<td>
						<input type="text" id="locale" name="locale" value="<?php echo esc_attr( $site_locale ); ?>" />
						<p class="description"><?php esc_html_e( 'Locale code (e.g., en_US, fr_FR, es_ES)', 'msh-image-optimizer' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="tone"><?php esc_html_e( 'Tone', 'msh-image-optimizer' ); ?></label>
					</th>
					<td>
						<select id="tone" name="tone">
							<option value="professional" <?php selected( $current_profile['tone'], 'professional' ); ?>><?php esc_html_e( 'Professional', 'msh-image-optimizer' ); ?></option>
							<option value="friendly" <?php selected( $current_profile['tone'], 'friendly' ); ?>><?php esc_html_e( 'Friendly', 'msh-image-optimizer' ); ?></option>
							<option value="casual" <?php selected( $current_profile['tone'], 'casual' ); ?>><?php esc_html_e( 'Casual', 'msh-image-optimizer' ); ?></option>
							<option value="formal" <?php selected( $current_profile['tone'], 'formal' ); ?>><?php esc_html_e( 'Formal', 'msh-image-optimizer' ); ?></option>
							<option value="technical" <?php selected( $current_profile['tone'], 'technical' ); ?>><?php esc_html_e( 'Technical', 'msh-image-optimizer' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Overall communication style for metadata', 'msh-image-optimizer' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="formality_level"><?php esc_html_e( 'Formality Level', 'msh-image-optimizer' ); ?></label>
					</th>
					<td>
						<input type="range" id="formality_level" name="formality_level" min="1" max="5" value="<?php echo esc_attr( $current_profile['formality_level'] ); ?>" />
						<span id="formality_value"><?php echo esc_html( $current_profile['formality_level'] ); ?></span> / 5
						<p class="description"><?php esc_html_e( '1 = Very casual, 5 = Very formal', 'msh-image-optimizer' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="cta_style"><?php esc_html_e( 'Call-to-Action Style', 'msh-image-optimizer' ); ?></label>
					</th>
					<td>
						<select id="cta_style" name="cta_style">
							<option value="subtle" <?php selected( $current_profile['cta_style'], 'subtle' ); ?>><?php esc_html_e( 'Subtle', 'msh-image-optimizer' ); ?></option>
							<option value="moderate" <?php selected( $current_profile['cta_style'], 'moderate' ); ?>><?php esc_html_e( 'Moderate', 'msh-image-optimizer' ); ?></option>
							<option value="strong" <?php selected( $current_profile['cta_style'], 'strong' ); ?>><?php esc_html_e( 'Strong', 'msh-image-optimizer' ); ?></option>
							<option value="none" <?php selected( $current_profile['cta_style'], 'none' ); ?>><?php esc_html_e( 'None', 'msh-image-optimizer' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'How assertive should call-to-action language be?', 'msh-image-optimizer' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="special_instructions"><?php esc_html_e( 'Special Instructions', 'msh-image-optimizer' ); ?></label>
					</th>
					<td>
						<textarea id="special_instructions" name="special_instructions" rows="4"><?php echo esc_textarea( $current_profile['special_instructions'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Custom guidelines for this locale (e.g., "Avoid idioms", "Use metric units")', 'msh-image-optimizer' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="forbidden_terms"><?php esc_html_e( 'Forbidden Terms', 'msh-image-optimizer' ); ?></label>
					</th>
					<td>
						<textarea id="forbidden_terms" name="forbidden_terms" rows="3"><?php echo esc_textarea( $current_profile['forbidden_terms'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One term per line. These words will be flagged in metadata validation.', 'msh-image-optimizer' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="confidence_threshold"><?php esc_html_e( 'Confidence Threshold', 'msh-image-optimizer' ); ?></label>
					</th>
					<td>
						<input type="number" id="confidence_threshold" name="confidence_threshold" min="0" max="1" step="0.05" value="<?php echo esc_attr( $current_profile['confidence_threshold'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Minimum AI confidence level (0.0 - 1.0). Lower = more suggestions.', 'msh-image-optimizer' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" name="msh_save_locale_profile" class="button button-primary button-dot-primary" value="<?php esc_attr_e( 'Save Locale Profile', 'msh-image-optimizer' ); ?>" />
			</p>
		</form>
	</div>

	<!-- WP-CLI Reference -->
	<div class="msh-cli-reference msh-settings-card">
		<h2><?php esc_html_e( 'WP-CLI Commands', 'msh-image-optimizer' ); ?></h2>
		<p><?php esc_html_e( 'You can also manage locale profiles via WP-CLI:', 'msh-image-optimizer' ); ?></p>
		<pre><code>wp msh locale profile_list
wp msh locale profile_show --locale=<?php echo esc_html( $site_locale ); ?>

wp msh locale test_prompt --media-id=123 --locale=<?php echo esc_html( $site_locale ); ?>
</code></pre>
	</div>
</div>

<script>
// Update formality level display
document.getElementById('formality_level').addEventListener('input', function() {
	document.getElementById('formality_value').textContent = this.value;
});
</script>
