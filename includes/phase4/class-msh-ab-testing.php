<?php
/**
 * Phase 4 - A/B Testing Manager.
 *
 * Handles creation of metadata experimentation campaigns, variant tracking,
 * and statistical analysis.
 *
 * @package MSH_Image_Optimizer
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSH_AB_Testing
 */
class MSH_AB_Testing {

	const CAMPAIGNS_TABLE = 'msh_ab_campaigns';
	const VARIANTS_TABLE  = 'msh_ab_variants';
	const SCHEMA_VERSION  = 1;

	/**
	 * Singleton instance.
	 *
	 * @var MSH_AB_Testing|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_AB_Testing
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
		add_action( 'init', array( $this, 'maybe_create_tables' ) );
	}

	/**
	 * Full table name with prefix.
	 *
	 * @param string $table Base table name.
	 * @return string
	 */
	private function table( $table ) {
		global $wpdb;
		return $wpdb->prefix . $table;
	}

	/**
	 * Maybe create database tables.
	 *
	 * @return void
	 */
	public function maybe_create_tables() {
		$installed = (int) get_option( 'msh_ab_testing_schema_version', 0 );
		if ( $installed >= self::SCHEMA_VERSION ) {
			return;
		}

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$campaign_sql = "CREATE TABLE {$this->table( self::CAMPAIGNS_TABLE )} (
			campaign_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			description TEXT NULL,
			created_at DATETIME NOT NULL,
			created_by BIGINT(20) UNSIGNED NULL,
			winner_id BIGINT(20) UNSIGNED NULL,
			auto_selected TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (campaign_id),
			KEY status_idx (status),
			KEY winner_idx (winner_id),
			KEY created_at_idx (created_at)
		) {$charset_collate};";

