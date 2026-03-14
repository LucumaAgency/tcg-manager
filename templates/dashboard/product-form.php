<?php
defined( 'ABSPATH' ) || exit;

$section    = TCG_Dashboard::get_current_section();
$product_id = 0;

if ( $section === 'edit-product' ) {
	$product_id = absint( $_GET['tcg-id'] ?? 0 );
}
?>

<h2>
	<?php echo $product_id
		? esc_html__( 'Editar Producto', 'tcg-manager' )
		: esc_html__( 'Nuevo Producto', 'tcg-manager' ); ?>
</h2>

<?php TCG_Product_Form::render_form( $product_id ); ?>
