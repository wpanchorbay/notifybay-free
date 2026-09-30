import { __ } from "@wordpress/i18n";

/**
 * Ready-to-paste connection snippets, one per MCP client.
 *
 * Two transports show up here, and the difference is not cosmetic. Claude
 * Desktop speaks stdio, so a hand-written config runs the npx proxy and reads
 * credentials from `env` -- which is what the MCP specification prescribes for
 * stdio ("retrieve credentials from the environment"). Cursor, Codex and
 * anything else speaking Streamable HTTP talk to the endpoint directly and
 * authenticate with an ordinary Basic header, no proxy involved.
 *
 * **Every snippet here is complete.** There is no placeholder form. There used
 * to be one, rendered whenever no password was on screen, and it was actively
 * harmful: it put `<base64(admin:application-password)>` in front of someone
 * with no encoder, no link to one, and no indication that the thing they were
 * copying could not work. A snippet that cannot be pasted is not a lesser
 * snippet, it is a wrong answer that looks like a right one. The caller now
 * renders nothing at all in that state -- see McpClientConfig.
 *
 * That is why `password` is required rather than optional.
 */
export interface McpSnippet {
  id: string;
  label: string;
  /** Rendered verbatim in a <pre>; only used to hint at syntax, not executed. */
  kind: "json" | "toml" | "shell" | "text";
  snippet: string;
  instructions: string;
  /** Set when the snippet contains something the reader must be warned about. */
  warning?: string;
}

interface BuildOptions {
  endpoint: string;
  /** The account the credential belongs to. */
  username: string;
  /** Only ever true on an https development host -- see Admin::get_mcp_localize(). */
  offerTlsBypass: boolean;
  /**
   * The just-generated Application Password, while it is still on screen.
   * Required: there is no useful snippet without it, and pretending otherwise
   * is what the placeholder form used to do.
   */
  password: string;
}

/*
 * Pinned, not `@latest`. An unpinned spec means the proxy a merchant's config
 * resolves changes underneath them with no release on our side, so a snippet
 * that worked when it was copied can break later for reasons nothing on this
 * site can explain. The bundled Claude Desktop path does not use this package
 * at all any more -- it ships its own dependency-free bridge -- so this is only
 * for someone configuring Claude Desktop by hand.
 */
const PROXY_PACKAGE = "@automattic/mcp-wordpress-remote@0.4.0";

