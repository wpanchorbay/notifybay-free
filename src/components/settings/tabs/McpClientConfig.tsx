import React, { useState, ReactNode } from "react";
import { __, sprintf } from "@wordpress/i18n";
import { CopyToClipboard } from "../../common/CopyToClipboard";
import { McpSnippet } from "./mcpSnippets";
import { postTo } from "./postTo";

interface McpClientConfigProps {
  /**
   * Every client's snippet, always complete. `null` means no credential is on
   * screen, and then no snippet is rendered at all -- see the component
   * docblock.
   */
  snippets: McpSnippet[] | null;
  /** Named in the block header, because Basic auth hides it inside the base64. */
  username: string;
  /** Rendered above everything. Used for "the endpoint is currently off". */
  notice?: ReactNode;
  /** Reported when either the header Copy or a snippet Copy succeeds. */
  onCopy?: () => void;
  /** Nonced admin-post URL for the Claude Desktop `.mcpb` bundle. */
  bundleUrl?: string;
  /**
   * The live Application Password, posted with the bundle request so the
   * downloaded file installs without anything to paste. Absent after a reload,
   * where the bundle still downloads and asks for the password itself.
   */
  password?: string | null;
}

/**
 * The download, and -- only when it can be useful -- the client picker.
 *
 * Two rules shape this, both learned the hard way.
 *
 * **The download comes first and is always visible.** It used to appear only
 * after clicking the "Claude Desktop" pill, on a picker that opened on Claude
 * Code. So the first thing a store owner saw under "Client configuration" was a
 * terminal command, and the one path that needs no terminal, no JSON and no
 * file editing was hidden behind a click they had no reason to make.
 *
 * **Nothing is rendered that cannot be used.** When no password is on screen
 * the picker and snippet are gone entirely, rather than falling back to
 * `<base64(admin:application-password)>` in front of someone with no encoder.
 * An unusable snippet is not a lesser snippet; it reads as the answer and
 * silently is not one.
 *
 * This renders in two places at once -- on the MCP Connection tab and inside the
 * one-time modal -- and each instance keeps its own selected client. That is
 * deliberate: they answer different questions, and syncing them would mean the
 * modal silently changing what the tab shows behind it.
 *
 * @param root0
 * @param root0.snippets
 * @param root0.username
 * @param root0.notice
 * @param root0.onCopy
 * @param root0.bundleUrl
 * @param root0.password
 */
export const McpClientConfig: React.FC<McpClientConfigProps> = ({
  snippets,
  username,
  notice,
  onCopy,
  bundleUrl,
  password,
}) => {
  const [activeClient, setActiveClient] = useState<string | null>(null);

  const snippet = snippets
    ? snippets.find((s) => s.id === activeClient) || snippets[0]
    : null;

  return (
    <div className="notifybay-flex notifybay-flex-col notifybay-gap-3">
      {notice}

      {bundleUrl && (
        <div className="notifybay-max-w-3xl notifybay-rounded-md notifybay-border notifybay-border-blue-300 notifybay-bg-blue-50 notifybay-p-3">
          <button
            type="button"
            className="button button-primary"
            onClick={() => postTo(bundleUrl, { app_password: password })}
          >
            {__(
              "Download for Claude Desktop",
              "notifybay-waitlist-and-stock-alert-woo",
            )}
          </button>
          <p className="description notifybay-mt-2 notifybay-mb-0">
            {password
              ? __(
                  "Double-click the downloaded file, then press Install. Nothing to copy and nothing to fill in — your password is already in it. Claude Desktop moves it into your operating system keychain.",
                  "notifybay-waitlist-and-stock-alert-woo",
                )
              : __(
                  "Double-click the downloaded file to install it, then paste an application password when Claude Desktop asks. Generate one above and the download fills it in for you.",
                  "notifybay-waitlist-and-stock-alert-woo",
                )}
          </p>
        </div>
      )}

      {/*
        No credential, no snippets. The recovery line matters more than it
        looks: the secret is genuinely unrecoverable, and without being told
        that generating a second one leaves the first working, people revoke a
        credential their assistant is still using.
      */}
      {!snippet && (
        <p className="description notifybay-m-0 notifybay-max-w-3xl">
          {__(
            "Setup details for other clients — Claude Code, Cursor, Codex — appear here the moment you generate a password, with the credential already filled in. WordPress can only show a password once, so they are not shown for an existing one. Generating another does not stop the ones already in use.",
            "notifybay-waitlist-and-stock-alert-woo",
          )}
        </p>
      )}

      {snippet && snippets && (
        <>
          <div className="notifybay-flex notifybay-flex-wrap notifybay-gap-1.5">
            {snippets.map((s) => (
              <button
                key={s.id}
                type="button"
                onClick={() => setActiveClient(s.id)}
                className={`notifybay-cursor-pointer notifybay-rounded-full notifybay-border notifybay-px-3 notifybay-py-1 notifybay-text-xs notifybay-font-medium ${
                  snippet.id === s.id
                    ? "notifybay-border-blue-600 notifybay-bg-blue-600 notifybay-text-white"
                    : "notifybay-border-gray-300 notifybay-bg-white notifybay-text-gray-700 hover:notifybay-border-gray-500"
                }`}
              >
                {s.label}
              </button>
            ))}
          </div>

          <p className="description notifybay-m-0">{snippet.instructions}</p>

          {snippet.warning && (
            <p className="notifybay-m-0 notifybay-max-w-3xl notifybay-rounded-md notifybay-border notifybay-border-red-300 notifybay-bg-red-50 notifybay-p-3 notifybay-text-sm notifybay-text-red-800">
              {snippet.warning}
            </p>
          )}

          {/*
            The copy button belongs in the block's own header rather than
            floating above it -- this screen has several copyable things and a
            detached button does not say which one it takes.
          */}
          <div className="notifybay-max-w-3xl notifybay-overflow-hidden notifybay-rounded-lg notifybay-border notifybay-border-gray-200">
            <div className="notifybay-flex notifybay-items-center notifybay-justify-between notifybay-border-b notifybay-border-gray-200 notifybay-bg-gray-100 notifybay-px-3 notifybay-py-1.5">
              <span className="notifybay-text-xs notifybay-font-semibold notifybay-text-gray-600">
                {snippet.label}
                {/*
                  Basic auth carries the username inside the base64, so most of
                  these snippets never show which account they are for. Naming
                  it here fixes that without putting a comment inside a JSON
                  file, which Cursor's mcp.json would reject.
                */}
                {username && (
                  <span className="notifybay-font-normal notifybay-text-gray-500">
                    {" "}
                    {sprintf(
                      /* translators: %s: the WordPress username the snippet authenticates as. */
                      __(
                        "· connects as %s",
                        "notifybay-waitlist-and-stock-alert-woo",
                      ),
                      username,
                    )}
                  </span>
                )}
              </span>
              <CopyToClipboard text={snippet.snippet} onCopy={onCopy} />
            </div>
            <pre className="notifybay-m-0 notifybay-overflow-x-auto notifybay-bg-gray-50 notifybay-p-3 notifybay-text-xs">
              {snippet.snippet}
            </pre>
          </div>
        </>
      )}
    </div>
  );
};
