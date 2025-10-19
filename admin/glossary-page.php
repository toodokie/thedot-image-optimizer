<?php
/**
 * Glossary Manager Admin Page
 *
 * Visual interface for managing protected terms and translations.
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

// Handle delete action
if ( isset( $_POST['msh_delete_glossary_term'] ) && check_admin_referer( 'msh_delete_glossary' ) ) {
	$entry_id = intval( $_POST['entry_id'] );
	$result   = $locale_manager->delete_glossary_entry( $entry_id );

	if ( $result ) {
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Glossary term deleted.', 'msh-image-optimizer' ) . '</p></div>';
	} else {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to delete glossary term.', 'msh-image-optimizer' ) . '</p></div>';
	}
}

// Handle add/edit action
if ( isset( $_POST['msh_save_glossary_term'] ) && check_admin_referer( 'msh_save_glossary' ) ) {
	$locale      = sanitize_text_field( $_POST['locale'] ?? $site_locale );
	$term        = sanitize_text_field( $_POST['term'] ?? '' );
	$translation = sanitize_text_field( $_POST['translation'] ?? '' );
	$category    = sanitize_text_field( $_POST['category'] ?? 'general' );
	$protected   = isset( $_POST['protected'] ) ? 1 : 0;

	if ( ! empty( $term ) ) {
		$glossary_data = array(
			'term'        => $term,
			'translation' => $translation,
			'category'    => $category,
			'protected'   => $protected,
		);

		$result = $locale_manager->add_glossary_entry( $locale, $glossary_data );

		if ( $result ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Glossary term saved successfully.', 'msh-image-optimizer' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Term may already exist in glossary.', 'msh-image-optimizer' ) . '</p></div>';
		}
	}
}

// Get glossary terms for current locale
$glossary_terms = $locale_manager->get_glossary_entries( $site_locale );

// Enqueue brand fonts
wp_enqueue_style( 'msh-brand-fonts', 'https://use.typekit.net/gac6jnd.css', array(), null );
?>

<style>
/* Brand-compliant styling - matches image-optimizer-settings.css */
#wpcontent, #wpbody, #wpbody-content {
	background-color: #FAF9F6;
}

.msh-glossary-wrap {
	background-color: #FAF9F6;
	font-family: 'ff-real-text-pro', Arial, sans-serif;
	color: #35332f;
	padding: 20px;
	max-width: 1200px;
}

.msh-glossary-wrap h1,
.msh-glossary-wrap h2 {
	font-family: 'futura-pt', Arial, sans-serif !important;
	text-transform: uppercase !important;
	letter-spacing: 0.08em !important;
	font-weight: 400 !important;
	color: #35332f !important;
	margin-top: 0;
	margin-bottom: 24px;
}

.msh-glossary-wrap h1 {
	font-size: 24px;
}

.msh-glossary-wrap h2 {
	font-size: 18px;
	margin-top: 32px;
}

.msh-glossary-wrap .description {
	font-family: 'ff-real-text-pro', Arial, sans-serif;
	font-size: 14px;
	color: #4a4945;
	line-height: 1.6;
	margin-bottom: 32px;
}

