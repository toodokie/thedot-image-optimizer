<?php
/**
 * Locale Profiles Manager
 *
 * Brand-compliant interface for managing locale-specific optimization settings.
 *
 * @package MSH_Image_Optimizer
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Locale Profiles Page Class
 */
class MSH_Locale_Profiles_Page {

	/**
	 * Page slug
	 */
	const PAGE_SLUG = 'msh-locale-profiles';

	/**
	 * Locale Profile Manager instance.
	 *
	 * @var MSH_Locale_Profile_Manager
	 */
	private $profile_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->profile_manager = MSH_Locale_Profile_Manager::get_instance();
		add_action( 'admin_menu', array( $this, 'register_menu' ), 25 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		if ( method_exists( $this, 'handle_form_submission' ) ) {
			add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
		}
	}

	/**
	 * Register submenu page
	 *
	 * NOTE: Menu registration disabled - this page is now accessed via
	 * the Localization tab in class-msh-optimizer-menu.php
	 */
	public function register_menu() {
		// Disabled - accessed via tabbed interface
		return;
	}

	/**
	 * Enqueue assets
	 *
	 * @param string $hook Current page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'msh-optimizer_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'msh-image-optimizer-fonts',
			'https://use.typekit.net/gac6jnd.css',
			array(),
			null
		);
	}

	/**
	 * Handle form submissions for creating/updating/deleting profiles.
	 *
	 * @return void
	 */
	public function handle_form_submission() {
		if ( ! isset( $_POST['msh_locale_action'] ) || ! isset( $_POST['msh_locale_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['msh_locale_nonce'] ) ), 'msh_locale_profile_action' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['msh_locale_action'] ) );

		if ( 'save' === $action ) {
			$this->handle_save_profile();
		} elseif ( 'delete' === $action ) {
			$this->handle_delete_profile();
		}
	}

	/**
	 * Handle saving a profile.
	 *
	 * @return void
	 */
	private function handle_save_profile() {
		$locale = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : '';

		if ( empty( $locale ) || ! preg_match( '/^[a-z]{2}_[A-Z]{2}$/', $locale ) ) {
			add_settings_error( 'msh_locale_messages', 'invalid_locale', __( 'Invalid locale code.', 'msh-image-optimizer' ), 'error' );
			return;
		}

		$profile_data = array(
			'tone'                 => isset( $_POST['tone'] ) ? sanitize_text_field( wp_unslash( $_POST['tone'] ) ) : 'professional',
			'cta_style'            => isset( $_POST['cta_style'] ) ? sanitize_text_field( wp_unslash( $_POST['cta_style'] ) ) : 'subtle',
			'formality_level'      => isset( $_POST['formality_level'] ) ? absint( $_POST['formality_level'] ) : 3,
			'special_instructions' => isset( $_POST['special_instructions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['special_instructions'] ) ) : '',
			'forbidden_terms'      => isset( $_POST['forbidden_terms'] ) ? sanitize_textarea_field( wp_unslash( $_POST['forbidden_terms'] ) ) : '',
			'confidence_threshold' => isset( $_POST['confidence_threshold'] ) ? (float) $_POST['confidence_threshold'] : 0.70,
		);

		$result = $this->profile_manager->save_profile( $locale, $profile_data );

		if ( false !== $result ) {
			add_settings_error( 'msh_locale_messages', 'profile_saved', __( 'Locale profile saved successfully.', 'msh-image-optimizer' ), 'success' );
		} else {
			add_settings_error( 'msh_locale_messages', 'save_failed', __( 'Failed to save locale profile.', 'msh-image-optimizer' ), 'error' );
		}

		set_transient( 'msh_locale_messages', get_settings_errors( 'msh_locale_messages' ), 30 );

		wp_safe_redirect( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle deleting a profile.
	 *
	 * @return void
	 */
	private function handle_delete_profile() {
		$locale = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : '';

		if ( empty( $locale ) ) {
			add_settings_error( 'msh_locale_messages', 'invalid_locale', __( 'Invalid locale code.', 'msh-image-optimizer' ), 'error' );
			return;
		}

		// Prevent deleting en_US (default locale)
		if ( 'en_US' === $locale ) {
			add_settings_error( 'msh_locale_messages', 'cannot_delete_default', __( 'Cannot delete the default locale profile.', 'msh-image-optimizer' ), 'error' );
			set_transient( 'msh_locale_messages', get_settings_errors( 'msh_locale_messages' ), 30 );
			wp_safe_redirect( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) ) );
			exit;
		}

		$result = $this->profile_manager->delete_profile( $locale );

		if ( $result ) {
			add_settings_error( 'msh_locale_messages', 'profile_deleted', __( 'Locale profile deleted successfully.', 'msh-image-optimizer' ), 'success' );
		} else {
			add_settings_error( 'msh_locale_messages', 'delete_failed', __( 'Failed to delete locale profile.', 'msh-image-optimizer' ), 'error' );
		}

		set_transient( 'msh_locale_messages', get_settings_errors( 'msh_locale_messages' ), 30 );

		wp_safe_redirect( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render profile create/edit form.
	 *
	 * @param object|null $profile  Existing profile object or null for new profile.
	 * @param string      $new_locale Locale code for new profile (when $profile is null).
	 * @return void
	 */
	private function render_profile_form( $profile = null, $new_locale = '' ) {
		$is_new = null === $profile;
		$locale = $is_new ? $new_locale : $profile['locale'];

		// Defaults for new profiles
		$tone = $is_new ? 'professional' : $profile['tone'];
		$cta_style = $is_new ? 'subtle' : $profile['cta_style'];
		$formality_level = $is_new ? 3 : $profile['formality_level'];
		$special_instructions = $is_new ? '' : $profile->special_instructions;
		$forbidden_terms = $is_new ? '' : $profile->forbidden_terms;
		$confidence_threshold = $is_new ? 0.70 : $profile->confidence_threshold;

		?>
		<div class="msh-profile-form-container">
			<form method="post" action="">
				<?php wp_nonce_field( 'msh_locale_profile_action', 'msh_locale_nonce' ); ?>
				<input type="hidden" name="msh_locale_action" value="save" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="locale"><?php esc_html_e( 'Locale Code', 'msh-image-optimizer' ); ?></label>
						</th>
						<td>
							<?php if ( $is_new && 'new' === $new_locale ) : ?>
								<input type="text" name="locale" id="locale" class="regular-text" placeholder="e.g., es_ES, fr_FR" required pattern="[a-z]{2}_[A-Z]{2}" />
								<p class="description"><?php esc_html_e( 'Enter locale code in format: language_COUNTRY (e.g., es_ES for Spanish Spain)', 'msh-image-optimizer' ); ?></p>
							<?php else : ?>
								<input type="text" name="locale" id="locale" class="regular-text" value="<?php echo esc_attr( $locale ); ?>" readonly />
								<p class="description"><?php esc_html_e( 'Locale code cannot be changed after creation.', 'msh-image-optimizer' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tone"><?php esc_html_e( 'Tone', 'msh-image-optimizer' ); ?></label>
						</th>
						<td>
							<select name="tone" id="tone">
								<option value="professional" <?php selected( $tone, 'professional' ); ?>><?php esc_html_e( 'Professional', 'msh-image-optimizer' ); ?></option>
								<option value="casual" <?php selected( $tone, 'casual' ); ?>><?php esc_html_e( 'Casual', 'msh-image-optimizer' ); ?></option>
								<option value="friendly" <?php selected( $tone, 'friendly' ); ?>><?php esc_html_e( 'Friendly', 'msh-image-optimizer' ); ?></option>
								<option value="authoritative" <?php selected( $tone, 'authoritative' ); ?>><?php esc_html_e( 'Authoritative', 'msh-image-optimizer' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Overall tone for AI-generated metadata.', 'msh-image-optimizer' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="cta_style"><?php esc_html_e( 'CTA Style', 'msh-image-optimizer' ); ?></label>
						</th>
						<td>
							<select name="cta_style" id="cta_style">
								<option value="subtle" <?php selected( $cta_style, 'subtle' ); ?>><?php esc_html_e( 'Subtle', 'msh-image-optimizer' ); ?></option>
								<option value="direct" <?php selected( $cta_style, 'direct' ); ?>><?php esc_html_e( 'Direct', 'msh-image-optimizer' ); ?></option>
								<option value="urgent" <?php selected( $cta_style, 'urgent' ); ?>><?php esc_html_e( 'Urgent', 'msh-image-optimizer' ); ?></option>
								<option value="none" <?php selected( $cta_style, 'none' ); ?>><?php esc_html_e( 'None', 'msh-image-optimizer' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Call-to-action style in image descriptions.', 'msh-image-optimizer' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="formality_level"><?php esc_html_e( 'Formality Level', 'msh-image-optimizer' ); ?></label>
						</th>
						<td>
							<input type="range" name="formality_level" id="formality_level" min="1" max="5" value="<?php echo esc_attr( $formality_level ); ?>" />
							<span id="formality_value"><?php echo esc_html( $formality_level ); ?></span>/5
							<p class="description"><?php esc_html_e( '1 = Very casual, 5 = Very formal', 'msh-image-optimizer' ); ?></p>
							<script>
								document.getElementById('formality_level').addEventListener('input', function(e) {
									document.getElementById('formality_value').textContent = e.target.value;
								});
							</script>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="confidence_threshold"><?php esc_html_e( 'Confidence Threshold', 'msh-image-optimizer' ); ?></label>
						</th>
						<td>
							<input type="number" name="confidence_threshold" id="confidence_threshold" min="0" max="1" step="0.01" value="<?php echo esc_attr( $confidence_threshold ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( 'Minimum AI confidence score (0.0-1.0). Lower values accept more suggestions.', 'msh-image-optimizer' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="special_instructions"><?php esc_html_e( 'Special Instructions', 'msh-image-optimizer' ); ?></label>
						</th>
						<td>
							<textarea name="special_instructions" id="special_instructions" rows="5" class="large-text"><?php echo esc_textarea( $special_instructions ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Additional instructions for AI metadata generation (e.g., "Always mention the brand name").', 'msh-image-optimizer' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="forbidden_terms"><?php esc_html_e( 'Forbidden Terms', 'msh-image-optimizer' ); ?></label>
						</th>
						<td>
							<textarea name="forbidden_terms" id="forbidden_terms" rows="3" class="large-text"><?php echo esc_textarea( $forbidden_terms ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Words/phrases that should never appear in AI-generated content (one per line).', 'msh-image-optimizer' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php echo $is_new ? esc_html__( 'Create Profile', 'msh-image-optimizer' ) : esc_html__( 'Update Profile', 'msh-image-optimizer' ); ?></button>
					<a href="<?php echo esc_url( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Cancel', 'msh-image-optimizer' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render locale profiles page
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'msh-image-optimizer' ) );
		}

		// Get all profiles from database
		$profiles = $this->profile_manager->get_all_profiles();
		$editing_locale = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : '';

		// If editing, fetch the profile
		$editing_profile = null;
		if ( $editing_locale ) {
			$editing_profile = $this->profile_manager->get_profile( $editing_locale );
		}

		?>
		<div class="wrap msh-locale-page">
			<?php
			// Display messages from form submissions
			if ( $messages = get_transient( 'msh_locale_messages' ) ) {
				delete_transient( 'msh_locale_messages' );
				foreach ( $messages as $message ) {
					printf(
						'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
						esc_attr( $message['type'] ),
						esc_html( $message['message'] )
					);
				}
			}
			?>
			<h1 class="msh-page-title"><?php esc_html_e( 'Locale Profiles', 'msh-image-optimizer' ); ?></h1>
			<p class="msh-page-subtitle"><?php esc_html_e( 'Configure locale-specific optimization settings and cultural adaptation rules.', 'msh-image-optimizer' ); ?></p>

			<?php if ( $editing_locale && $editing_profile ) : ?>
				<!-- Edit Profile Form -->
				<?php $this->render_profile_form( $editing_profile ); ?>
			<?php elseif ( $editing_locale && ! $editing_profile ) : ?>
				<!-- Create New Profile Form -->
				<?php $this->render_profile_form( null, $editing_locale ); ?>
			<?php else : ?>
				<!-- List View -->
				<div class="msh-locale-container">
					<div class="msh-locale-header">
						<h2><?php esc_html_e( 'Active Locale Profiles', 'msh-image-optimizer' ); ?></h2>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'edit' => 'new' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Add New Locale', 'msh-image-optimizer' ); ?>
						</a>
					</div>

					<?php if ( empty( $profiles ) ) : ?>
						<div class="msh-notice msh-notice-info">
							<p><?php esc_html_e( 'No locale profiles configured yet. Click "Add New Locale" to create your first profile.', 'msh-image-optimizer' ); ?></p>
						</div>
					<?php else : ?>
						<div class="msh-locale-list">
							<?php foreach ( $profiles as $profile ) : ?>
								<?php
								$locale_names = array(
									'en_US' => array( 'name' => 'English (United States)', 'flag' => '🇺🇸' ),
									'es_ES' => array( 'name' => 'Spanish (Spain)', 'flag' => '🇪🇸' ),
									'fr_FR' => array( 'name' => 'French (France)', 'flag' => '🇫🇷' ),
									'de_DE' => array( 'name' => 'German (Germany)', 'flag' => '🇩🇪' ),
									'it_IT' => array( 'name' => 'Italian (Italy)', 'flag' => '🇮🇹' ),
									'pt_BR' => array( 'name' => 'Portuguese (Brazil)', 'flag' => '🇧🇷' ),
								);

								$locale_info = isset( $locale_names[ $profile['locale'] ] ) ? $locale_names[ $profile['locale'] ] : array(
									'name' => $profile['locale'],
									'flag' => '🌐',
								);
								?>
								<div class="msh-locale-card">
									<div class="msh-locale-card-header">
										<div class="msh-locale-flag"><?php echo esc_html( $locale_info['flag'] ); ?></div>
										<div class="msh-locale-info">
											<div class="msh-locale-name"><?php echo esc_html( $locale_info['name'] ); ?></div>
											<div class="msh-locale-code"><?php echo esc_html( $profile['locale'] ); ?></div>
										</div>
										<?php if ( 'en_US' === $profile['locale'] ) : ?>
											<span class="msh-locale-badge"><?php esc_html_e( 'Default', 'msh-image-optimizer' ); ?></span>
										<?php endif; ?>
									</div>
									<div class="msh-locale-card-body">
										<div class="msh-profile-detail">
											<strong><?php esc_html_e( 'Tone:', 'msh-image-optimizer' ); ?></strong>
											<?php echo esc_html( ucfirst( $profile['tone'] ) ); ?>
										</div>
										<div class="msh-profile-detail">
											<strong><?php esc_html_e( 'Formality:', 'msh-image-optimizer' ); ?></strong>
											<?php echo esc_html( $profile['formality_level'] ); ?>/5
										</div>
										<div class="msh-profile-detail">
											<strong><?php esc_html_e( 'CTA Style:', 'msh-image-optimizer' ); ?></strong>
											<?php echo esc_html( ucfirst( $profile['cta_style'] ) ); ?>
										</div>
									</div>
									<div class="msh-locale-card-footer">
										<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'edit' => $profile['locale'] ), admin_url( 'admin.php' ) ) ); ?>" class="button button-secondary">
											<?php esc_html_e( 'Edit', 'msh-image-optimizer' ); ?>
										</a>
										<?php if ( 'en_US' !== $profile['locale'] ) : ?>
											<form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this profile?', 'msh-image-optimizer' ); ?>');">
												<?php wp_nonce_field( 'msh_locale_profile_action', 'msh_locale_nonce' ); ?>
												<input type="hidden" name="msh_locale_action" value="delete" />
												<input type="hidden" name="locale" value="<?php echo esc_attr( $profile['locale'] ); ?>" />
												<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Delete', 'msh-image-optimizer' ); ?></button>
											</form>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<style>
		.msh-locale-page {
			background: #FAF9F6;
			padding: 20px;
		}

		.msh-page-title {
			font-family: 'futura-pt', sans-serif;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			color: #35332f;
			font-size: 28px;
			margin-bottom: 8px;
		}

		.msh-page-subtitle {
			font-family: 'ff-real-text-pro', sans-serif;
			color: #8b8883;
			font-size: 16px;
			margin-bottom: 40px;
		}

		.msh-locale-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 30px;
		}

		.msh-locale-header h2 {
			font-family: 'futura-pt', sans-serif;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			color: #35332f;
			font-size: 20px;
			margin: 0;
		}

		.msh-profile-detail {
			margin-bottom: 8px;
			font-family: 'ff-real-text-pro', sans-serif;
			font-size: 14px;
			color: #35332f;
		}

		.msh-profile-detail strong {
			color: #8b8883;
			font-weight: 600;
		}

		.msh-profile-form-container {
			background: #fff;
			border-radius: 12px;
			padding: 30px;
			margin-top: 20px;
			border: 1px solid #ddd;
		}

		.msh-notice {
			padding: 16px;
			border-radius: 8px;
			margin-bottom: 20px;
		}

		.msh-notice-info {
			background: #e8f4fd;
			border-left: 4px solid #0073aa;
		}

		.msh-notice p {
			margin: 0;
			font-family: 'ff-real-text-pro', sans-serif;
			color: #35332f;
			font-size: 14px;
		}

		.msh-locale-list {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
			gap: 20px;
		}

		.msh-locale-card {
			background: #fff;
			border: 1px solid #ddd;
			border-radius: 12px;
			overflow: hidden;
			transition: border-color 0.2s;
		}

		.msh-locale-card:hover {
			border-color: #daff00;
		}

		.msh-locale-card-disabled {
			opacity: 0.6;
		}

		.msh-locale-card-header {
			padding: 20px;
			display: flex;
			align-items: center;
			gap: 12px;
			border-bottom: 1px solid #eee;
		}

		.msh-locale-flag {
			font-size: 32px;
			line-height: 1;
		}

		.msh-locale-info {
			flex: 1;
		}

		.msh-locale-name {
			font-family: 'futura-pt', sans-serif;
			text-transform: uppercase;
			letter-spacing: 0.05em;
			color: #35332f;
			font-size: 14px;
			font-weight: 700;
		}

		.msh-locale-code {
			font-family: 'ff-real-text-pro', sans-serif;
			color: #8b8883;
			font-size: 12px;
			margin-top: 4px;
		}

		.msh-locale-badge {
			font-family: 'futura-pt', sans-serif;
			text-transform: uppercase;
			letter-spacing: 0.05em;
			font-size: 10px;
			padding: 4px 8px;
			border-radius: 4px;
			background: #daff00;
			color: #35332f;
			font-weight: 700;
		}

		.msh-locale-badge-inactive {
			background: #eee;
			color: #8b8883;
		}

		.msh-locale-card-body {
			padding: 20px;
		}

		.msh-locale-card-body p {
			margin: 0;
			font-family: 'ff-real-text-pro', sans-serif;
			color: #8b8883;
			font-size: 14px;
			line-height: 1.6;
		}

		.msh-locale-card-footer {
			padding: 20px;
			border-top: 1px solid #eee;
			text-align: right;
		}

		.msh-btn-configure,
		.msh-btn-enable {
			font-family: 'futura-pt', sans-serif;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			padding: 8px 16px !important;
			border-radius: 8px;
		}

		.msh-btn-enable {
			background: #35332f !important;
			border-color: #35332f !important;
			color: #FAF9F6 !important;
		}

		.msh-btn-enable:hover:not(:disabled) {
			background: #daff00 !important;
			border-color: #daff00 !important;
			color: #35332f !important;
		}
		</style>
		<?php
	}
}

// Initialize
if ( is_admin() ) {
	new MSH_Locale_Profiles_Page();
}
