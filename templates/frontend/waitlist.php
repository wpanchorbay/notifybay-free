<?php
/**
 * Template: My Account Waitlist.
 *
 * @var array $leads List of lead objects.
 * @package NotifyBay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h3><?php esc_html_e( 'My Waitlist', 'notifybay-waitlist-and-stock-alert-woo' ); ?></h3>

<?php if ( empty( $leads ) ) : ?>
	<div class="woocommerce-Message woocommerce-Message--info woocommerce-info">
		<a class="woocommerce-Button button" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>">
			<?php esc_html_e( 'Go Shop', 'notifybay-waitlist-and-stock-alert-woo' ); ?>
		</a>
		<?php esc_html_e( 'You are not on any waitlists yet.', 'notifybay-waitlist-and-stock-alert-woo' ); ?>
	</div>
<?php else : ?>
	<table class="woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders notifybay-account-table">
		<thead>
			<tr>
				<th class="product-thumbnail">&nbsp;</th>
				<th class="product-name"><?php esc_html_e( 'Product', 'notifybay-waitlist-and-stock-alert-woo' ); ?></th>
				<th class="product-status"><?php esc_html_e( 'Status', 'notifybay-waitlist-and-stock-alert-woo' ); ?></th>
				<th class="product-actions">&nbsp;</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $leads as $notifybay_lead ) :
				$notifybay_product = wc_get_product( $notifybay_lead['variation_id'] ? $notifybay_lead['variation_id'] : $notifybay_lead['product_id'] );
				if ( ! $notifybay_product ) {
					continue;
				}
				?>
				<tr class="notifybay-lead-row" data-lead-id="<?php echo esc_attr( $notifybay_lead['id'] ); ?>">
					<td class="product-thumbnail">
						<a href="<?php echo esc_url( $notifybay_product->get_permalink() ); ?>" class="notifybay-thumbnail-wrapper">
							<?php echo wp_kses_post( $notifybay_product->get_image( array( 60, 60 ), array( 'class' => 'notifybay-product-thumb' ) ) ); ?>
						</a>
					</td>
					<td class="product-name" data-title="<?php esc_attr_e( 'Product', 'notifybay-waitlist-and-stock-alert-woo' ); ?>">
						<a href="<?php echo esc_url( $notifybay_product->get_permalink() ); ?>">
							<?php echo esc_html( $notifybay_product->get_name() ); ?>
						</a>
					</td>
					<td class="product-status" data-title="<?php esc_attr_e( 'Status', 'notifybay-waitlist-and-stock-alert-woo' ); ?>">
						<?php if ( $notifybay_product->is_in_stock() ) : ?>
							<mark class="instock"><?php esc_html_e( 'Back in Stock!', 'notifybay-waitlist-and-stock-alert-woo' ); ?></mark>
						<?php else : ?>
							<span><?php esc_html_e( 'Out of Stock', 'notifybay-waitlist-and-stock-alert-woo' ); ?></span>
						<?php endif; ?>
					</td>
					<td class="product-actions">
						<button class="button secondary notifybay-remove-subscription" data-lead-id="<?php echo esc_attr( $notifybay_lead['id'] ); ?>">
							<?php esc_html_e( 'Remove', 'notifybay-waitlist-and-stock-alert-woo' ); ?>
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
