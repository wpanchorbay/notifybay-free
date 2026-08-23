import React, { useState, ReactNode } from "react";
import { __, sprintf } from "@wordpress/i18n";
import { CopyToClipboard } from "../../common/CopyToClipboard";
import { McpSnippet } from "./mcpSnippets";

interface McpClientConfigProps {
  /** Every client's snippet, already filled or still holding placeholders. */
  snippets: McpSnippet[];
  /** Named in the block header, because Basic auth hides it inside the base64. */
  username: string;
  /** Rendered above the picker. Used for "the endpoint is currently off". */
  notice?: ReactNode;
  /** Reported when either the header Copy or a snippet Copy succeeds. */
  onCopy?: () => void;
}

/**
 * The client picker and its snippet pane.
 *
 * Extracted because this renders in two places at once -- on the AI Access tab,
 * where it usually holds placeholders, and inside the one-time modal, where it
 * holds the real credential. Those two must never drift: a snippet that is
 * correct in the modal and subtly wrong on the tab is worse than having only
 * one of them, because the reader has no way to tell which they are looking at.
 *
 * Each instance keeps its own selected client. That is deliberate -- the tab and
 * the modal are answering different questions ("what does connecting look
 * like?" versus "give me the thing I am about to paste"), and syncing them would
 * mean the modal silently changing what the tab shows behind it.
 *
 * @param root0
 * @param root0.snippets
 * @param root0.username
 * @param root0.notice
 * @param root0.onCopy
 */
export const McpClientConfig: React.FC<McpClientConfigProps> = ({
  snippets,
  username,
  notice,
  onCopy,
}) => {
  const [activeClient, setActiveClient] = useState("claude-code");
  const snippet = snippets.find((s) => s.id === activeClient) || snippets[0];

  return (
    <div className="notifybay-flex notifybay-flex-col notifybay-gap-2">
      {notice}

      <div className="notifybay-flex notifybay-flex-wrap notifybay-gap-1.5">
        {snippets.map((s) => (
          <button
            key={s.id}
            type="button"
            onClick={() => setActiveClient(s.id)}
            className={`notifybay-cursor-pointer notifybay-rounded-full notifybay-border notifybay-px-3 notifybay-py-1 notifybay-text-xs notifybay-font-medium ${
              activeClient === s.id
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
        The copy button belongs in the block's own header rather than floating
        above it -- this screen has four copyable things and a detached button
        does not say which one it takes.
      */}
      <div className="notifybay-max-w-3xl notifybay-overflow-hidden notifybay-rounded-lg notifybay-border notifybay-border-gray-200">
        <div className="notifybay-flex notifybay-items-center notifybay-justify-between notifybay-border-b notifybay-border-gray-200 notifybay-bg-gray-100 notifybay-px-3 notifybay-py-1.5">
          <span className="notifybay-text-xs notifybay-font-semibold notifybay-text-gray-600">
            {snippet.label}
            {/*
              Basic auth carries the username inside the base64, so three of
              these five snippets never show which account they are for. Naming
              it here fixes that without putting a comment inside a JSON file,
              which Cursor's mcp.json would reject.
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
    </div>
  );
};
