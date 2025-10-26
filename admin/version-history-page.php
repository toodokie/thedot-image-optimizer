<?php
/**
 * Phase 4 Version History Admin Page.
 *
 * @package MSH_Image_Optimizer
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Version History Admin UI.
 */
class MSH_Version_History_Page {

	const PAGE_SLUG      = 'msh-version-history';
	const ROLLBACK_NONCE = 'msh_version_rollback';
	const NOTE_NONCE     = 'msh_version_note';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ), 40 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_msh_version_rollback', array( $this, 'handle_rollback' ) );
		add_action( 'admin_post_msh_version_note', array( $this, 'handle_note' ) );
	}

	/**
	 * Register submenu page under The Dot menu.
	 *
	 * NOTE: Menu registration disabled - this page is now accessed via
	 * the Review Center tab in class-msh-optimizer-menu.php
	 *
	 * @return void
	 */
	public function register_page() {
		// Disabled - accessed via tabbed interface
		return;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current hook.
	 * @return void
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

		wp_enqueue_style(
			'msh-image-optimizer-settings',
			trailingslashit( MSH_IO_ASSETS_URL ) . 'css/image-optimizer-settings.css',
			array( 'msh-image-optimizer-fonts' ),
			MSH_Image_Optimizer_Plugin::VERSION
		);

		wp_enqueue_style(
			'msh-phase4-admin',
			trailingslashit( MSH_IO_ASSETS_URL ) . 'css/phase4-admin.css',
			array( 'msh-image-optimizer-settings' ),
			MSH_Image_Optimizer_Plugin::VERSION
		);

		wp_enqueue_script( 'jquery' );
		wp_add_inline_script(
			'jquery',
			"(function(){document.addEventListener('click',function(e){var button=e.target.closest('.msh-phase4-note-toggle');if(!button){return;}var target=document.getElementById(button.dataset.target);if(target){target.classList.toggle('is-open');}});}());"
		);
	}

	/**
	 * Handle rollback action.
	 *
	 * @return void
	 */
	public function handle_rollback() {
		check_admin_referer( self::ROLLBACK_NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'msh-image-optimizer' ) );
		}

		$version_id = isset( $_POST['version_id'] ) ? absint( $_POST['version_id'] ) : 0;
		if ( ! $version_id ) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		$result = MSH_Version_Manager::get_instance()->rollback_to_version( $version_id, get_current_user_id() );

		$redirect = isset( $_POST['redirect'] ) ? esc_url_raw( $_POST['redirect'] ) : wp_get_referer();
		$redirect = add_query_arg(
			array(
				'msh_rollback' => $result ? 'success' : 'failed',
			),
			$redirect
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle note append.
	 *
	 * @return void
	 */
	public function handle_note() {
		check_admin_referer( self::NOTE_NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'msh-image-optimizer' ) );
		}

		$version_id = isset( $_POST['version_id'] ) ? absint( $_POST['version_id'] ) : 0;
		$note       = isset( $_POST['version_note'] ) ? wp_unslash( $_POST['version_note'] ) : '';

		if ( $version_id && $note ) {
			MSH_Version_Manager::get_instance()->append_note( $version_id, get_current_user_id(), $note );
		}

		$redirect = isset( $_POST['redirect'] ) ? esc_url_raw( $_POST['redirect'] ) : wp_get_referer();
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'msh-image-optimizer' ) );
		}

		$media_id   = isset( $_GET['media_id'] ) ? absint( $_GET['media_id'] ) : 0;
		$locale     = isset( $_GET['locale'] ) ? sanitize_text_field( $_GET['locale'] ) : get_locale();
		$version_a  = isset( $_GET['version_a'] ) ? absint( $_GET['version_a'] ) : 0;
		$version_b  = isset( $_GET['version_b'] ) ? absint( $_GET['version_b'] ) : 0;
		$versioning = MSH_Version_Manager::get_instance();

		$diff_html = '';
		if ( $media_id && $version_a && $version_b ) {
			$diff_html = $versioning->get_diff_html( $version_a, $version_b );
		}

		?>
		<div class="wrap msh-phase4-wrap">
			<h1><?php esc_html_e( 'Metadata Version History', 'msh-image-optimizer' ); ?></h1>

			<form method="get" class="msh-phase4-filter">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<label for="msh-media-id">
					<span><?php esc_html_e( 'Attachment ID', 'msh-image-optimizer' ); ?></span>
					<input type="number" name="media_id" id="msh-media-id" value="<?php echo $media_id ? esc_attr( $media_id ) : ''; ?>" min="1" required>
				</label>
				<label for="msh-locale">
					<span><?php esc_html_e( 'Locale', 'msh-image-optimizer' ); ?></span>
					<input type="text" name="locale" id="msh-locale" value="<?php echo esc_attr( $locale ); ?>" placeholder="en_US">
				</label>
				<button class="button button-dot-primary"><?php esc_html_e( 'Load History', 'msh-image-optimizer' ); ?></button>
			</form>

			<?php if ( isset( $_GET['msh_rollback'] ) ) : ?>
				<div class="notice notice-<?php echo 'success' === $_GET['msh_rollback'] ? 'success' : 'error'; ?> is-dismissible">
					<p><?php echo 'success' === $_GET['msh_rollback'] ? esc_html__( 'Rollback applied successfully.', 'msh-image-optimizer' ) : esc_html__( 'Rollback failed. Please try again.', 'msh-image-optimizer' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $media_id ) : ?>
				<?php
				$history = $versioning->get_history_for_locale( $media_id, $locale );
				foreach ( $history as $field => $versions ) :
					if ( empty( $versions ) ) {
						continue;
					}
					?>
					<section class="msh-phase4-card">
						<header class="msh-phase4-card__header">
							<h2><?php echo esc_html( ucfirst( $field ) ); ?></h2>
							<p><?php esc_html_e( 'Track every change, compare versions, and roll back with confidence.', 'msh-image-optimizer' ); ?></p>
						</header>

						<div class="msh-phase4-table-wrap">
							<table class="msh-phase4-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Version', 'msh-image-optimizer' ); ?></th>
										<th><?php esc_html_e( 'Source', 'msh-image-optimizer' ); ?></th>
										<th><?php esc_html_e( 'Created', 'msh-image-optimizer' ); ?></th>
										<th><?php esc_html_e( 'Approved', 'msh-image-optimizer' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'msh-image-optimizer' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $versions as $index => $version ) : ?>
										<tr>
											<td>
												<strong><?php echo esc_html( '#' . $version['version'] ); ?></strong>
												<?php if ( 0 === $index ) : ?>
													<span class="msh-phase4-badge"><?php esc_html_e( 'Active', 'msh-image-optimizer' ); ?></span>
												<?php endif; ?>
											</td>
											<td><?php echo esc_html( ucfirst( $version['source'] ) ); ?></td>
											<td><?php echo esc_html( $version['created_at'] ); ?></td>
											<td><?php echo $version['approved_at'] ? esc_html( $version['approved_at'] ) : '&mdash;'; ?></td>
											<td class="msh-phase4-actions">
												<?php
												$compare_target = isset( $versions[ $index + 1 ] ) ? $versions[ $index + 1 ] : null;
												if ( $compare_target ) :
													$compare_url = add_query_arg(
														array(
															'page'      => self::PAGE_SLUG,
															'media_id'  => $media_id,
															'locale'    => $locale,
															'version_a' => $compare_target['id'],
															'version_b' => $version['id'],
														),
														admin_url( 'admin.php' )
													);
													?>
													<a href="<?php echo esc_url( $compare_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Compare', 'msh-image-optimizer' ); ?></a>
												<?php endif; ?>

												<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
													<?php wp_nonce_field( self::ROLLBACK_NONCE ); ?>
													<input type="hidden" name="action" value="msh_version_rollback">
													<input type="hidden" name="version_id" value="<?php echo esc_attr( $version['id'] ); ?>">
													<input type="hidden" name="redirect" value="<?php echo esc_url( add_query_arg( array( 'media_id' => $media_id, 'locale' => $locale ), admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) ); ?>">
													<button type="submit" class="button button-dot-primary"><?php esc_html_e( 'Rollback', 'msh-image-optimizer' ); ?></button>
												</form>

												<button type="button" class="button button-link msh-phase4-note-toggle" data-target="msh-note-<?php echo esc_attr( $version['id'] ); ?>">
													<?php esc_html_e( 'Add Note', 'msh-image-optimizer' ); ?>
												</button>
											</td>
										</tr>
										<tr id="msh-note-<?php echo esc_attr( $version['id'] ); ?>" class="msh-phase4-note-row">
											<td colspan="5">
												<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="msh-phase4-note-form">
													<?php wp_nonce_field( self::NOTE_NONCE ); ?>
													<input type="hidden" name="action" value="msh_version_note">
													<input type="hidden" name="version_id" value="<?php echo esc_attr( $version['id'] ); ?>">
													<input type="hidden" name="redirect" value="<?php echo esc_url( add_query_arg( array( 'media_id' => $media_id, 'locale' => $locale ), admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) ); ?>">
													<label>
														<span class="screen-reader-text"><?php esc_html_e( 'Version note', 'msh-image-optimizer' ); ?></span>
														<textarea name="version_note" rows="3" placeholder="<?php esc_attr_e( 'Document why this change matters…', 'msh-image-optimizer' ); ?>"></textarea>
													</label>
													<button type="submit" class="button button-secondary"><?php esc_html_e( 'Save Note', 'msh-image-optimizer' ); ?></button>
												</form>

												<?php
												$notes = $versioning->get_notes( $version );
												if ( ! empty( $notes ) ) :
													?>
													<ul class="msh-phase4-notes">
														<?php foreach ( $notes as $note ) : ?>
															<li>
																<strong><?php echo esc_html( $note['user'] ? $note['user'] : __( 'System', 'msh-image-optimizer' ) ); ?></strong>
																<time><?php echo esc_html( $note['time'] ); ?></time>
																<p><?php echo wp_kses_post( nl2br( $note['message'] ) ); ?></p>
															</li>
														<?php endforeach; ?>
													</ul>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</section>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php if ( $diff_html ) : ?>
				<section class="msh-phase4-card">
					<header class="msh-phase4-card__header">
						<h2><?php esc_html_e( 'Diff Viewer', 'msh-image-optimizer' ); ?></h2>
						<p><?php esc_html_e( 'Green highlights additions, red highlights removals for a clean visual comparison.', 'msh-image-optimizer' ); ?></p>
					</header>
					<div class="msh-phase4-diff">
						<?php echo wp_kses_post( $diff_html ); ?>
					</div>
				</section>
			<?php endif; ?>
		</div>
		<?php
	}
}

if ( is_admin() ) {
	new MSH_Version_History_Page();
}
