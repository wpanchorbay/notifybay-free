<?php
/**
 * Template: Waitlist Form.
 *
 * Displayed on single product pages when the item is out of stock.
 *
 * @var \WC_Product $product       The product object.
 * @var string      $btn_text      The submit button label.
 * @var string      $css_class     Additional CSS class for the button.
 * @var object      $settings      The Settings instance.
 * @var bool        $is_subscribed Whether the user is already subscribed.
 *
 * @package NotifyBay
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notifybay_is_logged_in = is_user_logged_in();
?>

<div class="notifybay-waitlist-wrapper" data-notifybay-type="waitlist">
	<form class="notifybay-waitlist-form" onsubmit="return false;">
		<?php if ( $notifybay_is_logged_in ) : ?>
			<?php // Logged in: Simple inline button. ?>
			<input type="hidden" name="notifybay_email" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
			<button type="button" class="notifybay-waitlist-btn notifybay-submit button alt <?php echo esc_attr( $css_class ); ?>" <?php disabled( $is_subscribed ); ?>>
				<?php echo esc_html( $is_subscribed ? __( 'Already on Waitlist', 'notifybay-waitlist-and-stock-alert-woo' ) : $btn_text ); ?>
			</button>
		<?php else : ?>
			<?php // Guest: Trigger button that reveals the form. ?>
			<div class="notifybay-guest-trigger-wrapper">
				<button type="button" class="notifybay-waitlist-trigger notifybay-submit button alt <?php echo esc_attr( $css_class ); ?>" <?php disabled( $is_subscribed ); ?>>
					<?php echo esc_html( $is_subscribed ? __( 'Already on Waitlist', 'notifybay-waitlist-and-stock-alert-woo' ) : $btn_text ); ?>
				</button>
			</div>

			<div class="notifybay-guest-form-wrapper" style="display:none;">
				<p class="notifybay-waitlist-desc">
					<?php esc_html_e( 'This item is currently out of stock. Join our waitlist to be notified when it returns!', 'notifybay-waitlist-and-stock-alert-woo' ); ?>
				</p>
				<div class="notifybay-form-fields">
					<input type="email" name="notifybay_email" placeholder="<?php esc_attr_e( 'Your email address', 'notifybay-waitlist-and-stock-alert-woo' ); ?>">
					
					<?php
					if ( $settings->get_settings( 'appearance_waitlistExpiryEnabled', false ) ) :
						/*
						 * Positive integers only, matching
						 * Api\FrontendController::get_expiry_options(). is_numeric()
						 * was wrong here: it passes "0" and negatives, so a merchant
						 * option list of "0,7,14" rendered a "0 days" choice that the
						 * server -- whose every guard is `$days > 0` -- silently read
						 * as no expiry at all, a duplicate of the empty option. intval
						 * also normalises "07" to 7, without which the value posted
						 * back would fail the server's strict in_array() check.
						 */
						$notifybay_expiry_options = $settings->get_expiry_options();

						$notifybay_default_expiry = (int) $settings->get_settings( 'appearance_waitlistExpiryDefault', 0 );

						/*
						 * If the configured default is not one of the offered
						 * options, selected() below matches nothing and the
						 * browser falls back to the first <option> — the empty
						 * "No expiry" one. A returning guest can submit without
						 * ever opening this form, so that silently becomes their
						 * choice. Pin the first real option instead.
						 */
						if ( ! in_array( $notifybay_default_expiry, $notifybay_expiry_options, true ) ) {
							$notifybay_default_expiry = isset( $notifybay_expiry_options[0] ) ? $notifybay_expiry_options[0] : 0;
						}
						?>
						<select name="notifybay_expiry">
							<option value=""><?php esc_html_e( 'No expiry', 'notifybay-waitlist-and-stock-alert-woo' ); ?></option>
							<?php
							foreach ( $notifybay_expiry_options as $notifybay_days ) :
								?>
								<option value="<?php echo esc_attr( $notifybay_days ); ?>" <?php selected( $notifybay_default_expiry, $notifybay_days ); ?>>
									<?php echo (int) $notifybay_days; ?> <?php esc_html_e( 'days', 'notifybay-waitlist-and-stock-alert-woo' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>

					<button type="button" class="notifybay-guest-submit notifybay-submit button alt <?php echo esc_attr( $css_class ); ?>">
						<?php echo esc_html( $btn_text ); ?>
					</button>
				</div>
			</div>
		<?php endif; ?>

		<input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>">
	</form>
</div>
