<?php
defined( 'ABSPATH' ) || exit;

class TCG_Admin_Commissions {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'handle_actions' ] );
	}

	public function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Comisiones', 'tcg-manager' ),
			__( 'Comisiones', 'tcg-manager' ),
			'manage_woocommerce',
			'tcg-commissions',
			[ $this, 'render_page' ]
		);
	}

	public function handle_actions() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'tcg-commissions' ) return;

		// Single mark as paid.
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'mark_paid' && isset( $_GET['id'] ) ) {
			check_admin_referer( 'tcg_pay_' . $_GET['id'] );
			TCG_Commissions::mark_as_paid( absint( $_GET['id'] ) );
			wp_safe_redirect( admin_url( 'admin.php?page=tcg-commissions&tcg_msg=paid' ) );
			exit;
		}

		// Bulk mark as paid.
		if ( isset( $_POST['bulk_action'] ) && $_POST['bulk_action'] === 'mark_paid' && ! empty( $_POST['commission_ids'] ) ) {
			check_admin_referer( 'tcg_bulk_pay' );
			$ids = array_map( 'absint', $_POST['commission_ids'] );
			TCG_Commissions::mark_as_paid( $ids );
			wp_safe_redirect( admin_url( 'admin.php?page=tcg-commissions&tcg_msg=paid' ) );
			exit;
		}
	}

	public function render_page() {
		global $wpdb;
		$table = $wpdb->prefix . 'tcg_commissions';

		// Filters.
		$status    = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$vendor_id = isset( $_GET['vendor_id'] ) ? absint( $_GET['vendor_id'] ) : 0;
		$per_page  = 25;
		$paged     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$offset    = ( $paged - 1 ) * $per_page;

		$where = 'WHERE 1=1';
		if ( $status ) $where .= $wpdb->prepare( ' AND c.status = %s', $status );
		if ( $vendor_id ) $where .= $wpdb->prepare( ' AND c.vendor_id = %d', $vendor_id );

		$items = $wpdb->get_results( "SELECT c.* FROM {$table} c {$where} ORDER BY c.created_at DESC LIMIT {$per_page} OFFSET {$offset}" );
		$total = (int) $wpdb->get_var( "SELECT COUNT(1) FROM {$table} c {$where}" );

		$total_pending = (float) $wpdb->get_var( "SELECT COALESCE(SUM(vendor_net), 0) FROM {$table} WHERE status = 'pending'" );
		$total_paid_month = (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(vendor_net), 0) FROM {$table} WHERE status = 'paid' AND paid_date >= %s",
			date( 'Y-m-01 00:00:00' )
		) );

		// Vendors for filter dropdown.
		$vendors = get_users( [ 'role' => 'tcg_vendor', 'orderby' => 'display_name' ] );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Comisiones', 'tcg-manager' ); ?></h1>

			<?php if ( isset( $_GET['tcg_msg'] ) && $_GET['tcg_msg'] === 'paid' ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Comisiones marcadas como pagadas.', 'tcg-manager' ); ?></p></div>
			<?php endif; ?>

			<div style="display:flex;gap:20px;margin-bottom:20px;">
				<div class="card"><h3 style="margin:0;"><?php echo wc_price( $total_pending ); ?></h3><p><?php esc_html_e( 'Pendiente total', 'tcg-manager' ); ?></p></div>
				<div class="card"><h3 style="margin:0;"><?php echo wc_price( $total_paid_month ); ?></h3><p><?php esc_html_e( 'Pagado este mes', 'tcg-manager' ); ?></p></div>
			</div>

			<!-- Filters -->
			<form method="get" style="margin-bottom:15px;">
				<input type="hidden" name="page" value="tcg-commissions">
				<select name="status">
					<option value=""><?php esc_html_e( 'Todos los estados', 'tcg-manager' ); ?></option>
					<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pendiente', 'tcg-manager' ); ?></option>
					<option value="paid" <?php selected( $status, 'paid' ); ?>><?php esc_html_e( 'Pagado', 'tcg-manager' ); ?></option>
				</select>
				<select name="vendor_id">
					<option value=""><?php esc_html_e( 'Todos los vendedores', 'tcg-manager' ); ?></option>
					<?php foreach ( $vendors as $v ) : ?>
						<option value="<?php echo esc_attr( $v->ID ); ?>" <?php selected( $vendor_id, $v->ID ); ?>><?php echo esc_html( $v->display_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'tcg-manager' ); ?></button>
			</form>

			<form method="post">
				<?php wp_nonce_field( 'tcg_bulk_pay' ); ?>
				<table class="wp-list-table widefat fixed striped">
					<thead><tr>
						<td class="check-column"><input type="checkbox" id="cb-select-all"></td>
						<th>ID</th>
						<th><?php esc_html_e( 'Pedido', 'tcg-manager' ); ?></th>
						<th><?php esc_html_e( 'Vendedor', 'tcg-manager' ); ?></th>
						<th><?php esc_html_e( 'Producto', 'tcg-manager' ); ?></th>
						<th><?php esc_html_e( 'Venta', 'tcg-manager' ); ?></th>
						<th><?php esc_html_e( 'Comisión', 'tcg-manager' ); ?></th>
						<th><?php esc_html_e( 'Neto', 'tcg-manager' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'tcg-manager' ); ?></th>
						<th><?php esc_html_e( 'Fecha', 'tcg-manager' ); ?></th>
						<th><?php esc_html_e( 'Acciones', 'tcg-manager' ); ?></th>
					</tr></thead>
					<tbody>
					<?php if ( empty( $items ) ) : ?>
						<tr><td colspan="11"><?php esc_html_e( 'No hay comisiones.', 'tcg-manager' ); ?></td></tr>
					<?php else : foreach ( $items as $item ) :
						$vendor = get_userdata( $item->vendor_id );
						?>
						<tr>
							<th class="check-column"><input type="checkbox" name="commission_ids[]" value="<?php echo esc_attr( $item->id ); ?>"></th>
							<td><?php echo esc_html( $item->id ); ?></td>
							<td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $item->order_id . '&action=edit' ) ); ?>">#<?php echo esc_html( $item->order_id ); ?></a></td>
							<td><?php echo $vendor ? esc_html( $vendor->display_name ) : '—'; ?></td>
							<td><?php echo esc_html( get_the_title( $item->product_id ) ); ?></td>
							<td><?php echo wc_price( $item->sale_total ); ?></td>
							<td><?php echo wc_price( $item->commission ); ?></td>
							<td><?php echo wc_price( $item->vendor_net ); ?></td>
							<td>
								<?php if ( $item->status === 'pending' ) : ?>
									<span class="dashicons dashicons-clock" style="color:#856404;"></span> <?php esc_html_e( 'Pendiente', 'tcg-manager' ); ?>
								<?php else : ?>
									<span class="dashicons dashicons-yes-alt" style="color:#155724;"></span> <?php esc_html_e( 'Pagado', 'tcg-manager' ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $item->created_at ) ) ); ?></td>
							<td>
								<?php if ( $item->status === 'pending' ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=tcg-commissions&action=mark_paid&id=' . $item->id ), 'tcg_pay_' . $item->id ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Marcar pagado', 'tcg-manager' ); ?>
									</a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>

				<?php if ( ! empty( $items ) ) : ?>
					<div style="margin-top:10px;">
						<select name="bulk_action">
							<option value=""><?php esc_html_e( 'Acciones masivas', 'tcg-manager' ); ?></option>
							<option value="mark_paid"><?php esc_html_e( 'Marcar como pagado', 'tcg-manager' ); ?></option>
						</select>
						<button type="submit" class="button"><?php esc_html_e( 'Aplicar', 'tcg-manager' ); ?></button>
					</div>
				<?php endif; ?>
			</form>

			<?php
			$total_pages = ceil( $total / $per_page );
			if ( $total_pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo paginate_links( [
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $paged,
					'total'   => $total_pages,
				] );
				echo '</div></div>';
			}
			?>
		</div>
		<?php
	}
}
