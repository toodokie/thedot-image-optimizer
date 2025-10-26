<?php
/**
 * Template Admin Notices
 *
 * Displays admin notices for template system performance issues.
 *
 * @package MSH_Image_Optimizer
 * @since Phase 6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSH_Template_Admin_Notices
 *
 * Handles admin notices for template monitoring events.
 */
class MSH_Template_Admin_Notices {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Template_Admin_Notices|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Template_Admin_Notices
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
		add_action( 'admin_notices', array( $this, 'display_monitor_notices' ) );
		add_action( 'wp_ajax_msh_dismiss_template_notice', array( $this, 'ajax_dismiss_notice' ) );
	}

	/**
	 * Display template monitor notices.
	 *
	 * @return void
	 */
	public function display_monitor_notices() {
		// Only show to admins
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if Template Monitor exists
		if ( ! class_exists( 'MSH_Template_Monitor' ) ) {
			return;
		}

		$monitor = MSH_Template_Monitor::get_instance();
		$notice = $monitor->get_admin_notice();

		if ( ! $notice ) {
			return;
		}

		// Build notice HTML
		$type = $notice['type'] ?? 'warning';
		$reason = $notice['reason'] ?? 'unknown';
		$stats = $notice['stats'] ?? array();

		$message = $this->get_notice_message( $reason, $stats );
		$actions = $this->get_notice_actions( $reason );

		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible msh-template-notice" data-reason="<?php echo esc_attr( $reason ); ?>">
			<p>
				<strong><?php esc_html_e( 'TinyDot Template Intelligence', 'msh-image-optimizer' ); ?>:</strong>
				<?php echo wp_kses_post( $message ); ?>
			</p>
			<?php if ( ! empty( $stats ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Performance Stats:', 'msh-image-optimizer' ); ?></strong><br>
					<?php esc_html_e( 'Hit Rate:', 'msh-image-optimizer' ); ?> <?php echo esc_html( $stats['hit_rate_percent'] ?? 0 ); ?>%
					(<?php echo esc_html( number_format( $stats['total_hits'] ?? 0 ) ); ?> / <?php echo esc_html( number_format( $stats['total_evaluations'] ?? 0 ) ); ?>)<br>
					<?php esc_html_e( 'p50 / p95 Duration:', 'msh-image-optimizer' ); ?> <?php echo esc_html( $stats['p50_duration_ms'] ?? 0 ); ?>ms / <?php echo esc_html( $stats['p95_duration_ms'] ?? 0 ); ?>ms<br>
					<?php esc_html_e( 'Error Rate:', 'msh-image-optimizer' ); ?> <?php echo esc_html( $stats['error_rate_percent'] ?? 0 ); ?>%
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $actions ) ) : ?>
				<p><?php echo wp_kses_post( $actions ); ?></p>
			<?php endif; ?>
		</div>
		<script>
		jQuery(document).ready(function($) {
			$('.msh-template-notice').on('click', '.notice-dismiss', function() {
				$.post(ajaxurl, {
					action: 'msh_dismiss_template_notice',
					nonce: '<?php echo esc_js( wp_create_nonce( 'msh_template_notice' ) ); ?>'
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Get notice message based on reason.
	 *
	 * @param string $reason Reason code.
	 * @param array  $stats  Statistics.
	 * @return string Notice message.
	 */
	private function get_notice_message( $reason, $stats ) {
		switch ( $reason ) {
			case 'low_hit_rate':
				return sprintf(
					/* translators: %s is the hit rate percentage */
					__( 'Template system has been <strong>automatically disabled</strong> due to low hit rate (%s%%). Templates are matching fewer than 10%% of images, which means they\'re not providing value. Review your template coverage or disable the feature.', 'msh-image-optimizer' ),
					isset( $stats['hit_rate_percent'] ) ? $stats['hit_rate_percent'] : '0'
				);

			case 'slow_performance':
				return sprintf(
					/* translators: %s is the p95 duration in milliseconds */
					__( 'Template system has been <strong>automatically disabled</strong> due to slow performance (%sms p95). Template matching tail latency is too high and may impact page load times. Review your templates or disable the feature.', 'msh-image-optimizer' ),
					isset( $stats['p95_duration_ms'] ) ? $stats['p95_duration_ms'] : '0'
				);

			case 'high_error_rate':
				return sprintf(
					/* translators: %s is the error rate percentage */
					__( 'Template system has been <strong>automatically disabled</strong> due to high error rate (%s%%). Template matching is throwing too many errors. Check your templates for syntax issues or disable the feature.', 'msh-image-optimizer' ),
					isset( $stats['error_rate_percent'] ) ? $stats['error_rate_percent'] : '0'
				);

			default:
				return __( 'Template system has been automatically disabled. Check the diagnostics page for details.', 'msh-image-optimizer' );
		}
	}

	/**
	 * Get notice actions based on reason.
	 *
	 * @param string $reason Reason code.
	 * @return string HTML for actions.
	 */
	private function get_notice_actions( $reason ) {
		$actions = array();

		// Link to templates page
		$templates_url = admin_url( 'admin.php?page=msh-optimizer-settings&tab=templates' );
		$actions[] = sprintf(
			'<a href="%s" class="button button-secondary">%s</a>',
			esc_url( $templates_url ),
			esc_html__( 'View Templates', 'msh-image-optimizer' )
		);

		// Link to diagnostics (if we had one)
		// $actions[] = sprintf(
		// 	'<a href="%s" class="button button-secondary">%s</a>',
		// 	esc_url( $diagnostics_url ),
		// 	esc_html__( 'View Diagnostics', 'msh-image-optimizer' )
		// );

		return implode( ' ', $actions );
	}

	/**
	 * AJAX handler to dismiss notice.
	 *
	 * @return void
	 */
	public function ajax_dismiss_notice() {
		check_ajax_referer( 'msh_template_notice', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'msh-image-optimizer' ) ) );
		}

		if ( class_exists( 'MSH_Template_Monitor' ) ) {
			$monitor = MSH_Template_Monitor::get_instance();
			$monitor->dismiss_notice();
		}

		wp_send_json_success();
	}
}

// Initialize
MSH_Template_Admin_Notices::get_instance();
