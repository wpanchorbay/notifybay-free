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
 * Every snippet carries a placeholder rather than a real secret. Nothing here
 * ever receives an actual Application Password -- the value is substituted by
 * the person pasting it.
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
}

const BASIC = "<base64(username:application-password)>";
const PASS = "<your-application-password>";

export function buildMcpSnippets({
  endpoint,
  username,
  offerTlsBypass,
}: BuildOptions): McpSnippet[] {
  const user = username || "<your-username>";

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
      instructions: __(
        'Run this in a terminal where Claude Code is installed, replacing the placeholder with base64 of "username:application-password".',
        "notifybay-waitlist-and-stock-alert-woo",
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
      instructions: __(
        "Add this to your Claude Desktop config (Settings → Developer → Edit Config), fill in the application password, then restart Claude Desktop.",
        "notifybay-waitlist-and-stock-alert-woo",
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
      instructions: __(
        'Add this to Cursor\'s mcp.json, replacing the placeholder with base64 of "username:application-password". No proxy is needed.',
        "notifybay-waitlist-and-stock-alert-woo",
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
