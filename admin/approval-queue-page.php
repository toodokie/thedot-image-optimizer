<?php
/**
 * Phase 4 Approval Queue Admin Page.
 *
 * @package MSH_Image_Optimizer
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Approval workflow admin UI.
 */
class MSH_Approval_Queue_Page {

	const PAGE_SLUG       = 'msh-approval-queue';
	const ACTION_NONCE    = 'msh_approval_action';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ), 50 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_msh_approval_action', array( $this, 'handle_action' ) );
	}

	/**
	 * Register submenu under The Dot menu.
	 *
	 * @return void
	 */
	public function register_page() {
		add_submenu_page(
			'msh-optimizer',
			__( 'Approval Queue', 'msh-image-optimizer' ),
			__( 'Approval Queue', 'msh-image-optimizer' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue assets.
	 *
	 * @param string $hook Current hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'the-dot_page_' . self::PAGE_SLUG !== $hook ) {
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
	}

	/**
	 * Handle approval action.
	 *
	 * @return void
	 */
	public function handle_action() {
		check_admin_referer( self::ACTION_NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'msh-image-optimizer' ) );
		}

		$queue_id = isset( $_POST['queue_id'] ) ? absint( $_POST['queue_id'] ) : 0;
		$action   = isset( $_POST['decision'] ) ? sanitize_text_field( $_POST['decision'] ) : '';
		$comment  = isset( $_POST['review_comment'] ) ? wp_unslash( $_POST['review_comment'] ) : '';

		if ( $queue_id && in_array( $action, array( 'approve', 'request_changes' ), true ) ) {
			MSH_Approval_Workflow::get_instance()->handle_action( $queue_id, $action, get_current_user_id(), $comment );
			if ( 'approve' === $action ) {
				$this->maybe_notify_submitter( $queue_id );
			}
		}

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'msh_action' => $action ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Optional notification for submitter.
	 *
	 * @param int $queue_id Queue ID.
	 * @return void
	 */
	private function maybe_notify_submitter( $queue_id ) {
		$record = MSH_Approval_Workflow::get_instance()->get_queue_item( $queue_id );
		if ( ! $record || empty( $record['submitted_by'] ) ) {
			return;
		}

		$submitter = get_user_by( 'id', $record['submitted_by'] );
		if ( ! $submitter ) {
			return;
		}

		$subject = __( 'Metadata Approved', 'msh-image-optimizer' );
		$message = __( 'Your metadata version has been approved and is ready for rollout.', 'msh-image-optimizer' );
		wp_mail( $submitter->user_email, $subject, $message );
	}

	/**
	 * Render admin UI.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'msh-image-optimizer' ) );
		}

		$workflow = MSH_Approval_Workflow::get_instance();
		$queue    = $workflow->get_queue( isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '' );
		$action   = isset( $_GET['msh_action'] ) ? sanitize_text_field( $_GET['msh_action'] ) : '';

		$versioning = MSH_Metadata_Versioning::get_instance();

		?>
		<div class="wrap msh-phase4-wrap">
			<h1><?php esc_html_e( 'Approval Queue', 'msh-image-optimizer' ); ?></h1>
			<p class="msh-phase4-lede"><?php esc_html_e( 'Guide every metadata update from draft to launch with transparent, auditable checkpoints.', 'msh-image-optimizer' ); ?></p>

			<?php if ( 'approve' === $action ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Metadata approved and published to version history.', 'msh-image-optimizer' ); ?></p>
				</div>
			<?php elseif ( 'request_changes' === $action ) : ?>
				<div class="notice notice-warning is-dismissible">
					<p><?php esc_html_e( 'Feedback shared with the author. They will revise and resubmit.', 'msh-image-optimizer' ); ?></p>
				</div>
			<?php endif; ?>

			<section class="msh-phase4-card">
				<header class="msh-phase4-card__header">
					<h2><?php esc_html_e( 'Pending Reviews', 'msh-image-optimizer' ); ?></h2>
					<p><?php esc_html_e( 'Prioritize reviews by status and accelerate approvals without sacrificing quality.', 'msh-image-optimizer' ); ?></p>
				</header>

				<?php if ( empty( $queue ) ) : ?>
					<p class="msh-phase4-empty"><?php esc_html_e( 'The queue is clear. Great work!', 'msh-image-optimizer' ); ?></p>
				<?php else : ?>
					<?php foreach ( $queue as $item ) :
						$version = $versioning->get_version_by_id( $item['version_id'] );
						$workflow_meta = $item['workflow'];
						?>
						<article class="msh-phase4-queue">
							<header class="msh-phase4-queue__header">
								<div>
									<h3><?php echo esc_html( sprintf( __( 'Media #%d · %s', 'msh-image-optimizer' ), $item['media_id'], strtoupper( $version['locale'] ) ) ); ?></h3>
									<p class="msh-phase4-meta">
										<?php
										printf(
											/* translators: 1: field, 2: created at */
											esc_html__( 'Field: %1$s · Submitted %2$s', 'msh-image-optimizer' ),
											esc_html( ucfirst( $version['field'] ) ),
											esc_html( $item['created_at'] )
										);
										?>
									</p>
								</div>
								<span class="msh-phase4-status status-<?php echo esc_attr( $item['status'] ); ?>">
									<?php echo esc_html( ucfirst( str_replace( '_', ' ', $item['status'] ) ) ); ?>
								</span>
							</header>

							<div class="msh-phase4-queue__body">
								<div class="msh-phase4-version-preview">
									<h4><?php esc_html_e( 'Proposed Metadata', 'msh-image-optimizer' ); ?></h4>
									<p><?php echo esc_html( $version['value'] ); ?></p>
								</div>

								<?php if ( ! empty( $workflow_meta['history'] ) ) : ?>
									<div class="msh-phase4-history">
										<h4><?php esc_html_e( 'Audit Trail', 'msh-image-optimizer' ); ?></h4>
										<ul>
											<?php foreach ( array_reverse( $workflow_meta['history'] ) as $entry ) : ?>
												<li>
													<strong><?php echo esc_html( $entry['status'] ); ?></strong>
													<time><?php echo esc_html( $entry['time'] ); ?></time>
													<?php if ( $entry['user'] ) : ?>
														<span><?php echo esc_html( $entry['user'] ); ?></span>
													<?php endif; ?>
													<?php if ( $entry['note'] ) : ?>
														<p><?php echo esc_html( $entry['note'] ); ?></p>
													<?php endif; ?>
												</li>
											<?php endforeach; ?>
										</ul>
									</div>
								<?php endif; ?>

								<?php if ( ! empty( $workflow_meta['comments'] ) ) : ?>
									<div class="msh-phase4-comments">
										<h4><?php esc_html_e( 'Reviewer Comments', 'msh-image-optimizer' ); ?></h4>
										<ul>
											<?php foreach ( array_reverse( $workflow_meta['comments'] ) as $comment ) : ?>
												<li>
													<strong><?php echo esc_html( $comment['user'] ); ?></strong>
													<time><?php echo esc_html( $comment['time'] ); ?></time>
													<p><?php echo wp_kses_post( nl2br( $comment['message'] ) ); ?></p>
												</li>
											<?php endforeach; ?>
										</ul>
									</div>
								<?php endif; ?>
							</div>

							<footer class="msh-phase4-queue__footer">
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( self::ACTION_NONCE ); ?>
									<input type="hidden" name="action" value="msh_approval_action">
									<input type="hidden" name="queue_id" value="<?php echo esc_attr( $item['queue_id'] ); ?>">

									<label>
										<span class="screen-reader-text"><?php esc_html_e( 'Reviewer comment', 'msh-image-optimizer' ); ?></span>
										<textarea name="review_comment" rows="3" placeholder="<?php esc_attr_e( 'Share context, rationale, or requested changes…', 'msh-image-optimizer' ); ?>"></textarea>
									</label>

									<div class="msh-phase4-decisions">
										<button type="submit" name="decision" value="request_changes" class="button button-secondary"><?php esc_html_e( 'Request Changes', 'msh-image-optimizer' ); ?></button>
										<button type="submit" name="decision" value="approve" class="button button-dot-primary"><?php esc_html_e( 'Approve Metadata', 'msh-image-optimizer' ); ?></button>
									</div>
								</form>
							</footer>
						</article>
					<?php endforeach; ?>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}
}

if ( is_admin() ) {
	new MSH_Approval_Queue_Page();
}

