import React, { useState, useEffect } from "react";
import { __, sprintf } from "@wordpress/i18n";
import apiFetch from "../../../utils/apiFetch";
import { ClassicSettingsTable, ClassicToggle } from "../../classics";
import { ConfirmationModal } from "../../common/ConfirmationModal";
import { CopyToClipboard } from "../../common/CopyToClipboard";
import { useToast } from "../../../store/toast/use-toast";
import { McpLocalize, McpStatus } from "../../../utils/types";
import { buildMcpSnippets } from "./mcpSnippets";
import { McpAppPasswords } from "./McpAppPasswords";
import {
  TONE,
  IDLE_CARD,
  IDLE_BADGE,
  LEVEL_TONE,
  levelBadge,
  endpointState,
} from "./mcpTone";

interface McpTabProps {
  mcp: McpLocalize;
}

/**
 * Human-readable names for the access levels the kit currently ships. Keyed by
 * the raw value so a level added to the kit later still renders -- it falls
 * back to its own raw string rather than disappearing from the list, which is
 * the whole reason the options come from `available_access_levels` instead of
 * being hardcoded here.
 */
const LEVEL_LABELS: Record<string, string> = {
  read: __("Read only", "notifybay-waitlist-and-stock-alert-woo"),
  "read+modify": __("Read and modify", "notifybay-waitlist-and-stock-alert-woo"),
  full: __("Full access", "notifybay-waitlist-and-stock-alert-woo"),
};

const LEVEL_DESCRIPTIONS: Record<string, string> = {
  read: __(
    "The assistant can list leads and read system status. It cannot change anything.",
    "notifybay-waitlist-and-stock-alert-woo",
  ),
  "read+modify": __(
    "Adds editing a lead. The assistant still cannot delete anything.",
    "notifybay-waitlist-and-stock-alert-woo",
  ),
  full: __(
    "Adds deleting leads permanently. Only grant this if you trust the assistant and the account it connects with.",
    "notifybay-waitlist-and-stock-alert-woo",
  ),
};

/**
 * The MCP settings panel.
 *
 * Two things about how this saves, both deliberate:
 *
 * It applies every change immediately rather than collecting them behind a
 * Save button. The surrounding Settings page owns a single `hasChanges` flag
 * computed from the plugin settings blob, and gates tab-switching on it. MCP
 * state is a separate option owned by the kit, so a second pool of unsaved
 * changes here would be invisible to that check and would be silently
 * discarded when the user switched tabs.
 *
 * It re-renders from the response of every write, never from local state. The
 * kit's settings route returns the same payload as its status route, so what
 * is on screen is always what the server actually stored.
 *
 * @param root0
 * @param root0.mcp
 */