export function buildMcpSnippets({
  endpoint,
  username,
  offerTlsBypass,
  password,
}: BuildOptions): McpSnippet[] {
  const user = username || "<your-username>";

  /*
   * btoa is the browser's own base64, and it is correct here because both
   * halves of an HTTP Basic credential are ASCII: a WordPress user_login is
   * sanitised to a restricted set, and core generates the password from
   * [A-Za-z0-9]. A multi-byte character would throw, which is why this is not
   * a general-purpose encoder.
   */
  const BASIC = window.btoa(`${user}:${password}`);

  const desktopEnv: Record<string, string> = {
    WP_API_URL: endpoint,
    WP_API_USERNAME: user,
    WP_API_PASSWORD: password,
    // The proxy would otherwise attempt an OAuth discovery flow that this
    // endpoint does not implement.
    OAUTH_ENABLED: "false",
  };

  if (offerTlsBypass) {
    desktopEnv.NODE_TLS_REJECT_UNAUTHORIZED = "0";
  }

  return [
    {
      id: "claude-desktop",
      label: __("Claude Desktop", "notifybay-waitlist-and-stock-alert-woo"),
      kind: "json",
      snippet: JSON.stringify(
        {
          mcpServers: {
            notifybay: {
              command: "npx",
              args: ["-y", PROXY_PACKAGE],
              env: desktopEnv,
            },
          },
        },
        null,
        2,
      ),
      instructions: __(
        "Only needed if you would rather not use the download above. Paste this into your Claude Desktop config yourself, at Settings → Developer → Edit Config — Claude Desktop cannot add it for you. It needs Node.js installed. Then fully quit and reopen it.",
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
      id: "claude-code",
      label: __("Claude Code", "notifybay-waitlist-and-stock-alert-woo"),
      kind: "shell",
      snippet: [
        `# Connects as ${user}.`,
        "claude mcp add \\",
        "  --transport http \\",
        `  notifybay ${endpoint} \\`,
        `  --header "Authorization: Basic ${BASIC}"`,
      ].join("\n"),
      instructions: __(
        "Ready to paste. Run it in a terminal where Claude Code is installed — your new password is already encoded into the header.",
        "notifybay-waitlist-and-stock-alert-woo",
      ),
    },
    {
      id: "cursor",
      label: __("Cursor", "notifybay-waitlist-and-stock-alert-woo"),
      kind: "json",
      snippet: JSON.stringify(
        {
          mcpServers: {
            notifybay: {
              url: endpoint,
              type: "http",
              headers: { Authorization: `Basic ${BASIC}` },
            },
          },
        },
        null,
        2,
      ),
      instructions: __(
        "Add this to Cursor's mcp.json — ~/.cursor/mcp.json for every project, or .cursor/mcp.json inside one project. It is complete as shown: no proxy, nothing to substitute.",
        "notifybay-waitlist-and-stock-alert-woo",
      ),
    },
    {
      /*
       * This was a labelled block of Name / Transport / URL / Header, under an
       * instruction to "add a custom MCP server" in a settings screen Codex CLI
       * does not have -- the last piece of unpasteable prose in this file.
       *
       * It was then deleted outright, on the grounds that the TOML key names
       * could not be confirmed against a trustworthy source. They since have
       * been, against Codex's own parser rather than its documentation (whose
       * docs/config.md is now a stub): codex-rs/config/src/mcp_edit.rs reads the
       * top-level `mcp_servers` table from config.toml, and
       * codex-rs/config/src/mcp_types.rs declares `url` and `http_headers` on
       * RawMcpServerConfig -- supplying `url` selects the streamable_http
       * transport, and http_headers is `throw_if_set` on the stdio branch only.
       * So a static Authorization header is supported exactly where this uses
       * it, and the shape is verified rather than merely plausible.
       *
       * The earlier deletion was a search failure, not an absence of evidence:
       * the struct is in config/src/mcp_types.rs, not core/src/config_types.rs.
       *
       * The file, not `codex mcp add`: that command takes `--url` but has no
       * header flag, so it cannot carry the credential. Anyone told to use it
       * would get a server entry that authenticates as nobody and 401s.
       */
      id: "codex",
      label: __("Codex", "notifybay-waitlist-and-stock-alert-woo"),
      kind: "toml",
      snippet: [
        `# Connects as ${user}.`,
        "[mcp_servers.notifybay]",
        `url = "${endpoint}"`,
        `http_headers = { Authorization = "Basic ${BASIC}" }`,
      ].join("\n"),
      instructions: __(
        "Add this to ~/.codex/config.toml, then restart Codex. It is complete as shown: no proxy, nothing to substitute. Do not use `codex mcp add` for this — it cannot attach the Authorization header, so the server would be added without a credential.",
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
        `curl -s -u '${user}:${password}' \\`,
        `  -X POST ${endpoint} \\`,
        "  -H 'Content-Type: application/json' \\",
        "  -H 'Accept: application/json, text/event-stream' \\",
        /*
         * 2025-06-18 is what the endpoint itself advertises back
         * (InitializeHandler), and what the bundled Claude Desktop bridge
         * sends. This said 2025-11-25, so a merchant testing with this curl was
         * performing a different handshake from the one the bundle performs --
         * harmless while nothing server-side enforces the header, and
         * needlessly confusing the day something does.
         */
        `  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"curl","version":"1"}}}'`,
      ].join("\n"),
      instructions: __(
        "Any MCP client that speaks Streamable HTTP can connect with this URL and a Basic auth header.",
        "notifybay-waitlist-and-stock-alert-woo",
      ),
    },
  ];
}
