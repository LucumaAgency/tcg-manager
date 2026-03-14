<?php
defined( 'ABSPATH' ) || exit;

class TCG_Display {

	public function __construct() {
		add_action( 'woocommerce_single_product_summary', [ $this, 'render_card_stats' ], 25 );
		add_action( 'woocommerce_single_product_summary', [ $this, 'render_ref_prices' ], 35 );
		add_action( 'template_redirect', [ $this, 'redirect_linked_products' ] );
	}

	public function redirect_linked_products() {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		$card_id = (int) get_post_meta( get_the_ID(), '_linked_ygo_card', true );
		if ( ! $card_id ) {
			return;
		}
		$card_url = get_permalink( $card_id );
		if ( $card_url ) {
			wp_redirect( $card_url, 301 );
			exit;
		}
	}

	public function render_card_stats() {
		global $product;
		$card_id = (int) get_post_meta( $product->get_id(), '_linked_ygo_card', true );
		if ( ! $card_id ) return;

		$stats = $this->get_display_stats( $card_id );
		if ( empty( $stats ) ) return;

		echo '<div class="tcg-card-stats">';
		echo '<h4>' . esc_html__( 'Card Stats', 'tcg-manager' ) . '</h4>';
		echo '<table class="tcg-stats-table">';
		foreach ( $stats as $label => $value ) {
			echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		}
		echo '</table></div>';
	}

	public function render_ref_prices() {
		global $product;
		$card_id = (int) get_post_meta( $product->get_id(), '_linked_ygo_card', true );
		if ( ! $card_id ) return;

		$prices_json = get_post_meta( $card_id, '_ygo_ref_prices', true );
		$prices = $prices_json ? json_decode( $prices_json, true ) : [];
		if ( empty( $prices ) ) return;

		$labels = [ 'tcgplayer' => 'TCGPlayer', 'cardmarket' => 'Cardmarket', 'ebay' => 'eBay', 'amazon' => 'Amazon' ];

		$has_values = false;
		foreach ( $labels as $key => $label ) {
			if ( ! empty( $prices[ $key ] ) && $prices[ $key ] !== '0' && $prices[ $key ] !== '0.00' ) {
				$has_values = true;
				break;
			}
		}
		if ( ! $has_values ) return;

		echo '<div class="tcg-ref-prices">';
		echo '<h4>' . esc_html__( 'Precios de Referencia', 'tcg-manager' ) . '</h4>';
		echo '<table class="tcg-prices-table">';
		foreach ( $labels as $key => $label ) {
			if ( empty( $prices[ $key ] ) || $prices[ $key ] === '0' || $prices[ $key ] === '0.00' ) continue;
			echo '<tr><th>' . esc_html( $label ) . '</th><td>$' . esc_html( $prices[ $key ] ) . '</td></tr>';
		}
		echo '</table></div>';
	}

	private function get_display_stats( $card_id ) {
		$stats = [];
		$map = [
			'_ygo_set_code'   => __( 'Set Code', 'tcg-manager' ),
			'_ygo_set_rarity' => __( 'Rarity', 'tcg-manager' ),
			'_ygo_frame_type' => __( 'Card Type', 'tcg-manager' ),
			'_ygo_typeline'   => __( 'Type', 'tcg-manager' ),
			'_ygo_level'      => __( 'Level', 'tcg-manager' ),
			'_ygo_rank'       => __( 'Rank', 'tcg-manager' ),
			'_ygo_linkval'    => __( 'Link', 'tcg-manager' ),
			'_ygo_scale'      => __( 'Pendulum Scale', 'tcg-manager' ),
			'_ygo_atk'        => __( 'ATK', 'tcg-manager' ),
			'_ygo_def'        => __( 'DEF', 'tcg-manager' ),
		];

		foreach ( $map as $key => $label ) {
			$val = get_post_meta( $card_id, $key, true );
			if ( $key === '_ygo_frame_type' && $val ) {
				$val = ucfirst( $val );
			}
			if ( $val !== '' && $val !== false ) {
				$stats[ $label ] = $val;
			}
		}

		$attrs = wp_get_post_terms( $card_id, 'ygo_attribute', [ 'fields' => 'names' ] );
		if ( ! is_wp_error( $attrs ) && ! empty( $attrs ) ) {
			$stats[ __( 'Attribute', 'tcg-manager' ) ] = implode( ', ', $attrs );
		}

		return $stats;
	}
}
