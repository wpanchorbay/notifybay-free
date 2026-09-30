<?php
/**
 * Claude Desktop bundle download.
 *
 * @package    NotifyBay
 * @subpackage Mcp
 * @since      1.0.3
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

namespace NotifyBay\Mcp;

use NotifyBay\Core\Base;
use NotifyBay\Core\Plugin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the `.mcpb` bundle a merchant can install into Claude Desktop by
 * double-clicking it, instead of hand-editing claude_desktop_config.json.
 *
 * Claude Desktop cannot add a server to its own config from inside a
 * conversation -- people reasonably try, because the assistant is right there,
 * and it refuses. The connection screen's JSON is correct but it is a file the
 * merchant has to find and edit themselves, which is the step most of them
 * stop at.
 *
 * An MCP Bundle is a zip carrying a manifest and the server entry point
 * (github.com/modelcontextprotocol/mcpb). Claude Desktop reads the manifest's
 * `user_config`, renders a small form for the values it needs, and stores
 * anything marked `sensitive` in the operating system keychain -- Keychain on
 * macOS, Credential Manager on Windows.
 *
 * The application password IS written into the bundle, as the `default` of a
 * `sensitive` user_config field, whenever the connection screen still has a
 * freshly generated one to give. That is a deliberate reversal of the earlier
 * design, which left the field empty so the file was not worth stealing. It
 * cost a step that turned out to be the step people stopped at: read a password
 * out of a dialog that can never be reopened, hold it in your head across an
 * application switch, and type it into a form. Prefilled, installing is
 * Download, double-click, Install. Claude Desktop still moves the value into
 * the OS keychain; what changes is that a live credential also sits in the
 * downloaded file until it is deleted, and Revoke on the connection screen is
 * the answer to that.
 *
 * Nothing here depends on it. With no password posted -- the page was reloaded,
 * so the secret is genuinely gone -- the bundle is built exactly as before and
 * Claude Desktop asks for the value itself.
 *
 * @since 1.0.3
 */
class DesktopBundle extends Base {

	/**
	 * Query arg for the admin-post action.
	 *
	 * @var string
	 */
	const ACTION = 'notifybay_mcp_bundle';

	/**
	 * Nonce action.
	 *
	 * @var string
	 */
	const NONCE = 'notifybay_mcp_bundle';

	/**
	 * Register hooks.
	 *
	 * @param Plugin $plugin The plugin instance.
	 */
	public function run( Plugin $plugin ) {
		$loader = $plugin->get_loader();
		$loader->add_action( 'admin_post_' . self::ACTION, $this, 'handle_download' );
	}

