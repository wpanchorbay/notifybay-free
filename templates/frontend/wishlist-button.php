<?php
/**
 * Template: Wishlist Button.
 *
 * Displayed on single product pages when the item is in stock.
 *
 * @var string $btn_text  The button label.
 * @var string $css_class     Additional CSS class for the button.
 * @var bool   $is_subscribed Whether the user is already subscribed.
 * @package NotifyBay
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<?php
$notifybay_is_logged_in = is_user_logged_in();
$is_variable            = isset( $is_variable ) ? $is_variable : false; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $is_variable is a template variable passed in by TemplateRenderer, not a plugin global.
?>
<div class="notifybay-wishlist-wrapper" data-notifybay-type="wishlist">
	<form class="notifybay-wishlist-form" onsubmit="return false;">
		<?php if ( $notifybay_is_logged_in ) : ?>
			<input type="hidden" name="notifybay_email" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
			<button type="button" class="notifybay-wishlist-btn notifybay-submit button <?php echo esc_attr( $css_class ); ?> <?php echo $is_subscribed ? 'active' : ''; ?>" <?php disabled( $is_subscribed || $is_variable ); ?>>
				<span class="notifybay-icon"></span>
				<?php echo esc_html( $is_subscribed ? __( 'Already in Wishlist', 'notifybay-waitlist-and-stock-alert-woo' ) : $btn_text ); ?>
			</button>
		<?php else : ?>
			<div class="notifybay-guest-trigger-wrapper">
				<button type="button" class="notifybay-wishlist-trigger notifybay-submit button <?php echo esc_attr( $css_class ); ?> <?php echo $is_subscribed ? 'active' : ''; ?>" <?php disabled( $is_subscribed || $is_variable ); ?>>
					<span class="notifybay-icon"></span>
					<?php echo esc_html( $is_subscribed ? __( 'Already in Wishlist', 'notifybay-waitlist-and-stock-alert-woo' ) : $btn_text ); ?>
				</button>
			</div>

			<div class="notifybay-guest-form-wrapper" style="display:none;">
				<div class="notifybay-form-fields">
					<input type="email" name="notifybay_email" placeholder="<?php esc_attr_e( 'Your email address', 'notifybay-waitlist-and-stock-alert-woo' ); ?>">
					<button type="button" class="notifybay-guest-submit notifybay-submit button alt <?php echo esc_attr( $css_class ); ?>">
						<?php echo esc_html( $btn_text ); ?>
					</button>
				</div>
			</div>
		<?php endif; ?>
	</form>
</div>