export const McpTab: React.FC<McpTabProps> = ({ mcp }) => {
  const [status, setStatus] = useState<McpStatus | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [pendingLevel, setPendingLevel] = useState<string | null>(null);
  const [activeClient, setActiveClient] = useState("claude-code");

  /*
   * Held here rather than in McpAppPasswords because the snippets need it: a
   * connection command with "<base64(username:application-password)>" in it is
   * not a command, it is homework. Lives only as long as the one-time display.
   */
  const [liveSecret, setLiveSecret] = useState<string | null>(null);
  const { addToast } = useToast();

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const response = (await apiFetch({
          url: `${mcp.rest_url}/status`,
        })) as McpStatus;
        if (!cancelled) {
          setStatus(response);
        }
      } catch (error) {
        if (!cancelled) {
          addToast(
            __(
              "Failed to load MCP settings.",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            "error",
          );
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const field = (key: string) => status?.settings?.find((s) => s.key === key);

  const isEnabled = Boolean(field("enabled")?.value);
  const accessLevel = String(field("access_level")?.value ?? "read");
  const state = endpointState(isEnabled, accessLevel);

  const save = async (payload: Record<string, unknown>) => {
    setIsSaving(true);
    try {
      const response = (await apiFetch({
        url: `${mcp.rest_url}/settings`,
        method: "POST",
        data: payload,
      })) as McpStatus;

      setStatus(response);

      /*
       * The kit rejects an unknown access level by returning the unchanged
       * state rather than an error, so a write that did nothing would
       * otherwise look identical to one that worked -- the radio would just
       * snap back with no explanation.
       */
      const requested = payload.access_level;
      const stored = response.settings?.find(
        (s) => s.key === "access_level",
      )?.value;

      if (requested !== undefined && requested !== stored) {
        addToast(
          __(
            "That access level was rejected. Nothing was changed.",
            "notifybay-waitlist-and-stock-alert-woo",
          ),
          "error",
        );
        return;
      }

      addToast(
        __("MCP settings saved.", "notifybay-waitlist-and-stock-alert-woo"),
        "success",
      );

      /*
       * WooCommerce's settings script arms an unsaved-changes prompt on any
       * input inside #mainform, which these controls live in. The change is
       * already persisted by this point, so the prompt would be a lie.
       */
      window.onbeforeunload = null;
    } catch (error) {
      addToast(
        __(
          "Failed to save MCP settings.",
          "notifybay-waitlist-and-stock-alert-woo",
        ),
        "error",
      );
    } finally {
      setIsSaving(false);
    }
  };

  const handleLevelChange = (level: string) => {
    if (level === accessLevel) {
      return;
    }
    // Escalating to full hands the assistant destructive tools; everything
    // else applies straight away.
    if (level === "full") {
      setPendingLevel(level);
      return;
    }
    save({ access_level: level });
  };

  if (isLoading) {
    return (
      <p>
        {__(
          "Loading MCP settings…",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
      </p>
    );
  }

  if (!status) {
    return (
      <p>
        {__(
          "Failed to load MCP settings.",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
      </p>
    );
  }

  const snippets = buildMcpSnippets({
    endpoint: status.endpoint,
    username: mcp.current_user_login,
    offerTlsBypass: mcp.offer_tls_bypass,
    password: liveSecret,
  });
  const snippet = snippets.find((s) => s.id === activeClient) || snippets[0];

  return (
    <div className="notifybay-flex notifybay-flex-col notifybay-gap-6">
      <ClassicSettingsTable
        title={__(
          "AI assistant access (MCP)",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        description={__(
          "Lets an AI assistant work with your waitlist through the Model Context Protocol. It is off until you turn it on, and whoever connects still signs in as a WordPress user with their own permissions.",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        fields={[
          {
            id: "mcp_enabled",
            label: __("Enable MCP", "notifybay-waitlist-and-stock-alert-woo"),
            tooltip: __(
              "Turns the endpoint on. While off, it refuses every request.",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            render: () => (
              <div className="notifybay-flex notifybay-items-center notifybay-gap-3">
                <ClassicToggle
                  id="mcp_enabled"
                  checked={isEnabled}
                  disabled={isSaving}
                  onChange={(checked) => save({ enabled: checked })}
                />
                <span
                  className={`notifybay-inline-flex notifybay-items-center notifybay-gap-1.5 notifybay-rounded-full notifybay-border notifybay-px-2.5 notifybay-py-0.5 notifybay-text-xs notifybay-font-semibold ${
                    TONE[state.tone].pill
                  }`}
                >
                  <span
                    className={`notifybay-h-1.5 notifybay-w-1.5 notifybay-rounded-full ${
                      TONE[state.tone].dot
                    }`}
                  />
                  {state.text}
                </span>
              </div>
            ),
          },
          {
            id: "mcp_access_level",
            label: __(
              "Access level",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            tooltip: __(
              "How much the assistant is allowed to do. Tools above this level are not offered to it at all.",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            render: () => (
              /*
               * Deliberately still settable while the endpoint is off. The
               * level is stored policy, not a live property of the running
               * endpoint, and greying it out would force the owner to enable
               * first and only then choose -- so the endpoint would briefly
               * serve at whatever level happened to be stored.
               */
              <fieldset
                className="notifybay-flex notifybay-flex-col notifybay-gap-2"
                disabled={isSaving}
              >
                {status.available_access_levels.map((level) => {
                  const selected = accessLevel === level;
                  const tone = LEVEL_TONE[level] || "neutral";
                  const badge = levelBadge(level);

                  return (
                    <label
                      key={level}
                      htmlFor={`mcp_level_${level}`}
                      className={`notifybay-flex notifybay-items-start notifybay-gap-3 notifybay-cursor-pointer notifybay-rounded-lg notifybay-border notifybay-p-3 notifybay-max-w-2xl ${
                        selected ? TONE[tone].card : IDLE_CARD
                      }`}
                    >
                      <input
                        type="radio"
                        id={`mcp_level_${level}`}
                        name="mcp_access_level"
                        value={level}
                        checked={selected}
                        disabled={isSaving}
                        onChange={() => handleLevelChange(level)}
                        className="notifybay-mt-1"
                      />
                      <span className="notifybay-w-full">
                        <span className="notifybay-flex notifybay-items-center notifybay-gap-2">
                          <span className="notifybay-font-semibold">
                            {LEVEL_LABELS[level] || level}
                          </span>
                          {badge && (
                            <span
                              className={`notifybay-rounded notifybay-border notifybay-px-1.5 notifybay-py-0.5 notifybay-text-[10px] notifybay-font-bold notifybay-tracking-wide ${
                                selected ? TONE[tone].badge : IDLE_BADGE
                              }`}
                            >
                              {badge}
                            </span>
                          )}
                        </span>
                        {LEVEL_DESCRIPTIONS[level] && (
                          <span className="notifybay-block notifybay-text-sm notifybay-text-gray-600 notifybay-mt-0.5">
                            {LEVEL_DESCRIPTIONS[level]}
                          </span>
                        )}
                      </span>
                    </label>
                  );
                })}
              </fieldset>
            ),
          },
          {
            id: "mcp_tool_count",
            label: __(
              "Tools available",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            tooltip: __(
              "How many NotifyBay tools this endpoint publishes in total, before the access level filters them.",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            render: () => (
              <span className="notifybay-inline-block notifybay-rounded-lg notifybay-border notifybay-border-gray-200 notifybay-bg-gray-50 notifybay-px-4 notifybay-py-2 notifybay-text-xl notifybay-font-bold">
                {status.tool_count}
              </span>
            ),
          },
        ]}
      />

      <ClassicSettingsTable
        title={__(
          "Connecting a client",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        description={__(
          "Your assistant signs in as an ordinary WordPress user with an Application Password. Its own role still limits what it can reach, on top of the access level above.",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        fields={[
          {
            id: "mcp_endpoint",
            label: __(
              "Endpoint URL",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            render: () => (
              <div className="notifybay-flex notifybay-items-center notifybay-gap-2 notifybay-max-w-2xl">
                <code className="notifybay-flex-1 notifybay-rounded-md notifybay-border notifybay-border-gray-200 notifybay-bg-gray-50 notifybay-px-2 notifybay-py-1.5 notifybay-text-xs notifybay-break-all">
                  {status.endpoint}
                </code>
                <CopyToClipboard text={status.endpoint} />
              </div>
            ),
          },
          {
            id: "mcp_connect_as",
            label: __(
              "Connect as",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            tooltip: __(
              "The username half of the credential. An Application Password belongs to an account, so the assistant inherits whatever that account may do.",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            render: () => (
              <div className="notifybay-flex notifybay-items-center notifybay-gap-2 notifybay-max-w-2xl">
                <code className="notifybay-flex-1 notifybay-rounded-md notifybay-border notifybay-border-gray-200 notifybay-bg-gray-50 notifybay-px-2 notifybay-py-1.5 notifybay-text-xs notifybay-break-all">
                  {mcp.current_user_login}
                </code>
                <CopyToClipboard text={mcp.current_user_login} />
              </div>
            ),
          },
          {
            id: "mcp_app_password",
            label: __(
              "Application Password",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            tooltip: __(
              "A per-application credential. It cannot be used to sign in to the dashboard, and revoking it does not change the account's own password.",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            render: () => (
              <div className="notifybay-flex notifybay-flex-col notifybay-gap-2">
                {mcp.app_passwords_available ? (
                  <>
                    <McpAppPasswords
                      endpoint={mcp.app_passwords_rest_url}
                      suggestedName={`NotifyBay MCP - ${snippet.label}`}
                      onSecretChange={setLiveSecret}
                    />
                    {/*
                      Verified against this site, not inferred: an assistant
                      holding one of these can POST the same core route and
                      issue itself another (HTTP 201). Revoking is therefore
                      only a reliable kill switch for an account that cannot
                      edit users -- which an administrator can. Saying so here
                      is the difference between a credential the reader
                      understands and one they over-trust.
                    */}
                    <p className="description notifybay-m-0">
                      {__(
                        "Revoking stops that credential immediately. Note that an assistant connecting as an account that can edit users could issue itself another one — so revoke is a reliable off switch only for an account without that permission.",
                        "notifybay-waitlist-and-stock-alert-woo",
                      )}
                      {" "}
                      <a href={mcp.app_passwords_url}>
                        {__(
                          "Manage all of this account's passwords",
                          "notifybay-waitlist-and-stock-alert-woo",
                        )}
                      </a>
                    </p>
                  </>
                ) : (
                  /*
                   * Without this the adopter gets an unexplained 401 and no
                   * hint that the transport is the problem: WordPress refuses
                   * Application Passwords entirely over plain http, and an MCP
                   * client has no other way to authenticate.
                   */
                  <p className="notifybay-m-0 notifybay-max-w-2xl notifybay-rounded-md notifybay-border notifybay-border-red-300 notifybay-bg-red-50 notifybay-p-3 notifybay-text-sm notifybay-text-red-800">
                    {__(
                      "WordPress is refusing Application Passwords on this site, which usually means it is served over plain http. No MCP client can authenticate until the site uses https.",
                      "notifybay-waitlist-and-stock-alert-woo",
                    )}
                  </p>
                )}
              </div>
            ),
          },
          {
            id: "mcp_client_config",
            label: __(
              "Client configuration",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            tooltip: __(
              "Pick your client, paste the snippet, then substitute the credential. Nothing here contains a real password.",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            render: () => (
              <div className="notifybay-flex notifybay-flex-col notifybay-gap-2">
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

                <p className="description notifybay-m-0">
                  {snippet.instructions}
                </p>

                {snippet.warning && (
                  <p className="notifybay-m-0 notifybay-max-w-3xl notifybay-rounded-md notifybay-border notifybay-border-red-300 notifybay-bg-red-50 notifybay-p-3 notifybay-text-sm notifybay-text-red-800">
                    {snippet.warning}
                  </p>
                )}

                {/*
                  The copy button belongs in the block's own header rather than
                  floating above it -- this screen has four copyable things and
                  a detached button does not say which one it takes.
                */}
                <div className="notifybay-max-w-3xl notifybay-overflow-hidden notifybay-rounded-lg notifybay-border notifybay-border-gray-200">
                  <div className="notifybay-flex notifybay-items-center notifybay-justify-between notifybay-border-b notifybay-border-gray-200 notifybay-bg-gray-100 notifybay-px-3 notifybay-py-1.5">
                    <span className="notifybay-text-xs notifybay-font-semibold notifybay-text-gray-600">
                      {snippet.label}
                      {/*
                        Basic auth carries the username inside the base64, so
                        three of these five snippets never show which account
                        they are for. Naming it here fixes that without putting
                        a comment inside a JSON file, which Cursor's mcp.json
                        would reject.
                      */}
                      {mcp.current_user_login && (
                        <span className="notifybay-font-normal notifybay-text-gray-500">
                          {sprintf(
                            /* translators: %s: the WordPress username the snippet authenticates as. */
                            __(
                              " · connects as %s",
                              "notifybay-waitlist-and-stock-alert-woo",
                            ),
                            mcp.current_user_login,
                          )}
                        </span>
                      )}
                    </span>
                    <CopyToClipboard text={snippet.snippet} />
                  </div>
                  <pre className="notifybay-m-0 notifybay-overflow-x-auto notifybay-bg-gray-50 notifybay-p-3 notifybay-text-xs">
                    {snippet.snippet}
                  </pre>
                </div>
              </div>
            ),
          },
        ]}
      />

      <ConfirmationModal
        isOpen={null !== pendingLevel}
        title={__(
          "Allow the assistant to delete?",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        message={__(
          "Full access lets the assistant permanently delete leads. Deleted leads cannot be recovered. Only grant this if you trust the assistant and the account it connects with.",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        confirmLabel={__(
          "Grant full access",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        cancelLabel={__("Cancel", "notifybay-waitlist-and-stock-alert-woo")}
        /*
         * Not the DOM autoFocus attribute the a11y rule is aimed at -- this is
         * ConfirmationModal's own prop, which focuses one of its two buttons by
         * ref. It defaults to the confirm button, so without this, opening the
         * dialog and pressing Enter would grant full access.
         */
        // eslint-disable-next-line jsx-a11y/no-autofocus
        autoFocus="cancel"
        classNames={{
          button: { confirmColor: "danger" },
        }}
        onConfirm={() => {
          const level = pendingLevel;
          setPendingLevel(null);
          if (level) {
            save({ access_level: level });
          }
        }}
        onCancel={() => setPendingLevel(null)}
      />
    </div>
  );
};
