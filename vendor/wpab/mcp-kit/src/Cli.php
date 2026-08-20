<?php
/**
 * `wp wpab-mcp account` -- provisioning the account an assistant connects as.
 *
 * @package WPAB\Mcp
 */

declare(strict_types=1);

namespace WPAB\Mcp;

/**
 * WP-CLI commands for the kit.
 *
 * Why this exists at all, given the kit deliberately has no UI:
 *
 * Gate::activate() grants wpab_mcp_access to the administrator role and to
 * nothing else, so out of the box the only account able to reach an endpoint
 * is an administrator. That is the wrong account to hand an assistant, and not
 * because of the access ladder -- an application password is not scoped to
 * MCP. It authenticates every REST route its account can reach, so connecting
 * as an administrator gives an assistant the whole site whatever the ladder
 * says.
 *
 * Something therefore has to be able to create a narrow account, and a CLI
 * command is the smallest thing that can. A consuming plugin may put a screen
 * in front of this; the kit does not.
 *
 * `list` and `revoke` are not conveniences. A `create` with no way to
 * enumerate or undo its work produces accounts that hold the capability while
 * nothing on the site can say so -- which is exactly what happened to the one
 * adopter that shipped creation on its own.
 */
final class Cli {

	/** The role a provisioned account gets. Never taken from input. */
	private const ROLE = 'subscriber';

	/** Marks the passwords this command issues, so revoke knows its own. */
	private const PW_NAME = 'WPAB MCP';

