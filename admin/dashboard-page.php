<?php
/**
 * The Dot Optimizer - Dashboard Page
 *
 * Main overview page showing quick stats and navigation.
 *
 * @package    MSH_Image_Optimizer
 * @subpackage Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get quick stats
$total_images      = wp_count_posts( 'attachment' )->inherit ?? 0;
$site_locale       = get_locale();
$locale_manager    = class_exists( 'MSH_Locale_Profile_Manager' ) ? MSH_Locale_Profile_Manager::get_instance() : null;
$active_profile    = $locale_manager ? $locale_manager->get_profile( $site_locale ) : null;

// Get optimization stats
global $wpdb;
$optimized_count = $wpdb->get_var(
	"SELECT COUNT(DISTINCT post_id)
	FROM {$wpdb->postmeta}
	WHERE meta_key = '_msh_optimized'
	AND meta_value = '1'"
) ?? 0;

// Enqueue brand fonts
wp_enqueue_style( 'msh-brand-fonts', 'https://use.typekit.net/gac6jnd.css', array(), null );
?>

<style>
/* Brand-compliant minimal dashboard */
#wpcontent, #wpbody, #wpbody-content {
	background-color: #FAF9F6;
}

.msh-dashboard-wrap {
	background-color: #FAF9F6;
	font-family: 'ff-real-text-pro', Arial, sans-serif;
	color: #35332f;
	padding: 20px;
	max-width: 1200px;
}

.msh-dashboard-wrap h1,
.msh-dashboard-wrap h2 {
	font-family: 'futura-pt', Arial, sans-serif !important;
	text-transform: uppercase !important;
	letter-spacing: 0.08em !important;
	font-weight: 400 !important;
	color: #35332f !important;
	margin-bottom: 24px;
}

.msh-dashboard-wrap h1 {
	font-size: 24px;
}

.msh-dashboard-wrap h2 {
	font-size: 18px;
	margin-top: 40px;
}

.msh-stats-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
	margin: 32px 0;
}

.msh-stat-card {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 12px;
	padding: 24px;
	box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.msh-stat-card h3 {
	font-family: 'futura-pt', Arial, sans-serif;
	font-size: 36px;
	font-weight: 400;
	color: #35332f;
	margin: 0 0 8px 0;
	line-height: 1;
}

.msh-stat-card p {
	font-family: 'ff-real-text-pro', Arial, sans-serif;
	font-size: 14px;
	color: #8b8883;
	margin: 0;
	text-transform: uppercase;
	letter-spacing: 0.05em;
}

.msh-action-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 16px;
	margin: 24px 0 40px 0;
}

.msh-action-link {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 20px 24px;
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 12px;
	color: #35332f;
	text-decoration: none;
	font-family: 'futura-pt', Arial, sans-serif;
	font-size: 14px;
	font-weight: 400;
	text-transform: uppercase;
	letter-spacing: 0.08em;
	transition: all 0.2s ease;
}

.msh-action-link:hover {
	background-color: #daff00;
	border-color: #daff00;
	color: #35332f;
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(218, 255, 0, 0.3);
}

.msh-action-link .dashicons {
	font-size: 20px;
	width: 20px;
	height: 20px;
	color: #8b8883;
}

.msh-action-link:hover .dashicons {
	color: #35332f;
}

.msh-locale-status {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 12px;
	padding: 32px;
	margin-top: 40px;
}

.msh-locale-status .msh-status-success {
	color: #2f5400;
	font-weight: 500;
	margin: 16px 0;
}

.msh-locale-status .msh-status-warning {
	color: #8b8883;
	font-weight: 500;
	margin: 16px 0;
}

.msh-locale-status .dashicons {
	vertical-align: text-bottom;
	margin-right: 4px;
}

.msh-profile-details {
	list-style: none;
	padding: 0;
	margin: 16px 0 0 0;
	color: #35332f;
	font-size: 14px;
}

.msh-profile-details li {
	padding: 8px 0;
	border-bottom: 1px solid #f0f0f0;
}

.msh-profile-details li:last-child {
	border-bottom: none;
}

