<?php
defined( 'ABSPATH' ) || exit;

class TCG_Product_Sync {

	public function __construct() {
		add_action( 'tcg_manager_product_saved', [ $this, 'sync_from_card' ], 10, 2 );
		add_action( 'admin_init', [ $this, 'migrate_catalog_visibility' ] );
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
