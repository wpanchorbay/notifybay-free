<?php
/**
 * Template: Admin Alert Email.
 *
 * Sent to the store admin when a new lead subscribes.
 *
 * @var string $user_email    The subscriber's email.
 * @var string $lead_type     The subscription type (waitlist/wishlist).
 * @var string $product_title The product title.
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
	printf(
		/* translators: 1: user email, 2: subscription type, 3: product title */
		esc_html__( 'A new user (%1$s) has joined the %2$s for %3$s.', 'notifybay-waitlist-and-stock-alert-woo' ),
		esc_html( $user_email ),
		esc_html( $lead_type ),
		esc_html( $product_title )
	);
	?>
</p>