		$variant_sql = "CREATE TABLE {$this->table( self::VARIANTS_TABLE )} (
			variant_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id BIGINT(20) UNSIGNED NOT NULL,
			label VARCHAR(50) NOT NULL,
			media_id BIGINT(20) UNSIGNED NULL,
			metadata_json LONGTEXT NULL,
			views BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			clicks BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			ctr DECIMAL(6,4) NOT NULL DEFAULT 0,
			last_updated DATETIME NOT NULL,
			PRIMARY KEY (variant_id),
			KEY campaign_idx (campaign_id),
			KEY media_idx (media_id),
			CONSTRAINT fk_campaign FOREIGN KEY (campaign_id)
				REFERENCES {$this->table( self::CAMPAIGNS_TABLE )} (campaign_id)
				ON DELETE CASCADE
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $campaign_sql );
		dbDelta( $variant_sql );

		update_option( 'msh_ab_testing_schema_version', self::SCHEMA_VERSION );
	}

	/**
	 * Create new campaign.
	 *
	 * @param string $name        Campaign name.
	 * @param int    $variants    Number of placeholder variants.
	 * @param array  $args        Additional data.
	 * @return int|false
	 */
	public function create_campaign( $name, $variants = 2, $args = array() ) {
		global $wpdb;

		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			return false;
		}

		$wpdb->insert(
			$this->table( self::CAMPAIGNS_TABLE ),
			array(
				'name'        => $name,
				'status'      => isset( $args['status'] ) ? sanitize_text_field( $args['status'] ) : 'draft',
				'description' => isset( $args['description'] ) ? wp_kses_post( $args['description'] ) : null,
				'created_at'  => current_time( 'mysql' ),
				'created_by'  => isset( $args['created_by'] ) ? absint( $args['created_by'] ) : get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);

		if ( ! $wpdb->insert_id ) {
			return false;
		}

		$campaign_id = (int) $wpdb->insert_id;
		$labels      = range( 65, 90 ); // ASCII A-Z.

		for ( $i = 0; $i < max( 2, (int) $variants ); $i++ ) {
			$this->add_variant(
				$campaign_id,
				array(
					'label'         => 'Variant ' . chr( $labels[ $i % count( $labels ) ] ),
					'metadata_json' => wp_json_encode( array() ),
				)
			);
		}

		return $campaign_id;
	}

	/**
	 * Add variant to campaign.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $data        Variant data.
	 * @return int|false
	 */
	public function add_variant( $campaign_id, $data ) {
		global $wpdb;

		$campaign_id = absint( $campaign_id );
		if ( ! $campaign_id ) {
			return false;
		}

		$label = isset( $data['label'] ) ? sanitize_text_field( $data['label'] ) : '';
		if ( '' === $label ) {
			return false;
		}

		$metadata_json = isset( $data['metadata_json'] ) ? $data['metadata_json'] : null;
		if ( is_array( $metadata_json ) ) {
			$metadata_json = wp_json_encode( $metadata_json );
		}

		$wpdb->insert(
			$this->table( self::VARIANTS_TABLE ),
			array(
				'campaign_id'   => $campaign_id,
				'label'         => $label,
				'media_id'      => isset( $data['media_id'] ) ? absint( $data['media_id'] ) : null,
				'metadata_json' => $metadata_json,
				'last_updated'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s' )
		);

		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Record view for variant.
	 *
	 * @param int $variant_id Variant ID.
	 * @param int $views      Views count to add.
	 * @return bool
	 */
	public function record_views( $variant_id, $views = 1 ) {
		return $this->increment_metric( $variant_id, 'views', $views );
	}

	/**
	 * Record click for variant.
	 *
	 * @param int $variant_id Variant ID.
	 * @param int $clicks     Clicks count to add.
	 * @return bool
	 */
	public function record_clicks( $variant_id, $clicks = 1 ) {
		return $this->increment_metric( $variant_id, 'clicks', $clicks );
	}

	/**
	 * Helper to bump metric and recalc CTR.
	 *
	 * @param int    $variant_id Variant ID.
	 * @param string $column     Column to increment.
	 * @param int    $amount     Amount.
	 * @return bool
	 */
	private function increment_metric( $variant_id, $column, $amount ) {
		global $wpdb;

		$variant_id = absint( $variant_id );
		if ( ! $variant_id || ! in_array( $column, array( 'views', 'clicks' ), true ) ) {
			return false;
		}

		$amount = max( 1, absint( $amount ) );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table( self::VARIANTS_TABLE )}
				SET {$column} = {$column} + %d,
				    last_updated = %s
				WHERE variant_id = %d",
				$amount,
				current_time( 'mysql' ),
				$variant_id
			)
		);

		$this->recalculate_ctr( $variant_id );
		return true;
	}

	/**
	 * Recalculate CTR for variant.
	 *
	 * @param int $variant_id Variant ID.
	 * @return void
	 */
	private function recalculate_ctr( $variant_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT views, clicks FROM {$this->table( self::VARIANTS_TABLE )} WHERE variant_id = %d",
				$variant_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return;
		}

		$views = (int) $row['views'];
		$clicks = (int) $row['clicks'];
		$ctr = $views > 0 ? round( ( $clicks / $views ), 4 ) : 0;

		$wpdb->update(
			$this->table( self::VARIANTS_TABLE ),
			array(
				'ctr'          => $ctr,
				'last_updated' => current_time( 'mysql' ),
			),
			array( 'variant_id' => $variant_id ),
			array( '%f', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get campaign with variants.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array|null
	 */
	public function get_campaign( $campaign_id ) {
		global $wpdb;

		$campaign = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table( self::CAMPAIGNS_TABLE )} WHERE campaign_id = %d",
				absint( $campaign_id )
			),
			ARRAY_A
		);

		if ( ! $campaign ) {
			return null;
		}

		$campaign['variants'] = $this->get_variants( $campaign_id );
		$campaign['significance'] = $this->calculate_significance( $campaign_id );

		return $campaign;
	}

	/**
	 * Fetch variants for campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array
	 */
	public function get_variants( $campaign_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table( self::VARIANTS_TABLE )} WHERE campaign_id = %d ORDER BY variant_id ASC",
				absint( $campaign_id )
			),
			ARRAY_A
		);
	}

	/**
	 * Calculate statistical significance.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array
	 */
	public function calculate_significance( $campaign_id ) {
		$variants = $this->get_variants( $campaign_id );
		if ( count( $variants ) < 2 ) {
			return array(
				'is_significant' => false,
				'p_value'        => null,
				'winner'         => null,
				'reason'         => __( 'Not enough variants for comparison.', 'msh-image-optimizer' ),
			);
		}

		usort(
			$variants,
			function ( $a, $b ) {
				return $b['ctr'] <=> $a['ctr'];
			}
		);

		$control = $variants[0];
		// Compare control against second best.
		$challenger = $variants[1];

		if ( (int) $control['views'] < 30 || (int) $challenger['views'] < 30 ) {
			return array(
				'is_significant' => false,
				'p_value'        => null,
				'winner'         => null,
				'reason'         => __( 'Need at least 30 views per variant.', 'msh-image-optimizer' ),
			);
		}

		$result = $this->two_proportion_z_test( $control['clicks'], $control['views'], $challenger['clicks'], $challenger['views'] );

		return array(
			'is_significant' => $result['p_value'] <= 0.05,
			'p_value'        => $result['p_value'],
			'z_score'        => $result['z_score'],
			'winner'         => $result['winner'],
			'control'        => $control['variant_id'],
			'challenger'     => $challenger['variant_id'],
		);
	}

	/**
	 * Two proportion z test.
	 *
	 * @param int $clicks_a Clicks variant A.
	 * @param int $views_a  Views variant A.
	 * @param int $clicks_b Clicks variant B.
	 * @param int $views_b  Views variant B.
	 * @return array
	 */
	private function two_proportion_z_test( $clicks_a, $views_a, $clicks_b, $views_b ) {
		$views_a  = max( 1, (int) $views_a );
		$views_b  = max( 1, (int) $views_b );
		$clicks_a = (int) $clicks_a;
		$clicks_b = (int) $clicks_b;

		$p1 = $clicks_a / $views_a;
		$p2 = $clicks_b / $views_b;
		$p_pool = ( $clicks_a + $clicks_b ) / ( $views_a + $views_b );
		$standard_error = sqrt( $p_pool * ( 1 - $p_pool ) * ( 1 / $views_a + 1 / $views_b ) );

		if ( $standard_error <= 0 ) {
			return array(
				'z_score' => 0,
				'p_value' => 1,
				'winner'  => null,
			);
		}

		$z = ( $p1 - $p2 ) / $standard_error;
		$p_value = 2 * ( 1 - $this->normal_cdf( abs( $z ) ) );
		$winner  = $p1 > $p2 ? 'a' : 'b';

		return array(
			'z_score' => round( $z, 4 ),
			'p_value' => round( $p_value, 4 ),
			'winner'  => $winner,
		);
	}

	/**
	 * Normal cumulative distribution function approximation.
	 *
	 * @param float $value Z score.
	 * @return float
	 */
	private function normal_cdf( $value ) {
		$sign = $value >= 0 ? 1 : -1;
		$x    = abs( $value ) / sqrt( 2 );
		$t    = 1 / ( 1 + 0.3275911 * $x );

		// Abramowitz and Stegun formula 7.1.26 approximation for error function.
		$a1 = 0.254829592;
		$a2 = -0.284496736;
		$a3 = 1.421413741;
		$a4 = -1.453152027;
		$a5 = 1.061405429;

		$erf = 1 - ( ( ( ( ( $a5 * $t + $a4 ) * $t ) + $a3 ) * $t + $a2 ) * $t + $a1 ) * $t * exp( -$x * $x );
		$erf = $sign * $erf;

		return 0.5 * ( 1 + $erf );
	}

	/**
	 * Automatically select winner when significance threshold met.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool
	 */
	public function maybe_select_winner( $campaign_id ) {
		global $wpdb;

		$campaign = $this->get_campaign( $campaign_id );
		if ( ! $campaign ) {
			return false;
		}

		$significance = $campaign['significance'];
		if ( empty( $significance['is_significant'] ) || empty( $significance['winner'] ) ) {
			return false;
		}

		$winner_variant = ( 'a' === $significance['winner'] ) ? $significance['control'] : $significance['challenger'];

		$wpdb->update(
			$this->table( self::CAMPAIGNS_TABLE ),
			array(
				'status'       => 'completed',
				'winner_id'    => $winner_variant,
				'auto_selected'=> 1,
			),
			array( 'campaign_id' => $campaign_id ),
			array( '%s', '%d', '%d' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * List campaigns.
	 *
	 * @param array $args Filter args.
	 * @return array
	 */
	public function get_campaigns( $args = array() ) {
		global $wpdb;

		$where  = array();
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_text_field( $args['status'] );
		}

		$sql = "SELECT * FROM {$this->table( self::CAMPAIGNS_TABLE )}";
		if ( $where ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$sql .= ' ORDER BY created_at DESC';

		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		$campaigns = $wpdb->get_results( $sql, ARRAY_A );

		foreach ( $campaigns as &$campaign ) {
			$campaign['variants'] = $this->get_variants( $campaign['campaign_id'] );
		}

		return $campaigns;
	}

	/**
	 * Update campaign status.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $status      New status.
	 * @return bool
	 */
	public function update_status( $campaign_id, $status ) {
		global $wpdb;

		$allowed = array( 'draft', 'running', 'paused', 'completed' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		return false !== $wpdb->update(
			$this->table( self::CAMPAIGNS_TABLE ),
			array( 'status' => $status ),
			array( 'campaign_id' => absint( $campaign_id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}
}
