<?php
/**
 * Settings screen for the MSH Image Optimizer.
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Image_Optimizer_Settings {

	const PAGE_SLUG         = 'msh-image-optimizer-settings';
	const PRIMARY_OPTION    = 'msh_onboarding_context';
	const PROFILES_OPTION   = 'msh_onboarding_context_profiles';
	const NONCE_ACTION      = 'msh_save_context_settings';
	const ADMIN_POST_ACTION = 'msh_save_context_settings';

	public function __construct() {
		// Menu registration now handled by class-msh-optimizer-menu.php
		// add_action( 'admin_menu', array( $this, 'register_settings_page' ), 55 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::ADMIN_POST_ACTION, array( $this, 'handle_save' ) );
		add_action( 'msh_image_optimizer_settings_notices', array( $this, 'output_notice' ) );
	}

	/**
	 * Register menu item under The Dot menu.
	 */
	public function register_settings_page() {
		add_submenu_page(
			'msh-optimizer',
			__( 'Settings', 'msh-image-optimizer' ),
			'<span class="dashicons dashicons-admin-generic"></span> ' . __( 'Settings', 'msh-image-optimizer' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Load assets for settings screen.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_assets( $hook ) {
		$screen = 'the-dot_page_' . self::PAGE_SLUG;
		if ( $hook !== $screen ) {
			return;
		}

		wp_enqueue_style(
			'msh-image-optimizer-fonts',
			'https://use.typekit.net/gac6jnd.css',
			array(),
			null
		);

		wp_enqueue_style(
			'msh-image-optimizer-settings',
			trailingslashit( MSH_IO_ASSETS_URL ) . 'css/image-optimizer-settings.css',
			array( 'msh-image-optimizer-fonts' ),
			MSH_Image_Optimizer_Plugin::VERSION
		);

		wp_enqueue_script(
			'msh-image-optimizer-settings',
			trailingslashit( MSH_IO_ASSETS_URL ) . 'js/image-optimizer-settings.js',
			array( 'jquery' ),
			MSH_Image_Optimizer_Plugin::VERSION,
			true
		);

		$profile_strings = array(
			'profileLabelPlaceholder' => __( 'e.g., Spanish Landing Page', 'msh-image-optimizer' ),
			'profileNotesPlaceholder' => __( 'Usage notes, target audience, campaign links…', 'msh-image-optimizer' ),
			'deleteProfileConfirm'    => __( 'Remove this context profile?', 'msh-image-optimizer' ),
		);

		wp_localize_script(
			'msh-image-optimizer-settings',
			'mshSettings',
			array(
				'labelMap' => MSH_Image_Optimizer_Context_Helper::get_label_map(),
				'strings'  => $profile_strings,
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'msh-image-optimizer' ) );
		}

		$primary_context = MSH_Image_Optimizer_Context_Helper::sanitize_context(
			get_option( self::PRIMARY_OPTION, array() ),
			false
		);

		$profiles       = MSH_Image_Optimizer_Context_Helper::get_profiles();
		$labels         = MSH_Image_Optimizer_Context_Helper::get_label_map();
		$active_profile = get_option( 'msh_active_context_profile', 'primary' );
		if ( 'primary' !== $active_profile && ! isset( $profiles[ $active_profile ] ) ) {
			$active_profile = 'primary';
		}
		$rename_enabled = get_option( 'msh_enable_file_rename', '0' ) === '1';
		$index_stats    = $this->get_usage_index_stats();
		$diagnostics    = $this->get_diagnostics_snapshot();
		$ai_mode        = get_option( 'msh_ai_mode', 'manual' );
		$ai_api_key     = get_option( 'msh_ai_api_key', '' );
		$ai_features    = get_option( 'msh_ai_features', array() );
		if ( ! is_array( $ai_features ) ) {
			$ai_features = array();
		}
		$user_mode      = get_option( 'msh_user_mode', 'basic' );
		$success = isset( $_GET['msh_saved'] ) && '1' === $_GET['msh_saved'];
		$errors  = isset( $_GET['msh_error'] ) ? sanitize_text_field( $_GET['msh_error'] ) : '';

		?>
		<div class="wrap msh-settings-wrap">
			<div class="msh-page-header">
				<div class="msh-logo-container">
					<img src="<?php echo esc_url( trailingslashit( MSH_IO_ASSETS_URL ) . 'icons/optimizer-logo.svg' ); ?>"
						alt="<?php esc_attr_e( 'The Dot Image Optimizer', 'msh-image-optimizer' ); ?>"
						class="msh-logo" />
				</div>
				<div class="msh-header-links">
					<a href="mailto:support@thedot.com" class="msh-support-link">
						<span class="msh-support-text"><?php esc_html_e( 'reach out for support', 'msh-image-optimizer' ); ?></span>
						<svg width="24" height="25" viewBox="0 0 24 25" fill="none" xmlns="http://www.w3.org/2000/svg" class="msh-mail-icon">
							<path d="M4 20.3735C3.45 20.3735 2.975 20.1819 2.575 19.7985C2.19167 19.3985 2 18.9235 2 18.3735V6.37353C2 5.82354 2.19167 5.35687 2.575 4.97353C2.975 4.57353 3.45 4.37354 4 4.37354H20C20.55 4.37354 21.0167 4.57353 21.4 4.97353C21.8 5.35687 22 5.82354 22 6.37353V18.3735C22 18.9235 21.8 19.3985 21.4 19.7985C21.0167 20.1819 20.55 20.3735 20 20.3735H4ZM12 13.3735L20 8.37354V6.37353L12 11.3735L4 6.37353V8.37354L12 13.3735Z" fill="#35332F"/>
						</svg>
					</a>
					<a href="https://thedot.com" target="_blank" rel="noopener noreferrer" class="msh-website-link">
						<span class="msh-website-text"><?php esc_html_e( 'visit our website', 'msh-image-optimizer' ); ?></span>
						<svg width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg" class="msh-website-icon">
							<path d="M10 20.3735C8.63333 20.3735 7.34167 20.111 6.125 19.586C4.90833 19.061 3.84583 18.3444 2.9375 17.436C2.02917 16.5277 1.3125 15.4652 0.7875 14.2485C0.2625 13.0319 0 11.7402 0 10.3735C0 8.9902 0.2625 7.69437 0.7875 6.48604C1.3125 5.2777 2.02917 4.21937 2.9375 3.31104C3.84583 2.4027 4.90833 1.68604 6.125 1.16104C7.34167 0.636035 8.63333 0.373535 10 0.373535C11.3833 0.373535 12.6792 0.636035 13.8875 1.16104C15.0958 1.68604 16.1542 2.4027 17.0625 3.31104C17.9708 4.21937 18.6875 5.2777 19.2125 6.48604C19.7375 7.69437 20 8.9902 20 10.3735C20 11.7402 19.7375 13.0319 19.2125 14.2485C18.6875 15.4652 17.9708 16.5277 17.0625 17.436C16.1542 18.3444 15.0958 19.061 13.8875 19.586C12.6792 20.111 11.3833 20.3735 10 20.3735ZM10 18.3235C10.4333 17.7235 10.8083 17.0985 11.125 16.4485C11.4417 15.7985 11.7 15.1069 11.9 14.3735H8.1C8.3 15.1069 8.55833 15.7985 8.875 16.4485C9.19167 17.0985 9.56667 17.7235 10 18.3235ZM7.4 17.9235C7.1 17.3735 6.8375 16.8027 6.6125 16.211C6.3875 15.6194 6.2 15.0069 6.05 14.3735H3.1C3.58333 15.2069 4.1875 15.9319 4.9125 16.5485C5.6375 17.1652 6.46667 17.6235 7.4 17.9235ZM12.6 17.9235C13.5333 17.6235 14.3625 17.1652 15.0875 16.5485C15.8125 15.9319 16.4167 15.2069 16.9 14.3735H13.95C13.8 15.0069 13.6125 15.6194 13.3875 16.211C13.1625 16.8027 12.9 17.3735 12.6 17.9235ZM2.25 12.3735H5.65C5.6 12.0402 5.5625 11.711 5.5375 11.386C5.5125 11.061 5.5 10.7235 5.5 10.3735C5.5 10.0235 5.5125 9.68604 5.5375 9.36104C5.5625 9.03604 5.6 8.70687 5.65 8.37354H2.25C2.16667 8.70687 2.10417 9.03604 2.0625 9.36104C2.02083 9.68604 2 10.0235 2 10.3735C2 10.7235 2.02083 11.061 2.0625 11.386C2.10417 11.711 2.16667 12.0402 2.25 12.3735ZM7.65 12.3735H12.35C12.4 12.0402 12.4375 11.711 12.4625 11.386C12.4875 11.061 12.5 10.7235 12.5 10.3735C12.5 10.0235 12.4875 9.68604 12.4625 9.36104C12.4375 9.03604 12.4 8.70687 12.35 8.37354H7.65C7.6 8.70687 7.5625 9.03604 7.5375 9.36104C7.5125 9.68604 7.5 10.0235 7.5 10.3735C7.5 10.7235 7.5125 11.061 7.5375 11.386C7.5625 11.711 7.6 12.0402 7.65 12.3735ZM14.35 12.3735H17.75C17.8333 12.0402 17.8958 11.711 17.9375 11.386C17.9792 11.061 18 10.7235 18 10.3735C18 10.0235 17.9792 9.68604 17.9375 9.36104C17.8958 9.03604 17.8333 8.70687 17.75 8.37354H14.35C14.4 8.70687 14.4375 9.03604 14.4625 9.36104C14.4875 9.68604 14.5 10.0235 14.5 10.3735C14.5 10.7235 14.4875 11.061 14.4625 11.386C14.4375 11.711 14.4 12.0402 14.35 12.3735ZM13.95 6.37354H16.9C16.4167 5.5402 15.8125 4.8152 15.0875 4.19854C14.3625 3.58187 13.5333 3.12354 12.6 2.82354C12.9 3.37354 13.1625 3.94437 13.3875 4.53604C13.6125 5.1277 13.8 5.7402 13.95 6.37354ZM8.1 6.37354H11.9C11.7 5.6402 11.4417 4.94854 11.125 4.29854C10.8083 3.64854 10.4333 3.02354 10 2.42354C9.56667 3.02354 9.19167 3.64854 8.875 4.29854C8.55833 4.94854 8.3 5.6402 8.1 6.37354ZM3.1 6.37354H6.05C6.2 5.7402 6.3875 5.1277 6.6125 4.53604C6.8375 3.94437 7.1 3.37354 7.4 2.82354C6.46667 3.12354 5.6375 3.58187 4.9125 4.19854C4.1875 4.8152 3.58333 5.5402 3.1 6.37354Z" fill="#35332F"/>
						</svg>
					</a>
				</div>
			</div>

			<?php
			if ( $success ) {
				$this->display_notice(
					__( 'Context settings saved.', 'msh-image-optimizer' ),
					'updated'
				);
			}

			if ( ! empty( $errors ) ) {
				$this->display_notice(
					esc_html( $errors ),
					'error'
				);
			}
			?>

			<?php
			// Tab navigation
			$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
			$tabs = array(
				'general' => __( 'General', 'msh-image-optimizer' ),
				'context' => __( 'Context', 'msh-image-optimizer' ),
				'ai'      => __( 'AI', 'msh-image-optimizer' ),
				'sync'    => __( 'Sync', 'msh-image-optimizer' ),
				'account' => __( 'Account', 'msh-image-optimizer' ),
				'advanced' => __( 'Advanced', 'msh-image-optimizer' ),
			);
			?>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => $tab_key ), admin_url( 'admin.php' ) ) ); ?>"
					   class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="msh-settings-form">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ADMIN_POST_ACTION ); ?>">
				<input type="hidden" name="active_tab" value="<?php echo esc_attr( $active_tab ); ?>">

				<!-- GENERAL TAB -->
				<?php if ( 'general' === $active_tab ) : ?>

				<section class="msh-settings-card">
					<header>
						<h2><?php esc_html_e( 'User Mode', 'msh-image-optimizer' ); ?></h2>
						<p><?php esc_html_e( 'Control menu complexity: Basic mode shows essential features only, Advanced mode shows all tools and options.', 'msh-image-optimizer' ); ?></p>
					</header>
					<fieldset style="border: none; padding: 0; margin: 0;">
						<p style="margin-bottom: 16px;">
							<label style="display: block; margin-bottom: 4px;">
								<input type="radio" name="options[user_mode]" value="basic" <?php checked( $user_mode, 'basic' ); ?> style="vertical-align: middle; margin-right: 8px;">
								<strong style="vertical-align: middle;"><?php esc_html_e( 'Basic Mode', 'msh-image-optimizer' ); ?></strong>
							</label>
							<span class="description" style="display: block; margin-left: 28px; color: #646970;">
								<?php esc_html_e( 'Shows Dashboard, Image Optimizer, Settings, and Help. Perfect for day-to-day optimization work.', 'msh-image-optimizer' ); ?>
							</span>
						</p>
						<p style="margin-bottom: 16px;">
							<label style="display: block; margin-bottom: 4px;">
								<input type="radio" name="options[user_mode]" value="advanced" <?php checked( $user_mode, 'advanced' ); ?> style="vertical-align: middle; margin-right: 8px;">
								<strong style="vertical-align: middle;"><?php esc_html_e( 'Advanced Mode', 'msh-image-optimizer' ); ?></strong>
							</label>
							<span class="description" style="display: block; margin-left: 28px; color: #646970;">
								<?php esc_html_e( 'Displays all features including Optimizer Hub, Localization, Insights & Analytics, and Review Center (Pro features require active license).', 'msh-image-optimizer' ); ?>
							</span>
						</p>
					</fieldset>
				</section>

				<!-- Optimization Controls (also in General tab) -->
				<section class="msh-settings-card msh-settings-card--rename">
					<header>
						<h2><?php esc_html_e( 'Optimization Controls', 'msh-image-optimizer' ); ?></h2>
						<p><?php esc_html_e( 'Keep file renaming in sync with your usage index so URLs stay intact across campaigns and migrations.', 'msh-image-optimizer' ); ?></p>
					</header>
					<div class="msh-settings-grid">
						<div class="msh-settings-field msh-settings-checkbox">
							<input type="hidden" name="options[rename_enabled]" value="0">
							<label class="msh-checkbox-field">
								<input type="checkbox" name="options[rename_enabled]" value="1" <?php checked( $rename_enabled ); ?>>
								<span><?php esc_html_e( 'Enable safe file renaming', 'msh-image-optimizer' ); ?></span>
							</label>
							<p class="msh-settings-note">
								<?php esc_html_e( 'When active, Analyze & Apply can publish SEO-friendly filenames without breaking links. Requires a fresh usage index.', 'msh-image-optimizer' ); ?>
							</p>
							<?php
							$index_status_class = 'status-disabled';
							$index_status_label = __( 'Renaming disabled', 'msh-image-optimizer' );
							if ( $rename_enabled ) {
								if ( ! empty( $index_stats ) ) {
									$index_status_class = 'status-ready';
									$index_status_label = __( 'Ready – usage index verified', 'msh-image-optimizer' );
								} else {
									$index_status_class = 'status-pending';
									$index_status_label = __( 'Action needed – build usage index before renaming', 'msh-image-optimizer' );
								}
							}
							?>
							<div class="msh-status-pill <?php echo esc_attr( $index_status_class ); ?>">
								<?php echo esc_html( $index_status_label ); ?>
							</div>
						</div>
					</div>
				</section>

				<?php endif; // End General Tab ?>

				<!-- CONTEXT TAB -->
				<?php if ( 'context' === $active_tab ) : ?>

				<section class="msh-settings-card">
					<header>
						<h2><?php esc_html_e( 'Primary Context', 'msh-image-optimizer' ); ?></h2>
						<p><?php esc_html_e( 'Default business context used for metadata recommendations, reports, and AI prompts.', 'msh-image-optimizer' ); ?></p>
					</header>

					<div class="msh-settings-grid">
						<?php $this->render_text_input( 'primary[business_name]', __( 'Business Name', 'msh-image-optimizer' ), $primary_context['business_name'], true ); ?>

						<?php
						$this->render_select_input(
							'primary[industry]',
							__( 'Industry', 'msh-image-optimizer' ),
							$labels['industry'],
							$primary_context['industry'],
							true
						);
						?>

						<?php
						$this->render_select_input(
							'primary[business_type]',
							__( 'Business Type', 'msh-image-optimizer' ),
							$labels['business_type'],
							$primary_context['business_type'],
							true
						);
						?>

						<?php
						$this->render_text_input(
							'primary[target_audience]',
							__( 'Ideal customer', 'msh-image-optimizer' ),
							$primary_context['target_audience'],
							true,
							__( 'e.g., SaaS founders and B2B marketing leads launching demand-gen campaigns', 'msh-image-optimizer' )
						);
						?>

						<?php
						$this->render_textarea(
							'primary[pain_points]',
							__( 'Problems you solve', 'msh-image-optimizer' ),
							$primary_context['pain_points'],
							__( 'e.g., Clarifying positioning, building conversion-focused landing pages…', 'msh-image-optimizer' )
						);
						?>

						<?php
						$this->render_text_input(
							'primary[demographics]',
							__( 'Demographics (optional)', 'msh-image-optimizer' ),
							$primary_context['demographics'],
							false,
							__( 'e.g., VC-backed teams, 10–100 employees, North American tech hubs', 'msh-image-optimizer' )
						);
						?>

						<?php
						$this->render_radio_group(
							'primary[brand_voice]',
							__( 'Brand voice', 'msh-image-optimizer' ),
							$labels['brand_voice'],
							$primary_context['brand_voice'],
							true
						);
						?>

						<?php
						$this->render_textarea(
							'primary[uvp]',
							__( 'What makes you different?', 'msh-image-optimizer' ),
							$primary_context['uvp'],
							__( 'Highlight differentiators, proof points, or signature offers.', 'msh-image-optimizer' ),
							true
						);
						?>

						<?php
						$this->render_select_input(
							'primary[cta_preference]',
							__( 'Call-to-action style', 'msh-image-optimizer' ),
							$labels['cta_preference'],
							$primary_context['cta_preference'],
							false
						);
						?>

						<?php
						$this->render_text_input(
							'primary[city]',
							__( 'City', 'msh-image-optimizer' ),
							$primary_context['city']
						);
						?>

						<?php
						$this->render_text_input(
							'primary[region]',
							__( 'Province / Region', 'msh-image-optimizer' ),
							$primary_context['region']
						);
						?>

						<?php
						$this->render_text_input(
							'primary[country]',
							__( 'Country', 'msh-image-optimizer' ),
							$primary_context['country']
						);
						?>

						<?php
						$this->render_text_input(
							'primary[service_area]',
							__( 'Service area', 'msh-image-optimizer' ),
							$primary_context['service_area'],
							false,
							__( 'e.g., Remote across North America', 'msh-image-optimizer' )
						);
						?>

						<div class="msh-settings-field msh-settings-checkbox">
							<label>
								<input type="checkbox" name="primary[ai_interest]" value="1" <?php checked( ! empty( $primary_context['ai_interest'] ) ); ?>>
								<span><?php esc_html_e( 'Subscribe to AI feature updates', 'msh-image-optimizer' ); ?></span>
							</label>
						</div>

						<input type="hidden" name="primary[updated_at]" value="<?php echo esc_attr( isset( $primary_context['updated_at'] ) ? (int) $primary_context['updated_at'] : 0 ); ?>">
					</div>
					<div class="msh-settings-field">
						<label for="msh_active_profile"><?php esc_html_e( 'Active Context', 'msh-image-optimizer' ); ?></label>
						<select id="msh_active_profile" name="options[active_profile]" class="msh-select">
							<?php
							$primary_label = ! empty( $primary_context['business_name'] )
								? $primary_context['business_name']
								: __( 'Primary Context', 'msh-image-optimizer' );
							?>
							<option value="primary" <?php selected( $active_profile, 'primary' ); ?>>
								<?php echo esc_html( sprintf( __( 'Primary – %s', 'msh-image-optimizer' ), $primary_label ) ); ?>
							</option>
							<?php foreach ( $profiles as $profile ) : ?>
								<?php
								$label = ! empty( $profile['label'] )
									? $profile['label']
									: __( 'Context profile', 'msh-image-optimizer' );
								?>
								<option value="<?php echo esc_attr( $profile['id'] ); ?>" <?php selected( $active_profile, $profile['id'] ); ?>>
									<?php echo esc_html( sprintf( __( 'Profile – %s', 'msh-image-optimizer' ), $label ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="msh-settings-note">
							<?php esc_html_e( 'This context powers dashboard copy, automation prompts, and AI recommendations.', 'msh-image-optimizer' ); ?>
						</p>
					</div>
				</section>

				<section class="msh-settings-card msh-settings-card--profiles">
					<header>
						<h2><?php esc_html_e( 'Context Profiles', 'msh-image-optimizer' ); ?></h2>
						<p><?php esc_html_e( 'Manage additional contexts for landing pages, multilingual experiences, or campaigns. Profiles inherit the same structure as the primary context.', 'msh-image-optimizer' ); ?></p>
					</header>

					<div id="msh-context-profiles" class="msh-profile-collection" data-next-index="<?php echo esc_attr( count( $profiles ) ); ?>">
						<?php
						if ( ! empty( $profiles ) ) {
							foreach ( $profiles as $index => $profile ) {
								$this->render_profile_fieldset( $index, $profile, $labels );
							}
						}
						?>
					</div>

					<button type="button" class="button button-dot-secondary msh-add-profile">
						<?php esc_html_e( 'Add Context Profile', 'msh-image-optimizer' ); ?>
					</button>
					<p class="msh-settings-note"><?php esc_html_e( 'Changes to profiles are saved when you click "Save Settings" below.', 'msh-image-optimizer' ); ?></p>
				</section>

				<?php endif; // End Context Tab ?>

				<!-- ADVANCED TAB -->
				<?php if ( 'advanced' === $active_tab ) : ?>

				<section class="msh-settings-card msh-settings-card--diagnostics">
					<header>
						<h2><?php esc_html_e( 'Diagnostics Snapshot', 'msh-image-optimizer' ); ?></h2>
						<p><?php esc_html_e( 'Quick glance at the most recent optimization activity. Use this to confirm scheduled jobs and automation are running on time.', 'msh-image-optimizer' ); ?></p>
					</header>
					<div class="msh-diagnostics-grid">
						<div class="msh-diagnostics-item">
							<span class="msh-diagnostics-label"><?php esc_html_e( 'Analyzer', 'msh-image-optimizer' ); ?></span>
							<span class="msh-diagnostics-value"><?php echo esc_html( $diagnostics['last_analyzer_run'] ); ?></span>
						</div>
						<div class="msh-diagnostics-item">
							<span class="msh-diagnostics-label"><?php esc_html_e( 'Optimization batch', 'msh-image-optimizer' ); ?></span>
							<span class="msh-diagnostics-value"><?php echo esc_html( $diagnostics['last_optimization_run'] ); ?></span>
						</div>
						<div class="msh-diagnostics-item">
							<span class="msh-diagnostics-label"><?php esc_html_e( 'Duplicate scan', 'msh-image-optimizer' ); ?></span>
							<span class="msh-diagnostics-value"><?php echo esc_html( $diagnostics['last_duplicate_scan'] ); ?></span>
						</div>
						<div class="msh-diagnostics-item">
							<span class="msh-diagnostics-label"><?php esc_html_e( 'Visual similarity scan', 'msh-image-optimizer' ); ?></span>
							<span class="msh-diagnostics-value"><?php echo esc_html( $diagnostics['last_visual_scan'] ); ?></span>
						</div>
						<div class="msh-diagnostics-item">
							<span class="msh-diagnostics-label"><?php esc_html_e( 'CLI optimization', 'msh-image-optimizer' ); ?></span>
							<span class="msh-diagnostics-value"><?php echo esc_html( $diagnostics['last_cli_run'] ); ?></span>
						</div>
						<div class="msh-diagnostics-item">
							<span class="msh-diagnostics-label"><?php esc_html_e( 'Index entries', 'msh-image-optimizer' ); ?></span>
							<span class="msh-diagnostics-value">
								<?php
								if ( $index_stats ) {
									echo esc_html( number_format_i18n( $index_stats['total_entries'] ) );
								} else {
									esc_html_e( 'Not yet built', 'msh-image-optimizer' );
								}
								?>
							</span>
						</div>
						<div class="msh-diagnostics-item">
							<span class="msh-diagnostics-label"><?php esc_html_e( 'Indexed attachments', 'msh-image-optimizer' ); ?></span>
							<span class="msh-diagnostics-value">
								<?php
								if ( $index_stats ) {
									echo esc_html( number_format_i18n( $index_stats['unique_attachments'] ) );
								} else {
									esc_html_e( '—', 'msh-image-optimizer' );
								}
								?>
							</span>
						</div>
						<div class="msh-diagnostics-item">
							<span class="msh-diagnostics-label"><?php esc_html_e( 'Index refreshed', 'msh-image-optimizer' ); ?></span>
							<span class="msh-diagnostics-value">
								<?php
								if ( $index_stats && ! empty( $index_stats['last_update'] ) ) {
									echo esc_html( $this->format_datetime( $index_stats['last_update'] ) );
								} else {
									esc_html_e( '—', 'msh-image-optimizer' );
								}
								?>
							</span>
						</div>
					</div>
				</section>

				<?php endif; // End Advanced Tab ?>

				<!-- AI TAB -->
				<?php if ( 'ai' === $active_tab ) : ?>

				<section class="msh-settings-card msh-settings-card--ai">
					<header>
						<h2><?php esc_html_e( 'AI Automation', 'msh-image-optimizer' ); ?></h2>
						<p><?php esc_html_e( 'Choose how AI assists your team. Manual keeps everything human, Assisted handles metadata on request, Hybrid enables full automation once you are ready.', 'msh-image-optimizer' ); ?></p>
					</header>
					<div class="msh-settings-grid">
						<div class="msh-settings-field msh-ai-mode-field">
							<span class="msh-field-heading"><?php esc_html_e( 'AI Mode', 'msh-image-optimizer' ); ?></span>
							<label class="msh-radio-tile">
								<input type="radio" name="options[ai_mode]" value="manual" <?php checked( $ai_mode, 'manual' ); ?>>
								<span class="msh-radio-title"><?php esc_html_e( 'Manual', 'msh-image-optimizer' ); ?></span>
								<span class="msh-radio-copy"><?php esc_html_e( 'Keep prompts handy but require a human to approve each change.', 'msh-image-optimizer' ); ?></span>
							</label>
							<label class="msh-radio-tile">
								<input type="radio" name="options[ai_mode]" value="assist" <?php checked( $ai_mode, 'assist' ); ?>>
								<span class="msh-radio-title"><?php esc_html_e( 'Assisted', 'msh-image-optimizer' ); ?></span>
								<span class="msh-radio-copy"><?php esc_html_e( 'Generate metadata, descriptions, and alt text on request. You approve before publish.', 'msh-image-optimizer' ); ?></span>
							</label>
							<label class="msh-radio-tile">
								<input type="radio" name="options[ai_mode]" value="hybrid" <?php checked( $ai_mode, 'hybrid' ); ?>>
								<span class="msh-radio-title"><?php esc_html_e( 'Hybrid Automation', 'msh-image-optimizer' ); ?></span>
								<span class="msh-radio-copy"><?php esc_html_e( 'Let AI run in the background with escalations when confidence drops.', 'msh-image-optimizer' ); ?></span>
							</label>
						</div>

						<div class="msh-settings-field">
							<label for="msh_ai_provider"><?php esc_html_e( 'AI Provider (optional)', 'msh-image-optimizer' ); ?></label>
							<select
								id="msh_ai_provider"
								name="options[ai_provider]"
								class="msh-select"
							>
								<option value=""><?php esc_html_e( 'Use bundled credits (default)', 'msh-image-optimizer' ); ?></option>
								<option value="openai" <?php selected( get_option( 'msh_ai_provider', '' ), 'openai' ); ?>><?php esc_html_e( 'OpenAI (GPT-4 Vision)', 'msh-image-optimizer' ); ?></option>
								<option value="anthropic" <?php selected( get_option( 'msh_ai_provider', '' ), 'anthropic' ); ?>><?php esc_html_e( 'Anthropic (Claude 3.5 Sonnet)', 'msh-image-optimizer' ); ?></option>
								<option value="google" <?php selected( get_option( 'msh_ai_provider', '' ), 'google' ); ?>><?php esc_html_e( 'Google (Gemini 1.5 Pro)', 'msh-image-optimizer' ); ?></option>
							</select>
							<p class="msh-settings-note">
								<?php esc_html_e( 'Select which AI provider to use for metadata generation. Leave as default to use bundled credits.', 'msh-image-optimizer' ); ?>
							</p>
						</div>

						<div class="msh-settings-field">
							<label for="msh_ai_api_key"><?php esc_html_e( 'Bring-your-own API key (optional)', 'msh-image-optimizer' ); ?></label>
							<input
								type="password"
								id="msh_ai_api_key"
								name="options[ai_api_key]"
								value="<?php echo esc_attr( $ai_api_key ); ?>"
								class="msh-input"
								autocomplete="off"
								placeholder="<?php esc_attr_e( 'Leave blank to use bundled credits', 'msh-image-optimizer' ); ?>"
							/>
							<p class="msh-settings-note">
								<?php esc_html_e( 'Provide your own API key to bypass credit billing. Get keys from: ', 'msh-image-optimizer' ); ?>
								<strong>OpenAI</strong>: platform.openai.com/api-keys |
								<strong>Anthropic</strong>: console.anthropic.com |
								<strong>Google</strong>: makersuite.google.com
							</p>
						</div>

						<div class="msh-settings-field">
							<label for="msh_ai_live_link_url"><?php esc_html_e( 'Local Live Link URL (for testing)', 'msh-image-optimizer' ); ?></label>
							<input
								type="url"
								id="msh_ai_live_link_url"
								name="options[ai_live_link_url]"
								value="<?php echo esc_attr( get_option( 'msh_ai_live_link_url', '' ) ); ?>"
								class="msh-input"
								placeholder="<?php esc_attr_e( 'https://abc123.loca.lt', 'msh-image-optimizer' ); ?>"
							/>
							<p class="msh-settings-note">
								<?php esc_html_e( 'For local development: Enable "Live Link" in Local by Flywheel (disable password protection!) and paste the public URL here. OpenAI will use this instead of base64 encoding. Leave blank for automatic base64 fallback.', 'msh-image-optimizer' ); ?>
							</p>
						</div>

						<div class="msh-settings-field msh-settings-checkbox-group">
							<span class="msh-field-heading"><?php esc_html_e( 'Enabled AI modules', 'msh-image-optimizer' ); ?></span>
							<label class="msh-checkbox-field">
								<input type="checkbox" name="options[ai_features][]" value="meta" <?php checked( in_array( 'meta', $ai_features, true ) ); ?>>
								<span><?php esc_html_e( 'Metadata & alt text suggestions', 'msh-image-optimizer' ); ?></span>
							</label>
							<label class="msh-checkbox-field">
								<input type="checkbox" name="options[ai_features][]" value="vision" <?php checked( in_array( 'vision', $ai_features, true ) ); ?>>
								<span><?php esc_html_e( 'Vision analysis (quality & branding checks)', 'msh-image-optimizer' ); ?></span>
							</label>
							<label class="msh-checkbox-field">
								<input type="checkbox" name="options[ai_features][]" value="filename" <?php checked( in_array( 'filename', $ai_features, true ) ); ?>>
								<span><?php esc_html_e( 'AI-powered filename generation', 'msh-image-optimizer' ); ?></span>
							</label>
							<label class="msh-checkbox-field">
								<input type="checkbox" name="options[ai_features][]" value="duplicate" <?php checked( in_array( 'duplicate', $ai_features, true ) ); ?>>
								<span><?php esc_html_e( 'Duplicate detection + smart cleanup targets', 'msh-image-optimizer' ); ?></span>
							</label>
							<p class="msh-settings-note">
								<?php esc_html_e( 'AI modules respect the active context profile and only run when credits are available.', 'msh-image-optimizer' ); ?>
							</p>
						</div>
					</div>
				</section>

				<?php endif; // End AI Tab ?>

			<!-- SYNC TAB -->
			<?php if ( 'sync' === $active_tab ) : ?>
				<?php
				$sync_instance = MSH_Remote_Sync::get_instance();
				$sync_status = $sync_instance->get_status();
				$conflict_strategy = get_option( MSH_Remote_Sync::CONFLICT_STRATEGY_OPTION, 'local_wins' );
				$auto_sync_schedule = get_option( 'msh_auto_sync_schedule', 'off' );
				$quota_data = $sync_instance->get_quota();
				?>

				<!-- Cloud Sync Status Section -->
				<section class="msh-settings-card">
					<header>
						<h2><?php esc_html_e( 'Cloud Sync Status', 'msh-image-optimizer' ); ?></h2>
						<p><?php esc_html_e( 'Keep track of your synchronization status, connected site ID, and quota usage.', 'msh-image-optimizer' ); ?></p>
					</header>
					<div class="msh-settings-grid">
						<div class="msh-settings-field">
							<label><?php esc_html_e( 'Sync Status:', 'msh-image-optimizer' ); ?></label>
							<div><strong><?php echo $sync_status['enabled'] ? esc_html__( 'Active', 'msh-image-optimizer' ) : esc_html__( 'Inactive', 'msh-image-optimizer' ); ?></strong></div>
						</div>
						<?php if ( ! empty( $sync_status['site_id'] ) ) : ?>
						<div class="msh-settings-field">
							<label><?php esc_html_e( 'Site ID:', 'msh-image-optimizer' ); ?></label>
							<div><code><?php echo esc_html( $sync_status['site_id'] ); ?></code></div>
						</div>
						<?php endif; ?>
						<div class="msh-settings-field">
							<label><?php esc_html_e( 'Last Sync:', 'msh-image-optimizer' ); ?></label>
							<div><strong><?php echo esc_html( $sync_status['last_sync_ago'] ); ?></strong></div>
						</div>
						<div class="msh-settings-field">
							<label><?php esc_html_e( 'Pending Changes:', 'msh-image-optimizer' ); ?></label>
							<div><strong><?php echo esc_html( number_format_i18n( $sync_status['pending'] ) ); ?></strong></div>
						</div>
						<?php if ( ! empty( $quota_data ) && isset( $quota_data['used'], $quota_data['limit'] ) ) : ?>
						<div class="msh-settings-field" style="grid-column: 1 / -1;">
							<label><?php esc_html_e( 'Quota Usage:', 'msh-image-optimizer' ); ?></label>
							<div style="margin-top: 8px;">
								<div style="background: #e0e0e0; border-radius: 4px; height: 20px; overflow: hidden; position: relative;">
									<div style="background: linear-gradient(90deg, #4CAF50, #45a049); height: 100%; width: <?php echo esc_attr( min( 100, ( $quota_data['used'] / max( 1, $quota_data['limit'] ) ) * 100 ) ); ?>%; transition: width 0.3s ease;"></div>
								</div>
								<p class="msh-settings-note" style="margin-top: 4px;">
									<?php
									printf(
										/* translators: 1: used syncs, 2: total syncs, 3: period */
										esc_html__( '%1$s / %2$s syncs this %3$s', 'msh-image-optimizer' ),
										'<strong>' . esc_html( number_format_i18n( $quota_data['used'] ) ) . '</strong>',
										'<strong>' . esc_html( number_format_i18n( $quota_data['limit'] ) ) . '</strong>',
										'<strong>' . esc_html( $quota_data['period'] ?? 'month' ) . '</strong>'
									);
									?>
								</p>
							</div>
						</div>
						<?php endif; ?>
					</div>
				</section>

				<!-- Sync Preferences Section -->
				<section class="msh-settings-card">
					<header>
						<h2><?php esc_html_e( 'Sync Preferences', 'msh-image-optimizer' ); ?></h2>
						<p><?php esc_html_e( 'Choose how the plugin handles conflicting edits between your local site and the cloud.', 'msh-image-optimizer' ); ?></p>
					</header>
					<div class="msh-settings-grid">
						<div class="msh-settings-field">
							<label for="msh-conflict-strategy"><?php esc_html_e( 'Conflict Resolution:', 'msh-image-optimizer' ); ?></label>
							<select name="msh_sync_conflict_strategy" id="msh-conflict-strategy" style="max-width: 400px; margin: 8px 0;">
								<option value="local_wins" <?php selected( $conflict_strategy, 'local_wins' ); ?>>
									<?php esc_html_e( 'Local version (default) — Your local edits are never silently overwritten.', 'msh-image-optimizer' ); ?>
								</option>
								<option value="remote_wins" <?php selected( $conflict_strategy, 'remote_wins' ); ?>>
									<?php esc_html_e( 'Cloud version — Always keep the version stored in your cloud.', 'msh-image-optimizer' ); ?>
								</option>
								<option value="manual" <?php selected( $conflict_strategy, 'manual' ); ?>>
									<?php esc_html_e( 'Ask each time — Review conflicts manually in the Sync Hub.', 'msh-image-optimizer' ); ?>
								</option>
							</select>
							<p class="msh-settings-note">
								<?php esc_html_e( 'When both your local site and cloud have different changes to the same image metadata, this setting decides which version wins. Local version (default) protects your work by never overwriting local edits during automatic sync.', 'msh-image-optimizer' ); ?>
							</p>
						</div>
						<div class="msh-settings-field">
							<label for="msh-auto-sync-schedule"><?php esc_html_e( 'Auto Sync:', 'msh-image-optimizer' ); ?></label>
							<select name="msh_auto_sync_schedule" id="msh-auto-sync-schedule" style="max-width: 400px; margin: 8px 0;">
								<option value="off" <?php selected( $auto_sync_schedule, 'off' ); ?>>
									<?php esc_html_e( 'Off — Manual sync only', 'msh-image-optimizer' ); ?>
								</option>
								<option value="hourly" <?php selected( $auto_sync_schedule, 'hourly' ); ?>>
									<?php esc_html_e( 'Hourly — Sync automatically every hour', 'msh-image-optimizer' ); ?>
								</option>
								<option value="daily" <?php selected( $auto_sync_schedule, 'daily' ); ?>>
									<?php esc_html_e( 'Daily — Sync automatically once per day', 'msh-image-optimizer' ); ?>
								</option>
							</select>
							<p class="msh-settings-note">
								<?php esc_html_e( 'Choose how often your metadata should automatically sync with the cloud. You can always trigger a manual sync from the Sync Hub.', 'msh-image-optimizer' ); ?>
							</p>
						</div>
					</div>
				</section>

				<!-- Manage Sync Section -->
				<section class="msh-settings-card">
					<header>
						<h2><?php esc_html_e( 'Manage Sync', 'msh-image-optimizer' ); ?></h2>
						<p><?php esc_html_e( 'Go to the Sync Hub to trigger manual syncs, check activity logs, and view your quota usage.', 'msh-image-optimizer' ); ?></p>
					</header>
					<div class="msh-settings-grid">
						<div class="msh-settings-field">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-hub&tab=sync' ) ); ?>" class="button button-dot-primary" style="display: inline-block; margin: 8px 0; min-width: 140px; width: fit-content; padding: 0 12px;">
								<?php esc_html_e( 'Open Sync Hub', 'msh-image-optimizer' ); ?>
							</a>
							<p class="msh-settings-note">
								<?php esc_html_e( 'The Sync Hub is your central control panel for all sync operations, including viewing recent activity and resolving conflicts.', 'msh-image-optimizer' ); ?>
							</p>
						</div>
						<?php
						$last_activity_log = get_option( 'msh_last_sync_log', array() );
						if ( ! empty( $last_activity_log ) && is_array( $last_activity_log ) ) :
						?>
						<div class="msh-settings-field">
							<label><?php esc_html_e( 'Last Activity:', 'msh-image-optimizer' ); ?></label>
							<div>
								<?php
								$log_time = ! empty( $last_activity_log['time'] ) ? $last_activity_log['time'] : 0;
								$log_action = ! empty( $last_activity_log['action'] ) ? $last_activity_log['action'] : 'sync';
								$log_status = ! empty( $last_activity_log['status'] ) ? $last_activity_log['status'] : 'unknown';

								if ( $log_time ) {
									$time_ago = human_time_diff( $log_time, current_time( 'timestamp' ) );
									printf(
										/* translators: 1: action, 2: status, 3: time ago */
										esc_html__( '%1$s - %2$s (%3$s ago)', 'msh-image-optimizer' ),
										'<strong>' . esc_html( ucfirst( $log_action ) ) . '</strong>',
										'<span style="color: ' . ( $log_status === 'success' ? '#4CAF50' : '#f44336' ) . ';">' . esc_html( $log_status ) . '</span>',
										esc_html( $time_ago )
									);
								}
								?>
							</div>
							<p class="msh-settings-note">
								<?php esc_html_e( 'Track your most recent sync activity. View full history and details in the Sync Hub.', 'msh-image-optimizer' ); ?>
							</p>
						</div>
						<?php endif; ?>
					</div>
				</section>

			<?php endif; // End Sync Tab ?>

				<!-- ACCOUNT TAB -->
				<?php if ( 'account' === $active_tab ) : ?>

				<section class="msh-settings-card msh-settings-card--license">
					<header>
						<h2><?php esc_html_e( 'License', 'msh-image-optimizer' ); ?></h2>
						<p><?php esc_html_e( 'Manage your subscription, view license status, and access billing portal.', 'msh-image-optimizer' ); ?></p>
					</header>
					<div class="msh-settings-grid">
						<?php
						$is_pro = function_exists( 'msh_is_pro_active' ) && msh_is_pro_active();
						if ( $is_pro ) :
							// License is active - show status and manage subscription
							?>
							<div class="msh-license-status">
								<div class="msh-license-badge msh-license-active">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Pro License Active', 'msh-image-optimizer' ); ?>
								</div>
								<p><?php esc_html_e( 'Your Pro license is active and all features are available.', 'msh-image-optimizer' ); ?></p>
							</div>
							<div class="msh-settings-field">
								<a href="https://billing.thedot.com/customer-portal" target="_blank" class="button button-dot-secondary">
									<?php esc_html_e( 'Manage Subscription', 'msh-image-optimizer' ); ?>
									<span class="dashicons dashicons-external"></span>
								</a>
								<p class="msh-settings-note">
									<?php esc_html_e( 'Update payment method, view invoices, or cancel your subscription.', 'msh-image-optimizer' ); ?>
								</p>
							</div>
						<?php else : ?>
							<!-- Free plan - show upgrade options -->
							<div class="msh-license-status">
								<div class="msh-license-badge msh-license-free">
									<span class="dashicons dashicons-info"></span>
									<?php esc_html_e( 'Free Plan', 'msh-image-optimizer' ); ?>
								</div>
								<p><?php esc_html_e( 'Upgrade to Pro to unlock advanced features like Hub Sync, AI Automation, and priority support.', 'msh-image-optimizer' ); ?></p>
							</div>
							<div class="msh-settings-field">
								<a href="https://thedot.com/pricing" target="_blank" class="button button-dot-primary">
									<?php esc_html_e( 'Upgrade to Pro', 'msh-image-optimizer' ); ?>
								</a>
								<p class="msh-settings-note">
									<?php esc_html_e( 'View pricing plans and features comparison.', 'msh-image-optimizer' ); ?>
								</p>
							</div>
						<?php endif; ?>
					</div>
				</section>

				<?php endif; // End Account Tab ?>

				<div class="msh-settings-actions">
					<?php submit_button( __( 'Save Settings', 'msh-image-optimizer' ), 'button-dot-primary', 'submit', false ); ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'msh_saved' => 0 ), admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ) ); ?>" class="button button-dot-secondary">
						<?php esc_html_e( 'Cancel', 'msh-image-optimizer' ); ?>
					</a>
				</div>
			</form>

			<?php $this->render_profile_template( $labels ); ?>
		</div>
		<?php
	}

	/**
	 * Handle form submission.
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'msh-image-optimizer' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		// Preserve active tab on redirect
		$active_tab = isset( $_POST['active_tab'] ) ? sanitize_text_field( $_POST['active_tab'] ) : 'general';
		$redirect_url = add_query_arg( array( 'tab' => $active_tab ), admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );

		$primary_raw = isset( $_POST['primary'] ) ? wp_unslash( $_POST['primary'] ) : array();
		$primary     = MSH_Image_Optimizer_Context_Helper::sanitize_context(
			$primary_raw,
			true,
			isset( $primary_raw['updated_at'] ) ? absint( $primary_raw['updated_at'] ) : 0
		);

		update_option( self::PRIMARY_OPTION, $primary, false );

		$profiles_raw       = isset( $_POST['profiles'] ) ? wp_unslash( $_POST['profiles'] ) : array();
		$sanitized_profiles = $this->sanitize_profiles( $profiles_raw );
		update_option( self::PROFILES_OPTION, $sanitized_profiles, false );

		$options_raw = isset( $_POST['options'] ) ? wp_unslash( $_POST['options'] ) : array();

		$rename_enabled = ( isset( $options_raw['rename_enabled'] ) && '1' === (string) $options_raw['rename_enabled'] ) ? '1' : '0';
		update_option( 'msh_enable_file_rename', $rename_enabled, false );

		$user_mode = isset( $options_raw['user_mode'] ) ? sanitize_text_field( $options_raw['user_mode'] ) : 'basic';
		if ( ! in_array( $user_mode, array( 'basic', 'advanced' ), true ) ) {
			$user_mode = 'basic';
		}
		update_option( 'msh_user_mode', $user_mode, false );

		$ai_mode = isset( $options_raw['ai_mode'] ) ? sanitize_text_field( $options_raw['ai_mode'] ) : 'manual';
		if ( ! in_array( $ai_mode, array( 'manual', 'assist', 'hybrid' ), true ) ) {
			$ai_mode = 'manual';
		}
		update_option( 'msh_ai_mode', $ai_mode, false );

		$ai_api_key = isset( $options_raw['ai_api_key'] ) ? sanitize_text_field( $options_raw['ai_api_key'] ) : '';
		update_option( 'msh_ai_api_key', $ai_api_key, false );

		$ai_live_link_url = isset( $options_raw['ai_live_link_url'] ) ? esc_url_raw( $options_raw['ai_live_link_url'] ) : '';
		update_option( 'msh_ai_live_link_url', $ai_live_link_url, false );

		$ai_features_sanitized = array();
		if ( isset( $options_raw['ai_features'] ) && is_array( $options_raw['ai_features'] ) ) {
			$allowed_features = array( 'meta', 'vision', 'filename', 'duplicate' );
			foreach ( $options_raw['ai_features'] as $feature ) {
				$feature = sanitize_text_field( $feature );
				if ( in_array( $feature, $allowed_features, true ) ) {
					$ai_features_sanitized[] = $feature;
				}
			}
		}
		update_option( 'msh_ai_features', $ai_features_sanitized, false );

		$pending_active_profile = isset( $options_raw['active_profile'] ) ? sanitize_text_field( $options_raw['active_profile'] ) : 'primary';
		$available_profiles     = MSH_Image_Optimizer_Context_Helper::get_profiles();
		if ( 'primary' !== $pending_active_profile && ! isset( $available_profiles[ $pending_active_profile ] ) ) {
			$pending_active_profile = 'primary';
		}
		update_option( 'msh_active_context_profile', $pending_active_profile, false );

		// Save Sync settings
		if ( isset( $_POST['msh_sync_conflict_strategy'] ) ) {
			$conflict_strategy = sanitize_text_field( $_POST['msh_sync_conflict_strategy'] );
			$allowed_strategies = array( 'local_wins', 'remote_wins', 'manual' );
			if ( in_array( $conflict_strategy, $allowed_strategies, true ) ) {
				update_option( MSH_Remote_Sync::CONFLICT_STRATEGY_OPTION, $conflict_strategy, false );
			}
		}

		if ( isset( $_POST['msh_auto_sync_schedule'] ) ) {
			$auto_sync_schedule = sanitize_text_field( $_POST['msh_auto_sync_schedule'] );
			$allowed_schedules = array( 'off', 'hourly', 'daily' );
			if ( in_array( $auto_sync_schedule, $allowed_schedules, true ) ) {
				update_option( 'msh_auto_sync_schedule', $auto_sync_schedule, false );

				// Schedule or unschedule the cron job
				$this->update_sync_cron_schedule( $auto_sync_schedule );
			}
		}

		wp_safe_redirect( add_query_arg( 'msh_saved', '1', $redirect_url ) );
		exit;
	}

	/**
	 * Update WP-Cron schedule for auto-sync.
	 *
	 * @param string $schedule 'off', 'hourly', or 'daily'.
	 * @return void
	 */
	private function update_sync_cron_schedule( $schedule ) {
		$hook = 'msh_auto_sync_cron';

		// Clear existing scheduled event
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}

		// Schedule new event if not 'off'
		if ( 'hourly' === $schedule ) {
			wp_schedule_event( time(), 'hourly', $hook );
		} elseif ( 'daily' === $schedule ) {
			wp_schedule_event( time(), 'daily', $hook );
		}
	}

	/**
	 * Sanitize submitted profiles.
	 *
	 * @param array $profiles_raw Raw profiles data.
	 * @return array
	 */
	private function sanitize_profiles( $profiles_raw ) {
		if ( ! is_array( $profiles_raw ) || empty( $profiles_raw ) ) {
			return array();
		}

		$sanitized = array();
		$seen_ids  = array();

		foreach ( $profiles_raw as $submitted_profile ) {
			if ( ! is_array( $submitted_profile ) ) {
				continue;
			}

			$label  = isset( $submitted_profile['label'] ) ? sanitize_text_field( $submitted_profile['label'] ) : '';
			$usage  = isset( $submitted_profile['usage'] ) ? sanitize_text_field( $submitted_profile['usage'] ) : '';
			$locale = isset( $submitted_profile['locale'] ) ? sanitize_text_field( $submitted_profile['locale'] ) : '';
			$notes  = isset( $submitted_profile['notes'] ) ? sanitize_textarea_field( $submitted_profile['notes'] ) : '';

			$context_raw = isset( $submitted_profile['context'] ) ? $submitted_profile['context'] : array();
			$context     = MSH_Image_Optimizer_Context_Helper::sanitize_context(
				$context_raw,
				true,
				isset( $context_raw['updated_at'] ) ? absint( $context_raw['updated_at'] ) : 0
			);

			// Require at least label and business name to consider profile valid.
			if ( '' === $label || '' === $context['business_name'] ) {
				continue;
			}

			$profile_id = isset( $submitted_profile['id'] ) ? sanitize_title( $submitted_profile['id'] ) : '';
			if ( '' === $profile_id ) {
				$profile_id = sanitize_title( $label );
			}

			if ( '' === $profile_id ) {
				$profile_id = uniqid( 'context_', false );
			}

			$original_id = $profile_id;
			$suffix      = 1;
			while ( isset( $seen_ids[ $profile_id ] ) ) {
				$profile_id = $original_id . '-' . $suffix;
				++$suffix;
			}

			$seen_ids[ $profile_id ] = true;

			$sanitized[ $profile_id ] = array(
				'id'         => $profile_id,
				'label'      => $label,
				'usage'      => $usage,
				'locale'     => $locale,
				'notes'      => $notes,
				'context'    => $context,
				'updated_at' => $context['updated_at'],
			);
		}

		return $sanitized;
	}

	/**
	 * Fetch usage index stats for display.
	 *
	 * @return array|false
	 */
	private function get_usage_index_stats() {
		try {
			if ( class_exists( 'MSH_Image_Usage_Index' ) ) {
				$usage_index = MSH_Image_Usage_Index::get_instance();
				$stats       = $usage_index->get_index_stats();

				if ( $stats && isset( $stats['summary'] ) && $stats['summary']->total_entries > 0 ) {
					return array(
						'total_entries'      => (int) $stats['summary']->total_entries,
						'unique_attachments' => (int) $stats['summary']->indexed_attachments,
						'last_update'        => $stats['summary']->last_update,
					);
				}
			}
		} catch ( Exception $e ) {
			// Silent fail – diagnostics card will show placeholders.
		}

		return false;
	}

	/**
	 * Build diagnostics snapshot from stored timestamps.
	 *
	 * @return array
	 */
	private function get_diagnostics_snapshot() {
		return array(
			'last_analyzer_run'     => $this->format_datetime( get_option( 'msh_last_analyzer_run' ) ),
			'last_optimization_run' => $this->format_datetime( get_option( 'msh_last_optimization_run' ) ),
			'last_duplicate_scan'   => $this->format_datetime( get_option( 'msh_last_duplicate_scan' ) ),
			'last_visual_scan'      => $this->format_datetime( get_option( 'msh_last_visual_scan' ) ),
			'last_cli_run'          => $this->format_datetime( get_option( 'msh_last_cli_optimization' ) ),
		);
	}

	/**
	 * Format stored datetime into a localized string.
	 *
	 * @param string|false $value Raw value from database.
	 * @return string
	 */
	private function format_datetime( $value ) {
		if ( empty( $value ) ) {
			return __( '—', 'msh-image-optimizer' );
		}

		$timestamp = strtotime( $value );
		if ( ! $timestamp ) {
			return $value;
		}

		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		return date_i18n( $format, $timestamp );
	}

	/**
	 * Fetch stored context profiles.
	 *
	 * @return array
	 */
	private function get_context_profiles() {
		$profiles = get_option( self::PROFILES_OPTION, array() );
		if ( ! is_array( $profiles ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $profiles as $profile_id => $profile ) {
			if ( ! is_array( $profile ) ) {
				continue;
			}

			$context = isset( $profile['context'] )
				? MSH_Image_Optimizer_Context_Helper::sanitize_context( $profile['context'], false )
				: array();

			$sanitized[ $profile_id ] = array(
				'id'      => isset( $profile['id'] ) ? sanitize_title( $profile['id'] ) : $profile_id,
				'label'   => isset( $profile['label'] ) ? sanitize_text_field( $profile['label'] ) : '',
				'usage'   => isset( $profile['usage'] ) ? sanitize_text_field( $profile['usage'] ) : '',
				'locale'  => isset( $profile['locale'] ) ? sanitize_text_field( $profile['locale'] ) : '',
				'notes'   => isset( $profile['notes'] ) ? sanitize_textarea_field( $profile['notes'] ) : '',
				'context' => $context,
			);
		}

		return $sanitized;
	}

	/**
	 * Output text input.
	 */
	private function render_text_input( $name, $label, $value = '', $required = false, $placeholder = '' ) {
		?>
		<div class="msh-settings-field">
			<label>
				<span><?php echo esc_html( $label ); ?><?php echo $required ? '<span class="required">*</span>' : ''; ?></span>
				<input
					type="text"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					class="msh-input"
					<?php echo $required ? 'required' : ''; ?>
					<?php echo $placeholder ? 'placeholder="' . esc_attr( $placeholder ) . '"' : ''; ?>
				/>
			</label>
		</div>
		<?php
	}

	/**
	 * Output textarea.
	 */
	private function render_textarea( $name, $label, $value = '', $placeholder = '', $required = false ) {
		?>
		<div class="msh-settings-field">
			<label>
				<span><?php echo esc_html( $label ); ?><?php echo $required ? '<span class="required">*</span>' : ''; ?></span>
				<textarea
					name="<?php echo esc_attr( $name ); ?>"
					rows="3"
					class="msh-textarea"
					<?php echo $required ? 'required' : ''; ?>
					<?php echo $placeholder ? 'placeholder="' . esc_attr( $placeholder ) . '"' : ''; ?>
				><?php echo esc_textarea( $value ); ?></textarea>
			</label>
		</div>
		<?php
	}

	/**
	 * Output select input.
	 */
	private function render_select_input( $name, $label, $choices, $selected = '', $required = false ) {
		?>
		<div class="msh-settings-field">
			<label>
				<span><?php echo esc_html( $label ); ?><?php echo $required ? '<span class="required">*</span>' : ''; ?></span>
				<select name="<?php echo esc_attr( $name ); ?>" class="msh-select" <?php echo $required ? 'required' : ''; ?>>
					<option value=""><?php esc_html_e( 'Select…', 'msh-image-optimizer' ); ?></option>
					<?php foreach ( $choices as $value => $choice_label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>>
							<?php echo esc_html( $choice_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>
		<?php
	}

	/**
	 * Output radio group.
	 */
	private function render_radio_group( $name, $label, $choices, $selected = '', $required = false ) {
		?>
		<fieldset class="msh-settings-field msh-settings-radio">
			<legend><?php echo esc_html( $label ); ?><?php echo $required ? '<span class="required">*</span>' : ''; ?></legend>
			<div class="msh-radio-grid">
				<?php foreach ( $choices as $value => $choice_label ) : ?>
					<label>
						<input
							type="radio"
							name="<?php echo esc_attr( $name ); ?>"
							value="<?php echo esc_attr( $value ); ?>"
							<?php checked( $selected, $value ); ?>
							<?php echo $required ? 'required' : ''; ?>
						/>
						<span><?php echo esc_html( $choice_label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Render an individual profile fieldset.
	 *
	 * @param int   $index Position index.
	 * @param array $profile Profile data.
	 * @param array $labels Label map.
	 */
	private function render_profile_fieldset( $index, $profile, $labels ) {
		$context = isset( $profile['context'] ) ? $profile['context'] : array();
		?>
		<fieldset class="msh-profile" data-index="<?php echo esc_attr( $index ); ?>">
			<div class="msh-profile-header">
				<div class="msh-settings-field">
					<label>
						<span><?php esc_html_e( 'Profile Label', 'msh-image-optimizer' ); ?></span>
						<input type="text" name="profiles[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $profile['label'] ); ?>" class="msh-input" required>
					</label>
				</div>
				<div class="msh-settings-field">
					<label>
						<span><?php esc_html_e( 'Profile Key', 'msh-image-optimizer' ); ?></span>
						<input type="text" name="profiles[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $profile['id'] ); ?>" class="msh-input" placeholder="<?php esc_attr_e( 'Auto-generated from label', 'msh-image-optimizer' ); ?>">
					</label>
				</div>
				<div class="msh-settings-field">
					<label>
						<span><?php esc_html_e( 'Usage', 'msh-image-optimizer' ); ?></span>
						<select name="profiles[<?php echo esc_attr( $index ); ?>][usage]" class="msh-select">
							<option value=""><?php esc_html_e( 'Select usage…', 'msh-image-optimizer' ); ?></option>
							<option value="landing_page" <?php selected( isset( $profile['usage'] ) ? $profile['usage'] : '', 'landing_page' ); ?>>
								<?php esc_html_e( 'Landing Page', 'msh-image-optimizer' ); ?>
							</option>
							<option value="locale" <?php selected( isset( $profile['usage'] ) ? $profile['usage'] : '', 'locale' ); ?>>
								<?php esc_html_e( 'Locale / Language', 'msh-image-optimizer' ); ?>
							</option>
							<option value="campaign" <?php selected( isset( $profile['usage'] ) ? $profile['usage'] : '', 'campaign' ); ?>>
								<?php esc_html_e( 'Campaign', 'msh-image-optimizer' ); ?>
							</option>
							<option value="custom" <?php selected( isset( $profile['usage'] ) ? $profile['usage'] : '', 'custom' ); ?>>
								<?php esc_html_e( 'Custom', 'msh-image-optimizer' ); ?>
							</option>
						</select>
					</label>
				</div>
				<div class="msh-settings-field">
					<label>
						<span><?php esc_html_e( 'Locale code (optional)', 'msh-image-optimizer' ); ?></span>
						<input type="text" name="profiles[<?php echo esc_attr( $index ); ?>][locale]" value="<?php echo esc_attr( $profile['locale'] ); ?>" class="msh-input" placeholder="en-US">
					</label>
				</div>
			</div>

			<div class="msh-settings-field">
				<label>
					<span><?php esc_html_e( 'Notes', 'msh-image-optimizer' ); ?></span>
					<textarea name="profiles[<?php echo esc_attr( $index ); ?>][notes]" rows="3" class="msh-textarea"><?php echo esc_textarea( isset( $profile['notes'] ) ? $profile['notes'] : '' ); ?></textarea>
				</label>
			</div>

			<details class="msh-profile-details" open>
				<summary><?php esc_html_e( 'Context Details', 'msh-image-optimizer' ); ?></summary>
				<div class="msh-settings-grid">
					<?php $this->render_text_input( "profiles[{$index}][context][business_name]", __( 'Business Name', 'msh-image-optimizer' ), isset( $context['business_name'] ) ? $context['business_name'] : '', true ); ?>
					<?php $this->render_select_input( "profiles[{$index}][context][industry]", __( 'Industry', 'msh-image-optimizer' ), $labels['industry'], isset( $context['industry'] ) ? $context['industry'] : '', true ); ?>
					<?php $this->render_select_input( "profiles[{$index}][context][business_type]", __( 'Business Type', 'msh-image-optimizer' ), $labels['business_type'], isset( $context['business_type'] ) ? $context['business_type'] : '', true ); ?>
					<?php $this->render_text_input( "profiles[{$index}][context][target_audience]", __( 'Ideal customer', 'msh-image-optimizer' ), isset( $context['target_audience'] ) ? $context['target_audience'] : '', true ); ?>
					<?php $this->render_textarea( "profiles[{$index}][context][pain_points]", __( 'Problems you solve', 'msh-image-optimizer' ), isset( $context['pain_points'] ) ? $context['pain_points'] : '' ); ?>
					<?php $this->render_text_input( "profiles[{$index}][context][demographics]", __( 'Demographics (optional)', 'msh-image-optimizer' ), isset( $context['demographics'] ) ? $context['demographics'] : '' ); ?>
					<?php $this->render_radio_group( "profiles[{$index}][context][brand_voice]", __( 'Brand voice', 'msh-image-optimizer' ), $labels['brand_voice'], isset( $context['brand_voice'] ) ? $context['brand_voice'] : '', true ); ?>
					<?php $this->render_textarea( "profiles[{$index}][context][uvp]", __( 'What makes you different?', 'msh-image-optimizer' ), isset( $context['uvp'] ) ? $context['uvp'] : '', '', true ); ?>
					<?php $this->render_select_input( "profiles[{$index}][context][cta_preference]", __( 'Call-to-action style', 'msh-image-optimizer' ), $labels['cta_preference'], isset( $context['cta_preference'] ) ? $context['cta_preference'] : '' ); ?>
					<?php $this->render_text_input( "profiles[{$index}][context][city]", __( 'City', 'msh-image-optimizer' ), isset( $context['city'] ) ? $context['city'] : '' ); ?>
					<?php $this->render_text_input( "profiles[{$index}][context][region]", __( 'Province / Region', 'msh-image-optimizer' ), isset( $context['region'] ) ? $context['region'] : '' ); ?>
					<?php $this->render_text_input( "profiles[{$index}][context][country]", __( 'Country', 'msh-image-optimizer' ), isset( $context['country'] ) ? $context['country'] : '' ); ?>
					<?php $this->render_text_input( "profiles[{$index}][context][service_area]", __( 'Service area', 'msh-image-optimizer' ), isset( $context['service_area'] ) ? $context['service_area'] : '' ); ?>

					<div class="msh-settings-field msh-settings-checkbox">
						<label>
							<input type="checkbox" name="profiles[<?php echo esc_attr( $index ); ?>][context][ai_interest]" value="1" <?php checked( ! empty( $context['ai_interest'] ) ); ?>>
							<span><?php esc_html_e( 'Subscribe to AI feature updates', 'msh-image-optimizer' ); ?></span>
						</label>
					</div>

					<input type="hidden" name="profiles[<?php echo esc_attr( $index ); ?>][context][updated_at]" value="<?php echo esc_attr( isset( $context['updated_at'] ) ? (int) $context['updated_at'] : 0 ); ?>">
				</div>
			</details>

			<button type="button" class="button link-delete msh-remove-profile"><?php esc_html_e( 'Remove profile', 'msh-image-optimizer' ); ?></button>
		</fieldset>
		<?php
	}

	/**
	 * Render template element for JS cloning.
	 *
	 * @param array $labels Label map.
	 */
	private function render_profile_template( $labels ) {
		?>
		<template id="msh-profile-template">
			<?php
			$placeholder_profile = array(
				'id'      => '',
				'label'   => '',
				'usage'   => '',
				'locale'  => '',
				'notes'   => '',
				'context' => array(
					'business_name'   => '',
					'industry'        => '',
					'business_type'   => '',
					'target_audience' => '',
					'pain_points'     => '',
					'demographics'    => '',
					'brand_voice'     => '',
					'uvp'             => '',
					'cta_preference'  => '',
					'city'            => '',
					'region'          => '',
					'country'         => '',
					'service_area'    => '',
					'ai_interest'     => false,
					'updated_at'      => 0,
				),
			);
			$this->render_profile_fieldset( '__index__', $placeholder_profile, $labels );
			?>
		</template>
		<?php
	}

	/**
	 * Display admin notice helper.
	 *
	 * @param string $message The message.
	 * @param string $type Notice type.
	 */
	private function display_notice( $message, $type = 'updated' ) {
		printf(
			'<div class="notice %1$s"><p>%2$s</p></div>',
			esc_attr( $type ),
			wp_kses_post( $message )
		);
	}

	/**
	 * Output notices via hook (extensibility).
	 */
	public function output_notice() {
		// Placeholder for future notice system.
	}
}

new MSH_Image_Optimizer_Settings();
