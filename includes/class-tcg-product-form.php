<?php
defined( 'ABSPATH' ) || exit;

class TCG_Product_Form {

	public function __construct() {
		add_action( 'template_redirect', [ $this, 'process_form' ] );
		add_action( 'template_redirect', [ $this, 'process_bulk_add' ] );
		add_action( 'template_redirect', [ $this, 'process_csv_import' ] );
		add_action( 'template_redirect', [ $this, 'process_csv_export' ] );
		add_action( 'template_redirect', [ $this, 'process_bulk_delete' ] );
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
		$rarity      = [];

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
			$rarity    = wp_get_post_terms( $product_id, 'ygo_rarity', [ 'fields' => 'ids' ] );

			if ( is_wp_error( $condition ) ) $condition = [];
			if ( is_wp_error( $printing ) )  $printing  = [];
			if ( is_wp_error( $language ) )   $language  = [];
			if ( is_wp_error( $rarity ) )     $rarity    = [];
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
				'ygo_rarity'    => [ 'label' => __( 'Rareza', 'tcg-manager' ),     'current' => $rarity ],
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
					   value="<?php echo esc_attr( $price ); ?>" step="0.01" min="0.01" required>
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

		if ( $price <= 0 ) {
			$redirect_tab = $product_id ? TCG_Dashboard::get_dashboard_url( 'edit-product', [ 'tcg-id' => $product_id ] ) : TCG_Dashboard::get_dashboard_url( 'new-product' );
			wp_safe_redirect( add_query_arg( 'tcg_error', urlencode( __( 'El precio debe ser mayor a 0.', 'tcg-manager' ) ), $redirect_tab ) );
			exit;
		}

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
		$taxonomies = [ 'ygo_rarity', 'ygo_condition', 'ygo_printing', 'ygo_language' ];
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
	 * Process CSV import — creates draft products from pasted tab-separated data.
	 */
	public function process_csv_import() {
		if ( ! isset( $_POST['tcg_action'] ) || $_POST['tcg_action'] !== 'csv_import' ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['tcg_csv_nonce'] ?? '', 'tcg_csv_import' ) ) {
			return;
		}

		if ( ! TCG_Vendor_Role::is_vendor() ) {
			return;
		}

		if ( empty( $_FILES['csv_file']['tmp_name'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_safe_redirect( add_query_arg( 'tcg_error', urlencode( __( 'No se subió ningún archivo.', 'tcg-manager' ) ), TCG_Dashboard::get_dashboard_url( 'import-csv' ) ) );
			exit;
		}

		$raw = file_get_contents( $_FILES['csv_file']['tmp_name'] );

		if ( empty( trim( $raw ) ) ) {
			wp_safe_redirect( add_query_arg( 'tcg_error', urlencode( __( 'El archivo está vacío.', 'tcg-manager' ) ), TCG_Dashboard::get_dashboard_url( 'import-csv' ) ) );
			exit;
		}

		$lines       = explode( "\n", $raw );
		$user_id     = get_current_user_id();
		$created     = 0;
		$updated     = 0;
		$errors      = 0;
		$error_names = [];
		$warn_names  = [];

		// Detect separator from first line.
		$first_line = trim( $lines[0] ?? '' );
		if ( strpos( $first_line, "\t" ) !== false ) {
			$separator = "\t";
		} elseif ( strpos( $first_line, ';' ) !== false ) {
			$separator = ';';
		} else {
			$separator = ',';
		}

		// Detect header format: TCGPlayer export or simple format.
		$col_map    = null;
		$header_row = true;

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			// Use str_getcsv to handle quoted fields with commas inside.
			$cols = str_getcsv( $line, $separator, '"', '\\' );
			if ( count( $cols ) < 3 ) {
				$errors++;
				continue;
			}

			// Detect and parse header row.
			if ( $header_row ) {
				$header_row = false;
				$normalized = array_map( function( $h ) { return strtolower( trim( $h ) ); }, $cols );

				// Check if this is a TCGPlayer format.
				if ( in_array( 'tcgplayer id', $normalized, true ) || in_array( 'product line', $normalized, true ) ) {
					$col_map = array_flip( $normalized );
					continue;
				}

				// Check if this is our simple format header.
				if ( in_array( 'product name', $normalized, true ) || in_array( 'nombre', $normalized, true ) ) {
					continue;
				}

				// No header detected — process this line as data.
			}

			// Map columns based on detected format.
			if ( $col_map !== null ) {
				// TCGPlayer format — map by column name.
				$product_name = trim( $cols[ $col_map['product name'] ?? -1 ] ?? '' );
				$set_code     = trim( $cols[ $col_map['number'] ?? -1 ] ?? '' );
				$rarity_raw   = trim( $cols[ $col_map['rarity'] ?? -1 ] ?? '' );
				$condition    = trim( $cols[ $col_map['condition'] ?? -1 ] ?? '' );
				$price_col    = $cols[ $col_map['tcg market price'] ?? $col_map['tcg low price'] ?? -1 ] ?? '';
				$price_raw    = str_replace( ',', '.', trim( $price_col ) );
				$price        = is_numeric( $price_raw ) ? floatval( $price_raw ) : 0;
				$quantity     = absint( $cols[ $col_map['add to quantity'] ?? $col_map['total quantity'] ?? -1 ] ?? 0 );
				$printing     = trim( $cols[ $col_map['printing'] ?? -1 ] ?? '' );
				$language     = trim( $cols[ $col_map['language'] ?? -1 ] ?? '' );
			} else {
				// Simple format: Product Name | Number | Rarity | Condition | Price | Quantity | Printing | Language
				$product_name = trim( $cols[0] ?? '' );
				$set_code     = trim( $cols[1] ?? '' );
				$rarity_raw   = trim( $cols[2] ?? '' );
				$condition    = trim( $cols[3] ?? '' );
				$price_raw    = str_replace( ',', '.', trim( $cols[4] ?? '' ) );
				$price        = is_numeric( $price_raw ) ? floatval( $price_raw ) : 0;
				$quantity     = absint( $cols[5] ?? 0 );
				$printing     = trim( $cols[6] ?? '' );
				$language     = trim( $cols[7] ?? '' );
			}

			if ( empty( $product_name ) && empty( $set_code ) ) {
				$errors++;
				continue;
			}

			// Find ygo_card by _ygo_set_code, fallback to title search.
			$card_id = 0;
			if ( $set_code ) {
				$card_id = $this->find_card_by_set_code( $set_code );
			}
			if ( ! $card_id && $product_name ) {
				$card_id = $this->find_card_by_name( $product_name );
			}
			if ( ! $card_id ) {
				$error_names[] = $product_name . ( $set_code ? ' [' . $set_code . ']' : '' );
				$errors++;
				continue;
			}

			// Filter rarity: remove "Short Print", keep rest.
			$rarity = $this->clean_rarity( $rarity_raw );

			// Validate taxonomy values — clear invalid ones and warn.
			$tax_validations = [
				'condition' => [ 'value' => &$condition, 'taxonomy' => 'ygo_condition' ],
				'printing'  => [ 'value' => &$printing,  'taxonomy' => 'ygo_printing' ],
				'language'  => [ 'value' => &$language,  'taxonomy' => 'ygo_language' ],
			];
			foreach ( $tax_validations as $field => $info ) {
				if ( $info['value'] && ! term_exists( $info['value'], $info['taxonomy'] ) ) {
					$warn_names[] = $product_name . ': ' . $field . ' "' . $info['value'] . '" no existe';
					$info['value'] = ''; // Clear invalid value.
				}
			}

			// Validate price.
			if ( ! empty( trim( $cols[7] ?? '' ) ) && $price <= 0 ) {
				$warn_names[] = $product_name . ': precio "' . trim( $cols[7] ?? '' ) . '" no valido';
			}

			// Check for existing product with same card + rarity + condition + printing + language.
			$existing_id = $this->find_existing_vendor_product( $user_id, $card_id, $rarity, $condition, $printing, $language );

			if ( $existing_id ) {
				// Update existing product.
				$product_id = $existing_id;
				if ( $quantity > 0 ) {
					update_post_meta( $product_id, '_stock', $quantity );
					update_post_meta( $product_id, '_stock_status', 'instock' );
				}
				if ( $price > 0 ) {
					update_post_meta( $product_id, '_regular_price', $price );
					update_post_meta( $product_id, '_price', $price );
				}
				$updated++;
			} else {
				// Determine status: draft if missing any data, publish if complete.
				$has_all_data = $price > 0 && $quantity > 0 && $condition && $printing && $rarity_raw;
				$post_status  = $has_all_data ? 'publish' : 'draft';

				// Create product.
				$product_id = wp_insert_post( [
					'post_type'   => 'product',
					'post_status' => $post_status,
					'post_author' => $user_id,
					'post_title'  => get_the_title( $card_id ),
				] );

				if ( is_wp_error( $product_id ) ) {
					$errors++;
					continue;
				}

				// Meta.
				update_post_meta( $product_id, '_linked_ygo_card', $card_id );
				update_post_meta( $product_id, '_manage_stock', 'yes' );
				update_post_meta( $product_id, '_stock', $quantity );
				update_post_meta( $product_id, '_stock_status', $quantity > 0 ? 'instock' : 'outofstock' );
				if ( $price > 0 ) {
					update_post_meta( $product_id, '_regular_price', $price );
					update_post_meta( $product_id, '_price', $price );
				}
				wp_set_object_terms( $product_id, 'simple', 'product_type' );

				// Taxonomies.
				$this->set_taxonomy_term( $product_id, $rarity, 'ygo_rarity', true );
				$this->set_taxonomy_term( $product_id, $condition, 'ygo_condition' );
				$this->set_taxonomy_term( $product_id, $printing, 'ygo_printing' );
				$this->set_taxonomy_term( $product_id, $language, 'ygo_language' );

				$created++;
			}

			do_action( 'tcg_manager_product_saved', $product_id, $card_id );
		}

		// Delete uploaded file.
		@unlink( $_FILES['csv_file']['tmp_name'] );

		$total_ok  = $created + $updated;
		$warnings  = ! empty( $warn_names ) ? ' | ' . implode( '; ', array_slice( $warn_names, 0, 5 ) ) : '';

		if ( $total_ok > 0 && $errors > 0 ) {
			$msg = sprintf( __( '%d creado(s), %d actualizado(s), %d error(es): %s', 'tcg-manager' ), $created, $updated, $errors, implode( ', ', array_slice( $error_names, 0, 5 ) ) ) . $warnings;
			wp_safe_redirect( add_query_arg( 'tcg_error', urlencode( $msg ), TCG_Dashboard::get_dashboard_url( 'products' ) ) );
		} elseif ( $total_ok > 0 && ! empty( $warn_names ) ) {
			$msg = sprintf( __( '%d creado(s), %d actualizado(s). Advertencias: %s', 'tcg-manager' ), $created, $updated, implode( '; ', array_slice( $warn_names, 0, 5 ) ) );
			wp_safe_redirect( add_query_arg( 'tcg_error', urlencode( $msg ), TCG_Dashboard::get_dashboard_url( 'products' ) ) );
		} elseif ( $total_ok > 0 ) {
			wp_safe_redirect( add_query_arg( 'tcg_msg', 'csv_imported', TCG_Dashboard::get_dashboard_url( 'products' ) ) );
		} else {
			$msg = sprintf( __( 'No se creó ningún producto. %d error(es).', 'tcg-manager' ), $errors );
			if ( ! empty( $error_names ) ) {
				$msg .= ' ' . __( 'No encontradas:', 'tcg-manager' ) . ' ' . implode( ', ', array_slice( $error_names, 0, 10 ) );
			}
			wp_safe_redirect( add_query_arg( 'tcg_error', urlencode( $msg ), TCG_Dashboard::get_dashboard_url( 'import-csv' ) ) );
		}
		exit;
	}

	/**
	 * Export vendor products as CSV download.
	 */
	public function process_csv_export() {
		if ( ! isset( $_GET['tcg_action'] ) || $_GET['tcg_action'] !== 'export_csv' ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'tcg_csv_export' ) ) {
			return;
		}

		if ( ! TCG_Vendor_Role::is_vendor() ) {
			return;
		}

		$vendor_id = get_current_user_id();

		$query = new WP_Query( [
			'post_type'      => 'product',
			'post_status'    => [ 'publish', 'draft', 'pending' ],
			'author'         => $vendor_id,
			'posts_per_page' => -1,
			'meta_query'     => [ [ 'key' => '_linked_ygo_card', 'compare' => 'EXISTS' ] ],
		] );

		$shop_name = sanitize_title( TCG_Vendor_Profile::get_shop_name( $vendor_id ) );
		$filename  = 'productos-' . $shop_name . '-' . date( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// BOM for Excel UTF-8.
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Header row.
		fputcsv( $output, [
			'Product Name',
			'Number',
			'Rarity',
			'Condition',
			'Price',
			'Quantity',
			'Printing',
			'Language',
			'Status',
		] );

		foreach ( $query->posts as $post ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product ) {
				continue;
			}

			$card_id  = (int) get_post_meta( $post->ID, '_linked_ygo_card', true );
			$set_code = $card_id ? get_post_meta( $card_id, '_ygo_set_code', true ) : '';

			$rarity    = wp_get_post_terms( $post->ID, 'ygo_rarity', [ 'fields' => 'names' ] );
			$condition = wp_get_post_terms( $post->ID, 'ygo_condition', [ 'fields' => 'names' ] );
			$printing  = wp_get_post_terms( $post->ID, 'ygo_printing', [ 'fields' => 'names' ] );
			$language  = wp_get_post_terms( $post->ID, 'ygo_language', [ 'fields' => 'names' ] );

			fputcsv( $output, [
				$post->post_title,
				$set_code,
				! is_wp_error( $rarity ) && ! empty( $rarity ) ? $rarity[0] : '',
				! is_wp_error( $condition ) && ! empty( $condition ) ? $condition[0] : '',
				$product->get_regular_price() ?? '',
				$product->get_stock_quantity() ?? 0,
				! is_wp_error( $printing ) && ! empty( $printing ) ? $printing[0] : '',
				! is_wp_error( $language ) && ! empty( $language ) ? $language[0] : '',
				$post->post_status,
			] );
		}

		fclose( $output );
		wp_reset_postdata();
		exit;
	}

