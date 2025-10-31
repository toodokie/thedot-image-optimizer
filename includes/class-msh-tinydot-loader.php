<?php
/**
 * TinyDot Loader Global Enqueue Helper
 *
 * Ensures the tinydot-loader.css is available on all admin pages
 * where MSH Image Optimizer needs loading animations.
 *
 * @package    MSH_Image_Optimizer
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSH_TinyDot_Loader
 *
 * Singleton that enqueues TinyDot loader styles globally in admin.
 */
class MSH_TinyDot_Loader {

	/**
	 * Singleton instance holder.
	 *
	 * @var MSH_TinyDot_Loader|null
	 */
	private static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @return MSH_TinyDot_Loader
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Lock constructor to enforce singleton usage.
	 */
	private function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_loader_styles' ), 5 );
	}

	/**
	 * Enqueue TinyDot loader CSS on all admin pages.
	 *
	 * Priority 5 ensures it loads early so other styles can override if needed.
	 *
	 * @param string $hook Current admin hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_loader_styles( $hook ) {
		// Only enqueue on MSH admin pages
		// Hook format: {menu_slug}_page_{page_slug} where menu_slug is from menu title "TinyDot"
		$msh_pages = array(
			'toplevel_page_msh-optimizer',
			'tinydot_page_msh-image-optimizer',  // Image Optimizer submenu page
			'tinydot_page_msh-hub',
			'tinydot_page_msh-glossary',
			'tinydot_page_msh-locale-profiles',
			'tinydot_page_msh-image-optimizer-settings',
			'tinydot_page_msh-context-analytics',
			'tinydot_page_msh-dashboard',
			'tinydot_page_msh-approval-queue',
			'tinydot_page_msh-version-history',
			'tinydot_page_msh-ab-testing',
		);

		// Also enqueue on upload.php (Media Library) for image upload feedback
		if ( ! in_array( $hook, $msh_pages, true ) && 'upload.php' !== $hook ) {
			return;
		}

		$assets_base = defined( 'MSH_IO_ASSETS_URL' )
			? trailingslashit( MSH_IO_ASSETS_URL )
			: trailingslashit( plugin_dir_url( __FILE__ ) . '../assets' );

		$style_file    = dirname( __FILE__ ) . '/../assets/css/tinydot-loader.css';
		$style_version = file_exists( $style_file ) ? filemtime( $style_file ) : '2.0.0';

		wp_enqueue_style(
			'msh-tinydot-loader',
			$assets_base . 'css/tinydot-loader.css',
			array(),
			$style_version
		);
	}

	/**
	 * Generate TinyDot loader HTML.
	 *
	 * Helper method to generate loader HTML for use in PHP templates.
	 *
	 * @param string $text Loading text to display.
	 * @param string $size Size variant: 'small', 'medium', 'large'.
	 * @param string $animation Animation variant: 'spin', 'pulse', 'bounce', 'pulse-spin'.
	 *
	 * @return string HTML markup for loader.
	 */
	public static function get_loader_html( $text = '', $size = 'medium', $animation = 'pulse-spin' ) {
		$text = $text ? esc_html( $text ) : esc_html__( 'Loading...', 'msh-image-optimizer' );

		$assets_base = defined( 'MSH_IO_ASSETS_URL' )
			? trailingslashit( MSH_IO_ASSETS_URL )
			: trailingslashit( plugin_dir_url( __FILE__ ) . '../assets' );

		$icon_url = $assets_base . 'icons/tinydot-icon.png';

		ob_start();
		?>
		<div class="msh-tinydot-loader msh-tinydot-loader--<?php echo esc_attr( $size ); ?> msh-tinydot-loader--<?php echo esc_attr( $animation ); ?>">
			<div class="msh-tinydot-loader__icon">
				<img src="<?php echo esc_url( $icon_url ); ?>" alt="">
			</div>
			<div class="msh-tinydot-loader__text">
				<?php echo $text; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

// Initialize singleton
MSH_TinyDot_Loader::get_instance();
