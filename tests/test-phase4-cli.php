<?php

namespace {

if ( ! defined( 'WP_CLI' ) ) {
	define( 'WP_CLI', true );
}

if ( ! class_exists( 'WP_CLI_Command' ) ) {
	class WP_CLI_Command {}
}

if ( ! class_exists( 'WP_CLI' ) ) {
	class WP_CLI {
		public static $messages = array();

		public static function line( $message ) {
			self::$messages[] = array( 'line', $message );
		}

		public static function success( $message ) {
			self::$messages[] = array( 'success', $message );
		}

		public static function warning( $message ) {
			self::$messages[] = array( 'warning', $message );
		}

		public static function error( $message ) {
			throw new \RuntimeException( $message );
		}

		public static function clear_messages() {
			self::$messages = array();
		}
	}
}

}

namespace WP_CLI\Utils {

if ( ! class_exists( 'Recorder' ) ) {
	class Recorder {
		public static $last_call = array();
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\format_items' ) ) {
	function format_items( $format, $items, $fields ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		Recorder::$last_call = compact( 'format', 'items', 'fields' );
	}
}

}

namespace {

class WP_CLI_Recorder_Helper {
	public static function reset() {
		\WP_CLI::clear_messages();
		\WP_CLI\Utils\Recorder::$last_call = array();
	}
}

class Test_MSH_Phase4_CLI extends \WP_UnitTestCase {

	public function setUp() : void {
		parent::setUp();

		WP_CLI_Recorder_Helper::reset();

		if ( ! class_exists( 'MSH_Version_CLI' ) ) {
			require_once MSH_IO_PLUGIN_DIR . 'includes/phase4/class-msh-phase4-cli.php';
		}
	}

	public function test_version_list_outputs_versions() {
		$media_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$versioning = MSH_Metadata_Versioning::get_instance();

		$versioning->save_version( $media_id, 'en_US', 'title', 'Variant A', 'ai' );
		$versioning->save_version( $media_id, 'en_US', 'title', 'Variant B', 'manual' );

		$cli = new \MSH_Version_CLI();
		$cli->list(
			array(),
			array(
				'media-id' => $media_id,
				'locale'   => 'en_US',
			)
		);

		$this->assertArrayHasKey( 'items', \WP_CLI\Utils\Recorder::$last_call );
		$this->assertCount( 2, \WP_CLI\Utils\Recorder::$last_call['items'] );
	}

	public function test_version_rollback_creates_new_version() {
		$media_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$versioning = MSH_Metadata_Versioning::get_instance();

		$v1 = $versioning->save_version( $media_id, 'en_US', 'alt', 'Alt A', 'ai' );
		$versioning->save_version( $media_id, 'en_US', 'alt', 'Alt B', 'manual' );

		$cli = new \MSH_Version_CLI();
		$cli->rollback(
			array(),
			array(
				'version-id' => $v1,
			)
		);

		$history = $versioning->get_version_history( $media_id, 'en_US', 'alt' );
		$this->assertCount( 3, $history );
		$this->assertEquals( 'rollback', $history[0]['source'] );
	}

	public function test_ab_create_and_results_flow() {
		$cli = new \MSH_AB_Testing_CLI();

		$cli->create(
			array(),
			array(
				'name'     => 'CLI Campaign',
				'variants' => 2,
			)
		);

		$campaigns = MSH_AB_Testing::get_instance()->get_campaigns();
		$this->assertNotEmpty( $campaigns );
		$campaign_id = $campaigns[0]['campaign_id'];

		$variants = MSH_AB_Testing::get_instance()->get_variants( $campaign_id );
		foreach ( $variants as $variant ) {
			MSH_AB_Testing::get_instance()->record_views( $variant['variant_id'], 50 );
			MSH_AB_Testing::get_instance()->record_clicks( $variant['variant_id'], 10 );
		}

		$cli->results(
			array(),
			array(
				'campaign-id' => $campaign_id,
			)
		);

		$this->assertEquals( 'table', \WP_CLI\Utils\Recorder::$last_call['format'] );

		$cli->winner(
			array(),
			array(
				'campaign-id' => $campaign_id,
			)
		);

		$campaign = MSH_AB_Testing::get_instance()->get_campaign( $campaign_id );
		$this->assertEquals( 'completed', $campaign['status'] );
	}
}

}
