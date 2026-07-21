<?php
/**
 * Template: Subscription verification (double opt-in) WooCommerce email.
 *
 * Override by copying to yourtheme/woocommerce/emails/wc/verification.php.
 *
 * @var string                             $email_heading The email heading.
 * @var array                              $context       Resolved values (product_name, verify_url, …).
 * @var bool                               $plain_text    Whether to render plain text.
 * @var \NotifyBay\Emails\VerificationEmail $email        The email instance.
 *
 * @package NotifyBay
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- WooCommerce email template: local vars ($product_name, $verify_url, $additional_content) are supplied by WC_Email and the woocommerce_email_header/footer hooks are core WooCommerce hooks. Standard WC template conventions, not plugin globals.

$product_name = isset( $context['product_name'] ) ? $context['product_name'] : '';
$verify_url   = isset( $context['verify_url'] ) ? $context['verify_url'] : '';

if ( $plain_text ) {
	echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n\n";
	/* translators: %s: product name */
	echo esc_html( sprintf( __( 'Please confirm you want to receive notifications for %s.', 'notifybay-waitlist-and-stock-alert-woo' ), $product_name ) ) . "\n\n";
	if ( $verify_url ) {
		echo esc_html__( 'Confirm:', 'notifybay-waitlist-and-stock-alert-woo' ) . ' ' . esc_url( $verify_url ) . "\n";
	}
	return;
}

/**
 * Output the WooCommerce email header.
 *
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
	<?php
	/* translators: %s: product name */
	printf( esc_html__( 'Please confirm you want to receive notifications for %s.', 'notifybay-waitlist-and-stock-alert-woo' ), '<strong>' . esc_html( $product_name ) . '</strong>' );
	?>
</p>

<?php if ( $verify_url ) : ?>
<p>
	<a href="<?php echo esc_url( $verify_url ); ?>" style="background:#000; color:#fff; padding:10px 20px; text-decoration:none; display:inline-block; border-radius:5px;">
		<?php esc_html_e( 'Confirm Subscription', 'notifybay-waitlist-and-stock-alert-woo' ); ?>
	</a>
</p>
<?php endif; ?>

<?php
$additional_content = $email->get_additional_content();
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/**
 * Output the WooCommerce email footer.
 *
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );

// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals
