<?php
defined( 'ABSPATH' ) || exit;

class TCG_Product_Form {

	public function __construct() {
		add_action( 'template_redirect', [ $this, 'process_form' ] );
		add_action( 'template_redirect', [ $this, 'process_bulk_add' ] );
		add_action( 'template_redirect', [ $this, 'process_delete' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Render the product form.
	 */
	public static function render_form( $product_id = 0 ) {
		$card_id      = 0;
		$card_title   = '';
		$price        = '';
		$stock        = '';
		$is_draft     = false;
		$condition   = [];
		$printing    = [];
		$language    = [];

		if ( $product_id ) {
			// Verify ownership.
			$product_post = get_post( $product_id );
			if ( ! $product_post || (int) $product_post->post_author !== get_current_user_id() ) {
				echo '<div class="tcg-alert tcg-alert-error">' . esc_html__( 'No tienes permiso para editar este producto.', 'tcg-manager' ) . '</div>';
				return;
			}

			$product    = wc_get_product( $product_id );
			$card_id    = (int) get_post_meta( $product_id, '_linked_ygo_card', true );
			$card_title = $card_id ? get_the_title( $card_id ) : '';
			$price      = $product ? $product->get_regular_price() : '';
			$stock      = $product ? $product->get_stock_quantity() : '';
			$is_draft   = $product_post->post_status === 'draft';

			$condition = wp_get_post_terms( $product_id, 'ygo_condition', [ 'fields' => 'ids' ] );
			$printing  = wp_get_post_terms( $product_id, 'ygo_printing', [ 'fields' => 'ids' ] );
			$language  = wp_get_post_terms( $product_id, 'ygo_language', [ 'fields' => 'ids' ] );

			if ( is_wp_error( $condition ) ) $condition = [];
			if ( is_wp_error( $printing ) )  $printing  = [];
			if ( is_wp_error( $language ) )  $language  = [];
		}
		?>
		<form method="post" class="tcg-product-form">
			<?php wp_nonce_field( 'tcg_save_product', 'tcg_product_nonce' ); ?>
			<input type="hidden" name="tcg_action" value="save_product">
			<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">

			<!-- Card Selector -->
			<div class="tcg-form-group tcg-card-selector">
				<label for="tcg-card-search" class="tcg-form-label">
					<?php esc_html_e( 'Carta vinculada', 'tcg-manager' ); ?> <span class="required">*</span>
				</label>
				<input
					type="text"
					id="tcg-card-search"
					class="tcg-form-control"
					placeholder="<?php esc_attr_e( 'Buscar carta por nombre...', 'tcg-manager' ); ?>"
					value="<?php echo esc_attr( $card_title ); ?>"
					<?php echo $card_id ? 'readonly' : ''; ?>
					autocomplete="off"
				>
				<input type="hidden" name="_linked_ygo_card" id="tcg-linked-card-id" value="<?php echo esc_attr( $card_id ); ?>">

				<?php if ( $card_id ) : ?>
					<button type="button" id="tcg-change-card" class="tcg-btn tcg-btn-secondary" style="margin-top:5px;">
						<?php esc_html_e( 'Cambiar carta', 'tcg-manager' ); ?>
					</button>
				<?php endif; ?>

				<div id="tcg-card-preview" class="tcg-card-preview" style="<?php echo $card_id ? '' : 'display:none;'; ?>">
				</div>
			</div>

			<?php
			// Taxonomy dropdowns.
			$taxonomies = [
				'ygo_condition' => [ 'label' => __( 'Condición', 'tcg-manager' ),  'current' => $condition ],
				'ygo_printing'  => [ 'label' => __( 'Printing', 'tcg-manager' ),   'current' => $printing ],
				'ygo_language'  => [ 'label' => __( 'Idioma', 'tcg-manager' ),     'current' => $language ],
			];

			foreach ( $taxonomies as $tax => $config ) :
				$terms = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => false, 'orderby' => 'name' ] );
				if ( is_wp_error( $terms ) || empty( $terms ) ) continue;
				?>
				<div class="tcg-form-group">
					<label for="tcg-<?php echo esc_attr( $tax ); ?>" class="tcg-form-label">
						<?php echo esc_html( $config['label'] ); ?> <span class="required">*</span>
					</label>
					<select name="<?php echo esc_attr( $tax ); ?>" id="tcg-<?php echo esc_attr( $tax ); ?>" class="tcg-form-control">
						<option value=""><?php esc_html_e( '— Seleccionar —', 'tcg-manager' ); ?></option>
						<?php foreach ( $terms as $term ) : ?>
							<option value="<?php echo esc_attr( $term->term_id ); ?>"
								<?php selected( in_array( $term->term_id, $config['current'], true ) ); ?>>
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endforeach; ?>

			<!-- Price -->
			<div class="tcg-form-group">
				<label for="tcg-price" class="tcg-form-label">
					<?php esc_html_e( 'Precio', 'tcg-manager' ); ?> (<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>) <span class="required">*</span>
				</label>
				<input type="number" name="price" id="tcg-price" class="tcg-form-control"
					   value="<?php echo esc_attr( $price ); ?>" step="0.01" min="0" required>
			</div>

			<!-- Stock -->
			<div class="tcg-form-group">
				<label for="tcg-stock" class="tcg-form-label">
					<?php esc_html_e( 'Stock', 'tcg-manager' ); ?> <span class="required">*</span>
				</label>
				<input type="number" name="stock" id="tcg-stock" class="tcg-form-control"
					   value="<?php echo esc_attr( $stock ); ?>" step="1" min="0" required>
			</div>

			<div class="tcg-form-actions">
				<button type="submit" class="tcg-btn tcg-btn-primary">
					<?php if ( $is_draft ) : ?>
						<?php esc_html_e( 'Publicar producto', 'tcg-manager' ); ?>
					<?php elseif ( $product_id ) : ?>
						<?php esc_html_e( 'Actualizar producto', 'tcg-manager' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Crear producto', 'tcg-manager' ); ?>
					<?php endif; ?>
				</button>
				<?php if ( $is_draft ) : ?>
					<button type="submit" name="save_draft" value="1" class="tcg-btn tcg-btn-secondary">
						<?php esc_html_e( 'Guardar borrador', 'tcg-manager' ); ?>
					</button>
				<?php endif; ?>
				<a href="<?php echo esc_url( TCG_Dashboard::get_dashboard_url( 'products' ) ); ?>" class="tcg-btn tcg-btn-secondary">
					<?php esc_html_e( 'Cancelar', 'tcg-manager' ); ?>
				</a>
			</div>
		</form>
		<?php
	}

	/**
	 * Process product form submission.
	 */
	public function process_form() {
		if ( ! isset( $_POST['tcg_action'] ) || $_POST['tcg_action'] !== 'save_product' ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['tcg_product_nonce'] ?? '', 'tcg_save_product' ) ) {
			return;
		}

		if ( ! TCG_Vendor_Role::is_vendor() ) {
			return;
		}

		$product_id = absint( $_POST['product_id'] ?? 0 );
		$card_id    = absint( $_POST['_linked_ygo_card'] ?? 0 );
		$price      = floatval( $_POST['price'] ?? 0 );
		$stock      = absint( $_POST['stock'] ?? 0 );

		if ( ! $card_id || get_post_type( $card_id ) !== 'ygo_card' ) {
			wp_safe_redirect( add_query_arg( 'tcg_error', urlencode( __( 'Carta no válida.', 'tcg-manager' ) ), TCG_Dashboard::get_dashboard_url( 'new-product' ) ) );
			exit;
		}

		$user_id = get_current_user_id();

		if ( $product_id ) {
			// Update: verify ownership.
			$existing = get_post( $product_id );
			if ( ! $existing || (int) $existing->post_author !== $user_id ) {
				return;
			}

			$new_status = ! empty( $_POST['save_draft'] ) ? 'draft' : 'publish';
			wp_update_post( [
				'ID'          => $product_id,
				'post_status' => $new_status,
			] );
		} else {
			// Create new product.
			$card = get_post( $card_id );
			$product_id = wp_insert_post( [
				'post_type'   => 'product',
				'post_status' => 'publish',
				'post_author' => $user_id,
				'post_title'  => $card->post_title,
			] );

			if ( is_wp_error( $product_id ) ) {
				wp_safe_redirect( add_query_arg( 'tcg_error', urlencode( __( 'Error al crear el producto.', 'tcg-manager' ) ), TCG_Dashboard::get_dashboard_url( 'new-product' ) ) );
				exit;
			}
		}

		// Save meta.
		update_post_meta( $product_id, '_linked_ygo_card', $card_id );
		update_post_meta( $product_id, '_regular_price', $price );
		update_post_meta( $product_id, '_price', $price );
		update_post_meta( $product_id, '_manage_stock', 'yes' );
		update_post_meta( $product_id, '_stock', $stock );
		update_post_meta( $product_id, '_stock_status', $stock > 0 ? 'instock' : 'outofstock' );

		// WC product type.
		wp_set_object_terms( $product_id, 'simple', 'product_type' );

		// Taxonomy terms.
		$taxonomies = [ 'ygo_condition', 'ygo_printing', 'ygo_language' ];
		foreach ( $taxonomies as $tax ) {
			if ( isset( $_POST[ $tax ] ) && $_POST[ $tax ] !== '' ) {
				wp_set_object_terms( $product_id, absint( $_POST[ $tax ] ), $tax );
			}
		}

		// Trigger sync hook.
		do_action( 'tcg_manager_product_saved', $product_id, $card_id );

		// If onboarding, advance to step 2.
		if ( ! empty( $_POST['tcg_onboarding'] ) ) {
			wp_safe_redirect( TCG_Dashboard::get_dashboard_url( 'onboarding', [ 'step' => 2 ] ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'tcg_msg', 'product_saved', TCG_Dashboard::get_dashboard_url( 'products' ) ) );
		exit;
	}

	/**
	 * Process bulk add by set — creates draft products.
	 */
	public function process_bulk_add() {
		if ( ! isset( $_POST['tcg_action'] ) || $_POST['tcg_action'] !== 'bulk_add' ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['tcg_bulk_nonce'] ?? '', 'tcg_bulk_add' ) ) {
			return;
		}

		if ( ! TCG_Vendor_Role::is_vendor() ) {
			return;
		}

		$card_ids = isset( $_POST['card_ids'] ) && is_array( $_POST['card_ids'] )
			? array_map( 'absint', $_POST['card_ids'] )
			: [];

		if ( empty( $card_ids ) ) {
			wp_safe_redirect( add_query_arg( 'tcg_error', urlencode( __( 'No seleccionaste ninguna carta.', 'tcg-manager' ) ), TCG_Dashboard::get_dashboard_url( 'bulk-add' ) ) );
			exit;
		}

		$user_id = get_current_user_id();
		$created = 0;

		foreach ( $card_ids as $card_id ) {
			if ( get_post_type( $card_id ) !== 'ygo_card' ) {
				continue;
			}

			$card = get_post( $card_id );
			if ( ! $card ) {
				continue;
			}

			$product_id = wp_insert_post( [
				'post_type'   => 'product',
				'post_status' => 'draft',
				'post_author' => $user_id,
				'post_title'  => $card->post_title,
			] );

			if ( is_wp_error( $product_id ) ) {
				continue;
			}

			update_post_meta( $product_id, '_linked_ygo_card', $card_id );
			update_post_meta( $product_id, '_manage_stock', 'yes' );
			update_post_meta( $product_id, '_stock', 0 );
			update_post_meta( $product_id, '_stock_status', 'outofstock' );
			wp_set_object_terms( $product_id, 'simple', 'product_type' );

			do_action( 'tcg_manager_product_saved', $product_id, $card_id );
			$created++;
		}

		$msg = sprintf( __( '%d borrador(es) creado(s). Edítalos para asignar precio, stock y condición.', 'tcg-manager' ), $created );
		wp_safe_redirect( add_query_arg( 'tcg_msg', 'bulk_created', TCG_Dashboard::get_dashboard_url( 'products' ) ) );
		exit;
	}

	/**
	 * Process product deletion.
	 */
	public function process_delete() {
		if ( ! isset( $_GET['tcg_action'] ) || $_GET['tcg_action'] !== 'delete_product' ) {
			return;
		}

		$product_id = absint( $_GET['product_id'] ?? 0 );
		if ( ! $product_id || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'tcg_delete_' . $product_id ) ) {
			return;
		}

		if ( ! TCG_Vendor_Role::is_vendor() ) {
			return;
		}

		$product = get_post( $product_id );
		if ( ! $product || (int) $product->post_author !== get_current_user_id() ) {
			return;
		}

		wp_trash_post( $product_id );

		wp_safe_redirect( add_query_arg( 'tcg_msg', 'product_deleted', TCG_Dashboard::get_dashboard_url( 'products' ) ) );
		exit;
	}

