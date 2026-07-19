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
		<?php esc_html_e( 'Disable Waitlist', 'notifybay' ); ?>
	</label>
</p>

<p>
	<label>
		<input type="checkbox" name="notifybay_disable_wishlist" value="1" <?php checked( ! empty( $overrides['disable_wishlist'] ) ); ?>>
		<?php esc_html_e( 'Disable Wishlist', 'notifybay' ); ?>
	</label>
</p>

<p>
	<label><?php esc_html_e( 'Smart Transition', 'notifybay' ); ?></label><br>
	<select name="notifybay_smart_transition" style="width:100%;">
		<option value="default" <?php selected( ( $overrides['smart_transition'] ?? 'default' ) === 'default' ); ?>><?php esc_html_e( 'Use Global Setting', 'notifybay' ); ?></option>
		<option value="enabled" <?php selected( ( $overrides['smart_transition'] ?? 'default' ) === 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'notifybay' ); ?></option>
		<option value="disabled" <?php selected( ( $overrides['smart_transition'] ?? 'default' ) === 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'notifybay' ); ?></option>
	</select>
</p>

<p>
	<label><?php esc_html_e( 'Backorder Behavior', 'notifybay' ); ?></label><br>
	<select name="notifybay_backorder_mode" style="width:100%;">
		<option value="default" <?php selected( ( $overrides['backorder_mode'] ?? 'default' ) === 'default' ); ?>><?php esc_html_e( 'Use Global Setting', 'notifybay' ); ?></option>
		<option value="0" <?php selected( ( $overrides['backorder_mode'] ?? 'default' ) === '0' ); ?>><?php esc_html_e( 'Waitlist if no backorders', 'notifybay' ); ?></option>
		<option value="1" <?php selected( ( $overrides['backorder_mode'] ?? 'default' ) === '1' ); ?>><?php esc_html_e( 'Always waitlist', 'notifybay' ); ?></option>
		<option value="2" <?php selected( ( $overrides['backorder_mode'] ?? 'default' ) === '2' ); ?>><?php esc_html_e( 'Never waitlist if backorderable', 'notifybay' ); ?></option>
	</select>
</p>

<p>
	<label><?php esc_html_e( 'Max Waitlist Size', 'notifybay' ); ?></label><br>
	<input type="number" name="notifybay_max_waitlist_size" value="<?php echo esc_attr( $overrides['max_waitlist_size'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Use Global', 'notifybay' ); ?>" style="width:100%;">
	<small><?php esc_html_e( 'Set to 0 or leave empty for global limit.', 'notifybay' ); ?></small>
</p>