	/**
	 * Find a ygo_card post by _ygo_set_code meta.
	 */
	private function find_card_by_set_code( $set_code ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_ygo_set_code' AND pm.meta_value = %s
			 WHERE p.post_type = 'ygo_card' AND p.post_status = 'publish'
			 LIMIT 1",
			$set_code
		) );
	}

	/**
	 * Find existing vendor product with same card + rarity + condition + printing + language.
	 */
	private function find_existing_vendor_product( $vendor_id, $card_id, $rarity, $condition, $printing, $language ) {
		$args = [
			'post_type'      => 'product',
			'post_status'    => [ 'publish', 'draft', 'pending' ],
			'author'         => $vendor_id,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [
				[ 'key' => '_linked_ygo_card', 'value' => $card_id ],
			],
		];

		// Build taxonomy query for matching variant.
		$tax_query = [];
		$checks    = [
			'ygo_rarity'    => $rarity,
			'ygo_condition' => $condition,
			'ygo_printing'  => $printing,
			'ygo_language'  => $language,
		];

		foreach ( $checks as $taxonomy => $term_name ) {
			if ( $term_name ) {
				$term = get_term_by( 'name', $term_name, $taxonomy );
				if ( $term ) {
					$tax_query[] = [
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					];
				}
			} else {
				// Match products that also don't have this taxonomy set.
				$tax_query[] = [
					'taxonomy' => $taxonomy,
					'operator' => 'NOT EXISTS',
				];
			}
		}

		if ( ! empty( $tax_query ) ) {
			$tax_query['relation'] = 'AND';
			$args['tax_query']     = $tax_query;
		}

		$results = get_posts( $args );
		return ! empty( $results ) ? $results[0] : 0;
	}

	/**
	 * Set a taxonomy term on a product. Optionally auto-create if not exists.
	 */
	private function set_taxonomy_term( $product_id, $term_name, $taxonomy, $auto_create = false ) {
		if ( empty( $term_name ) ) {
			return;
		}
		$term = term_exists( $term_name, $taxonomy );
		if ( ! $term && $auto_create ) {
			$term = wp_insert_term( $term_name, $taxonomy );
		}
		if ( $term && ! is_wp_error( $term ) ) {
			$tid = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
			wp_set_object_terms( $product_id, $tid, $taxonomy );
		}
	}

	/**
	 * Find a ygo_card post by title (partial match with set code in title).
	 */
	private function find_card_by_name( $name ) {
		global $wpdb;
		$like = $wpdb->esc_like( $name ) . '%';
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_type = 'ygo_card' AND post_status = 'publish' AND post_title LIKE %s
			 LIMIT 1",
			$like
		) );
	}

	/**
	 * Clean rarity string: remove "Short Print", return first valid rarity.
	 */
	private function clean_rarity( $raw ) {
		if ( empty( $raw ) ) {
			return '';
		}

		$parts = array_map( 'trim', explode( '/', $raw ) );
		$clean = [];
		foreach ( $parts as $part ) {
			if ( strtolower( $part ) !== 'short print' ) {
				$clean[] = $part;
			}
		}

		return ! empty( $clean ) ? $clean[0] : '';
	}

	/**
	 * Process product deletion.
	 */
	/**
	 * Process bulk delete of products.
	 */
	public function process_bulk_delete() {
		if ( ! isset( $_POST['tcg_action'] ) || $_POST['tcg_action'] !== 'bulk_delete' ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['tcg_bulk_delete_nonce'] ?? '', 'tcg_bulk_delete' ) ) {
			return;
		}

		if ( ! TCG_Vendor_Role::is_vendor() ) {
			return;
		}

		$product_ids = isset( $_POST['product_ids'] ) && is_array( $_POST['product_ids'] )
			? array_map( 'absint', $_POST['product_ids'] )
			: [];

		if ( empty( $product_ids ) ) {
			wp_safe_redirect( TCG_Dashboard::get_dashboard_url( 'products' ) );
			exit;
		}

		$user_id = get_current_user_id();
		$deleted = 0;

		foreach ( $product_ids as $pid ) {
			$post = get_post( $pid );
			if ( $post && (int) $post->post_author === $user_id ) {
				wp_trash_post( $pid );
				$deleted++;
			}
		}

		$msg = sprintf( __( '%d producto(s) eliminado(s).', 'tcg-manager' ), $deleted );
		wp_safe_redirect( add_query_arg( 'tcg_msg', 'product_deleted', TCG_Dashboard::get_dashboard_url( 'products' ) ) );
		exit;
	}

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
