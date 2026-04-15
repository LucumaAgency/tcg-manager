<?php
defined( 'ABSPATH' ) || exit;

/**
 * CPT tcg_pickup_store — tiendas físicas del marketplace para recojo en tienda.
 */
class TCG_Pickup_Store {

	const CPT = 'tcg_pickup_store';

	public function __construct() {
		add_action( 'init', [ __CLASS__, 'register_cpt' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_metabox' ] );
		add_action( 'save_post_' . self::CPT, [ $this, 'save_meta' ], 10, 2 );
	}

	public static function register_cpt() {
		register_post_type( self::CPT, [
			'label'         => __( 'Tiendas de recojo', 'tcg-manager' ),
			'labels'        => [
				'name'          => __( 'Tiendas de recojo', 'tcg-manager' ),
				'singular_name' => __( 'Tienda de recojo', 'tcg-manager' ),
				'add_new_item'  => __( 'Agregar tienda', 'tcg-manager' ),
				'edit_item'     => __( 'Editar tienda', 'tcg-manager' ),
				'menu_name'     => __( 'Tiendas de recojo', 'tcg-manager' ),
			],
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => 'woocommerce',
			'supports'      => [ 'title' ],
			'capability_type' => 'post',
			'map_meta_cap'  => true,
		] );
	}

	public function add_metabox() {
		add_meta_box(
			'tcg_pickup_store_details',
			__( 'Datos de la tienda', 'tcg-manager' ),
			[ $this, 'render_metabox' ],
			self::CPT,
			'normal',
			'high'
		);
	}

	public function render_metabox( $post ) {
		wp_nonce_field( 'tcg_pickup_store_save', 'tcg_pickup_store_nonce' );
		$address  = get_post_meta( $post->ID, '_tcg_pickup_address', true );
		$district = get_post_meta( $post->ID, '_tcg_pickup_district', true );
		$hours    = get_post_meta( $post->ID, '_tcg_pickup_hours', true );
		$phone    = get_post_meta( $post->ID, '_tcg_pickup_phone', true );
		?>
		<table class="form-table">
			<tr>
				<th><label for="tcg_pickup_address"><?php esc_html_e( 'Dirección', 'tcg-manager' ); ?></label></th>
				<td><input type="text" id="tcg_pickup_address" name="tcg_pickup_address" value="<?php echo esc_attr( $address ); ?>" class="large-text"></td>
			</tr>
			<tr>
				<th><label for="tcg_pickup_district"><?php esc_html_e( 'Distrito', 'tcg-manager' ); ?></label></th>
				<td><input type="text" id="tcg_pickup_district" name="tcg_pickup_district" value="<?php echo esc_attr( $district ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="tcg_pickup_hours"><?php esc_html_e( 'Horario', 'tcg-manager' ); ?></label></th>
				<td><input type="text" id="tcg_pickup_hours" name="tcg_pickup_hours" value="<?php echo esc_attr( $hours ); ?>" class="large-text" placeholder="Lun-Sáb 10:00-19:00"></td>
			</tr>
			<tr>
				<th><label for="tcg_pickup_phone"><?php esc_html_e( 'Teléfono', 'tcg-manager' ); ?></label></th>
				<td><input type="text" id="tcg_pickup_phone" name="tcg_pickup_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text"></td>
			</tr>
		</table>
		<?php
	}

	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['tcg_pickup_store_nonce'] ) || ! wp_verify_nonce( $_POST['tcg_pickup_store_nonce'], 'tcg_pickup_store_save' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$fields = [ '_tcg_pickup_address', '_tcg_pickup_district', '_tcg_pickup_hours', '_tcg_pickup_phone' ];
		foreach ( $fields as $meta_key ) {
			$post_key = ltrim( $meta_key, '_' );
			$val      = sanitize_text_field( $_POST[ $post_key ] ?? '' );
			update_post_meta( $post_id, $meta_key, $val );
		}
	}

	/**
	 * Get all published pickup stores.
	 *
	 * @return array[] [ id, name, address, district, hours, phone ]
	 */
	public static function get_all() {
		$posts = get_posts( [
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$out = [];
		foreach ( $posts as $p ) {
			$out[] = self::format( $p );
		}
		return $out;
	}

	public static function get( $id ) {
		$p = get_post( (int) $id );
		if ( ! $p || $p->post_type !== self::CPT || $p->post_status !== 'publish' ) return null;
		return self::format( $p );
	}

	private static function format( $post ) {
		return [
			'id'       => $post->ID,
			'name'     => $post->post_title,
			'address'  => get_post_meta( $post->ID, '_tcg_pickup_address', true ),
			'district' => get_post_meta( $post->ID, '_tcg_pickup_district', true ),
			'hours'    => get_post_meta( $post->ID, '_tcg_pickup_hours', true ),
			'phone'    => get_post_meta( $post->ID, '_tcg_pickup_phone', true ),
		];
	}
}
