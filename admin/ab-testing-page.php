<?php
/**
 * Phase 4 A/B Testing Dashboard.
 *
 * @package MSH_Image_Optimizer
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A/B Testing Admin Page.
 */
class MSH_AB_Testing_Page {

	const PAGE_SLUG          = 'msh-ab-testing';
	const CREATE_NONCE       = 'msh_ab_create';
	const WINNER_NONCE       = 'msh_ab_winner';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ), 45 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_msh_ab_create', array( $this, 'handle_create' ) );
		add_action( 'admin_post_msh_ab_winner', array( $this, 'handle_winner' ) );
	}

	/**
	 * Register submenu under The Dot menu.
	 *
	 * NOTE: Menu registration disabled - this page is now accessed via
	 * the Insights & Analytics tab in class-msh-optimizer-menu.php
	 *
	 * @return void
	 */
	public function register_page() {
		// Disabled - accessed via tabbed interface
		return;
	}

	/**
	 * Enqueue assets.
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
	}

	/**
	 * Handle create campaign request.
	 *
	 * @return void
	 */
	public function handle_create() {
		check_admin_referer( self::CREATE_NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'msh-image-optimizer' ) );
		}

		$name        = isset( $_POST['campaign_name'] ) ? sanitize_text_field( wp_unslash( $_POST['campaign_name'] ) ) : '';
		$description = isset( $_POST['campaign_description'] ) ? wp_kses_post( wp_unslash( $_POST['campaign_description'] ) ) : '';
		$variants    = isset( $_POST['campaign_variants'] ) ? absint( $_POST['campaign_variants'] ) : 2;

		if ( $name ) {
			MSH_AB_Testing::get_instance()->create_campaign(
				$name,
				max( 2, $variants ),
				array(
					'description' => $description,
					'created_by'  => get_current_user_id(),
				)
			);
		}

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'msh_created' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle winner selection.
	 *
	 * @return void
	 */
	public function handle_winner() {
		check_admin_referer( self::WINNER_NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'msh-image-optimizer' ) );
		}

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		if ( $campaign_id ) {
			MSH_AB_Testing::get_instance()->maybe_select_winner( $campaign_id );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'msh_winner' => $campaign_id ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render the dashboard.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'msh-image-optimizer' ) );
		}

		$testing    = MSH_AB_Testing::get_instance();
		$campaigns  = $testing->get_campaigns();
		$created    = isset( $_GET['msh_created'] );
		$winner_id  = isset( $_GET['msh_winner'] ) ? absint( $_GET['msh_winner'] ) : 0;

		?>
		<div class="wrap msh-phase4-wrap">
			<h1><?php esc_html_e( 'A/B Testing Dashboard', 'msh-image-optimizer' ); ?></h1>
			<p class="msh-phase4-lede"><?php esc_html_e( 'Design experiments, monitor performance, and champion the winning metadata for every market.', 'msh-image-optimizer' ); ?></p>

			<?php if ( $created ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Campaign created. Add variants and start collecting insights.', 'msh-image-optimizer' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $winner_id ) : ?>
				<div class="notice notice-info is-dismissible">
					<p>
						<?php
						printf(
							/* translators: %d: campaign id */
							esc_html__( 'Winner evaluation triggered for campaign #%d.', 'msh-image-optimizer' ),
							$winner_id
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<section class="msh-phase4-card">
				<header class="msh-phase4-card__header">
					<h2><?php esc_html_e( 'Launch New Campaign', 'msh-image-optimizer' ); ?></h2>
					<p><?php esc_html_e( 'Establish a focused test to evaluate messaging, tone, or localization hypotheses.', 'msh-image-optimizer' ); ?></p>
				</header>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="msh-phase4-form">
					<?php wp_nonce_field( self::CREATE_NONCE ); ?>
					<input type="hidden" name="action" value="msh_ab_create">
					<div class="msh-phase4-two-column">
						<label>
							<span><?php esc_html_e( 'Campaign Name', 'msh-image-optimizer' ); ?></span>
							<input type="text" name="campaign_name" required placeholder="<?php esc_attr_e( 'Spring Alt Text Refresh', 'msh-image-optimizer' ); ?>">
						</label>
						<label>
							<span><?php esc_html_e( 'Variant Count', 'msh-image-optimizer' ); ?></span>
							<input type="number" name="campaign_variants" value="2" min="2" max="5">
						</label>
					</div>
					<label class="msh-phase4-full">
						<span><?php esc_html_e( 'Hypothesis & Success Criteria', 'msh-image-optimizer' ); ?></span>
						<textarea name="campaign_description" rows="4" placeholder="<?php esc_attr_e( 'Document the hypothesis, target persona, and success signal (CTR, lead capture, etc.).', 'msh-image-optimizer' ); ?>"></textarea>
					</label>
					<button type="submit" class="button button-dot-primary"><?php esc_html_e( 'Create Campaign', 'msh-image-optimizer' ); ?></button>
				</form>
			</section>

			<section class="msh-phase4-card">
				<header class="msh-phase4-card__header">
					<h2><?php esc_html_e( 'Active Experiments', 'msh-image-optimizer' ); ?></h2>
					<p><?php esc_html_e( 'Monitor conversions and push the winning variant live once significance hits 95%.', 'msh-image-optimizer' ); ?></p>
				</header>

				<?php if ( empty( $campaigns ) ) : ?>
					<p class="msh-phase4-empty"><?php esc_html_e( 'No experiments yet. Launch a campaign to begin capturing insights.', 'msh-image-optimizer' ); ?></p>
				<?php else : ?>
					<?php foreach ( $campaigns as $campaign ) : ?>
						<article class="msh-phase4-campaign">
							<div class="msh-phase4-campaign__header">
								<h3><?php echo esc_html( $campaign['name'] ); ?></h3>
								<span class="msh-phase4-status status-<?php echo esc_attr( $campaign['status'] ); ?>">
									<?php echo esc_html( ucfirst( str_replace( '_', ' ', $campaign['status'] ) ) ); ?>
								</span>
							</div>
							<?php if ( ! empty( $campaign['description'] ) ) : ?>
								<p class="msh-phase4-description"><?php echo wp_kses_post( $campaign['description'] ); ?></p>
							<?php endif; ?>

							<div class="msh-phase4-table-wrap">
								<table class="msh-phase4-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Variant', 'msh-image-optimizer' ); ?></th>
											<th><?php esc_html_e( 'Views', 'msh-image-optimizer' ); ?></th>
											<th><?php esc_html_e( 'Clicks', 'msh-image-optimizer' ); ?></th>
											<th><?php esc_html_e( 'CTR', 'msh-image-optimizer' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $campaign['variants'] as $variant ) : ?>
											<tr>
												<td><?php echo esc_html( $variant['label'] ); ?></td>
												<td><?php echo esc_html( number_format_i18n( $variant['views'] ) ); ?></td>
												<td><?php echo esc_html( number_format_i18n( $variant['clicks'] ) ); ?></td>
												<td><?php echo esc_html( number_format_i18n( $variant['ctr'] * 100, 2 ) ); ?>%</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>

							<div class="msh-phase4-campaign__footer">
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( self::WINNER_NONCE ); ?>
									<input type="hidden" name="action" value="msh_ab_winner">
									<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $campaign['campaign_id'] ); ?>">
									<button type="submit" class="button button-secondary"><?php esc_html_e( 'Evaluate Winner', 'msh-image-optimizer' ); ?></button>
								</form>
								<?php
								$winner_label = '';
								if ( ! empty( $campaign['winner_id'] ) ) {
									foreach ( $campaign['variants'] as $variant ) {
										if ( (int) $variant['variant_id'] === (int) $campaign['winner_id'] ) {
											$winner_label = $variant['label'];
											break;
										}
									}
								}
								if ( $winner_label ) :
									?>
									<span class="msh-phase4-winner">
										<?php
										printf(
											/* translators: %s winner label. */
											esc_html__( 'Winner: %s', 'msh-image-optimizer' ),
											esc_html( $winner_label )
										);
										?>
									</span>
								<?php endif; ?>
							</div>
						</article>
					<?php endforeach; ?>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}
}

if ( is_admin() ) {
	new MSH_AB_Testing_Page();
}
