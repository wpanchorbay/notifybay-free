import React, { useState, useEffect } from "react";
import { __ } from "@wordpress/i18n";
import apiFetch from "../../../utils/apiFetch";
import { ClassicSettingsTable, ClassicToggle } from "../../classics";
import { ConfirmationModal } from "../../common/ConfirmationModal";
import { CopyToClipboard } from "../../common/CopyToClipboard";
import { useToast } from "../../../store/toast/use-toast";
import { McpLocalize, McpStatus } from "../../../utils/types";
import { buildMcpSnippets } from "./mcpSnippets";
import { McpAccounts } from "./McpAccounts";

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
                <span className="notifybay-text-sm notifybay-text-gray-600">
                  {isEnabled
                    ? __(
                        "Active",
                        "notifybay-waitlist-and-stock-alert-woo",
                      )
                    : __(
                        "Off",
                        "notifybay-waitlist-and-stock-alert-woo",
                      )}
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
                {status.available_access_levels.map((level) => (
                  <label
                    key={level}
                    htmlFor={`mcp_level_${level}`}
                    className="notifybay-flex notifybay-items-start notifybay-gap-2 notifybay-cursor-pointer"
                  >
                    <input
                      type="radio"
                      id={`mcp_level_${level}`}
                      name="mcp_access_level"
                      value={level}
                      checked={accessLevel === level}
                      disabled={isSaving}
                      onChange={() => handleLevelChange(level)}
                      className="notifybay-mt-1"
                    />
                    <span>
                      <span className="notifybay-font-semibold">
                        {LEVEL_LABELS[level] || level}
                      </span>
                      {LEVEL_DESCRIPTIONS[level] && (
                        <span className="notifybay-block notifybay-text-sm notifybay-text-gray-600">
                          {LEVEL_DESCRIPTIONS[level]}
                        </span>
                      )}
                    </span>
                  </label>
                ))}
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
              <span className="notifybay-text-sm">{status.tool_count}</span>
            ),
          },
        ]}
      />

      <McpAccounts mcp={mcp} />

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
              <div className="notifybay-flex notifybay-items-center notifybay-gap-2">
                <code className="notifybay-text-xs notifybay-break-all">
                  {status.endpoint}
                </code>
                <CopyToClipboard text={status.endpoint} />
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
              "A revocable, per-application credential. It cannot be used to sign in to the dashboard, and revoking it does not change the account's own password.",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            render: () => (
              <div className="notifybay-flex notifybay-flex-col notifybay-gap-2">
                {mcp.app_passwords_available ? (
                  <p className="description notifybay-m-0">
                    <a href={mcp.app_passwords_url}>
                      {__(
                        "Create one on your profile",
                        "notifybay-waitlist-and-stock-alert-woo",
                      )}
                    </a>
                    {" — "}
                    {__(
                      "or create a dedicated account below, which is the safer option.",
                      "notifybay-waitlist-and-stock-alert-woo",
                    )}
                  </p>
                ) : (
                  /*
                   * Without this the adopter gets an unexplained 401 and no
                   * hint that the transport is the problem: WordPress refuses
                   * Application Passwords entirely over plain http, and an MCP
                   * client has no other way to authenticate.
                   */
                  <p className="notifybay-m-0 notifybay-text-sm notifybay-text-red-700">
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
                <ul className="subsubsub !notifybay-m-0 !notifybay-p-0 notifybay-list-none">
                  {snippets.map((s, index) => (
                    <li
                      key={s.id}
                      className="notifybay-inline-block !notifybay-m-0"
                    >
                      <button
                        type="button"
                        onClick={() => setActiveClient(s.id)}
                        className={`notifybay-p-0 notifybay-bg-transparent notifybay-border-0 notifybay-cursor-pointer notifybay-text-[13px] ${
                          activeClient === s.id
                            ? "notifybay-text-black notifybay-font-bold"
                            : "notifybay-text-[#2271b1] hover:notifybay-text-[#135e96]"
                        }`}
                      >
                        {s.label}
                      </button>
                      {index < snippets.length - 1 && (
                        <span className="notifybay-mx-1 notifybay-text-[#c3c4c7]">
                          |
                        </span>
                      )}
                    </li>
                  ))}
                </ul>

                <p className="description notifybay-m-0">
                  {snippet.instructions}
                </p>

                {snippet.warning && (
                  <p className="notifybay-m-0 notifybay-text-sm notifybay-text-red-700">
                    {snippet.warning}
                  </p>
                )}

                <div className="notifybay-flex notifybay-justify-end">
                  <CopyToClipboard text={snippet.snippet} />
                </div>
                <pre className="notifybay-bg-gray-50 notifybay-border notifybay-border-gray-200 notifybay-p-3 notifybay-rounded-lg notifybay-text-xs notifybay-overflow-x-auto notifybay-m-0">
                  {snippet.snippet}
                </pre>
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
