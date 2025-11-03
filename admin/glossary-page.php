<?php
/**
 * Glossary Manager - Term Management UI
 *
 * Brand-compliant glossary interface for managing terminology.
 *
 * @package MSH_Image_Optimizer
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Glossary Page Class
 */
class MSH_Glossary_Page {

	/**
	 * Page slug
	 */
	const PAGE_SLUG = 'msh-glossary';

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
		add_action( 'admin_menu', array( $this, 'register_menu' ), 30 );
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
	 * Handle form submissions for glossary operations.
	 *
	 * @return void
	 */
	public function handle_form_submission() {
		if ( ! isset( $_POST['msh_glossary_action'] ) || ! isset( $_POST['msh_glossary_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['msh_glossary_nonce'] ) ), 'msh_glossary_action' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['msh_glossary_action'] ) );

		if ( 'add' === $action ) {
			$this->handle_add_term();
		} elseif ( 'update' === $action ) {
			$this->handle_update_term();
		} elseif ( 'delete' === $action ) {
			$this->handle_delete_term();
		}
	}

	/**
	 * Handle adding a glossary term.
	 *
	 * @return void
	 */
	private function handle_add_term() {
		$locale = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : 'en_US';
		$term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
		$replacement = isset( $_POST['replacement'] ) ? sanitize_text_field( wp_unslash( $_POST['replacement'] ) ) : '';
		$context = isset( $_POST['context'] ) ? sanitize_textarea_field( wp_unslash( $_POST['context'] ) ) : '';

		if ( empty( $term ) ) {
			add_settings_error( 'msh_glossary_messages', 'empty_term', __( 'Term cannot be empty.', 'msh-image-optimizer' ), 'error' );
			set_transient( 'msh_glossary_messages', get_settings_errors( 'msh_glossary_messages' ), 30 );
			wp_safe_redirect( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) ) );
			exit;
		}

		$glossary_data = array(
			'term'        => $term,
			'replacement' => $replacement,
			'context'     => $context,
			'protected'   => isset( $_POST['protected'] ) ? 1 : 0,
		);

		$result = $this->profile_manager->add_glossary_entry( $locale, $glossary_data );

		if ( $result ) {
			add_settings_error( 'msh_glossary_messages', 'term_added', __( 'Glossary term added successfully.', 'msh-image-optimizer' ), 'success' );
		} else {
			add_settings_error( 'msh_glossary_messages', 'add_failed', __( 'Failed to add glossary term.', 'msh-image-optimizer' ), 'error' );
		}

		set_transient( 'msh_glossary_messages', get_settings_errors( 'msh_glossary_messages' ), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'locale' => $locale ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle updating a glossary term.
	 *
	 * @return void
	 */
	private function handle_update_term() {
		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
		$locale = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : 'en_US';

		if ( ! $entry_id ) {
			add_settings_error( 'msh_glossary_messages', 'invalid_entry', __( 'Invalid entry ID.', 'msh-image-optimizer' ), 'error' );
			set_transient( 'msh_glossary_messages', get_settings_errors( 'msh_glossary_messages' ), 30 );
			wp_safe_redirect( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) ) );
			exit;
		}

		$data = array(
			'term'        => isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '',
			'replacement' => isset( $_POST['replacement'] ) ? sanitize_text_field( wp_unslash( $_POST['replacement'] ) ) : '',
			'context'     => isset( $_POST['context'] ) ? sanitize_textarea_field( wp_unslash( $_POST['context'] ) ) : '',
			'protected'   => isset( $_POST['protected'] ) ? 1 : 0,
		);

		$result = $this->profile_manager->update_glossary_entry( $entry_id, $data );

		if ( $result ) {
			add_settings_error( 'msh_glossary_messages', 'term_updated', __( 'Glossary term updated successfully.', 'msh-image-optimizer' ), 'success' );
		} else {
			add_settings_error( 'msh_glossary_messages', 'update_failed', __( 'Failed to update glossary term.', 'msh-image-optimizer' ), 'error' );
		}

		set_transient( 'msh_glossary_messages', get_settings_errors( 'msh_glossary_messages' ), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'locale' => $locale ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle deleting a glossary term.
	 *
	 * @return void
	 */
	private function handle_delete_term() {
		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
		$locale = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : 'en_US';

		if ( ! $entry_id ) {
			add_settings_error( 'msh_glossary_messages', 'invalid_entry', __( 'Invalid entry ID.', 'msh-image-optimizer' ), 'error' );
			set_transient( 'msh_glossary_messages', get_settings_errors( 'msh_glossary_messages' ), 30 );
			wp_safe_redirect( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) ) );
			exit;
		}

		$result = $this->profile_manager->delete_glossary_entry( $entry_id );

		if ( $result ) {
			add_settings_error( 'msh_glossary_messages', 'term_deleted', __( 'Glossary term deleted successfully.', 'msh-image-optimizer' ), 'success' );
		} else {
			add_settings_error( 'msh_glossary_messages', 'delete_failed', __( 'Failed to delete glossary term.', 'msh-image-optimizer' ), 'error' );
		}

		set_transient( 'msh_glossary_messages', get_settings_errors( 'msh_glossary_messages' ), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'locale' => $locale ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render glossary page
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'msh-image-optimizer' ) );
		}

