<?php
/**
 * Template: Back-in-stock (restock) WooCommerce email.
 *
 * Override by copying to yourtheme/woocommerce/emails/wc/restock.php.
 *
 * @var string                        $email_heading The email heading.
 * @var array                         $context       Resolved values (product_name, buy_link, …).
 * @var bool                          $plain_text    Whether to render plain text.
 * @var \NotifyBay\Emails\RestockEmail $email        The email instance.
 *
 * @package NotifyBay
 */

defined( 'ABSPATH' ) || exit;

$product_name = isset( $context['product_name'] ) ? $context['product_name'] : '';
$buy_link     = isset( $context['buy_link'] ) ? $context['buy_link'] : '';

if ( $plain_text ) {
	echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n\n";
	/* translators: %s: product name */
	echo esc_html( sprintf( __( 'The item %s you were waiting for is now available!', 'notifybay' ), $product_name ) ) . "\n\n";
	if ( $buy_link ) {
		echo esc_html__( 'Buy now:', 'notifybay' ) . ' ' . esc_url( $buy_link ) . "\n\n";
	}
	if ( ! empty( $context['unsubscribe_url'] ) ) {
		echo esc_html__( 'Unsubscribe:', 'notifybay' ) . ' ' . esc_url( $context['unsubscribe_url'] ) . "\n";
	}
	return;
}

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
	<?php
	/* translators: %s: product name */
	printf( esc_html__( 'The item %s you were waiting for is now available!', 'notifybay' ), '<strong>' . esc_html( $product_name ) . '</strong>' );
	?>
</p>

<?php if ( $buy_link ) : ?>
<p>
	<a href="<?php echo esc_url( $buy_link ); ?>" style="background:#000; color:#fff; padding:10px 20px; text-decoration:none; display:inline-block; border-radius:5px;">
		<?php esc_html_e( 'Buy Now', 'notifybay' ); ?>
	</a>
</p>
<?php endif; ?>

<?php
// User-defined additional content, set per email in WooCommerce → Settings → Emails.
$additional_content = $email->get_additional_content();
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

// Per-lead unsubscribe link (shared markup from the base class).
echo wp_kses_post( $email->get_unsubscribe_html() );

/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
