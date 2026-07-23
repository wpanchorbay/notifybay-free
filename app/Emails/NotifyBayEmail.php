<?php
/**
 * Abstract base for all NotifyBay WooCommerce emails.
 *
 * NotifyBay's notifications are delivered as native WooCommerce emails so they
 * appear under WooCommerce → Settings → Emails with enable/disable, subject,
 * heading, email-type, WC header/footer branding, and theme-overridable
 * templates. Unlike core WC emails, these are NOT triggered by WC actions:
 * NotifyBay's Engine\Worker owns dispatch (batching, retry/backoff, status
 * transitions) and calls render_final_html() on the instance to obtain the
 * rendered, CSS-inlined HTML, then sends via wp_mail() itself.
 *
 * Free ships the base + its own concrete emails (restock, verification); a
 * premium add-on (NotifyBay Pro) registers additional subclasses against this
 * same base via the woocommerce_email_classes filter.
 *
 * @package    NotifyBay
 * @subpackage Emails
 * @since      1.1.0
 */

namespace NotifyBay\Emails;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// WooCommerce (and therefore WC_Email) may not be loaded when this file is
// autoloaded; the class is only ever instantiated from woocommerce_email_classes.
if ( ! class_exists( '\WC_Email' ) ) {
	return;
}

/**
 * Class NotifyBayEmail
 */
abstract class NotifyBayEmail extends \WC_Email {

	/**
	 * Per-send context (product_name, buy_link, unsubscribe_url, verify_url,
	 * stock_qty, customer_name, …). Populated by the Worker via set_context().
	 *
	 * @var array
	 */
	public $context = array();

	/**
	 * The NotifyBay lead being emailed, when set.
	 *
	 * @var \NotifyBay\Models\Lead|null
	 */
	public $lead = null;

	/**
	 * Constructor. Subclasses set id/title/description/template_html and their
	 * default subject/heading BEFORE calling parent::__construct().
	 */
	public function __construct() {
		// NotifyBay templates live in this plugin, not WC core.
		$this->template_base = \NOTIFYBAY_PATH . 'templates/';

		// Customer-facing email (delivered to the subscriber, not the admin).
		$this->customer_email = true;

		parent::__construct();
	}

	/**
	 * Inject per-lead context and merge-tag placeholders before rendering.
	 *
	 * @param \NotifyBay\Models\Lead|null $lead    The lead, if any.
	 * @param array                       $context Resolved template/merge values.
	 * @return void
	 */
	public function set_context( $lead, array $context ) {
		$this->lead    = $lead;
		$this->context = $context;
		$this->object  = $lead;

		// Merge tags honored by subject + heading (format_string) and available
		// to templates. Merged on top of WC's built-in {site_title} etc.
		$this->placeholders = array_merge(
			$this->placeholders,
			array(
				'{product_name}'          => isset( $context['product_name'] ) ? $context['product_name'] : '',
				'{customer_name}'         => isset( $context['customer_name'] ) ? $context['customer_name'] : '',
				'{customer_first_name}'   => isset( $context['customer_first_name'] ) ? $context['customer_first_name'] : '',
				'{stock_qty}'             => isset( $context['stock_qty'] ) ? (string) $context['stock_qty'] : '',
				'{original_product_name}' => isset( $context['original_product_name'] ) ? $context['original_product_name'] : ( isset( $context['product_name'] ) ? $context['product_name'] : '' ),
			)
		);
	}

