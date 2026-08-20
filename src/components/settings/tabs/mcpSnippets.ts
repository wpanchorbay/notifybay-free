import { __ } from "@wordpress/i18n";

/**
 * Ready-to-paste connection snippets, one per MCP client.
 *
 * Two transports show up here, and the difference is not cosmetic. Claude
 * Desktop speaks stdio, so it runs the npx proxy and reads credentials from
 * `env` -- which is what the MCP specification prescribes for stdio ("retrieve
 * credentials from the environment"). Cursor, Codex and anything else speaking
 * Streamable HTTP talk to the endpoint directly and authenticate with an
 * ordinary Basic header, no proxy involved.
 *
 * A snippet is only useful if it can be pasted as-is. When a password has just
 * been generated, the real credential is substituted -- including the base64 of
 * `username:password` that Basic auth requires, which the reader would
 * otherwise have to compute by hand before the command would run. Once the
 * one-time display is dismissed the snippets revert to placeholders, because
 * the secret is genuinely gone by then and cannot be re-derived.
 */
export interface McpSnippet {
  id: string;
  label: string;
  /** Rendered verbatim in a <pre>; only used to hint at syntax, not executed. */
  kind: "json" | "shell" | "text";
  snippet: string;
  instructions: string;
  /** Set when the snippet contains something the reader must be warned about. */
  warning?: string;
}

interface BuildOptions {
  endpoint: string;
  /** Prefilled from the logged-in user, purely as a hint of the expected shape. */
  username: string;
  /** Only ever true on an https development host -- see Admin::get_mcp_localize(). */
  offerTlsBypass: boolean;
  /**
   * The just-generated Application Password, while it is still on screen.
   * Absent at every other time, which is the only reason the placeholders
   * below still exist.
   */
  password?: string | null;
}

const BASIC_PLACEHOLDER = "<base64(username:application-password)>";
const PASS_PLACEHOLDER = "<your-application-password>";

export function buildMcpSnippets({
  endpoint,
  username,
  offerTlsBypass,
  password,
}: BuildOptions): McpSnippet[] {
  const user = username || "<your-username>";
  const hasSecret = Boolean(password);

  const PASS = password || PASS_PLACEHOLDER;

  /*
   * btoa is the browser's own base64, and it is correct here because both
   * halves of an HTTP Basic credential are ASCII: a WordPress user_login is
   * sanitised to a restricted set, and core generates the password from
   * [A-Za-z0-9]. A multi-byte character would throw, which is why this is not
   * a general-purpose encoder.
   */
  const BASIC = hasSecret
    ? window.btoa(`${user}:${password}`)
    : BASIC_PLACEHOLDER;

  /*
   * Half of each instruction was telling the reader to substitute a
   * credential. When the credential is already in the snippet that sentence is
   * not merely redundant, it sends them looking for work that does not exist.
   */
  const ready = (whenReady: string, whenPlaceholder: string) =>
    hasSecret ? whenReady : whenPlaceholder;

  const desktopEnv: Record<string, string> = {
    WP_API_URL: endpoint,
    WP_API_USERNAME: user,
    WP_API_PASSWORD: PASS,
    // The proxy would otherwise attempt an OAuth discovery flow that this
    // endpoint does not implement.
    OAUTH_ENABLED: "false",
  };

  if (offerTlsBypass) {
    desktopEnv.NODE_TLS_REJECT_UNAUTHORIZED = "0";
  }

  return [
    {
      id: "claude-code",
      label: __("Claude Code", "notifybay-waitlist-and-stock-alert-woo"),
      kind: "shell",
      snippet: [
        "claude mcp add \\",
        "  --transport http \\",
        `  notifybay ${endpoint} \\`,
        `  --header "Authorization: Basic ${BASIC}"`,
      ].join("\n"),
      instructions: ready(
        __(
          "Ready to paste. Run it in a terminal where Claude Code is installed — your new password is already encoded into the header.",
          "notifybay-waitlist-and-stock-alert-woo",
        ),
        __(
          'Generate a password above and this becomes ready to paste. Otherwise, replace the placeholder with base64 of "username:application-password".',
          "notifybay-waitlist-and-stock-alert-woo",
        ),
      ),
    },
    {
      id: "claude-desktop",
      label: __("Claude Desktop", "notifybay-waitlist-and-stock-alert-woo"),
      kind: "json",
      snippet: JSON.stringify(
        { mcpServers: { notifybay: { command: "npx", args: ["-y", "@automattic/mcp-wordpress-remote@latest"], env: desktopEnv } } },
        null,
        2,
      ),
      instructions: ready(
        __(
          "Add this to your Claude Desktop config (Settings → Developer → Edit Config), then restart Claude Desktop. Your new password is already filled in.",
          "notifybay-waitlist-and-stock-alert-woo",
        ),
        __(
          "Add this to your Claude Desktop config (Settings → Developer → Edit Config), fill in the application password, then restart Claude Desktop.",
          "notifybay-waitlist-and-stock-alert-woo",
        ),
      ),
      warning: offerTlsBypass
        ? __(
            "NODE_TLS_REJECT_UNAUTHORIZED=0 turns off certificate checking for the proxy. It is included because this looks like a development site with a self-signed certificate. Never keep it on a live store — trust the certificate authority instead.",
            "notifybay-waitlist-and-stock-alert-woo",
          )
        : undefined,
    },
    {
      id: "cursor",
      label: __("Cursor", "notifybay-waitlist-and-stock-alert-woo"),
      kind: "json",
      snippet: JSON.stringify(
        { mcpServers: { notifybay: { url: endpoint, type: "http", headers: { Authorization: `Basic ${BASIC}` } } } },
        null,
        2,
      ),
      instructions: ready(
        __(
          "Add this to Cursor's mcp.json. It is complete as shown — no proxy, nothing to substitute.",
          "notifybay-waitlist-and-stock-alert-woo",
        ),
        __(
          'Add this to Cursor\'s mcp.json, replacing the placeholder with base64 of "username:application-password". No proxy is needed.',
          "notifybay-waitlist-and-stock-alert-woo",
        ),
      ),
    },
    {
      id: "codex",
      label: __("Codex", "notifybay-waitlist-and-stock-alert-woo"),
      kind: "text",
      snippet: [
        "Name:       notifybay",
        "Transport:  Streamable HTTP",
        `URL:        ${endpoint}`,
        "",
        "Header:",
        "  Key:    Authorization",
        `  Value:  Basic ${BASIC}`,
      ].join("\n"),
      instructions: __(
        "In Codex, add a custom MCP server with Streamable HTTP transport and the Authorization header above.",
        "notifybay-waitlist-and-stock-alert-woo",
      ),
    },
    {
      id: "generic",
      label: __("Anything else", "notifybay-waitlist-and-stock-alert-woo"),
      kind: "shell",
      snippet: [
        `URL:   ${endpoint}`,
        `Auth:  Authorization: Basic ${BASIC}`,
        "",
        "# Quick test -- curl does the base64 for you:",
        `curl -s -u '${user}:${PASS}' \\`,
        `  -X POST ${endpoint} \\`,
        "  -H 'Content-Type: application/json' \\",
        "  -H 'Accept: application/json, text/event-stream' \\",
        `  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"curl","version":"1"}}}'`,
      ].join("\n"),
      instructions: __(
        "Any MCP client that speaks Streamable HTTP can connect with this URL and a Basic auth header.",
        "notifybay-waitlist-and-stock-alert-woo",
      ),
    },
  ];
}
