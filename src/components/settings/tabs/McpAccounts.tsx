import React, { useState, useEffect, useCallback } from "react";
import { __, sprintf } from "@wordpress/i18n";
import apiFetch from "../../../utils/apiFetch";
import { ClassicSettingsTable, ClassicInput, ClassicButton } from "../../classics";
import { ConfirmationModal } from "../../common/ConfirmationModal";
import { CopyToClipboard } from "../../common/CopyToClipboard";
import { useToast } from "../../../store/toast/use-toast";
import { McpAccount, McpLocalize } from "../../../utils/types";

interface McpAccountsProps {
  mcp: McpLocalize;
}

/**
 * Which WordPress accounts may reach the MCP endpoint, and how to make one.
 *
 * This section exists because of a gap rather than a preference. The kit gates
 * its endpoint on the `wpab_mcp_access` capability, and the only thing that
 * grants it is the kit's activation hook, which adds it to the administrator
 * role -- so by default the only account that can connect is an administrator,
 * and an administrator can POST to the kit's own settings route and raise its
 * access level to full. The ladder is decorative until a narrower account
 * exists, and until now there was no way to make one without wp-cli.
 *
 * Fluent Support, which is the closest comparable implementation, stops at
 * deep-linking to the profile screen. It can afford to, because it reuses its
 * own agent permissions instead of a dedicated capability.
 *
 * @param root0
 * @param root0.mcp
 */