.msh-settings-card {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 12px;
	padding: 32px;
	margin-bottom: 32px;
	box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.form-table {
	background: transparent;
}

.form-table th {
	font-family: 'futura-pt', Arial, sans-serif;
	font-size: 14px;
	font-weight: 400;
	color: #35332f;
	letter-spacing: 0.5px;
	padding: 12px 0 12px 0;
	width: 200px;
}

.form-table td {
	padding: 12px 0;
}

.form-table input[type="text"],
.form-table select {
	width: 100%;
	max-width: 400px;
	height: 44px;
	border: 1px solid #ccc;
	border-radius: 6px;
	padding: 0 16px;
	font-family: 'futura-pt', Arial, sans-serif;
	font-size: 14px;
	color: #35332f;
	background-color: #fff;
	transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-table input[type="text"]:hover,
.form-table select:hover {
	border-color: #daff00;
	box-shadow: 0 0 8px rgba(218, 255, 0, 0.3);
}

.form-table input[type="text"]:focus,
.form-table select:focus {
	outline: none !important;
	border-color: #35332f !important;
	box-shadow: 0 0 0 4px rgba(53, 51, 47, 0.15) !important;
}

.form-table .description {
	font-size: 13px;
	color: #4a4945;
	margin-top: 8px;
	font-style: normal;
}

.form-table input[type="checkbox"] {
	width: 22px !important;
	height: 22px !important;
	border: 1px solid #ccc !important;
	border-radius: 4px !important;
	background-color: #fff !important;
	appearance: none !important;
	cursor: pointer !important;
	position: relative !important;
}

.form-table input[type="checkbox"]:checked {
	background-color: #daff00 !important;
	border-color: #35332f !important;
}

.form-table input[type="checkbox"]:checked::after {
	content: "";
	position: absolute;
	width: 6px;
	height: 12px;
	border: 2px solid #35332f;
	border-top: none;
	border-left: none;
	transform: rotate(45deg);
	top: 2px;
	left: 7px;
}

.submit .button-primary {
	font-family: 'futura-pt', Arial, sans-serif !important;
	text-transform: uppercase !important;
	letter-spacing: 2px !important;
	font-weight: 500 !important;
	padding: 14px 32px !important;
	height: auto !important;
	border-radius: 6px !important;
	background-color: #35332f !important;
	color: #faf9f6 !important;
	border: 1px solid #35332f !important;
	transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease !important;
	box-shadow: none !important;
}

.submit .button-primary:hover {
	background-color: #daff00 !important;
	border-color: #35332f !important;
	color: #35332f !important;
}

.wp-list-table {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 8px;
	font-family: 'ff-real-text-pro', Arial, sans-serif;
}

.wp-list-table thead th {
	font-family: 'futura-pt', Arial, sans-serif;
	text-transform: uppercase;
	letter-spacing: 0.08em;
	font-size: 12px;
	font-weight: 400;
	color: #35332f;
	background: #faf9f6;
	border-bottom: 2px solid #ddd;
}

.wp-list-table tbody td {
	color: #35332f;
	font-size: 14px;
}

.wp-list-table .dashicons {
	color: #35332f;
	font-size: 18px;
	width: 18px;
	height: 18px;
}

.wp-list-table .dashicons-lock {
	color: #daff00;
}

.button-link-delete {
	color: #35332f !important;
	font-family: 'futura-pt', Arial, sans-serif;
	font-size: 13px;
	text-decoration: none;
}

.button-link-delete:hover {
	color: #8b8883 !important;
	text-decoration: underline;
}

.msh-cli-reference {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 12px;
	padding: 32px;
	margin-top: 40px;
}

.msh-cli-reference h2 {
	margin-top: 0;
}

.msh-cli-reference p {
	font-size: 14px;
	color: #4a4945;
	line-height: 1.6;
}

.msh-cli-reference pre {
	background: #faf9f6;
	padding: 20px;
	border-left: 4px solid #daff00;
	border-radius: 6px;
	overflow-x: auto;
	margin-top: 16px;
}

.msh-cli-reference code {
	font-family: 'Courier New', monospace;
	font-size: 13px;
	color: #35332f;
	line-height: 1.8;
}
</style>

<div class="wrap msh-glossary-wrap">
	<h1><?php esc_html_e( 'Glossary Manager', 'msh-image-optimizer' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Manage protected brand terms and translations. Protected terms will not be translated or modified by AI.', 'msh-image-optimizer' ); ?>
	</p>

	<!-- Add New Term Form -->
	<div class="msh-settings-card">
		<h2><?php esc_html_e( 'Add New Term', 'msh-image-optimizer' ); ?></h2>

		<form method="post" action="">
			<?php wp_nonce_field( 'msh_save_glossary' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="locale"><?php esc_html_e( 'Locale', 'msh-image-optimizer' ); ?></label>
					</th>
					<td>
						<input type="text" id="locale" name="locale" value="<?php echo esc_attr( $site_locale ); ?>" class="regular-text" />
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="term"><?php esc_html_e( 'Term', 'msh-image-optimizer' ); ?> *</label>
					</th>
					<td>
						<input type="text" id="term" name="term" required />
						<p class="description"><?php esc_html_e( 'The original term (e.g., "WordPress", "YourBrand")', 'msh-image-optimizer' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="translation"><?php esc_html_e( 'Translation', 'msh-image-optimizer' ); ?></label>
					</th>
					<td>
						<input type="text" id="translation" name="translation" />
						<p class="description"><?php esc_html_e( 'Translation for this locale (leave empty for protected terms)', 'msh-image-optimizer' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="category"><?php esc_html_e( 'Category', 'msh-image-optimizer' ); ?></label>
					</th>
					<td>
						<select id="category" name="category">
							<option value="general"><?php esc_html_e( 'General', 'msh-image-optimizer' ); ?></option>
							<option value="brand"><?php esc_html_e( 'Brand', 'msh-image-optimizer' ); ?></option>
							<option value="product"><?php esc_html_e( 'Product', 'msh-image-optimizer' ); ?></option>
							<option value="technical"><?php esc_html_e( 'Technical', 'msh-image-optimizer' ); ?></option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Protection', 'msh-image-optimizer' ); ?>
					</th>
					<td>
						<label>
							<input type="checkbox" id="protected" name="protected" value="1" />
							<?php esc_html_e( 'Protected term (never translate or modify)', 'msh-image-optimizer' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" name="msh_save_glossary_term" class="button button-primary" value="<?php esc_attr_e( 'Add Term', 'msh-image-optimizer' ); ?>" />
			</p>
		</form>
	</div>

	<!-- Existing Terms Table -->
	<div class="msh-settings-card">
		<h2><?php esc_html_e( 'Current Glossary', 'msh-image-optimizer' ); ?> (<?php echo esc_html( $site_locale ); ?>)</h2>

		<?php if ( empty( $glossary_terms ) ) : ?>
			<p><?php esc_html_e( 'No glossary terms defined yet.', 'msh-image-optimizer' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Term', 'msh-image-optimizer' ); ?></th>
						<th><?php esc_html_e( 'Translation', 'msh-image-optimizer' ); ?></th>
						<th><?php esc_html_e( 'Category', 'msh-image-optimizer' ); ?></th>
						<th><?php esc_html_e( 'Protected', 'msh-image-optimizer' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'msh-image-optimizer' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $glossary_terms as $term_data ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $term_data['term'] ); ?></strong></td>
						<td>
							<?php
							if ( ! empty( $term_data['translation'] ) ) {
								echo esc_html( $term_data['translation'] );
							} else {
								echo '<em>' . esc_html__( '(no translation)', 'msh-image-optimizer' ) . '</em>';
							}
							?>
						</td>
						<td><?php echo esc_html( $term_data['category'] ?? 'general' ); ?></td>
						<td>
							<?php if ( ! empty( $term_data['protected'] ) ) : ?>
								<span class="dashicons dashicons-lock" title="<?php esc_attr_e( 'Protected', 'msh-image-optimizer' ); ?>"></span>
							<?php else : ?>
								<span class="dashicons dashicons-unlock" title="<?php esc_attr_e( 'Not protected', 'msh-image-optimizer' ); ?>"></span>
							<?php endif; ?>
						</td>
						<td>
							<form method="post" style="display: inline;">
								<?php wp_nonce_field( 'msh_delete_glossary' ); ?>
								<input type="hidden" name="entry_id" value="<?php echo esc_attr( $term_data['id'] ?? 0 ); ?>" />
								<button type="submit" name="msh_delete_glossary_term" class="button button-small button-link-delete">
									<?php esc_html_e( 'Delete', 'msh-image-optimizer' ); ?>
								</button>
							</form>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<!-- WP-CLI Reference -->
	<div class="msh-cli-reference">
		<h2><?php esc_html_e( 'WP-CLI Commands', 'msh-image-optimizer' ); ?></h2>
		<p><?php esc_html_e( 'You can also manage glossary terms via WP-CLI:', 'msh-image-optimizer' ); ?></p>
		<pre><code>wp msh locale glossary_list --locale=<?php echo esc_html( $site_locale ); ?>

wp msh locale glossary_add \
  --locale=<?php echo esc_html( $site_locale ); ?> \
  --term="WordPress" \
  --protected=1 \
  --category=brand
</code></pre>
	</div>
</div>