	/**
	 * Enqueue form assets on dashboard pages.
	 */
	public function enqueue_assets() {
		if ( ! TCG_Dashboard::is_dashboard_page() ) {
			return;
		}

		wp_enqueue_script( 'jquery-ui-autocomplete' );

		wp_enqueue_script(
			'tcg-vendor-form',
			TCG_MANAGER_URL . 'assets/js/vendor-form.js',
			[ 'jquery', 'jquery-ui-autocomplete' ],
			TCG_MANAGER_VERSION,
			true
		);

		wp_localize_script( 'tcg-vendor-form', 'tcgManager', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'tcg_manager_nonce' ),
			'cards'   => $this->get_all_cards_for_js(),
			'i18n'    => [
				'searching'  => __( 'Buscando...', 'tcg-manager' ),
				'noResults'  => __( 'No se encontraron cartas', 'tcg-manager' ),
				'selectCard' => __( 'Selecciona una carta para continuar', 'tcg-manager' ),
			],
		] );

		wp_enqueue_style(
			'tcg-vendor-form',
			TCG_MANAGER_URL . 'assets/css/vendor-form.css',
			[],
			TCG_MANAGER_VERSION
		);
	}

	/**
	 * Get all cards for client-side autocomplete.
	 */
	private function get_all_cards_for_js() {
		$cache_key = 'tcg_manager_cards_js';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT p.ID, p.post_title,
				MAX(CASE WHEN pm.meta_key = '_ygo_set_code' THEN pm.meta_value END) AS set_code,
				MAX(CASE WHEN pm.meta_key = '_ygo_set_rarity' THEN pm.meta_value END) AS set_rarity
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key IN ('_ygo_set_code', '_ygo_set_rarity')
			WHERE p.post_type = 'ygo_card' AND p.post_status = 'publish'
			GROUP BY p.ID
			ORDER BY p.post_title ASC",
			ARRAY_A
		);

		$cards = [];
		foreach ( $rows as $row ) {
			$set_code = $row['set_code'] ?: '';
			$cards[]  = [
				'id'         => (int) $row['ID'],
				'label'      => $row['post_title'] . ( $set_code ? " [{$set_code}]" : '' ),
				'value'      => $row['post_title'],
				'set_code'   => $set_code,
				'set_rarity' => $row['set_rarity'] ?: '',
			];
		}

		set_transient( $cache_key, $cards, HOUR_IN_SECONDS );
		return $cards;
	}
}
