<?php
defined( 'ABSPATH' ) || exit;

class TCG_Product_Sync {

	public function __construct() {
		add_action( 'tcg_manager_product_saved', [ $this, 'sync_from_card' ], 10, 2 );
		add_action( 'admin_init', [ $this, 'migrate_catalog_visibility' ] );
		add_action( 'admin_init', [ __CLASS__, 'bulk_recalc_listings_flag' ] );

		// Update _tcg_has_listings flag on card when product changes.
		add_action( 'tcg_manager_product_saved', [ $this, 'update_card_listings_flag' ] );
		add_action( 'trashed_post', [ $this, 'update_card_listings_flag_on_delete' ] );
		add_action( 'untrashed_post', [ $this, 'update_card_listings_flag' ] );
		add_action( 'woocommerce_product_set_stock', [ $this, 'update_card_listings_flag_from_product' ] );
	}

	/**
	 * Sync product data from the linked ygo_card.
	 */
	public function sync_from_card( $product_id, $card_id = null ) {
		if ( ! $card_id ) {
			$card_id = (int) get_post_meta( $product_id, '_linked_ygo_card', true );
		}

		if ( ! $card_id || get_post_type( $card_id ) !== 'ygo_card' ) {
			return;
		}

		$card = get_post( $card_id );
		if ( ! $card ) {
			return;
		}

		$excerpt = $this->build_stats_excerpt( $card_id );

		wp_update_post( [
			'ID'           => $product_id,
			'post_title'   => $card->post_title,
			'post_content' => $card->post_content,
			'post_excerpt' => $excerpt,
		] );

		// Hide from shop — card page is the public URL.
		$product = wc_get_product( $product_id );
		if ( $product && $product->get_catalog_visibility() !== 'hidden' ) {
			$product->set_catalog_visibility( 'hidden' );
			$product->save();
		}

		// Share featured image.
		$thumb_id = get_post_thumbnail_id( $card_id );
		if ( $thumb_id ) {
			set_post_thumbnail( $product_id, $thumb_id );
		}

		// Sync ygo_set taxonomy.
		$card_sets = wp_get_post_terms( $card_id, 'ygo_set', [ 'fields' => 'ids' ] );
		if ( ! is_wp_error( $card_sets ) && ! empty( $card_sets ) ) {
			wp_set_object_terms( $product_id, $card_sets, 'ygo_set' );
		}

		// Sync rarity.
		$rarity_name = get_post_meta( $card_id, '_ygo_set_rarity', true );
		if ( $rarity_name ) {
			$term = term_exists( $rarity_name, 'ygo_rarity' );
			if ( ! $term ) {
				$term = wp_insert_term( $rarity_name, 'ygo_rarity' );
			}
			if ( ! is_wp_error( $term ) ) {
				$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
				wp_set_object_terms( $product_id, $term_id, 'ygo_rarity' );
			}
		}
	}

	private function build_stats_excerpt( $card_id ) {
		$parts = [];

		$frame = get_post_meta( $card_id, '_ygo_frame_type', true );
		if ( $frame ) $parts[] = 'Type: ' . ucfirst( $frame );

		$typeline = get_post_meta( $card_id, '_ygo_typeline', true );
		if ( $typeline ) $parts[] = $typeline;

		$atk = get_post_meta( $card_id, '_ygo_atk', true );
		$def = get_post_meta( $card_id, '_ygo_def', true );
		if ( $atk !== '' ) {
			$stat = 'ATK/' . $atk;
			if ( $def !== '' ) $stat .= ' DEF/' . $def;
			$parts[] = $stat;
		}

		$level = get_post_meta( $card_id, '_ygo_level', true );
		if ( $level ) $parts[] = 'Level ' . $level;

		$rank = get_post_meta( $card_id, '_ygo_rank', true );
		if ( $rank ) $parts[] = 'Rank ' . $rank;

		$linkval = get_post_meta( $card_id, '_ygo_linkval', true );
		if ( $linkval ) $parts[] = 'Link-' . $linkval;

		$scale = get_post_meta( $card_id, '_ygo_scale', true );
		if ( $scale ) $parts[] = 'Scale ' . $scale;

		$set_code = get_post_meta( $card_id, '_ygo_set_code', true );
		if ( $set_code ) $parts[] = $set_code;

		return implode( ' | ', $parts );
	}