		// Display messages from form submissions
		if ( $messages = get_transient( 'msh_glossary_messages' ) ) {
			delete_transient( 'msh_glossary_messages' );
			foreach ( $messages as $message ) {
				printf(
					'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
					esc_attr( $message['type'] ),
					esc_html( $message['message'] )
				);
			}
		}

		// Get current locale filter
		$current_locale = isset( $_GET['locale'] ) ? sanitize_text_field( wp_unslash( $_GET['locale'] ) ) : 'en_US';

		// Get all available profiles for locale selector
		$profiles = $this->profile_manager->get_all_profiles();

		// Get glossary entries for current locale
		$entries = $this->profile_manager->get_glossary_entries( $current_locale );

		// Check if editing
		$editing_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$editing_entry = null;
		if ( $editing_id ) {
			foreach ( $entries as $entry ) {
				if ( $entry->id === $editing_id ) {
					$editing_entry = $entry;
					break;
				}
			}
		}

		?>
		<div class="wrap msh-glossary-page">
			<h1 class="msh-page-title"><?php esc_html_e( 'Glossary Manager', 'msh-image-optimizer' ); ?></h1>
			<p class="msh-page-subtitle"><?php esc_html_e( 'Manage terminology for consistent AI-generated metadata across all locales.', 'msh-image-optimizer' ); ?></p>