	/**
	 * Register the command, if WP-CLI is what is running.
	 */
	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! \WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'wpab-mcp account', self::class );
	}

	/**
	 * Create an account for an AI assistant to connect as.
	 *
	 * ## OPTIONS
	 *
	 * <login>
	 * : The username to create.
	 *
	 * [--email=<email>]
	 * : Address for the account. Derived from the site host when omitted.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpab-mcp account create nb-assistant
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function create( array $args, array $assoc_args ): void {

		$login = sanitize_user( $args[0], true );

		if ( '' === $login ) {
			\WP_CLI::error( 'That username is not usable.' );
		}

		if ( username_exists( $login ) ) {
			\WP_CLI::error( sprintf( '%s already exists. Pick another name, or revoke and reuse it.', $login ) );
		}

		$email = sanitize_email( (string) ( $assoc_args['email'] ?? '' ) );

		if ( '' === $email ) {
			// is_email() rejects a dotless domain, so deriving the address
			// from the host of a `localhost` install produces one that then
			// fails validation -- and the command refuses, complaining about
			// an address the caller never supplied. example.invalid is
			// reserved by RFC 2606 and can never resolve.
			$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
			$email = $login . '@' . ( false !== strpos( $host, '.' ) ? $host : 'example.invalid' );
		}

		if ( ! is_email( $email ) || email_exists( $email ) ) {
			\WP_CLI::error( sprintf( '%s is not a usable address. Pass --email=', $email ) );
		}

		// The account password is generated and then discarded -- nothing
		// prints it. This account exists to hold an application password, and
		// nobody, including the store owner, signs in to the dashboard as it.
		//
		// The role is a constant. A caller-supplied role is how a provisioning
		// command becomes a privilege escalation.
		$user_id = wp_insert_user(
			[
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'user_email'   => $email,
				'role'         => self::ROLE,
				'display_name' => $login,
			]
		);

		if ( is_wp_error( $user_id ) ) {
			\WP_CLI::error( $user_id->get_error_message() );
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user instanceof \WP_User ) {
			\WP_CLI::error( 'The account was created but could not be read back.' );
		}

		// Granted per user, never to the role. A role grant would silently
		// admit every other subscriber on the site, and could not be revoked
		// for one account without revoking it for all of them.
		$user->add_cap( 'wpab_mcp_access' );

		$password = self::issue_password( $user_id );

		\WP_CLI::log( '' );
		\WP_CLI::log( sprintf( 'Username:  %s', $login ) );
		\WP_CLI::log( sprintf( 'Password:  %s', $password ) );
		\WP_CLI::log( '' );
		\WP_CLI::log( 'Shown once. Losing it costs a `revoke` and a fresh `create`.' );
		\WP_CLI::success( sprintf( '%s may now reach every enabled MCP endpoint on this site.', $login ) );
	}

	/**
	 * List the accounts that can reach an MCP endpoint.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpab-mcp account list
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function list( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- WP-CLI dictates this signature.

		$rows = [];

		foreach ( get_users( [ 'capability' => 'wpab_mcp_access' ] ) as $user ) {
			$rows[] = [
				'login' => $user->user_login,
				'roles' => implode( ',', $user->roles ),
				// A per-user grant is the revocable kind. One inherited from a
				// role cannot be removed for a single account, so revoke does
				// not offer to.
				'grant' => isset( $user->caps['wpab_mcp_access'] ) ? 'per-user' : 'role',
				'note'  => user_can( $user->ID, 'manage_options' )
					// Not merely "this is an administrator". An application
					// password reaches the whole REST API, so this account
					// hands an assistant far more than the MCP endpoint.
					? 'ADMIN -- credential reaches the entire REST API'
					: '',
			];
		}

		if ( ! $rows ) {
			\WP_CLI::success( 'No account can reach an MCP endpoint.' );
			return;
		}

		\WP_CLI\Utils\format_items( 'table', $rows, [ 'login', 'roles', 'grant', 'note' ] );
	}

	/**
	 * Stop an account reaching MCP endpoints.
	 *
	 * ## OPTIONS
	 *
	 * <login>
	 * : The username to revoke.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpab-mcp account revoke nb-assistant
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function revoke( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- WP-CLI dictates this signature.

		$user = get_user_by( 'login', $args[0] );

		if ( ! $user instanceof \WP_User ) {
			\WP_CLI::error( sprintf( 'No account called %s.', $args[0] ) );
		}

		if ( user_can( $user->ID, 'manage_options' ) ) {
			\WP_CLI::error(
				sprintf(
					'%s can manage the site. Revoking here would not stop it: it holds wpab_mcp_access through the administrator role, and its application passwords are not this command\'s to delete. Remove the role instead.',
					$user->user_login
				)
			);
		}

		if ( ! isset( $user->caps['wpab_mcp_access'] ) ) {
			\WP_CLI::error(
				sprintf(
					'%s has no per-user grant to remove. If it can still connect, the capability comes from its role.',
					$user->user_login
				)
			);
		}

		$user->remove_cap( 'wpab_mcp_access' );

		$removed = 0;

		foreach ( (array) \WP_Application_Passwords::get_user_application_passwords( $user->ID ) as $item ) {
			if ( isset( $item['name'], $item['uuid'] ) && self::PW_NAME === $item['name'] ) {
				\WP_Application_Passwords::delete_application_password( $user->ID, $item['uuid'] );
				++$removed;
			}
		}

		// The account is kept. Deleting it would reassign anything attributed
		// to it, which is a far larger act than withdrawing access.
		\WP_CLI::success(
			sprintf(
				'%s can no longer reach an MCP endpoint (%d application password(s) deleted). The account itself is untouched.',
				$user->user_login,
				$removed
			)
		);
	}

	/**
	 * Mint an application password, stripped of the spaces core adds.
	 *
	 * WordPress returns it chunked for readability and strips whitespace again
	 * when authenticating, so both forms work -- but a space-separated secret
	 * is trivially truncated by shell word splitting, which produces a wrong
	 * password and a 401 that looks like a permissions problem.
	 *
	 * @param int $user_id The account to issue for.
	 * @return string
	 */
	private static function issue_password( int $user_id ): string {

		$created = \WP_Application_Passwords::create_new_application_password(
			$user_id,
			[ 'name' => self::PW_NAME ]
		);

		if ( is_wp_error( $created ) ) {
			\WP_CLI::error( $created->get_error_message() );
		}

		return str_replace( ' ', '', $created[0] );
	}
}
