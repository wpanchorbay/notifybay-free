<?php
/**
 * MCP Account API Controller.
 *
 * Provisions the low-privilege WordPress account an AI assistant connects as.
 *
 * Why this exists: the kit gates its MCP transport on the `wpab_mcp_access`
 * capability, and the only thing that ever grants it is the kit's activation
 * hook, which adds it to the administrator role. So out of the box the *only*
 * account that can connect is an administrator -- which defeats the access
 * ladder it is supposed to sit behind, because an administrator credential can
 * also POST to the kit's own settings route and raise itself to `full`. This
 * controller is how a store owner creates something narrower instead.
 *
 * Everything here is deliberately one-directional: it can create an account
 * that may talk to MCP and nothing else, and it can take that away again. It
 * cannot grant `manage_options`, cannot grant `manage_notifybay`, cannot accept
 * a role from the caller, and refuses to touch any account that already holds
 * `manage_options`.
 *
 * @package    NotifyBay
 * @subpackage Api
 * @since      1.0.3
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

namespace NotifyBay\Api;

use WP_Error;
use WP_REST_Request;
use WP_User;
use WP_Application_Passwords;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class McpAccountController
 */
class McpAccountController extends ApiController {

	/**
	 * The capability the kit's MCP transport gate requires.
	 *
	 * Granted here at user level, never at role level -- granting it to a role
	 * would silently widen access for every existing member of that role.
	 *
	 * @var string
	 */
	const MCP_CAP = 'wpab_mcp_access';

	/**
	 * The name given to every Application Password this controller issues.
	 *
	 * Revocation works by this name, so it must be stable. It also means a
	 * password the store owner created by hand on the profile screen is never
	 * touched by anything here.
	 *
	 * @var string
	 */
	const APP_PASSWORD_NAME = 'NotifyBay MCP';

	/**
	 * The only role this controller will ever assign.
	 *
	 * @var string
	 */
	const MCP_ROLE = 'subscriber';

	/**
	 * The single instance of the class.
	 *
	 * @var McpAccountController
	 */
	private static $instance = null;

