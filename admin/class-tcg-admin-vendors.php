<?php
defined( 'ABSPATH' ) || exit;

class TCG_Admin_Vendors {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'handle_save' ] );
	}

	public function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Vendedores', 'tcg-manager' ),
			__( 'Vendedores', 'tcg-manager' ),
			'manage_woocommerce',
			'tcg-vendors',
			[ $this, 'render_page' ]
		);
	}

	public function handle_save() {
		if ( ! isset( $_POST['tcg_save_vendor'] ) ) return;
		check_admin_referer( 'tcg_vendor_edit' );

		$vendor_id = absint( $_POST['vendor_id'] );
		if ( ! $vendor_id ) return;

		$rate  = sanitize_text_field( $_POST['commission_rate'] ?? '' );
		$fixed = sanitize_text_field( $_POST['commission_fixed'] ?? '' );
		update_user_meta( $vendor_id, '_tcg_commission_rate', $rate );
		update_user_meta( $vendor_id, '_tcg_commission_fixed', $fixed );

		wp_safe_redirect( admin_url( 'admin.php?page=tcg-vendors&tcg_msg=saved' ) );
		exit;
	}

	public function render_page() {
		// Edit single vendor.
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['vendor_id'] ) ) {
			$this->render_edit( absint( $_GET['vendor_id'] ) );
			return;
		}

		$vendors = get_users( [
			'role'    => 'tcg_vendor',
			'orderby' => 'registered',
			'order'   => 'DESC',
		] );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Vendedores', 'tcg-manager' ); ?></h1>

			<?php if ( isset( $_GET['tcg_msg'] ) && $_GET['tcg_msg'] === 'saved' ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Vendedor actualizado.', 'tcg-manager' ); ?></p></div>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th>ID</th>
					<th><?php esc_html_e( 'Nombre', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Tienda', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Comisión', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Productos', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Balance Pendiente', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Registro', 'tcg-manager' ); ?></th>
					<th><?php esc_html_e( 'Acciones', 'tcg-manager' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $vendors ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No hay vendedores registrados.', 'tcg-manager' ); ?></td></tr>
				<?php else : foreach ( $vendors as $vendor ) :
					$shop_name = TCG_Vendor_Profile::get_shop_name( $vendor->ID );
					$balance   = TCG_Commissions::get_vendor_balance( $vendor->ID );
					$products  = count_user_posts( $vendor->ID, 'product', true );
					?>
					<tr>
						<td><?php echo esc_html( $vendor->ID ); ?></td>
						<td><?php echo esc_html( $vendor->display_name ); ?></td>
						<td><?php echo esc_html( $shop_name ); ?></td>
						<td><?php echo wp_kses_post( TCG_Commissions::format_commission( $vendor->ID ) ); ?></td>
						<td><?php echo esc_html( $products ); ?></td>
						<td><?php echo wc_price( $balance ); ?></td>
						<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $vendor->user_registered ) ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=tcg-vendors&action=edit&vendor_id=' . $vendor->ID ) ); ?>" class="button button-small">
								<?php esc_html_e( 'Editar', 'tcg-manager' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function render_edit( $vendor_id ) {
		$vendor = get_userdata( $vendor_id );
		if ( ! $vendor ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Vendedor no encontrado.', 'tcg-manager' ) . '</p></div>';
			return;
		}

		$data         = TCG_Vendor_Role::get_vendor_data( $vendor_id );
		$global_rate  = get_option( 'tcg_manager_commission_rate', 10 );
		$global_fixed = get_option( 'tcg_manager_commission_fixed', 0 );
		$vendor_fixed = get_user_meta( $vendor_id, '_tcg_commission_fixed', true );
		$currency     = get_woocommerce_currency_symbol();
		?>
		<div class="wrap">
			<h1><?php printf( esc_html__( 'Editar vendedor: %s', 'tcg-manager' ), esc_html( $vendor->display_name ) ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tcg-vendors' ) ); ?>">&larr; <?php esc_html_e( 'Volver', 'tcg-manager' ); ?></a>

			<form method="post" style="max-width:500px;margin-top:20px;">
				<?php wp_nonce_field( 'tcg_vendor_edit' ); ?>
				<input type="hidden" name="vendor_id" value="<?php echo esc_attr( $vendor_id ); ?>">

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Tienda', 'tcg-manager' ); ?></th>
						<td><?php echo esc_html( $data['shop_name'] ?: '—' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Email', 'tcg-manager' ); ?></th>
						<td><?php echo esc_html( $vendor->user_email ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Info de pago', 'tcg-manager' ); ?></th>
						<td><?php echo $data['payment_info'] ? nl2br( esc_html( $data['payment_info'] ) ) : '—'; ?></td>
					</tr>
					<tr>
						<th><label for="commission_rate"><?php esc_html_e( 'Comisión porcentual (%)', 'tcg-manager' ); ?></label></th>
						<td>
							<input type="number" name="commission_rate" id="commission_rate"
								   value="<?php echo esc_attr( $data['commission_rate'] ); ?>"
								   step="0.1" min="0" max="100" style="width:80px;">
							<p class="description"><?php printf( esc_html__( 'Dejar vacío para usar la global (%s%%)', 'tcg-manager' ), esc_html( $global_rate ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="commission_fixed"><?php printf( esc_html__( 'Comisión fija (%s)', 'tcg-manager' ), esc_html( $currency ) ); ?></label></th>
						<td>
							<input type="number" name="commission_fixed" id="commission_fixed"
								   value="<?php echo esc_attr( $vendor_fixed ); ?>"
								   step="0.01" min="0" style="width:80px;">
							<p class="description"><?php printf( esc_html__( 'Por unidad vendida. Dejar vacío para usar la global (%s)', 'tcg-manager' ), esc_html( wc_price( $global_fixed ) ) ); ?></p>
						</td>
					</tr>
				</table>

				<p><button type="submit" name="tcg_save_vendor" value="1" class="button button-primary"><?php esc_html_e( 'Guardar', 'tcg-manager' ); ?></button></p>
			</form>
		</div>
		<?php
	}
}