export const McpAccounts: React.FC<McpAccountsProps> = ({ mcp }) => {
  const [accounts, setAccounts] = useState<McpAccount[] | null>(null);
  const [isBusy, setIsBusy] = useState(false);
  const [login, setLogin] = useState("");
  const [pendingRevoke, setPendingRevoke] = useState<McpAccount | null>(null);

  /*
   * The one and only copy of a freshly issued secret. It is never persisted,
   * never re-fetchable, and deliberately not cleared automatically -- losing it
   * costs a rotation, so the reader dismisses it themselves.
   */
  const [secret, setSecret] = useState<{ login: string; password: string } | null>(null);
  const { addToast } = useToast();

  const endpoint = `${mcp.admin_rest_url}/mcp/accounts`;

  const load = useCallback(async () => {
    try {
      const response = (await apiFetch({ url: endpoint })) as {
        accounts: McpAccount[];
      };
      setAccounts(response.accounts || []);
    } catch (error) {
      addToast(
        __(
          "Could not load the list of connected accounts.",
          "notifybay-waitlist-and-stock-alert-woo",
        ),
        "error",
      );
      setAccounts([]);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [endpoint]);

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Surface the server's own message; these refusals are explanatory by design.
  const failed = (error: any, fallback: string) => {
    addToast(error?.message || fallback, "error");
  };

  const create = async () => {
    if (!login.trim()) {
      addToast(
        __("Choose a username first.", "notifybay-waitlist-and-stock-alert-woo"),
        "error",
      );
      return;
    }

    setIsBusy(true);
    try {
      const response = (await apiFetch({
        url: endpoint,
        method: "POST",
        data: { user_login: login.trim() },
      })) as { user_login: string; password: string };

      setSecret({ login: response.user_login, password: response.password });
      setLogin("");
      await load();
    } catch (error) {
      failed(
        error,
        __(
          "Could not create the account.",
          "notifybay-waitlist-and-stock-alert-woo",
        ),
      );
    } finally {
      setIsBusy(false);
    }
  };

  const rotate = async (account: McpAccount) => {
    setIsBusy(true);
    try {
      const response = (await apiFetch({
        url: `${endpoint}/rotate`,
        method: "POST",
        data: { user_id: account.user_id },
      })) as { user_login: string; password: string };

      setSecret({ login: response.user_login, password: response.password });
      await load();
    } catch (error) {
      failed(
        error,
        __(
          "Could not issue a new password.",
          "notifybay-waitlist-and-stock-alert-woo",
        ),
      );
    } finally {
      setIsBusy(false);
    }
  };

  const revoke = async (account: McpAccount) => {
    setIsBusy(true);
    try {
      await apiFetch({
        url: `${endpoint}/revoke`,
        method: "POST",
        data: { user_id: account.user_id },
      });
      addToast(
        __(
          "Access revoked. The account still exists but can no longer connect.",
          "notifybay-waitlist-and-stock-alert-woo",
        ),
        "success",
      );
      await load();
    } catch (error) {
      failed(
        error,
        __(
          "Could not revoke access.",
          "notifybay-waitlist-and-stock-alert-woo",
        ),
      );
    } finally {
      setIsBusy(false);
    }
  };

  const lastUsed = (account: McpAccount) => {
    if (!account.app_passwords.length) {
      return __("no password issued here", "notifybay-waitlist-and-stock-alert-woo");
    }
    const used = account.app_passwords.map((p) => p.last_used).filter(Boolean);
    if (!used.length) {
      return __("never used", "notifybay-waitlist-and-stock-alert-woo");
    }
    return sprintf(
      /* translators: %s: date the credential was last used. */
      __("last used %s", "notifybay-waitlist-and-stock-alert-woo"),
      used[0] as string,
    );
  };

  return (
    <>
      <ClassicSettingsTable
        title={__(
          "Connected accounts",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        description={__(
          "An assistant connects as an ordinary WordPress account. Give it its own, with permission to use the assistant tools and nothing else — not an administrator account, which could change its own access level.",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        fields={[
          {
            id: "mcp_accounts_list",
            label: __(
              "Can connect now",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            render: () => {
              if (null === accounts) {
                return (
                  <p className="notifybay-m-0">
                    {__("Loading…", "notifybay-waitlist-and-stock-alert-woo")}
                  </p>
                );
              }

              if (!accounts.length) {
                return (
                  <p className="notifybay-m-0 notifybay-text-sm">
                    {__(
                      "No account can reach the endpoint yet.",
                      "notifybay-waitlist-and-stock-alert-woo",
                    )}
                  </p>
                );
              }

              return (
                <table className="widefat striped notifybay-w-full">
                  <tbody>
                    {accounts.map((account) => (
                      <tr key={account.user_id}>
                        <td>
                          <strong>{account.user_login}</strong>
                          <span className="notifybay-block notifybay-text-xs notifybay-text-gray-500">
                            {account.roles.join(", ")} — {lastUsed(account)}
                          </span>
                          {account.can_raise_own_level && (
                            <span className="notifybay-block notifybay-text-xs notifybay-text-red-700">
                              {__(
                                "Administrator — an assistant connecting as this account could raise its own access level. Use a dedicated account instead.",
                                "notifybay-waitlist-and-stock-alert-woo",
                              )}
                            </span>
                          )}
                        </td>
                        <td className="notifybay-text-right notifybay-whitespace-nowrap">
                          {account.granted_here ? (
                            <span className="notifybay-inline-flex notifybay-gap-2">
                              <ClassicButton
                                variant="secondary"
                                onClick={() => rotate(account)}
                                disabled={isBusy}
                              >
                                {__(
                                  "New password",
                                  "notifybay-waitlist-and-stock-alert-woo",
                                )}
                              </ClassicButton>
                              <ClassicButton
                                variant="link-delete"
                                onClick={() => setPendingRevoke(account)}
                                disabled={isBusy}
                              >
                                {__(
                                  "Revoke",
                                  "notifybay-waitlist-and-stock-alert-woo",
                                )}
                              </ClassicButton>
                            </span>
                          ) : (
                            /*
                             * The capability comes from the account's role, not
                             * from this screen. Removing it for one user is not
                             * what "revoke" would mean, so the screen does not
                             * offer to.
                             */
                            <span className="notifybay-text-xs notifybay-text-gray-500">
                              {__(
                                "granted by role",
                                "notifybay-waitlist-and-stock-alert-woo",
                              )}
                            </span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              );
            },
          },
          {
            id: "mcp_create_account",
            label: __(
              "Create an account",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            tooltip: __(
              "Creates a subscriber that may use the assistant tools and nothing else. It cannot sign in to the dashboard.",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            render: () =>
              mcp.app_passwords_available ? (
                <div className="notifybay-flex notifybay-flex-col notifybay-gap-2">
                  <div className="notifybay-flex notifybay-items-center notifybay-gap-2">
                    <ClassicInput
                      id="mcp_new_login"
                      value={login}
                      onChange={(e) => setLogin(e.target.value)}
                      placeholder={__(
                        "e.g. notifybay-assistant",
                        "notifybay-waitlist-and-stock-alert-woo",
                      )}
                      disabled={isBusy}
                    />
                    <ClassicButton onClick={create} disabled={isBusy}>
                      {__(
                        "Create account",
                        "notifybay-waitlist-and-stock-alert-woo",
                      )}
                    </ClassicButton>
                  </div>
                  <p className="description notifybay-m-0">
                    {__(
                      "It gets the Subscriber role plus permission to use the assistant tools. It is never given permission to manage the site, and cannot delete leads even at Full access.",
                      "notifybay-waitlist-and-stock-alert-woo",
                    )}
                  </p>
                </div>
              ) : (
                <p className="notifybay-m-0 notifybay-text-sm notifybay-text-red-700">
                  {__(
                    "Application Passwords are unavailable on this site, so a new account would have no way to sign in. Serve the site over https first.",
                    "notifybay-waitlist-and-stock-alert-woo",
                  )}
                </p>
              ),
          },
        ]}
      />

      {/*
        Shown once. There is no route that can return this value again, by
        design, so the copy says what the recovery path actually is.
      */}
      {secret && (
        <div className="notice notice-warning notifybay-p-4 notifybay-m-0">
          <p className="notifybay-mt-0 notifybay-font-semibold">
            {sprintf(
              /* translators: %s: the account's username. */
              __(
                "Password for %s — copy it now.",
                "notifybay-waitlist-and-stock-alert-woo",
              ),
              secret.login,
            )}
          </p>
          <div className="notifybay-flex notifybay-items-center notifybay-gap-2">
            <code className="notifybay-text-sm notifybay-break-all">
              {secret.password}
            </code>
            <CopyToClipboard text={secret.password} />
          </div>
          <p className="notifybay-mb-0 notifybay-text-sm">
            {__(
              "This is the only time it can be shown. If you lose it, use “New password” to issue another — which immediately stops the old one working.",
              "notifybay-waitlist-and-stock-alert-woo",
            )}
          </p>
          <ClassicButton variant="secondary" onClick={() => setSecret(null)}>
            {__("Done", "notifybay-waitlist-and-stock-alert-woo")}
          </ClassicButton>
        </div>
      )}

      <ConfirmationModal
        isOpen={null !== pendingRevoke}
        title={__(
          "Revoke this account's access?",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        message={__(
          "Its password stops working immediately and the assistant will be disconnected. The WordPress account itself is kept, so nothing attributed to it is lost.",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        confirmLabel={__("Revoke", "notifybay-waitlist-and-stock-alert-woo")}
        cancelLabel={__("Cancel", "notifybay-waitlist-and-stock-alert-woo")}
        // eslint-disable-next-line jsx-a11y/no-autofocus
        autoFocus="cancel"
        classNames={{ button: { confirmColor: "danger" } }}
        onConfirm={() => {
          const account = pendingRevoke;
          setPendingRevoke(null);
          if (account) {
            revoke(account);
          }
        }}
        onCancel={() => setPendingRevoke(null)}
      />
    </>
  );
};