	/* ─── Listings flag ─── */

	/**
	 * Update _tcg_has_listings on the linked card after a product is saved.
	 */
	public function update_card_listings_flag( $product_id ) {
		$card_id = (int) get_post_meta( $product_id, '_linked_ygo_card', true );
		if ( $card_id ) {
			self::recalc_card_flag( $card_id );
		}
	}

	/**
	 * On product trash, update the card flag.
	 */
	public function update_card_listings_flag_on_delete( $product_id ) {
		if ( get_post_type( $product_id ) !== 'product' ) return;
		$this->update_card_listings_flag( $product_id );
	}

	/**
	 * On stock change via WooCommerce, update the card flag.
	 */
	public function update_card_listings_flag_from_product( $product ) {
		$this->update_card_listings_flag( $product->get_id() );
	}

	/**
	 * Recalculate _tcg_has_listings for a single card.
	 * Returns true if the card has at least one published product with stock > 0.
	 */
	public static function recalc_card_flag( $card_id ) {
		global $wpdb;

		$has = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(1) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_linked_ygo_card' AND pm.meta_value = %s
			 INNER JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = '_stock' AND CAST(ps.meta_value AS SIGNED) > 0
			 WHERE p.post_type = 'product' AND p.post_status = 'publish'
			 LIMIT 1",
			$card_id
		) );

		update_post_meta( $card_id, '_tcg_has_listings', $has ? '1' : '0' );
		return $has > 0;
	}

	/**
	 * Bulk recalculate _tcg_has_listings for all cards. Run once via admin_init.
	 */
	public static function bulk_recalc_listings_flag() {
		if ( get_transient( 'tcg_listings_flag_synced' ) ) {
			return;
		}

		global $wpdb;

		// Get all card IDs that have at least one published product with stock.
		$cards_with_listings = $wpdb->get_col(
			"SELECT DISTINCT pm.meta_value FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_linked_ygo_card'
			 INNER JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = '_stock' AND CAST(ps.meta_value AS SIGNED) > 0
			 WHERE p.post_type = 'product' AND p.post_status = 'publish'"
		);

		// Set all cards to 0 first.
		$wpdb->query(
			"UPDATE {$wpdb->postmeta} SET meta_value = '0'
			 WHERE meta_key = '_tcg_has_listings'"
		);

		// Set cards with listings to 1.
		foreach ( $cards_with_listings as $card_id ) {
			update_post_meta( (int) $card_id, '_tcg_has_listings', '1' );
		}

		set_transient( 'tcg_listings_flag_synced', 1, DAY_IN_SECONDS );
	}

	public function migrate_catalog_visibility() {
		if ( get_transient( 'tcg_manager_visibility_migrated' ) ) {
			return;
		}

		$page = 1;
		do {
			$query = new WP_Query( [
				'post_type'      => 'product',
				'posts_per_page' => 100,
				'paged'          => $page,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'meta_query'     => [ [ 'key' => '_linked_ygo_card', 'compare' => 'EXISTS' ] ],
			] );

			foreach ( $query->posts as $pid ) {
				$product = wc_get_product( $pid );
				if ( $product && $product->get_catalog_visibility() !== 'hidden' ) {
					$product->set_catalog_visibility( 'hidden' );
					$product->save();
				}
			}
			$page++;
		} while ( $query->found_posts > ( $page - 1 ) * 100 );

		set_transient( 'tcg_manager_visibility_migrated', 1, 0 );
	}
}
