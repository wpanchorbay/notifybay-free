<?php
/**
 * Template: Email Footer Partial.
 *
 * Shared unsubscribe link and footer for all notification emails.
 *
 * @var string $unsubscribe_url The unsubscribe URL with token.
 *
 * @package NotifyBay
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<hr>
<p>
	<small>
		<a href="<?php echo esc_url( $unsubscribe_url ); ?>"><?php esc_html_e( 'Unsubscribe', 'notifybay' ); ?></a>
	</small>
</p>