	/**
	 * Render this email's template (theme-overridable). The template supplies
	 * the body markup and calls the WC header/footer actions in HTML mode; in
	 * plain mode it emits text only. A theme may override it by copying
	 * `templates/emails/wc/<name>.php` to `yourtheme/woocommerce/emails/wc/<name>.php`.
	 *
	 * @param bool $plain_text Whether to render the plain-text variant.
	 * @return string
	 */
	protected function render_template( $plain_text ) {
		return wc_get_template_html(
			$this->template_html,
			array(
				'email_heading' => $this->get_heading(),
				'context'       => $this->context,
				'email'         => $this,
				'plain_text'    => $plain_text,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * HTML content: the template renders WC header + body + unsubscribe + WC
	 * footer. WC's header/footer supply the store's email branding;
	 * style_inline() (run by render_final_html / WC send) inlines the CSS.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return $this->render_template( false );
	}

	/**
	 * Plain-text content.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return $this->render_template( true );
	}

	/**
	 * The per-lead unsubscribe link, rendered inside the WC wrapper (before the
	 * footer) by the template. The Worker separately emits a List-Unsubscribe
	 * header. Returns '' when the event carries no unsubscribe URL (e.g. the
	 * verification email).
	 *
	 * @return string
	 */
	public function get_unsubscribe_html() {
		if ( empty( $this->context['unsubscribe_url'] ) ) {
			return '';
		}
		return sprintf(
			'<p style="font-size:12px;color:#888;margin-top:24px;"><a href="%s">%s</a></p>',
			esc_url( $this->context['unsubscribe_url'] ),
			esc_html__( 'Unsubscribe', 'notifybay-waitlist-and-stock-alert-woo' )
		);
	}

	/**
	 * The final HTML the Worker sends: full content rendered + inline CSS.
	 *
	 * @return string
	 */
	public function render_final_html() {
		return $this->style_inline( $this->get_content() );
	}

	/**
	 * Render the WooCommerce settings screen, then append NotifyBay's Preview
	 * and Send-test controls. Handlers live in Emails\EmailManager.
	 */
	public function admin_options() {
		parent::admin_options();

		if ( empty( $this->id ) ) {
			return;
		}

		$preview_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=notifybay_email_preview&email_id=' . rawurlencode( $this->id ) ),
			'notifybay_email_preview_' . $this->id
		);
		$test_url    = wp_nonce_url(
			admin_url( 'admin-post.php?action=notifybay_email_test&email_id=' . rawurlencode( $this->id ) ),
			'notifybay_email_test_' . $this->id
		);
		?>
		<h3 class="wc-settings-sub-title"><?php esc_html_e( 'Preview & test', 'notifybay-waitlist-and-stock-alert-woo' ); ?></h3>
		<p>
			<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener" class="button button-secondary">
				<?php esc_html_e( 'Preview email', 'notifybay-waitlist-and-stock-alert-woo' ); ?>
			</a>
			<a href="<?php echo esc_url( $test_url ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Send test to admin', 'notifybay-waitlist-and-stock-alert-woo' ); ?>
			</a>
			<span class="description">
				<?php esc_html_e( 'Preview and test emails render with sample data.', 'notifybay-waitlist-and-stock-alert-woo' ); ?>
			</span>
		</p>
		<?php
	}

	/**
	 * Standard WooCommerce email settings: enable/subject/heading/type.
	 */
	public function init_form_fields() {
		$placeholder_hint = '<code>{product_name}, {customer_name}, {customer_first_name}, {stock_qty}, {site_title}</code>';

		$this->form_fields = array(
			'enabled'            => array(
				'title'   => __( 'Enable/Disable', 'notifybay-waitlist-and-stock-alert-woo' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'notifybay-waitlist-and-stock-alert-woo' ),
				'default' => 'yes',
			),
			'subject'            => array(
				'title'       => __( 'Subject', 'notifybay-waitlist-and-stock-alert-woo' ),
				'type'        => 'text',
				'desc_tip'    => true,
				/* translators: %s: list of available merge-tag placeholders */
				'description' => sprintf( __( 'Available placeholders: %s', 'notifybay-waitlist-and-stock-alert-woo' ), $placeholder_hint ),
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			),
			'heading'            => array(
				'title'       => __( 'Email heading', 'notifybay-waitlist-and-stock-alert-woo' ),
				'type'        => 'text',
				'desc_tip'    => true,
				/* translators: %s: list of available merge-tag placeholders */
				'description' => sprintf( __( 'Available placeholders: %s', 'notifybay-waitlist-and-stock-alert-woo' ), $placeholder_hint ),
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			),
			'additional_content' => array(
				'title'       => __( 'Additional content', 'notifybay-waitlist-and-stock-alert-woo' ),
				'description' => __( 'Text to appear below the main email content.', 'notifybay-waitlist-and-stock-alert-woo' ) . ' ' . sprintf(
					/* translators: %s: list of available merge-tag placeholders */
					__( 'Available placeholders: %s', 'notifybay-waitlist-and-stock-alert-woo' ),
					$placeholder_hint
				),
				'css'         => 'width:400px; height: 75px;',
				'placeholder' => __( 'N/A', 'notifybay-waitlist-and-stock-alert-woo' ),
				'type'        => 'textarea',
				'default'     => $this->get_default_additional_content(),
				'desc_tip'    => true,
			),
			'email_type'         => array(
				'title'       => __( 'Email type', 'notifybay-waitlist-and-stock-alert-woo' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'notifybay-waitlist-and-stock-alert-woo' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			),
		);
	}
}
