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
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 30 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register submenu page
	 */
	public function register_menu() {
		add_submenu_page(
			'msh-optimizer',
			__( 'Glossary', 'msh-image-optimizer' ),
			'<span class="dashicons dashicons-book"></span> ' . __( 'Glossary', 'msh-image-optimizer' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue assets
	 *
	 * @param string $hook Current page hook.
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
	}

	/**
	 * Render glossary page
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'msh-image-optimizer' ) );
		}

		?>
		<div class="wrap msh-glossary-page">
			<h1 class="msh-page-title"><?php esc_html_e( 'Glossary Manager', 'msh-image-optimizer' ); ?></h1>
			<p class="msh-page-subtitle"><?php esc_html_e( 'Manage terminology for consistent AI-generated metadata across all locales.', 'msh-image-optimizer' ); ?></p>

			<div class="msh-glossary-container">
				<!-- Add New Term Form -->
				<div class="msh-glossary-add-form">
					<h2><?php esc_html_e( 'Add New Term', 'msh-image-optimizer' ); ?></h2>
					<form method="post" action="">
						<?php wp_nonce_field( 'msh_add_glossary_term', 'msh_glossary_nonce' ); ?>

						<div class="msh-form-row">
							<label for="term-source"><?php esc_html_e( 'Source Term', 'msh-image-optimizer' ); ?></label>
							<input type="text" id="term-source" name="source_term" placeholder="<?php esc_attr_e( 'e.g., physical therapy', 'msh-image-optimizer' ); ?>" required />
						</div>

						<div class="msh-form-row">
							<label for="term-replacement"><?php esc_html_e( 'Preferred Term', 'msh-image-optimizer' ); ?></label>
							<input type="text" id="term-replacement" name="replacement_term" placeholder="<?php esc_attr_e( 'e.g., physiotherapy', 'msh-image-optimizer' ); ?>" required />
						</div>

						<div class="msh-form-row">
							<label for="term-context"><?php esc_html_e( 'Context / Notes', 'msh-image-optimizer' ); ?></label>
							<textarea id="term-context" name="term_context" rows="3" placeholder="<?php esc_attr_e( 'Optional: Add context for when this replacement should be used...', 'msh-image-optimizer' ); ?>"></textarea>
						</div>

						<button type="submit" class="button button-primary msh-btn-add"><?php esc_html_e( 'Add Term', 'msh-image-optimizer' ); ?></button>
					</form>
				</div>

				<!-- Glossary Terms Table -->
				<div class="msh-glossary-terms">
					<h2><?php esc_html_e( 'Current Glossary', 'msh-image-optimizer' ); ?></h2>

					<div class="msh-notice msh-notice-info">
						<p><?php esc_html_e( 'Glossary functionality is coming in Phase 3. This interface will allow you to define terminology that AI uses when generating metadata.', 'msh-image-optimizer' ); ?></p>
					</div>

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
							<tr>
								<td colspan="4" class="msh-empty-state"><?php esc_html_e( 'No glossary terms defined yet. Add your first term above.', 'msh-image-optimizer' ); ?></td>
							</tr>
						</tbody>
					</table>
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
			margin-bottom: 40px;
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

		.msh-empty-state {
			text-align: center;
			color: #8b8883 !important;
			padding: 40px !important;
		}
		</style>
		<?php
	}
}

// Initialize
if ( is_admin() ) {
	new MSH_Glossary_Page();
}