.button-primary {
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
	box-shadow: none !important;
}

.button-primary:hover {
	background-color: #daff00 !important;
	border-color: #35332f !important;
	color: #35332f !important;
}
</style>

<div class="wrap msh-dashboard-wrap">
	<h1><?php esc_html_e( 'Dashboard', 'msh-image-optimizer' ); ?></h1>

	<!-- Quick Stats -->
	<div class="msh-stats-grid">
		<div class="msh-stat-card">
			<h3><?php echo esc_html( number_format_i18n( $total_images ) ); ?></h3>
			<p><?php esc_html_e( 'Total Images', 'msh-image-optimizer' ); ?></p>
		</div>

		<div class="msh-stat-card">
			<h3><?php echo esc_html( number_format_i18n( $optimized_count ) ); ?></h3>
			<p><?php esc_html_e( 'Optimized', 'msh-image-optimizer' ); ?></p>
		</div>

		<div class="msh-stat-card">
			<h3><?php echo esc_html( $site_locale ); ?></h3>
			<p><?php esc_html_e( 'Active Locale', 'msh-image-optimizer' ); ?></p>
		</div>
	</div>

	<!-- Quick Actions -->
	<h2><?php esc_html_e( 'Quick Actions', 'msh-image-optimizer' ); ?></h2>
	<div class="msh-action-grid">
		<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="msh-action-link">
			<span class="dashicons dashicons-format-gallery"></span>
			<?php esc_html_e( 'Media Library', 'msh-image-optimizer' ); ?>
		</a>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-image-optimizer' ) ); ?>" class="msh-action-link">
			<span class="dashicons dashicons-admin-tools"></span>
			<?php esc_html_e( 'Optimize Images', 'msh-image-optimizer' ); ?>
		</a>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-context-analytics' ) ); ?>" class="msh-action-link">
			<span class="dashicons dashicons-chart-bar"></span>
			<?php esc_html_e( 'Analytics', 'msh-image-optimizer' ); ?>
		</a>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-locale-profiles' ) ); ?>" class="msh-action-link">
			<span class="dashicons dashicons-translation"></span>
			<?php esc_html_e( 'Locale Profiles', 'msh-image-optimizer' ); ?>
		</a>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-glossary' ) ); ?>" class="msh-action-link">
			<span class="dashicons dashicons-book"></span>
			<?php esc_html_e( 'Glossary', 'msh-image-optimizer' ); ?>
		</a>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-image-optimizer-settings' ) ); ?>" class="msh-action-link">
			<span class="dashicons dashicons-admin-settings"></span>
			<?php esc_html_e( 'Settings', 'msh-image-optimizer' ); ?>
		</a>
	</div>

	<!-- Locale Status -->
	<?php if ( $locale_manager ) : ?>
	<div class="msh-locale-status">
		<h2><?php esc_html_e( 'Locale Configuration', 'msh-image-optimizer' ); ?></h2>
		<?php if ( $active_profile ) : ?>
			<p class="msh-status-success">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Locale profile configured', 'msh-image-optimizer' ); ?>
			</p>
			<ul class="msh-profile-details">
				<li><strong><?php esc_html_e( 'Tone:', 'msh-image-optimizer' ); ?></strong> <?php echo esc_html( ucfirst( $active_profile['tone'] ?? 'professional' ) ); ?></li>
				<li><strong><?php esc_html_e( 'Formality:', 'msh-image-optimizer' ); ?></strong> <?php echo esc_html( $active_profile['formality_level'] ?? 3 ); ?>/5</li>
				<li><strong><?php esc_html_e( 'CTA Style:', 'msh-image-optimizer' ); ?></strong> <?php echo esc_html( ucfirst( $active_profile['cta_style'] ?? 'subtle' ) ); ?></li>
			</ul>
		<?php else : ?>
			<p class="msh-status-warning">
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'No locale profile configured. Using defaults.', 'msh-image-optimizer' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-locale-profiles' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Create Locale Profile', 'msh-image-optimizer' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>
	<?php endif; ?>
</div>
