<?php
/**
 * Onboarding Wizard
 *
 * First-time setup wizard for new MSH Image Optimizer installations.
 * Guides users through initial configuration and feature discovery.
 *
 * @package MSH_Image_Optimizer
 * @subpackage Enterprise
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Onboarding_Wizard {
	/**
	 * Singleton instance.
	 *
	 * @var MSH_Onboarding_Wizard
	 */
	private static $instance = null;

	/**
	 * Onboarding completed option key.
	 *
	 * @var string
	 */
	const ONBOARDING_COMPLETED = 'msh_onboarding_completed';

	/**
	 * Onboarding context option key (stores user selections).
	 *
	 * @var string
	 */
	const ONBOARDING_CONTEXT = 'msh_onboarding_context';

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Onboarding_Wizard
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Show onboarding wizard on plugin activation
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_onboarding' ) );

		// AJAX handlers
		add_action( 'wp_ajax_msh_onboarding_save_step', array( $this, 'ajax_save_step' ) );
		add_action( 'wp_ajax_msh_onboarding_complete', array( $this, 'ajax_complete' ) );
		add_action( 'wp_ajax_msh_onboarding_skip', array( $this, 'ajax_skip' ) );

		// Admin menu (hidden, accessible only during onboarding)
		add_action( 'admin_menu', array( $this, 'register_menu' ), 99 );

		// Enqueue assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Check if onboarding should be shown and redirect if needed.
	 *
	 * @return void
	 */
	public function maybe_redirect_to_onboarding() {
		// Only redirect once per activation
		if ( get_transient( 'msh_onboarding_redirect' ) ) {
			delete_transient( 'msh_onboarding_redirect' );

			// Don't redirect if already completed or if doing AJAX/CLI
			if ( $this->is_completed() || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
				return;
			}

			// Redirect to onboarding
			wp_safe_redirect( admin_url( 'admin.php?page=msh-onboarding' ) );
			exit;
		}
	}

	/**
	 * Register admin menu (hidden from main nav).
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			null, // Parent slug = null hides from menu
			__( 'Welcome to MSH Image Optimizer', 'msh-image-optimizer' ),
			__( 'Onboarding', 'msh-image-optimizer' ),
			'manage_options',
			'msh-onboarding',
			array( $this, 'render_wizard' )
		);
	}

	/**
	 * Enqueue assets for onboarding wizard.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'admin_page_msh-onboarding' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'msh-onboarding',
			MSH_IO_ASSETS_URL . 'css/onboarding.css',
			array(),
			MSH_Image_Optimizer_Plugin::VERSION
		);

		wp_enqueue_script(
			'msh-onboarding',
			MSH_IO_ASSETS_URL . 'js/onboarding.js',
			array( 'jquery' ),
			MSH_Image_Optimizer_Plugin::VERSION,
			true
		);

		wp_localize_script(
			'msh-onboarding',
			'mshOnboardingData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'msh_onboarding' ),
				'strings' => array(
					'saving'   => __( 'Saving...', 'msh-image-optimizer' ),
					'saved'    => __( 'Saved!', 'msh-image-optimizer' ),
					'error'    => __( 'Error saving. Please try again.', 'msh-image-optimizer' ),
					'skipping' => __( 'Skipping...', 'msh-image-optimizer' ),
				),
			)
		);
	}

	/**
	 * Render onboarding wizard.
	 *
	 * @return void
	 */
	public function render_wizard() {
		$current_step = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
		$context      = get_option( self::ONBOARDING_CONTEXT, array() );
		?>
		<div class="msh-onboarding-wizard">
			<div class="msh-onboarding-header">
				<h1><?php esc_html_e( 'Welcome to MSH Image Optimizer', 'msh-image-optimizer' ); ?></h1>
				<p class="msh-onboarding-subtitle"><?php esc_html_e( 'Let\'s get you set up in just a few steps', 'msh-image-optimizer' ); ?></p>
			</div>

			<div class="msh-onboarding-progress">
				<div class="msh-progress-bar">
					<div class="msh-progress-fill" style="width: <?php echo esc_attr( ( $current_step / 4 ) * 100 ); ?>%"></div>
				</div>
				<div class="msh-progress-steps">
					<span class="<?php echo $current_step >= 1 ? 'active' : ''; ?>"><?php esc_html_e( 'API Keys', 'msh-image-optimizer' ); ?></span>
					<span class="<?php echo $current_step >= 2 ? 'active' : ''; ?>"><?php esc_html_e( 'Languages', 'msh-image-optimizer' ); ?></span>
					<span class="<?php echo $current_step >= 3 ? 'active' : ''; ?>"><?php esc_html_e( 'Features', 'msh-image-optimizer' ); ?></span>
					<span class="<?php echo $current_step >= 4 ? 'active' : ''; ?>"><?php esc_html_e( 'Finish', 'msh-image-optimizer' ); ?></span>
				</div>
			</div>

			<div class="msh-onboarding-content">
				<?php
				switch ( $current_step ) {
					case 1:
						$this->render_step_api_keys( $context );
						break;
					case 2:
						$this->render_step_languages( $context );
						break;
					case 3:
						$this->render_step_features( $context );
						break;
					case 4:
						$this->render_step_finish( $context );
						break;
				}
				?>
			</div>

			<div class="msh-onboarding-footer">
				<?php if ( $current_step > 1 ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-onboarding&step=' . ( $current_step - 1 ) ) ); ?>" class="button msh-button-secondary">
						<?php esc_html_e( 'Back', 'msh-image-optimizer' ); ?>
					</a>
				<?php endif; ?>

				<button type="button" class="button button-link msh-onboarding-skip">
					<?php esc_html_e( 'Skip Setup', 'msh-image-optimizer' ); ?>
				</button>

				<?php if ( $current_step < 4 ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-onboarding&step=' . ( $current_step + 1 ) ) ); ?>" class="button msh-button-primary msh-onboarding-next">
						<?php esc_html_e( 'Next', 'msh-image-optimizer' ); ?>
					</a>
				<?php else : ?>
					<button type="button" class="button msh-button-primary msh-onboarding-finish">
						<?php esc_html_e( 'Start Using MSH Image Optimizer', 'msh-image-optimizer' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Step 1: API Keys.
	 *
	 * @param array $context Saved onboarding context.
	 * @return void
	 */
	private function render_step_api_keys( $context ) {
		$api_key = get_option( 'msh_openai_api_key', '' );
		?>
		<div class="msh-onboarding-step" data-step="1">
			<h2><?php esc_html_e( 'Connect Your AI Provider', 'msh-image-optimizer' ); ?></h2>
			<p><?php esc_html_e( 'MSH Image Optimizer uses AI to generate SEO-optimized metadata for your images. Connect your OpenAI or Anthropic account to get started.', 'msh-image-optimizer' ); ?></p>

			<div class="msh-onboarding-form">
				<div class="msh-form-group">
					<label for="msh-api-provider">
						<strong><?php esc_html_e( 'AI Provider', 'msh-image-optimizer' ); ?></strong>
					</label>
					<select id="msh-api-provider" name="api_provider">
						<option value="openai" <?php selected( $context['api_provider'] ?? 'openai', 'openai' ); ?>>OpenAI (GPT-4)</option>
						<option value="anthropic" <?php selected( $context['api_provider'] ?? '', 'anthropic' ); ?>>Anthropic (Claude)</option>
					</select>
					<p class="description"><?php esc_html_e( 'Choose your preferred AI provider. You can change this later.', 'msh-image-optimizer' ); ?></p>
				</div>

				<div class="msh-form-group">
					<label for="msh-api-key">
						<strong><?php esc_html_e( 'API Key', 'msh-image-optimizer' ); ?></strong>
					</label>
					<input type="password" id="msh-api-key" name="api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" placeholder="sk-...">
					<p class="description">
						<?php
						printf(
							/* translators: %s: link to API key documentation */
							esc_html__( 'Don\'t have an API key? %s', 'msh-image-optimizer' ),
							'<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Get one here', 'msh-image-optimizer' ) . '</a>'
						);
						?>
					</p>
				</div>

				<div class="msh-onboarding-tip">
					<strong><?php esc_html_e( 'Pro Tip:', 'msh-image-optimizer' ); ?></strong>
					<?php esc_html_e( 'Set spending limits in your OpenAI dashboard to control costs. Typical usage is $5-20/month for most sites.', 'msh-image-optimizer' ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Step 2: Languages.
	 *
	 * @param array $context Saved onboarding context.
	 * @return void
	 */
	private function render_step_languages( $context ) {
		$primary_locale = get_option( 'msh_primary_locale', 'en_US' );
		?>
		<div class="msh-onboarding-step" data-step="2">
			<h2><?php esc_html_e( 'Configure Languages', 'msh-image-optimizer' ); ?></h2>
			<p><?php esc_html_e( 'Set your primary language and enable multi-language support if needed.', 'msh-image-optimizer' ); ?></p>

			<div class="msh-onboarding-form">
				<div class="msh-form-group">
					<label for="msh-primary-locale">
						<strong><?php esc_html_e( 'Primary Language', 'msh-image-optimizer' ); ?></strong>
					</label>
					<select id="msh-primary-locale" name="primary_locale">
						<option value="en_US" <?php selected( $primary_locale, 'en_US' ); ?>>English (US)</option>
						<option value="en_GB" <?php selected( $primary_locale, 'en_GB' ); ?>>English (UK)</option>
						<option value="es_ES" <?php selected( $primary_locale, 'es_ES' ); ?>>Spanish (Spain)</option>
						<option value="fr_FR" <?php selected( $primary_locale, 'fr_FR' ); ?>>French (France)</option>
						<option value="de_DE" <?php selected( $primary_locale, 'de_DE' ); ?>>German (Germany)</option>
						<option value="it_IT" <?php selected( $primary_locale, 'it_IT' ); ?>>Italian (Italy)</option>
					</select>
					<p class="description"><?php esc_html_e( 'This will be the default language for AI-generated metadata.', 'msh-image-optimizer' ); ?></p>
				</div>

				<div class="msh-form-group">
					<label>
						<input type="checkbox" name="multilingual" value="1" <?php checked( $context['multilingual'] ?? false, 1 ); ?>>
						<strong><?php esc_html_e( 'Enable Multi-Language Support', 'msh-image-optimizer' ); ?></strong>
					</label>
					<p class="description"><?php esc_html_e( 'Generate metadata in multiple languages for international audiences.', 'msh-image-optimizer' ); ?></p>
				</div>

				<div class="msh-onboarding-tip">
					<strong><?php esc_html_e( 'Note:', 'msh-image-optimizer' ); ?></strong>
					<?php esc_html_e( 'Multi-language support requires a multilingual plugin like WPML or Polylang.', 'msh-image-optimizer' ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Step 3: Features.
	 *
	 * @param array $context Saved onboarding context.
	 * @return void
	 */
	private function render_step_features( $context ) {
		?>
		<div class="msh-onboarding-step" data-step="3">
			<h2><?php esc_html_e( 'Choose Features', 'msh-image-optimizer' ); ?></h2>
			<p><?php esc_html_e( 'Enable the features you want to use. You can always change these later in Settings.', 'msh-image-optimizer' ); ?></p>

			<div class="msh-onboarding-features">
				<label class="msh-feature-card">
					<input type="checkbox" name="feature_auto_optimize" value="1" <?php checked( $context['feature_auto_optimize'] ?? true, 1 ); ?>>
					<div class="msh-feature-content">
						<strong><?php esc_html_e( 'Automatic Optimization', 'msh-image-optimizer' ); ?></strong>
						<p><?php esc_html_e( 'Automatically generate metadata when new images are uploaded.', 'msh-image-optimizer' ); ?></p>
					</div>
				</label>

				<label class="msh-feature-card">
					<input type="checkbox" name="feature_duplicate_detection" value="1" <?php checked( $context['feature_duplicate_detection'] ?? true, 1 ); ?>>
					<div class="msh-feature-content">
						<strong><?php esc_html_e( 'Duplicate Detection', 'msh-image-optimizer' ); ?></strong>
						<p><?php esc_html_e( 'Find and manage duplicate images to save storage space.', 'msh-image-optimizer' ); ?></p>
					</div>
				</label>

				<label class="msh-feature-card">
					<input type="checkbox" name="feature_webp" value="1" <?php checked( $context['feature_webp'] ?? true, 1 ); ?>>
					<div class="msh-feature-content">
						<strong><?php esc_html_e( 'WebP Conversion', 'msh-image-optimizer' ); ?></strong>
						<p><?php esc_html_e( 'Automatically serve WebP images for better performance.', 'msh-image-optimizer' ); ?></p>
					</div>
				</label>

				<label class="msh-feature-card msh-feature-pro">
					<input type="checkbox" name="feature_cloud_sync" value="1" <?php checked( $context['feature_cloud_sync'] ?? false, 1 ); ?> disabled>
					<div class="msh-feature-content">
						<strong><?php esc_html_e( 'Cloud Sync', 'msh-image-optimizer' ); ?> <span class="msh-badge-pro">Pro</span></strong>
						<p><?php esc_html_e( 'Sync metadata across multiple sites via cloud.', 'msh-image-optimizer' ); ?></p>
					</div>
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Step 4: Finish.
	 *
	 * @param array $context Saved onboarding context.
	 * @return void
	 */
	private function render_step_finish( $context ) {
		?>
		<div class="msh-onboarding-step" data-step="4">
			<div class="msh-onboarding-success">
				<div class="msh-success-icon">✓</div>
				<h2><?php esc_html_e( 'All Set!', 'msh-image-optimizer' ); ?></h2>
				<p><?php esc_html_e( 'Your MSH Image Optimizer is configured and ready to use.', 'msh-image-optimizer' ); ?></p>
			</div>

			<div class="msh-onboarding-next-steps">
				<h3><?php esc_html_e( 'Next Steps:', 'msh-image-optimizer' ); ?></h3>
				<ul>
					<li>
						<strong><?php esc_html_e( 'Visit the Hub', 'msh-image-optimizer' ); ?></strong>
						<p><?php esc_html_e( 'Manage your metadata cache, view queue status, and monitor events.', 'msh-image-optimizer' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-optimizer-hub' ) ); ?>" class="button">
							<?php esc_html_e( 'Go to Hub', 'msh-image-optimizer' ); ?>
						</a>
					</li>
					<li>
						<strong><?php esc_html_e( 'Upload Your First Image', 'msh-image-optimizer' ); ?></strong>
						<p><?php esc_html_e( 'Try uploading an image to see automatic metadata generation in action.', 'msh-image-optimizer' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="button">
							<?php esc_html_e( 'Go to Media Library', 'msh-image-optimizer' ); ?>
						</a>
					</li>
					<li>
						<strong><?php esc_html_e( 'Explore Settings', 'msh-image-optimizer' ); ?></strong>
						<p><?php esc_html_e( 'Fine-tune behavior, configure locales, and manage your glossary.', 'msh-image-optimizer' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-image-optimizer-settings' ) ); ?>" class="button">
							<?php esc_html_e( 'Go to Settings', 'msh-image-optimizer' ); ?>
						</a>
					</li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: Save onboarding step.
	 *
	 * @return void
	 */
	public function ajax_save_step() {
		check_ajax_referer( 'msh_onboarding', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'msh-image-optimizer' ) ) );
		}

		$step = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 0;
		$data = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array();

		// Get existing context
		$context = get_option( self::ONBOARDING_CONTEXT, array() );

		// Save step-specific data
		switch ( $step ) {
			case 1:
				if ( isset( $data['api_key'] ) && ! empty( $data['api_key'] ) ) {
					update_option( 'msh_openai_api_key', sanitize_text_field( $data['api_key'] ) );
				}
				$context['api_provider'] = isset( $data['api_provider'] ) ? sanitize_text_field( $data['api_provider'] ) : 'openai';
				break;

			case 2:
				if ( isset( $data['primary_locale'] ) ) {
					update_option( 'msh_primary_locale', sanitize_text_field( $data['primary_locale'] ) );
				}
				$context['multilingual'] = ! empty( $data['multilingual'] );
				break;

			case 3:
				$context['feature_auto_optimize']      = ! empty( $data['feature_auto_optimize'] );
				$context['feature_duplicate_detection'] = ! empty( $data['feature_duplicate_detection'] );
				$context['feature_webp']               = ! empty( $data['feature_webp'] );
				break;
		}

		// Save context
		update_option( self::ONBOARDING_CONTEXT, $context );

		wp_send_json_success( array( 'message' => __( 'Step saved successfully.', 'msh-image-optimizer' ) ) );
	}

	/**
	 * AJAX: Complete onboarding.
	 *
	 * @return void
	 */
	public function ajax_complete() {
		check_ajax_referer( 'msh_onboarding', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'msh-image-optimizer' ) ) );
		}

		// Mark onboarding as completed
		update_option( self::ONBOARDING_COMPLETED, current_time( 'mysql' ) );

		// Log telemetry
		if ( function_exists( 'msh_telemetry' ) ) {
			$context = get_option( self::ONBOARDING_CONTEXT, array() );
			msh_telemetry( 'onboarding_completed', $context );
		}

		wp_send_json_success(
			array(
				'redirect' => admin_url( 'admin.php?page=msh-optimizer-hub' ),
				'message'  => __( 'Setup complete! Redirecting to Hub...', 'msh-image-optimizer' ),
			)
		);
	}

	/**
	 * AJAX: Skip onboarding.
	 *
	 * @return void
	 */
	public function ajax_skip() {
		check_ajax_referer( 'msh_onboarding', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'msh-image-optimizer' ) ) );
		}

		// Mark as skipped
		update_option( self::ONBOARDING_COMPLETED, 'skipped' );

		// Log telemetry
		if ( function_exists( 'msh_telemetry' ) ) {
			msh_telemetry( 'onboarding_skipped', array() );
		}

		wp_send_json_success(
			array(
				'redirect' => admin_url(),
				'message'  => __( 'Setup skipped.', 'msh-image-optimizer' ),
			)
		);
	}

	/**
	 * Check if onboarding has been completed.
	 *
	 * @return bool
	 */
	public function is_completed() {
		return ! empty( get_option( self::ONBOARDING_COMPLETED, false ) );
	}

	/**
	 * Reset onboarding (for testing).
	 *
	 * @return void
	 */
	public function reset() {
		delete_option( self::ONBOARDING_COMPLETED );
		delete_option( self::ONBOARDING_CONTEXT );
	}
}

// Initialize Onboarding Wizard
MSH_Onboarding_Wizard::get_instance();
