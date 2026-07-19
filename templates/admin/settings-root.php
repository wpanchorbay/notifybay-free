<?php
/**
 * Template: Admin Settings Root Container.
 *
 * The React app mount point for the admin dashboard.
 *
 * @var string $plugin_name The plugin slug used as the element ID.
 *
 * @package NotifyBay
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="<?php echo esc_attr( $plugin_name ); ?>">
	<div class="notifybay-loader-container">
		<p><?php esc_html_e( 'Loading...', 'notifybay' ); ?></p>
	</div>
</div>