	/**
	 * The URL the connection screen links to, nonce included.
	 *
	 * @since 1.0.3
	 * @return string
	 */
	public static function download_url() {
		/*
		 * Built with add_query_arg() rather than wp_nonce_url(), which runs its
		 * result through esc_html() for use in an href attribute. That turns
		 * the separating `&` into `&#038;`, and this URL is handed to
		 * JavaScript through wp_localize_script() -- so the nonce ends up part
		 * of the previous parameter's value, never reaches $_REQUEST, and the
		 * download answers 403 to a perfectly ordinary click.
		 */
		return add_query_arg(
			array(
				'action'   => self::ACTION,
				'_wpnonce' => wp_create_nonce( self::NONCE ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Stream the bundle as a download.
	 *
	 * @since 1.0.3
	 * @return void
	 */
	public function handle_download() {
		/*
		 * Same gate as Admin::get_mcp_localize(), which decides whether the
		 * connection screen renders at all. A lower-capability user cannot see
		 * the link, and must not be able to reach the file by guessing the URL:
		 * the bundle names the site and the account to connect as.
		 */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to download the Claude Desktop bundle.', 'notifybay-waitlist-and-stock-alert-woo' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::NONCE );

		/*
		 * Posted in the request body, never in the query string. The connection
		 * screen submits a form rather than following a link precisely so a live
		 * credential does not end up in the URL bar, in browser history, or in
		 * every access log between the browser and this server.
		 *
		 * Not sanitized with sanitize_text_field(): core generates application
		 * passwords from [A-Za-z0-9] and displays them space-chunked, so the only
		 * legitimate transformation is removing whitespace. Anything else is
		 * rejected outright rather than quietly altered into a credential that
		 * will not authenticate and cannot be told apart from a typo.
		 */
		$password = '';

		if ( isset( $_POST['app_password'] ) ) {
			$candidate = preg_replace( '/\s+/', '', wp_unslash( $_POST['app_password'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated against a strict allow-list on the next line; sanitize_text_field() would silently mangle rather than reject.

			if ( is_string( $candidate ) && preg_match( '/^[A-Za-z0-9]{16,64}$/', $candidate ) ) {
				$password = $candidate;
			}
		}

		if ( ! class_exists( '\ZipArchive' ) ) {
			wp_die(
				esc_html__( 'This server cannot build zip files (the PHP zip extension is missing), so the Claude Desktop bundle cannot be created here. Use the JSON snippet on the connection screen instead.', 'notifybay-waitlist-and-stock-alert-woo' ),
				'',
				array( 'response' => 500 )
			);
		}

		$file = $this->build( $password );

		if ( is_wp_error( $file ) ) {
			wp_die( esc_html( $file->get_error_message() ), '', array( 'response' => 500 ) );
		}

		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="notifybay.mcpb"' );
		header( 'Content-Length: ' . filesize( $file ) );

		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming a generated temp file; WP_Filesystem would read it into memory first.
		wp_delete_file( $file );
		exit;
	}

	/**
	 * Write the bundle to a temp file.
	 *
	 * @since 1.0.3
	 * @param string $password Application password to prefill, or '' to leave it
	 *                         for Claude Desktop to ask for.
	 * @return string|\WP_Error Absolute path to the zip, or an error.
	 */
	private function build( $password = '' ) {
		$path = wp_tempnam( 'notifybay-mcpb' );

		if ( ! $path ) {
			return new \WP_Error( 'notifybay_bundle_tmp', __( 'Could not create a temporary file for the bundle.', 'notifybay-waitlist-and-stock-alert-woo' ) );
		}

		$zip = new \ZipArchive();

		if ( true !== $zip->open( $path, \ZipArchive::OVERWRITE ) ) {
			wp_delete_file( $path );
			return new \WP_Error( 'notifybay_bundle_zip', __( 'Could not open the bundle archive for writing.', 'notifybay-waitlist-and-stock-alert-woo' ) );
		}

		$zip->addFromString( 'manifest.json', (string) wp_json_encode( $this->manifest( $password ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		$zip->addFromString( 'server/index.js', $this->launcher() );
		$zip->close();

		return $path;
	}

	/**
	 * The bundle manifest.
	 *
	 * @since 1.0.3
	 * @param string $password Application password to prefill, or '' for none.
	 * @return array
	 */
	public function manifest( $password = '' ) {
		$user = wp_get_current_user();

		/*
		 * OAUTH_ENABLED is gone with the proxy that needed it: it existed only to
		 * stop Automattic's mcp-wordpress-remote attempting a discovery flow this
		 * endpoint does not implement. The bundled bridge speaks to the endpoint
		 * directly and has no discovery step to suppress.
		 */
		$env = array(
			'WP_API_URL'      => $this->endpoint(),
			'WP_API_USERNAME' => '${user_config.username}',
			'WP_API_PASSWORD' => '${user_config.app_password}',
		);

		/*
		 * Certificate verification is disabled only where the connection screen
		 * would already offer it: a local development host actually served over
		 * https, where a self-signed certificate is the plausible reason the
		 * proxy cannot connect. Never on a real site.
		 */
		if ( $this->offer_tls_bypass() ) {
			$env['NODE_TLS_REJECT_UNAUTHORIZED'] = '0';
		}

		return array(
			'manifest_version' => '0.3',
			'name'             => 'notifybay',
			'display_name'     => 'NotifyBay',
			'version'          => defined( 'NOTIFYBAY_VERSION' ) ? NOTIFYBAY_VERSION : '1.0.0',
			'description'      => __( 'Work with your WooCommerce waitlist from Claude: read leads, diagnose failed notifications, and re-send back-in-stock emails.', 'notifybay-waitlist-and-stock-alert-woo' ),
			'author'           => array(
				'name' => 'WPAnchorBay',
				'url'  => 'https://wpanchorbay.com',
			),
			'server'           => array(
				'type'        => 'node',
				'entry_point' => 'server/index.js',
				'mcp_config'  => array(
					'command' => 'node',
					'args'    => array( '${__dirname}/server/index.js' ),
					'env'     => $env,
				),
			),
			'user_config'      => array(
				'username'     => array(
					'type'        => 'string',
					'title'       => __( 'WordPress username', 'notifybay-waitlist-and-stock-alert-woo' ),
					'description' => __( 'The account Claude connects as. Its role still limits what it can reach.', 'notifybay-waitlist-and-stock-alert-woo' ),
					'required'    => true,
					'default'     => $user && $user->exists() ? $user->user_login : '',
				),
				'app_password' => $this->password_field( $password ),
			),
		);
	}

	/**
	 * The application-password field, prefilled when there is one to prefill.
	 *
	 * `sensitive` is kept in both cases. It is what makes Claude Desktop hand the
	 * value to the OS keychain rather than leave it in its own config, and that
	 * is still worth having when the value arrived as a default -- the file in
	 * ~/Downloads is a copy the merchant can delete, whereas the installed
	 * config is not.
	 *
	 * @since 1.0.3
	 * @param string $password Application password to prefill, or '' for none.
	 * @return array
	 */
	private function password_field( $password ) {
		$field = array(
			'type'        => 'string',
			'title'       => __( 'Application password', 'notifybay-waitlist-and-stock-alert-woo' ),
			'description' => __( 'Generate one on the MCP Connection screen in WordPress and paste it here. It is stored in your operating system keychain, not in this file.', 'notifybay-waitlist-and-stock-alert-woo' ),
			'required'    => true,
			'sensitive'   => true,
		);

		if ( '' === $password ) {
			return $field;
		}

		$field['description'] = __( 'Already filled in from your WordPress site. It is stored in your operating system keychain.', 'notifybay-waitlist-and-stock-alert-woo' );
		$field['default']     = $password;

		return $field;
	}

	/**
	 * The bundled entry point.
	 *
	 * @since 1.0.3
	 * @return string
	 */
	private function launcher() {
		return <<<'JS'
#!/usr/bin/env node
'use strict';
/**
 * NotifyBay bridge for Claude Desktop.
 *
 * Claude Desktop runs MCP servers locally over stdio, but this one lives on a
 * WordPress site and speaks Streamable HTTP, so something has to sit between
 * them. This is that something: read JSON-RPC from stdin, POST it to the site,
 * write the answer to stdout.
 *
 * It used to shell out to `npx @automattic/mcp-wordpress-remote`, which meant
 * every first connection needed Node AND npm AND the npm registry -- three
 * things a store owner has no reason to have, failing into a log file they have
 * no reason to read. This has no dependencies at all. Node 18's built-in fetch
 * is the only thing it needs beyond the runtime Claude Desktop already provides
 * for a `node` extension.
 *
 * Credentials arrive through the environment, which is what the MCP
 * specification prescribes for stdio servers. Nothing is written to disk.
 */

var ENDPOINT = process.env.WP_API_URL || '';
var USERNAME = process.env.WP_API_USERNAME || '';
var PASSWORD = process.env.WP_API_PASSWORD || '';

/*
 * What the endpoint advertises (InitializeHandler). Sent for correctness; do
 * not depend on it -- nothing on the server side enforces the header.
 */
var PROTOCOL = '2025-06-18';

function note(message) {
  process.stderr.write('NotifyBay: ' + message + '\n');
}

function fail(message) {
  note(message);
  process.exit(1);
}

if (!ENDPOINT || !USERNAME || !PASSWORD) {
  fail(
    'no connection details. Reinstall the bundle from WooCommerce -> Settings ' +
      '-> Leads Settings -> MCP Connection.'
  );
}

if ('function' !== typeof fetch) {
  fail('this needs Node.js 18 or newer.');
}

var AUTH = 'Basic ' + Buffer.from(USERNAME + ':' + PASSWORD).toString('base64');

/*
 * The endpoint issues a session on initialize and rejects every later request
 * without it (HttpSessionValidator). Both of these are the whole reason this
 * file is more than a one-line pipe.
 */
var sessionId = null;
var handshake = null;

function send(payload) {
  process.stdout.write(JSON.stringify(payload) + '\n');
}

function rpcError(id, message) {
  return { jsonrpc: '2.0', id: id, error: { code: -32603, message: message } };
}

/*
 * An SSE-framed reply would arrive as `data:` lines. The endpoint answers plain
 * JSON today -- GET/SSE is a 405 there -- so this is defensive, not load-bearing.
 */
function parse(text) {
  var trimmed = (text || '').trim();

  if (!trimmed) {
    return null;
  }

  var payload = trimmed;

  if (0 === trimmed.indexOf('data:')) {
    payload = trimmed
      .split('\n')
      .filter(function (line) {
        return 0 === line.indexOf('data:');
      })
      .map(function (line) {
        return line.slice(5).trim();
      })
      .join('');
  }

  try {
    return JSON.parse(payload);
  } catch (error) {
    return null;
  }
}

function explain(status) {
  if (401 === status) {
    return 'the site rejected the password. It may have been revoked in WordPress.';
  }

  if (403 === status) {
    return 'that WordPress account is not allowed to use the assistant endpoint.';
  }

  if (404 === status) {
    return 'the endpoint is not there. MCP is most likely switched off in WordPress.';
  }

  return 'the site answered with HTTP ' + status + '.';
}

async function post(message) {
  var headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json, text/event-stream',
    Authorization: AUTH,
    'Mcp-Protocol-Version': PROTOCOL,
  };

  if (sessionId) {
    headers['Mcp-Session-Id'] = sessionId;
  }

  var response = await fetch(ENDPOINT, {
    method: 'POST',
    headers: headers,
    body: JSON.stringify(message),
  });

  var issued = response.headers.get('mcp-session-id');

  if (issued) {
    sessionId = issued;
  }

  return { status: response.status, body: parse(await response.text()) };
}

/*
 * A WordPress REST error -- {"code":"rest_no_route","message":...} -- is not a
 * JSON-RPC message, and forwarding one verbatim leaves the client holding a
 * reply it cannot match to its request. The commonest case is exactly the one a
 * merchant will hit: MCP switched off, so the route does not exist and every
 * call 404s.
 */
function isRpc(body) {
  if (Array.isArray(body)) {
    return true;
  }

  return !!body && ('2.0' === body.jsonrpc || undefined !== body.result || undefined !== body.error);
}

function isSessionFailure(result) {
  if (!result.body || !result.body.error || !isRpc(result.body)) {
    return false;
  }

  return /session/i.test(String(result.body.error.message || ''));
}

/*
 * Sessions are stored in user meta and expire after 24 hours of inactivity
 * (SessionManager), and only 32 are kept per user before the oldest is dropped.
 * A desktop app left open overnight therefore wakes up holding a session the
 * site has forgotten. Re-handshaking here, silently, is the difference between
 * that being invisible and it looking like the plugin broke.
 */
async function forward(message) {
  var result = await post(message);

  if (!isSessionFailure(result) || !handshake || 'initialize' === message.method) {
    return result;
  }

  sessionId = null;

  var again = await post(handshake);

  if (!again.body || again.body.error) {
    return result;
  }

  return post(message);
}

/*
 * Every id the caller is waiting on, not just the first.
 *
 * A batch of N requests that the server answers with a non-JSON-RPC body -- the
 * 404 when MCP is switched off, which is the likeliest failure of all -- used to
 * produce exactly one error reply. The other N-1 ids were never answered, so the
 * client sat waiting on them with nothing to time out against.
 */
function identifiers(message) {
  if (Array.isArray(message)) {
    return message
      .filter(function (entry) {
        return entry && undefined !== entry.id && null !== entry.id;
      })
      .map(function (entry) {
        return entry.id;
      });
  }

  if (message && undefined !== message.id && null !== message.id) {
    return [message.id];
  }

  return [];
}

async function handle(message) {
  if (message && 'initialize' === message.method) {
    handshake = message;
  }

  var ids = identifiers(message);

  try {
    var result = await forward(message);

    /*
     * A notification gets no reply, and must not get one: the server returns
     * nothing for it and any stray byte on stdout desynchronises the stream.
     */
    if (!ids.length) {
      if (result.status >= 400) {
        note(explain(result.status));
      }

      return;
    }

    if (isRpc(result.body)) {
      send(result.body);
      return;
    }

    /*
     * Prefer our own wording over the server's: "No route was found matching
     * the URL" is true and useless, where "MCP is most likely switched off"
     * names the switch that fixes it.
     */
    var detail = explain(result.status);

    note(detail);

    ids.forEach(function (id) {
      send(rpcError(id, detail));
    });
  } catch (error) {
    var reason = 'could not reach ' + ENDPOINT + ' -- ' + (error && error.message ? error.message : error);

    note(reason);

    ids.forEach(function (id) {
      send(rpcError(id, reason));
    });
  }
}

/*
 * One at a time. The handshake has to finish before anything that depends on
 * the session it issues, and interleaved requests would race it.
 */
var queue = Promise.resolve();

function enqueue(message) {
  queue = queue
    .then(function () {
      return handle(message);
    })
    /*
     * handle() guards the request itself, but not everything: its own catch
     * calls send(), and process.stdout.write throws on EPIPE when the client
     * has gone away. One unhandled rejection would settle this chain rejected
     * and every later message would be dropped silently -- no reply, no stderr
     * line, Claude Desktop simply hanging on the next tool call.
     */
    .catch(function (error) {
      note('dropped a message -- ' + (error && error.message ? error.message : error));
    });
}

var buffer = '';

process.stdin.setEncoding('utf8');

process.stdin.on('data', function (chunk) {
  buffer += chunk;

  var index = buffer.indexOf('\n');

  while (index >= 0) {
    var line = buffer.slice(0, index).trim();

    buffer = buffer.slice(index + 1);

    if (line) {
      var message = null;

      try {
        message = JSON.parse(line);
      } catch (error) {
        note('ignored an unreadable message from the client.');
      }

      if (message) {
        enqueue(message);
      }
    }

    index = buffer.indexOf('\n');
  }
});

process.stdin.on('end', function () {
  queue.then(function () {
    process.exit(0);
  });
});
JS;
	}

	/**
	 * The site's MCP endpoint.
	 *
	 * @since 1.0.3
	 * @return string
	 */
	private function endpoint() {
		/*
		 * The kit registers the route as `wpab/<product_key>/mcp`
		 * (WPAB\Mcp\AdminApi builds the same string with rest_url()). The key
		 * is read from the manifest rather than written out again here, so a
		 * rename cannot leave this pointing at a route that no longer exists.
		 */
		$manifest    = require NOTIFYBAY_PATH . 'config/mcp.php';
		$product_key = isset( $manifest['product_key'] ) ? (string) $manifest['product_key'] : 'notifybay';

		return rest_url( "wpab/{$product_key}/mcp" );
	}

	/**
	 * Whether to include the certificate-verification escape hatch.
	 *
	 * Defers to Admin::is_local_dev(), which is the same test the connection
	 * screen uses to decide whether to offer the flag and warn about it. This
	 * used to be a separate copy, kept deliberately separate so that a bundle
	 * could never silently disable TLS checks. It did exactly that: the copy
	 * accepted WP_ENVIRONMENT_TYPE=local on its own, so a staging site served
	 * over real https with that variable set shipped
	 * NODE_TLS_REJECT_UNAUTHORIZED=0 in the downloaded file while the screen
	 * that offered the download showed nothing.
	 *
	 * The copy also used str_ends_with(), which is PHP 8.0. This plugin
	 * requires 7.4 and vendors no polyfill, so on a 7.4 store that had not
	 * short-circuited on `localhost` -- which is to say, any real store --
	 * building a bundle was a fatal error rather than a download.
	 *
	 * @since 1.0.3
	 * @return bool
	 */
	private function offer_tls_bypass() {
		return \NotifyBay\Admin\Admin::is_local_dev()
			&& 'https' === strtolower( (string) wp_parse_url( home_url(), PHP_URL_SCHEME ) );
	}
}