	/**
	 * Gets an instance of this object.
	 *
	 * @static
	 * @access public
	 * @return McpAccountController
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register routes.
	 *
	 * @since 1.0.3
	 * @access public
	 * @return void
	 */
	public function register_routes() {
		$permission = array( $this, 'mcp_admin_permissions_check' );

		register_rest_route(
			$this->namespace . $this->version,
			'/admin/mcp/accounts',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_accounts' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_account' ),
					'permission_callback' => $permission,
					'args'                => array(
						'user_login' => array(
							'type'     => 'string',
							'required' => true,
						),
						'user_email' => array(
							'type'     => 'string',
							'required' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace . $this->version,
			'/admin/mcp/accounts/rotate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rotate_password' ),
				'permission_callback' => $permission,
				'args'                => array(
					'user_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			$this->namespace . $this->version,
			'/admin/mcp/accounts/revoke',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'revoke_access' ),
				'permission_callback' => $permission,
				'args'                => array(
					'user_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Permission check for every route in this controller.
	 *
	 * `manage_options`, not the `manage_notifybay` the rest of NotifyBay's admin
	 * routes use. That is narrower on purpose and matches the capability the kit
	 * gates its own settings routes on: the surface that decides what an AI
	 * account may do must not be reachable by a capability the AI account could
	 * plausibly hold. A shop manager can edit leads but cannot provision an
	 * assistant.
	 *
	 * The nonce requirement is the second half, and it matters more here than
	 * anywhere else in the plugin: a valid `wp_rest` nonce needs a cookie
	 * session, so these routes cannot be reached with an Application Password at
	 * all. Without that, an AI account holding `manage_options` could provision
	 * further AI accounts over MCP.
	 *
	 * @since 1.0.3
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function mcp_admin_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to manage MCP accounts.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_nonce_invalid',
				__( 'The security token is invalid.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * GET /admin/mcp/accounts
	 *
	 * Every account that can currently reach the MCP endpoint, whether it was
	 * created here or holds the capability through its role. Administrators are
	 * flagged rather than hidden -- they are the default state and the thing the
	 * store owner most needs to see, since an assistant connected as one can
	 * raise its own access level.
	 *
	 * @since 1.0.3
	 * @access public
	 * @return array
	 */
	public function list_accounts() {
		$users = get_users(
			array(
				'capability' => self::MCP_CAP,
				'number'     => 100,
				'orderby'    => 'ID',
			)
		);

		$accounts = array();

		foreach ( $users as $user ) {
			$passwords = array();

			foreach ( WP_Application_Passwords::get_user_application_passwords( $user->ID ) as $item ) {
				if ( isset( $item['name'] ) && self::APP_PASSWORD_NAME === $item['name'] ) {
					$passwords[] = array(
						'created'   => isset( $item['created'] ) ? (int) $item['created'] : 0,
						'last_used' => isset( $item['last_used'] ) ? $item['last_used'] : null,
						'last_ip'   => isset( $item['last_ip'] ) ? $item['last_ip'] : null,
					);
				}
			}

			$is_admin = user_can( $user, 'manage_options' );

			$accounts[] = array(
				'user_id'             => $user->ID,
				'user_login'          => $user->user_login,
				'roles'               => array_values( $user->roles ),

				// True when the capability comes from this controller rather
				// than from the account's role, which is what makes it safe to
				// revoke: removing a role-granted capability per user is not
				// what the caller means by "revoke".
				'granted_here'        => isset( $user->caps[ self::MCP_CAP ] ),
				'is_administrator'    => $is_admin,

				// An administrator can POST to the kit's settings route, so it
				// can move itself up the ladder. Worth saying out loud.
				'can_raise_own_level' => $is_admin,
				'can_delete_leads'    => user_can( $user, 'manage_notifybay' ),
				'app_passwords'       => $passwords,
			);
		}

		return array(
			'success'                 => true,
			'accounts'                => $accounts,
			'app_passwords_available' => wp_is_application_passwords_available(),
		);
	}

	/**
	 * POST /admin/mcp/accounts
	 *
	 * Creates a dedicated account and issues its Application Password. The
	 * password is in this response and nowhere else -- it is not stored, not
	 * logged, and cannot be read back. Losing it means rotating.
	 *
	 * @since 1.0.3
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array|WP_Error
	 */
	public function create_account( $request ) {
		if ( ! wp_is_application_passwords_available() ) {
			return new WP_Error(
				'notifybay_app_passwords_unavailable',
				__( 'WordPress is refusing Application Passwords on this site, which usually means it is served over plain http. An MCP client has no other way to sign in, so there is no point creating an account yet.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array( 'status' => 400 )
			);
		}

		$login = sanitize_user( (string) $request->get_param( 'user_login' ), true );

		if ( '' === $login ) {
			return new WP_Error(
				'notifybay_invalid_login',
				__( 'Please supply a username.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array( 'status' => 400 )
			);
		}

		if ( username_exists( $login ) ) {
			return new WP_Error(
				'notifybay_login_exists',
				__( 'That username is already taken. Pick another, or revoke and reuse the existing account.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array( 'status' => 400 )
			);
		}

		$email = sanitize_email( (string) $request->get_param( 'user_email' ) );

		if ( '' === $email ) {
			/*
			 * This account never receives mail; the address only has to be
			 * unique and syntactically valid.
			 *
			 * The site host is used when it has a dot in it and otherwise
			 * discarded, because is_email() rejects a dotless domain -- so on a
			 * `localhost` install, deriving the address from the host produced
			 * an address that then failed validation, and account creation
			 * refused with a message about the email the caller never supplied.
			 * example.invalid is reserved by RFC 2606 and can never resolve.
			 */
			$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
			$email = $login . '@' . ( false !== strpos( $host, '.' ) ? $host : 'example.invalid' );
		}

		if ( ! is_email( $email ) || email_exists( $email ) ) {
			return new WP_Error(
				'notifybay_invalid_email',
				__( 'That email address is not usable. Supply a different one.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array( 'status' => 400 )
			);
		}

		/*
		 * The account password is generated and then deliberately discarded --
		 * nothing here returns it. The account exists to hold an Application
		 * Password, and nobody, including the store owner, should be able to
		 * sign in to the dashboard as it.
		 *
		 * The role is hardcoded. It is never taken from the request, because a
		 * caller-supplied role is how this route would become a privilege
		 * escalation.
		 */
		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'user_email'   => $email,
				'role'         => self::MCP_ROLE,
				'display_name' => $login,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error(
				'notifybay_user_missing',
				__( 'The account was created but could not be read back.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array( 'status' => 500 )
			);
		}

		$user->add_cap( self::MCP_CAP );

		$password = $this->issue_password( $user_id );

		if ( is_wp_error( $password ) ) {
			return $password;
		}

		notifybay_log(
			sprintf( 'MCP account created: %s (id %d), role %s', $login, $user_id, self::MCP_ROLE ),
			'info'
		);

		return array(
			'success'    => true,
			'user_id'    => $user_id,
			'user_login' => $login,

			// Shown once, then gone.
			'password'   => $password,
			'message'    => __( 'Account created. Copy the password now — it cannot be shown again.', 'notifybay-waitlist-and-stock-alert-woo' ),
		);
	}

	/**
	 * POST /admin/mcp/accounts/rotate
	 *
	 * Replaces the account's Application Password, invalidating the old one
	 * immediately. This is also the recovery path for a password that was never
	 * copied, since there is no way to read one back.
	 *
	 * @since 1.0.3
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array|WP_Error
	 */
	public function rotate_password( $request ) {
		$user = $this->get_manageable_user( (int) $request->get_param( 'user_id' ) );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$this->delete_our_passwords( $user->ID );

		$password = $this->issue_password( $user->ID );

		if ( is_wp_error( $password ) ) {
			return $password;
		}

		notifybay_log( sprintf( 'MCP application password rotated for %s (id %d)', $user->user_login, $user->ID ), 'info' );

		return array(
			'success'    => true,
			'user_id'    => $user->ID,
			'user_login' => $user->user_login,
			'password'   => $password,
			'message'    => __( 'New password issued. The previous one stopped working immediately.', 'notifybay-waitlist-and-stock-alert-woo' ),
		);
	}

	/**
	 * POST /admin/mcp/accounts/revoke
	 *
	 * Removes MCP access: deletes the Application Passwords this controller
	 * issued and takes the capability away.
	 *
	 * The WordPress account itself is left alone. Deleting a user reassigns or
	 * destroys whatever else is attributed to them, which is not something to do
	 * as a side effect of switching an assistant off.
	 *
	 * @since 1.0.3
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array|WP_Error
	 */
	public function revoke_access( $request ) {
		$user = $this->get_manageable_user( (int) $request->get_param( 'user_id' ) );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$this->delete_our_passwords( $user->ID );
		$user->remove_cap( self::MCP_CAP );

		notifybay_log( sprintf( 'MCP access revoked for %s (id %d)', $user->user_login, $user->ID ), 'info' );

		return array(
			'success' => true,
			'message' => __( 'Access revoked. The account still exists but can no longer reach the endpoint.', 'notifybay-waitlist-and-stock-alert-woo' ),
		);
	}

	/**
	 * Resolve a user id this controller is allowed to act on.
	 *
	 * Refuses administrators outright. Granting or rotating MCP credentials for
	 * an account that holds `manage_options` is the exact arrangement this
	 * controller exists to avoid, and removing a role-granted capability from
	 * one user is not what "revoke" means to the caller.
	 *
	 * @since 1.0.3
	 * @access private
	 * @param int $user_id The requested user id.
	 * @return WP_User|WP_Error
	 */
	private function get_manageable_user( $user_id ) {
		$user = $user_id > 0 ? get_user_by( 'id', $user_id ) : false;

		if ( ! $user instanceof WP_User ) {
			return new WP_Error(
				'notifybay_user_not_found',
				__( 'That account does not exist.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array( 'status' => 404 )
			);
		}

		if ( user_can( $user, 'manage_options' ) ) {
			return new WP_Error(
				'notifybay_refuse_administrator',
				__( 'This screen will not issue MCP credentials for an administrator. An administrator can change the access level itself, which defeats the point of setting one. Create a dedicated account instead.', 'notifybay-waitlist-and-stock-alert-woo' ),
				array( 'status' => 400 )
			);
		}

		return $user;
	}

	/**
	 * Issue an Application Password, returning the secret.
	 *
	 * Spaces are stripped. WordPress hands the secret back in chunks and strips
	 * them again when authenticating, but a space-separated secret is silently
	 * truncated by shell word splitting -- which once made a 401 for bad
	 * credentials look like proof of a capability boundary.
	 *
	 * @since 1.0.3
	 * @access private
	 * @param int $user_id The account to issue for.
	 * @return string|WP_Error
	 */
	private function issue_password( $user_id ) {
		$created = WP_Application_Passwords::create_new_application_password(
			$user_id,
			array( 'name' => self::APP_PASSWORD_NAME )
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		return str_replace( ' ', '', $created[0] );
	}

	/**
	 * Delete only the Application Passwords this controller issued.
	 *
	 * Matched by name, so a password the store owner created by hand for some
	 * other integration is never touched.
	 *
	 * @since 1.0.3
	 * @access private
	 * @param int $user_id The account to clean up.
	 * @return void
	 */
	private function delete_our_passwords( $user_id ) {
		foreach ( WP_Application_Passwords::get_user_application_passwords( $user_id ) as $item ) {
			if ( isset( $item['name'], $item['uuid'] ) && self::APP_PASSWORD_NAME === $item['name'] ) {
				WP_Application_Passwords::delete_application_password( $user_id, $item['uuid'] );
			}
		}
	}
}
