<?php
/**
 * Template: Product Meta Box Overrides.
 *
 * Displays the NotifyBay settings sidebar on the product edit screen.
 *
 * @var array $overrides The current product-level overrides.
 *
 * @package NotifyBay
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<p>
	<label>
		<input type="checkbox" name="notifybay_disable_waitlist" value="1" <?php checked( ! empty( $overrides['disable_waitlist'] ) ); ?>>
		<?php esc_html_e( 'Disable Waitlist', 'notifybay-waitlist-and-stock-alert-woo' ); ?>
	</label>
</p>

<?php
/**
 * Fires after the Waitlist override field in the NotifyBay product meta box.
 *
 * Lets an add-on (NotifyBay Pro) inject its own override fields into this
 * meta box.
 *
 * @since 1.0.0
 * @param array $overrides The current product-level overrides.
 */
do_action( 'notifybay_product_overrides_meta_box', $overrides );

// Free-only: a disabled preview of Pro's "Disable Wishlist" override field, so
// a free user can see what NotifyBay Pro adds here. Once Pro is active, the
// action above renders the real, functional field in this same spot instead.
if ( ! defined( 'NOTIFYBAY_PRO_VERSION' ) ) :
	$notifybay_admin_config = include \NOTIFYBAY_PATH . 'config/admin.php';
	$buy_pro_url            = $notifybay_admin_config['plugin_data']['buy_pro_url'] ?? '#';
	?>
	<p data-notifybay-pro-preview="disable-wishlist" style="opacity:.6;cursor:not-allowed;">
		<label>
			<input type="checkbox" value="1" disabled>
			<?php esc_html_e( 'Disable Wishlist', 'notifybay-waitlist-and-stock-alert-woo' ); ?>
			<span class="notifybay-pro-badge" style="display:inline-block;margin-left:6px;padding:1px 6px;border-radius:10px;background:#f02a74;color:#fff;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.02em;vertical-align:middle;">
				<?php esc_html_e( 'Pro', 'notifybay-waitlist-and-stock-alert-woo' ); ?>
			</span>
		</label>
		<br>
		<a href="<?php echo esc_url( $buy_pro_url ); ?>" target="_blank" rel="noopener" style="font-size:11px;color:#f02a74;">
			<?php esc_html_e( 'Unlock with Pro', 'notifybay-waitlist-and-stock-alert-woo' ); ?>
		</a>
	</p>
	<?php
endif;
?>

<p>
	<label><?php esc_html_e( 'Backorder Behavior', 'notifybay-waitlist-and-stock-alert-woo' ); ?></label><br>
	<select name="notifybay_backorder_mode" style="width:100%;">
		<option value="default" <?php selected( ( $overrides['backorder_mode'] ?? 'default' ) === 'default' ); ?>><?php esc_html_e( 'Use Global Setting', 'notifybay-waitlist-and-stock-alert-woo' ); ?></option>
		<option value="0" <?php selected( ( $overrides['backorder_mode'] ?? 'default' ) === '0' ); ?>><?php esc_html_e( 'Waitlist if no backorders', 'notifybay-waitlist-and-stock-alert-woo' ); ?></option>
		<option value="1" <?php selected( ( $overrides['backorder_mode'] ?? 'default' ) === '1' ); ?>><?php esc_html_e( 'Always waitlist', 'notifybay-waitlist-and-stock-alert-woo' ); ?></option>
		<option value="2" <?php selected( ( $overrides['backorder_mode'] ?? 'default' ) === '2' ); ?>><?php esc_html_e( 'Never waitlist if backorderable', 'notifybay-waitlist-and-stock-alert-woo' ); ?></option>
	</select>
</p>
