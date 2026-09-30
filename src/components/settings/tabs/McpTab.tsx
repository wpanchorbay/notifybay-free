import React, { useState, useEffect, useCallback } from "react";
import { __ } from "@wordpress/i18n";
import apiFetch from "../../../utils/apiFetch";
import { ClassicSettingsTable } from "../../classics";
import { Switch } from "../../common/Switch";
import { ConfirmationModal } from "../../common/ConfirmationModal";
import { CopyToClipboard } from "../../common/CopyToClipboard";
import { useToast } from "../../../store/toast/use-toast";
import { McpLocalize, McpStatus } from "../../../utils/types";
import { buildMcpSnippets } from "./mcpSnippets";
import { postTo } from "./postTo";
import { McpAppPasswords } from "./McpAppPasswords";
import { McpClientConfig } from "./McpClientConfig";
import { McpConnectionModal } from "./McpConnectionModal";
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

/*
 * These have to name every tool the level unlocks. They are the only place a
 * store owner is told what they are agreeing to, and the ladder is the only
 * control they have -- a level that quietly gained a tool is consent they never
 * gave. Update this whenever config/mcp.php gains an ability.
 */
const LEVEL_DESCRIPTIONS: Record<string, string> = {
  read: __(
    "The assistant can list leads, see which products have the longest waitlists, read system status and diagnose failed notifications. It cannot change anything.",
    "notifybay-waitlist-and-stock-alert-woo",
  ),
  "read+modify": __(
    "Adds editing a lead's status or email address. The assistant still cannot delete anything or email your customers.",
    "notifybay-waitlist-and-stock-alert-woo",
  ),
  full: __(
    "Adds deleting leads permanently, and re-sending back-in-stock emails to real customers. Neither can be undone. Only grant this if you trust the assistant and the account it connects with.",
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
/**
 * The one-time Application Password, held for the lifetime of the PAGE rather
 * than of the component.
 *
 * The docblock on the state below promises it "lives until the page is
 * reloaded", and component state could not keep that promise: Settings.tsx
 * renders this tab as {activeTab === "mcp" && <McpTab/>}, so switching to any
 * other tab unmounts McpTab and discarded the secret. WordPress reveals an
 * application password exactly once, so a user who clicked away to check a
 * setting and came back found placeholders, with no recourse but to revoke the
 * credential and generate another.
 *
 * A module binding lives exactly as long as the page does, which is the
 * lifetime that was described all along. It is never persisted anywhere.
 */
let pageLiveSecret: string | null = null;

export const McpTab: React.FC<McpTabProps> = ({ mcp }) => {
  const [status, setStatus] = useState<McpStatus | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [pendingLevel, setPendingLevel] = useState<string | null>(null);

  /*
   * Held here rather than in McpAppPasswords because the snippets need it: a
   * connection command with "<base64(username:application-password)>" in it is
   * not a command, it is homework.
   *
   * It survives the modal being closed, and lives until the page is reloaded.
   * That is a deliberate second chance -- someone who dismisses the dialog too
   * fast can still read the snippets off the tab -- and it is why nothing here
   * claims the secret is destroyed at the moment the dialog closes.
   */
  const [liveSecret, setLiveSecretState] = useState<string | null>(
    pageLiveSecret,
  );

  /*
   * Mirrors into the module binding above so the value survives this
   * component being unmounted. Everything else about it is unchanged --
   * callers still just call setLiveSecret(secret).
   */
  const setLiveSecret = useCallback((secret: string | null) => {
    pageLiveSecret = secret;
    setLiveSecretState(secret);
  }, []);
  const [showConnection, setShowConnection] = useState(false);
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

  /*
   * Null, not a placeholder set. buildMcpSnippets() now requires a real
   * credential, because every snippet it used to produce without one was
   * unusable -- and unusable is worse than absent, since it reads as the
   * answer. McpClientConfig renders no snippet at all for null.
   */
  const snippets = liveSecret
    ? buildMcpSnippets({
        endpoint: status.endpoint,
        username: mcp.current_user_login,
        offerTlsBypass: mcp.offer_tls_bypass,
        password: liveSecret,
      })
    : null;

  /*
   * A snippet is only as good as the endpoint behind it. While MCP is off the
   * client cannot reach it at all, and nothing else on this screen says so at
   * the point where someone is copying a command they are about to run.
   *
   * "Not found", not "refused": Bootstrap returns before register_rest_route()
   * when the endpoint is disabled, so no route exists. A client reports a 404,
   * and someone told to expect a permissions error goes hunting through user
   * roles for a problem that is a switch on this page.
   */
  const offNotice = !isEnabled ? (
    <p className="notifybay-m-0 notifybay-max-w-3xl notifybay-rounded-md notifybay-border notifybay-border-amber-300 notifybay-bg-amber-50 notifybay-p-2 notifybay-text-sm">
      {__(
        "MCP is currently switched off. Until you turn on Enable MCP above, a client will report the address as not found rather than as a permissions problem.",
        "notifybay-waitlist-and-stock-alert-woo",
      )}
    </p>
  ) : undefined;

  return (
    <div className="notifybay-flex notifybay-flex-col notifybay-gap-6">
      <p className="notifybay-m-0 notifybay-max-w-3xl notifybay-rounded-md notifybay-border notifybay-border-sky-300 notifybay-bg-sky-50 notifybay-p-2 notifybay-text-sm">
        <strong>{__("Beta:", "notifybay-waitlist-and-stock-alert-woo")}</strong>{" "}
        {__(
          "the MCP connection is a beta feature. It may change in future releases, and assistant tools can make mistakes — review what an assistant changes, and give it the lowest access level that does the job.",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
      </p>
      {/*
        The endpoint requires `wpab_mcp_access`, but this screen is gated on
        `manage_options` -- two different capabilities. A site updated in place
        rather than activated never received the first one, so everything below
        reports a healthy, enabled endpoint while every actual MCP request is
        refused with a 403. Without this notice there is nothing anywhere that
        says why.
      */}
      {status.has_access_capability === false && (
        <div className="notifybay-rounded-lg notifybay-border notifybay-border-amber-300 notifybay-bg-amber-50 notifybay-p-4 notifybay-text-sm notifybay-text-amber-900">
          <strong className="notifybay-block notifybay-mb-1">
            {__(
              "Your account cannot use this endpoint yet.",
              "notifybay-waitlist-and-stock-alert-woo",
            )}
          </strong>
          {__(
            "The settings below are correct, but your user is missing the permission the MCP endpoint checks, so every request from an AI assistant will be refused.",
            "notifybay-waitlist-and-stock-alert-woo",
          )}
          {/*
            This used to print `wp cap add administrator wpab_mcp_access` and
            leave it there. For a store owner without shell access that is not a
            fix, it is a referral to somebody else -- so the button does it.
          */}
          <p className="notifybay-mt-3 notifybay-mb-0">
            <button
              type="button"
              className="button button-secondary"
              onClick={() => postTo(mcp.grant_cap_url)}
            >
              {__(
                "Grant access to administrators",
                "notifybay-waitlist-and-stock-alert-woo",
              )}
            </button>
          </p>
        </div>
      )}

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
                <Switch
                  id="mcp_enabled"
                  checked={isEnabled}
                  disabled={isSaving}
                  size="medium"
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
                      onSecretChange={(secret) => {
                        setLiveSecret(secret);
                        setShowConnection(null !== secret);
                      }}
                    />

                    {/*
                      The password is legible in two places for the rest of this
                      page load -- inside the dialog, and in the snippets below.
                      Saying so is the whole point: without it, someone who
                      closed the dialog early has no idea the value is still
                      recoverable, and reloads the page to look for it.
                    */}
                    {liveSecret && (
                      <p className="notifybay-m-0 notifybay-max-w-2xl notifybay-rounded-md notifybay-border notifybay-border-amber-300 notifybay-bg-amber-50 notifybay-p-2 notifybay-text-sm">
                        {__(
                          "The password you just created is still filled into the download and the connection details below. Reloading this page clears it for good — WordPress keeps only a hash and cannot show it again.",
                          "notifybay-waitlist-and-stock-alert-woo",
                        )}
                        {" "}
                        <button
                          type="button"
                          className="notifybay-cursor-pointer notifybay-underline notifybay-bg-transparent notifybay-border-0 notifybay-p-0 notifybay-text-sm"
                          onClick={() => setShowConnection(true)}
                        >
                          {__(
                            "Show the connection details again",
                            "notifybay-waitlist-and-stock-alert-woo",
                          )}
                        </button>
                      </p>
                    )}
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
              "Download the file for Claude Desktop and double-click it. Setup for other clients appears here while a freshly generated password is still on screen.",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            render: () => (
              <McpClientConfig
                snippets={snippets}
                username={mcp.current_user_login}
                bundleUrl={mcp.desktop_bundle_url}
                password={liveSecret}
                notice={offNotice}
              />
            ),
          },
        ]}
      />

      <McpConnectionModal
        isOpen={showConnection && null !== liveSecret}
        password={liveSecret || ""}
        snippets={snippets}
        bundleUrl={mcp.desktop_bundle_url}
        username={mcp.current_user_login}
        mcpEnabled={isEnabled}
        onClose={() => setShowConnection(false)}
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
