<?php
/**
 * Context Analytics Admin Page
 *
 * Displays analytics dashboard for Context Fusion Layer.
 *
 * @package MSH_Image_Optimizer
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context Analytics Page Class
 */
class MSH_Context_Analytics_Page {

	/**
	 * Initialize
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add analytics page to admin menu
	 *
	 * NOTE: Menu registration disabled - this page is now accessed via
	 * the Insights & Analytics tab in class-msh-optimizer-menu.php
	 */
	public function add_menu_page() {
		// Disabled - accessed via tabbed interface
		return;
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// WordPress uses 'admin_page_{page_slug}' for callback-based submenu pages
		if ( 'msh-optimizer_page_msh-context-analytics' !== $hook ) {
			return;
		}

		// Enqueue brand guidelines (base variables)
		$brand_guidelines_file = dirname( __FILE__ ) . '/../assets/css/brand-guidelines.css';
		wp_enqueue_style(
			'msh-brand-guidelines',
			trailingslashit( MSH_IO_ASSETS_URL ) . 'css/brand-guidelines.css',
			array(),
			file_exists( $brand_guidelines_file ) ? filemtime( $brand_guidelines_file ) : MSH_Image_Optimizer_Plugin::VERSION
		);

		// Enqueue list table branding
		$list_table_file = dirname( __FILE__ ) . '/../assets/css/wp-list-table-branding.css';
		wp_enqueue_style(
			'msh-list-table-branding',
			trailingslashit( MSH_IO_ASSETS_URL ) . 'css/wp-list-table-branding.css',
			array( 'msh-brand-guidelines' ),
			file_exists( $list_table_file ) ? filemtime( $list_table_file ) : MSH_Image_Optimizer_Plugin::VERSION
		);

		wp_enqueue_style(
			'msh-analytics',
			MSH_IO_PLUGIN_URL . 'admin/css/analytics.css',
			array( 'msh-brand-guidelines', 'msh-list-table-branding' ),
			MSH_Image_Optimizer_Plugin::VERSION
		);
	}

	/**
	 * Render analytics page
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'msh-image-optimizer' ) );
		}

		$analytics = new MSH_Context_Analytics();
		$overview = $analytics->get_overview();
		$keyword_stats = $analytics->get_keyword_stats( array( 'limit' => 15 ) );
		$post_type_stats = $analytics->get_post_type_stats();
		$quality_dist = $analytics->get_quality_distribution();

		?>
		<div class="wrap msh-analytics-page">
			<h1><?php esc_html_e( 'Context Analytics Dashboard', 'msh-image-optimizer' ); ?></h1>

			<!-- Overview Cards -->
			<div class="msh-analytics-cards">
				<div class="msh-card">
					<div class="msh-card-header">
						<h3><?php esc_html_e( 'Total Contexts', 'msh-image-optimizer' ); ?></h3>
					</div>
					<div class="msh-card-value"><?php echo number_format_i18n( $overview['totals']['contexts'] ); ?></div>
					<div class="msh-card-footer">
						<?php
						printf(
							/* translators: %d: number of images */
							esc_html__( 'Across %d images', 'msh-image-optimizer' ),
							number_format_i18n( $overview['totals']['images'] )
						);
						?>
					</div>
				</div>

				<div class="msh-card">
					<div class="msh-card-header">
						<h3><?php esc_html_e( 'Average Score', 'msh-image-optimizer' ); ?></h3>
					</div>
					<div class="msh-card-value"><?php echo esc_html( $overview['totals']['avg_score'] ); ?></div>
					<div class="msh-card-footer">
						<?php
						$score_class = $overview['totals']['avg_score'] >= 70 ? 'good' : ( $overview['totals']['avg_score'] >= 50 ? 'fair' : 'poor' );
						?>
						<span class="msh-badge msh-badge-<?php echo esc_attr( $score_class ); ?>">
							<?php
							if ( $overview['totals']['avg_score'] >= 70 ) {
								esc_html_e( 'Excellent', 'msh-image-optimizer' );
							} elseif ( $overview['totals']['avg_score'] >= 50 ) {
								esc_html_e( 'Good', 'msh-image-optimizer' );
							} else {
								esc_html_e( 'Needs Improvement', 'msh-image-optimizer' );
							}
							?>
						</span>
					</div>
				</div>

				<div class="msh-card">
					<div class="msh-card-header">
						<h3><?php esc_html_e( 'Posts Analyzed', 'msh-image-optimizer' ); ?></h3>
					</div>
					<div class="msh-card-value"><?php echo number_format_i18n( $overview['totals']['posts'] ); ?></div>
					<div class="msh-card-footer">
						<?php esc_html_e( 'With image context data', 'msh-image-optimizer' ); ?>
					</div>
				</div>

				<div class="msh-card">
					<div class="msh-card-header">
						<h3><?php esc_html_e( 'On-Topic Usage', 'msh-image-optimizer' ); ?></h3>
					</div>
					<div class="msh-card-value">
						<?php
						$total = array_sum( $overview['intent_distribution'] );
						$on_topic_pct = $total > 0 ? round( ( $overview['intent_distribution']['on_topic'] / $total ) * 100 ) : 0;
						echo esc_html( $on_topic_pct . '%' );
						?>
					</div>
					<div class="msh-card-footer">
						<?php
						printf(
							/* translators: %d: number of on-topic contexts */
							esc_html__( '%d on-topic contexts', 'msh-image-optimizer' ),
							number_format_i18n( $overview['intent_distribution']['on_topic'] )
						);
						?>
					</div>
				</div>
			</div>

			<!-- Two Column Layout -->
			<div class="msh-analytics-grid">
				<!-- Left Column -->
				<div class="msh-analytics-column">
					<!-- Intent Distribution -->
					<div class="msh-analytics-widget">
						<h2><?php esc_html_e( 'Intent Distribution', 'msh-image-optimizer' ); ?></h2>
						<div class="msh-intent-chart">
							<?php
							$total = array_sum( $overview['intent_distribution'] );
							foreach ( $overview['intent_distribution'] as $intent => $count ) {
								$percentage = $total > 0 ? round( ( $count / $total ) * 100 ) : 0;
								$intent_class = str_replace( '_', '-', $intent );
								?>
								<div class="msh-intent-row">
									<div class="msh-intent-label">
										<span class="msh-intent-badge msh-intent-<?php echo esc_attr( $intent_class ); ?>">
											<?php echo esc_html( ucfirst( str_replace( '_', ' ', $intent ) ) ); ?>
										</span>
									</div>
									<div class="msh-intent-bar">
										<div class="msh-intent-fill msh-intent-<?php echo esc_attr( $intent_class ); ?>"
											 style="width: <?php echo esc_attr( $percentage ); ?>%"></div>
									</div>
									<div class="msh-intent-count">
										<?php echo esc_html( number_format_i18n( $count ) . ' (' . $percentage . '%)' ); ?>
									</div>
								</div>
								<?php
							}
							?>
						</div>
					</div>

					<!-- Quality Distribution -->
					<div class="msh-analytics-widget">
						<h2><?php esc_html_e( 'Quality Distribution', 'msh-image-optimizer' ); ?></h2>
						<div class="msh-quality-chart">
							<?php
							$total_qual = array_sum( $quality_dist );
							$quality_labels = array(
								'excellent' => __( 'Excellent (80-100)', 'msh-image-optimizer' ),
								'good' => __( 'Good (60-79)', 'msh-image-optimizer' ),
								'fair' => __( 'Fair (40-59)', 'msh-image-optimizer' ),
								'poor' => __( 'Poor (0-39)', 'msh-image-optimizer' ),
							);

							foreach ( $quality_dist as $level => $count ) {
								$percentage = $total_qual > 0 ? round( ( $count / $total_qual ) * 100 ) : 0;
								?>
								<div class="msh-quality-row">
									<div class="msh-quality-label">
										<span class="msh-badge msh-badge-<?php echo esc_attr( $level ); ?>">
											<?php echo esc_html( $quality_labels[ $level ] ); ?>
										</span>
									</div>
									<div class="msh-quality-bar">
										<div class="msh-quality-fill msh-quality-<?php echo esc_attr( $level ); ?>"
											 style="width: <?php echo esc_attr( $percentage ); ?>%"></div>
									</div>
									<div class="msh-quality-count">
										<?php echo esc_html( number_format_i18n( $count ) . ' (' . $percentage . '%)' ); ?>
									</div>
								</div>
								<?php
							}
							?>
						</div>
					</div>

					<!-- Post Type Breakdown -->
					<div class="msh-analytics-widget">
						<h2><?php esc_html_e( 'Usage by Post Type', 'msh-image-optimizer' ); ?></h2>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Post Type', 'msh-image-optimizer' ); ?></th>
									<th><?php esc_html_e( 'Contexts', 'msh-image-optimizer' ); ?></th>
									<th><?php esc_html_e( 'Unique Images', 'msh-image-optimizer' ); ?></th>
									<th><?php esc_html_e( 'Avg Score', 'msh-image-optimizer' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $post_type_stats as $stat ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $stat['label'] ); ?></strong></td>
										<td><?php echo esc_html( number_format_i18n( $stat['context_count'] ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $stat['unique_images'] ) ); ?></td>
										<td>
											<?php
											$score_class = $stat['avg_score'] >= 70 ? 'good' : ( $stat['avg_score'] >= 50 ? 'fair' : 'poor' );
											?>
											<span class="msh-badge msh-badge-<?php echo esc_attr( $score_class ); ?>">
												<?php echo esc_html( $stat['avg_score'] ); ?>
											</span>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Right Column -->
				<div class="msh-analytics-column">
					<!-- Top Performers -->
					<div class="msh-analytics-widget">
						<h2><?php esc_html_e( 'Top Performing Images', 'msh-image-optimizer' ); ?></h2>
						<p class="description">
							<?php esc_html_e( 'Images with the highest average context scores', 'msh-image-optimizer' ); ?>
						</p>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Image', 'msh-image-optimizer' ); ?></th>
									<th><?php esc_html_e( 'Avg Score', 'msh-image-optimizer' ); ?></th>
									<th><?php esc_html_e( 'Uses', 'msh-image-optimizer' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $overview['top_performers'] as $image ) : ?>
									<tr>
										<td>
											<a href="<?php echo esc_url( get_edit_post_link( $image['media_id'] ) ); ?>">
												<?php echo esc_html( $image['title'] ); ?>
											</a>
										</td>
										<td>
											<span class="msh-badge msh-badge-excellent">
												<?php echo esc_html( $image['avg_score'] ); ?>
											</span>
										</td>
										<td><?php echo esc_html( number_format_i18n( $image['usage_count'] ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<!-- Needs Attention -->
					<div class="msh-analytics-widget">
						<h2><?php esc_html_e( 'Images Needing Attention', 'msh-image-optimizer' ); ?></h2>
						<p class="description">
							<?php esc_html_e( 'Images with low context scores or no on-topic usage', 'msh-image-optimizer' ); ?>
						</p>
						<?php if ( empty( $overview['needs_attention'] ) ) : ?>
							<p style="color: #46b450; font-style: italic;">
								<?php esc_html_e( 'All images are performing well!', 'msh-image-optimizer' ); ?>
							</p>
						<?php else : ?>
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Image', 'msh-image-optimizer' ); ?></th>
										<th><?php esc_html_e( 'Avg Score', 'msh-image-optimizer' ); ?></th>
										<th><?php esc_html_e( 'On-Topic', 'msh-image-optimizer' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $overview['needs_attention'] as $image ) : ?>
										<tr>
											<td>
												<a href="<?php echo esc_url( get_edit_post_link( $image['media_id'] ) ); ?>">
													<?php echo esc_html( $image['title'] ); ?>
												</a>
											</td>
											<td>
												<span class="msh-badge msh-badge-poor">
													<?php echo esc_html( $image['avg_score'] ); ?>
												</span>
											</td>
											<td>
												<?php
												if ( $image['on_topic_count'] === 0 ) {
													echo '<span style="color: #d63638;">✗ ' . esc_html__( 'Orphaned', 'msh-image-optimizer' ) . '</span>';
												} else {
													echo esc_html( number_format_i18n( $image['on_topic_count'] ) );
												}
												?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>

					<!-- Top Keywords -->
					<div class="msh-analytics-widget">
						<h2><?php esc_html_e( 'Most Common Keywords', 'msh-image-optimizer' ); ?></h2>
						<p class="description">
							<?php
							printf(
								/* translators: %d: number of unique keywords */
								esc_html__( 'Top %d keywords across all images', 'msh-image-optimizer' ),
								15
							);
							?>
						</p>
						<div class="msh-keyword-cloud">
							<?php
							$max_count = max( $keyword_stats['top_keywords'] );
							foreach ( $keyword_stats['top_keywords'] as $keyword => $count ) {
								$size = 12 + ( ( $count / $max_count ) * 16 );
								?>
								<span class="msh-keyword-tag" style="font-size: <?php echo esc_attr( $size ); ?>px;">
									<?php echo esc_html( $keyword ); ?>
									<span class="msh-keyword-count">(<?php echo esc_html( number_format_i18n( $count ) ); ?>)</span>
								</span>
								<?php
							}
							?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}

// Initialize
new MSH_Context_Analytics_Page();