			<!-- Locale Selector -->
			<div class="msh-glossary-locale-selector">
				<label for="glossary-locale"><?php esc_html_e( 'Locale:', 'msh-image-optimizer' ); ?></label>
				<select id="glossary-locale" onchange="window.location.href='?page=<?php echo esc_js( self::PAGE_SLUG ); ?>&locale=' + this.value;">
					<?php if ( empty( $profiles ) ) : ?>
						<option value="en_US"><?php esc_html_e( 'English (en_US)', 'msh-image-optimizer' ); ?></option>
					<?php else : ?>
						<?php foreach ( $profiles as $profile ) : ?>
							<option value="<?php echo esc_attr( $profile->locale ); ?>" <?php selected( $current_locale, $profile->locale ); ?>>
								<?php echo esc_html( $profile->locale ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<div class="msh-glossary-container">
				<!-- Add/Edit Term Form -->
				<div class="msh-glossary-add-form">
					<h2><?php echo $editing_entry ? esc_html__( 'Edit Term', 'msh-image-optimizer' ) : esc_html__( 'Add New Term', 'msh-image-optimizer' ); ?></h2>
					<form method="post" action="">
						<?php wp_nonce_field( 'msh_glossary_action', 'msh_glossary_nonce' ); ?>
						<input type="hidden" name="msh_glossary_action" value="<?php echo $editing_entry ? 'update' : 'add'; ?>" />
						<input type="hidden" name="locale" value="<?php echo esc_attr( $current_locale ); ?>" />
						<?php if ( $editing_entry ) : ?>
							<input type="hidden" name="entry_id" value="<?php echo esc_attr( $editing_entry->id ); ?>" />
						<?php endif; ?>

						<div class="msh-form-row">
							<label for="term-source"><?php esc_html_e( 'Source Term', 'msh-image-optimizer' ); ?></label>
							<input type="text" id="term-source" name="term" value="<?php echo $editing_entry ? esc_attr( $editing_entry->term ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g., physical therapy', 'msh-image-optimizer' ); ?>" required />
						</div>

						<div class="msh-form-row">
							<label for="term-replacement"><?php esc_html_e( 'Preferred Term', 'msh-image-optimizer' ); ?></label>
							<input type="text" id="term-replacement" name="replacement" value="<?php echo $editing_entry ? esc_attr( $editing_entry->replacement ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g., physiotherapy', 'msh-image-optimizer' ); ?>" />
						</div>

						<div class="msh-form-row">
							<label for="term-context"><?php esc_html_e( 'Context / Notes', 'msh-image-optimizer' ); ?></label>
							<textarea id="term-context" name="context" rows="3" placeholder="<?php esc_attr_e( 'Optional: Add context for when this replacement should be used...', 'msh-image-optimizer' ); ?>"><?php echo $editing_entry ? esc_textarea( $editing_entry->context ) : ''; ?></textarea>
						</div>

						<div class="msh-form-row">
							<label>
								<input type="checkbox" name="protected" value="1" <?php checked( $editing_entry && $editing_entry->protected, 1 ); ?> />
								<?php esc_html_e( 'Protected (never replace this term)', 'msh-image-optimizer' ); ?>
							</label>
						</div>

						<button type="submit" class="button button-primary msh-btn-add">
							<?php echo $editing_entry ? esc_html__( 'Update Term', 'msh-image-optimizer' ) : esc_html__( 'Add Term', 'msh-image-optimizer' ); ?>
						</button>
						<?php if ( $editing_entry ) : ?>
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'locale' => $current_locale ), admin_url( 'admin.php' ) ) ); ?>" class="button button-secondary">
								<?php esc_html_e( 'Cancel', 'msh-image-optimizer' ); ?>
							</a>
						<?php endif; ?>
					</form>
				</div>

				<!-- Glossary Terms Table -->
				<div class="msh-glossary-terms">
					<h2><?php esc_html_e( 'Current Glossary', 'msh-image-optimizer' ); ?></h2>

					<?php if ( empty( $entries ) ) : ?>
						<div class="msh-notice msh-notice-info">
							<p><?php esc_html_e( 'No glossary terms defined yet for this locale. Add your first term using the form on the left.', 'msh-image-optimizer' ); ?></p>
						</div>
					<?php else : ?>
						<table class="msh-glossary-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Source Term', 'msh-image-optimizer' ); ?></th>
									<th><?php esc_html_e( 'Preferred Term', 'msh-image-optimizer' ); ?></th>
									<th><?php esc_html_e( 'Context', 'msh-image-optimizer' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'msh-image-optimizer' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $entries as $entry ) : ?>
									<tr>
										<td>
											<strong><?php echo esc_html( $entry->term ); ?></strong>
											<?php if ( $entry->protected ) : ?>
												<span class="msh-badge msh-badge-protected"><?php esc_html_e( 'Protected', 'msh-image-optimizer' ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $entry->replacement ? $entry->replacement : '—' ); ?></td>
										<td><?php echo esc_html( $entry->context ? wp_trim_words( $entry->context, 10 ) : '—' ); ?></td>
										<td class="msh-table-actions">
											<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'locale' => $current_locale, 'edit' => $entry->id ), admin_url( 'admin.php' ) ) ); ?>" class="button button-small">
												<?php esc_html_e( 'Edit', 'msh-image-optimizer' ); ?>
											</a>
											<form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this term?', 'msh-image-optimizer' ); ?>');">
												<?php wp_nonce_field( 'msh_glossary_action', 'msh_glossary_nonce' ); ?>
												<input type="hidden" name="msh_glossary_action" value="delete" />
												<input type="hidden" name="locale" value="<?php echo esc_attr( $current_locale ); ?>" />
												<input type="hidden" name="entry_id" value="<?php echo esc_attr( $entry->id ); ?>" />
												<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'msh-image-optimizer' ); ?></button>
											</form>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<style>
		.msh-glossary-page {
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
			margin-bottom: 30px;
		}

		.msh-glossary-locale-selector {
			background: #fff;
			border: 1px solid #ddd;
			border-radius: 8px;
			padding: 16px;
			margin-bottom: 20px;
			display: flex;
			align-items: center;
			gap: 12px;
		}

		.msh-glossary-locale-selector label {
			font-family: 'ff-real-text-pro', sans-serif;
			color: #35332f;
			font-size: 14px;
			font-weight: 600;
			margin: 0;
		}

		.msh-glossary-locale-selector select {
			padding: 8px 32px 8px 12px;
			border: 1px solid #ddd;
			border-radius: 6px;
			font-family: 'ff-real-text-pro', sans-serif;
			font-size: 14px;
			color: #35332f;
			background-color: #fff;
			background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%2335332f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"%3e%3cpolyline points="6 9 12 15 18 9"%3e%3c/polyline%3e%3c/svg%3e');
			background-repeat: no-repeat;
			background-position: right 8px center;
			background-size: 16px;
			-webkit-appearance: none;
			-moz-appearance: none;
			appearance: none;
			cursor: pointer;
			min-width: 150px;
			transition: border-color 0.15s ease;
		}

		.msh-glossary-locale-selector select:hover {
			border-color: #35332f;
		}

		.msh-glossary-locale-selector select:focus {
			outline: none;
			border-color: #daff00;
			box-shadow: 0 0 0 1px #daff00;
		}

		.msh-glossary-container {
			display: grid;
			grid-template-columns: 350px 1fr;
			gap: 30px;
		}

		.msh-glossary-add-form,
		.msh-glossary-terms {
			background: #fff;
			border: 1px solid #ddd;
			border-radius: 12px;
			padding: 24px;
		}

		.msh-glossary-add-form h2,
		.msh-glossary-terms h2 {
			font-family: 'futura-pt', sans-serif;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			color: #35332f;
			font-size: 16px;
			margin-bottom: 20px;
		}

		.msh-form-row {
			margin-bottom: 20px;
		}

		.msh-form-row label {
			display: block;
			font-family: 'ff-real-text-pro', sans-serif;
			color: #35332f;
			font-size: 14px;
			margin-bottom: 8px;
			font-weight: 600;
		}

		.msh-form-row input[type="text"],
		.msh-form-row textarea {
			width: 100%;
			padding: 10px 12px;
			border: 1px solid #ddd;
			border-radius: 8px;
			font-family: 'ff-real-text-pro', sans-serif;
			font-size: 14px;
			color: #35332f;
		}

		.msh-form-row input[type="text"]:focus,
		.msh-form-row textarea:focus {
			outline: none;
			border-color: #daff00;
		}

		.msh-btn-add {
			background: #35332f !important;
			border-color: #35332f !important;
			color: #FAF9F6 !important;
			font-family: 'futura-pt', sans-serif;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			padding: 10px 20px !important;
			border-radius: 8px;
			cursor: pointer;
			transition: background 0.2s;
		}

		.msh-btn-add:hover {
			background: #daff00 !important;
			border-color: #daff00 !important;
			color: #35332f !important;
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

		.msh-glossary-table {
			width: 100%;
			border-collapse: collapse;
		}

		.msh-glossary-table th {
			font-family: 'futura-pt', sans-serif;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			color: #35332f;
			font-size: 12px;
			text-align: left;
			padding: 12px;
			border-bottom: 2px solid #ddd;
		}

		.msh-glossary-table td {
			font-family: 'ff-real-text-pro', sans-serif;
			color: #35332f;
			font-size: 14px;
			padding: 12px;
			border-bottom: 1px solid #eee;
		}

		.msh-table-actions {
			white-space: nowrap;
		}

		.msh-table-actions .button {
			margin-right: 8px;
		}

		.msh-badge-protected {
			display: inline-block;
			padding: 2px 8px;
			background: #daff00;
			color: #35332f;
			font-size: 11px;
			font-family: 'futura-pt', sans-serif;
			text-transform: uppercase;
			letter-spacing: 0.05em;
			border-radius: 4px;
			margin-left: 8px;
		}
		</style>
		<?php
	}
}

// Initialize
if ( is_admin() ) {
	new MSH_Glossary_Page();
}
