<?php
/**
 * Template: Product Column — Lead Count.
 *
 * Renders the lead count badge in the WooCommerce product list.
 *
 * @var int    $count   Number of active leads.
 * @var int    $post_id The product post ID.
 * @var string $type    'waitlist' or 'wishlist'.
 *
 * @package NotifyBay
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( $count > 0 ) :
	$notifybay_color = 'waitlist' === $type ? '#d9534f' : '#0073aa';
	$notifybay_label = 'waitlist' === $type ? _n( 'Lead', 'Leads', $count, 'notifybay-waitlist-and-stock-alert-woo' ) : _n( 'Watcher', 'Watchers', $count, 'notifybay-waitlist-and-stock-alert-woo' );
	?>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=notifybay#/leads?type=' . $type . '&search=' . $post_id ) ); ?>" style="font-weight:bold; color:<?php echo esc_attr( $notifybay_color ); ?>;">
		<?php
		echo (int) $count . ' ';
		echo esc_html( $notifybay_label );
		?>
	</a>
	<?php
else :
	?>
	<span style="color:#999;">0</span>
	<?php
endif;
