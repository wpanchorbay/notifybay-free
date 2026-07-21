<?php
/**
 * Template: Restock Notification Email.
 *
 * Sent to waitlist subscribers when an out-of-stock product is restocked.
 *
 * @var string      $product_name    The product name.
 * @var string      $buy_link        The product URL with UTM parameters.
 * @var string      $unsubscribe_url The unsubscribe URL.
 *
 * @package NotifyBay
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<p>
	<?php
	/* translators: %s: product name */
	printf( esc_html__( 'The item %s you were waiting for is now available!', 'notifybay-waitlist-and-stock-alert-woo' ), '<strong>' . esc_html( $product_name ) . '</strong>' );
	?>
</p>

<p>
	<a href="<?php echo esc_url( $buy_link ); ?>" style="background:#000; color:#fff; padding:10px 20px; text-decoration:none; display:inline-block; border-radius:5px;">
		<?php esc_html_e( 'Buy Now', 'notifybay-waitlist-and-stock-alert-woo' ); ?>
	</a>
</p>

<?php
\NotifyBay\Helper\TemplateRenderer::render(
	'emails/partials/footer',
	array( 'unsubscribe_url' => $unsubscribe_url )
);
