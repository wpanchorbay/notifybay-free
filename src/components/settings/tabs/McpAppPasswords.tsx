import React, { useState, useEffect, useCallback, useRef } from "react";
import { __, sprintf } from "@wordpress/i18n";
import apiFetch from "../../../utils/apiFetch";
import { ClassicInput, ClassicButton } from "../../classics";
import { useToast } from "../../../store/toast/use-toast";
import { McpAppPassword } from "../../../utils/types";

interface McpAppPasswordsProps {
  /** Absolute URL of core's `wp/v2/users/me/application-passwords` route. */
  endpoint: string;
  /**
   * Hands the new secret upward the moment it exists. The parent owns what
   * happens next -- it opens the one-time dialog and fills the connection
   * snippets, neither of which this component can see.
   */
  onSecretChange: (secret: string | null) => void;
}

/**
 * Issue and revoke the credential an assistant connects with, without leaving
 * this screen.
 *
 * There is no plugin route behind any of this. WordPress has shipped
 * `wp/v2/users/me/application-passwords` since 5.6, and it already enforces
 * everything a plugin route would have to re-implement: `create_app_password`
 * maps to `edit_user`, and the controller refuses when
 * wp_is_application_passwords_available_for_user() is false. Adding our own
 * endpoint would mean a second, worse copy of that.
 *
 * `me` is not a convenience here, it is the security boundary -- there is no
 * user_id in any request this component makes, so there is no target to
 * tamper with.
 *
 * The form is a name and one button, and that is the whole of it. WordPress
 * mints the secret inside the call that stores it, so the credential exists and
 * works from that moment -- there is no later step to commit, and this form
 * briefly had a Save button that could only ever have renamed something already
 * created. Nor does the secret appear here: it goes to the parent, which shows
 * it once alongside the connection snippets it belongs with. A password on its
 * own is not what anyone came for.
 *
 * @param root0
 * @param root0.endpoint
 * @param root0.onSecretChange
 */
/*
 * Which credential the on-screen secret belongs to.
 *
 * A module binding, not a ref, because it has to live exactly as long as the
 * secret it guards -- and that secret is itself a module binding in McpTab
 * (pageLiveSecret), deliberately, so it survives this component unmounting.
 * Settings.tsx renders the tab as {activeTab === "mcp" && <McpTab/>}, so
 * switching settings tabs and back unmounts and remounts everything here.
 *
 * A ref was wrong for precisely that reason: it reset on the remount while the
 * secret did not, so after a tab switch revoking the live credential no longer
 * recognised it as live. The snippets went on showing a dead password and the
 * Claude Desktop download went on baking it into the bundle -- an install that
 * 401s on first use, which is the failure this check exists to prevent.
 */
let pageLiveUuid: string | null = null;

function setLiveUuid(uuid: string | null) {
  pageLiveUuid = uuid;
}

function getLiveUuid() {
  return pageLiveUuid;
}

export const McpAppPasswords: React.FC<McpAppPasswordsProps> = ({
  endpoint,
  onSecretChange,
}) => {
  const [items, setItems] = useState<McpAppPassword[] | null>(null);


  const [isBusy, setIsBusy] = useState(false);
  const { addToast } = useToast();

  // `name` starts empty on purpose: a prefilled one gets accepted without being
  // read, and the name is the only thing that tells two rows apart when
  // deciding which to revoke.
  const [name, setName] = useState("");

  /*
   * Renaming an existing row. Core exposes PUT on the same route (verified:
   * HTTP 200, name updated), so it needs no more than the uuid already in hand.
   */
  const [editing, setEditing] = useState<{ uuid: string; name: string } | null>(
    null,
  );

  const load = useCallback(async () => {
    try {
      const response = (await apiFetch({ url: endpoint })) as McpAppPassword[];
      setItems(response || []);
    } catch (error) {
      /*
       * An empty list and a failed request looked identical here -- this was
       * the only failure path in this component that raised nothing. A 403 or
       * a network error rendered as "no credentials", so the user generated a
       * second one and could neither see nor revoke the first from this
       * screen.
       */
      addToast(
        error instanceof Error && error.message
          ? error.message
          : __(
              "Could not load your connection credentials.",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
        "error",
      );
      setItems([]);
    }
  }, [endpoint, addToast]);

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const failed = (error: any, fallback: string) => {
    addToast(error?.message || fallback, "error");
  };

  const generate = async () => {
    const trimmed = name.trim();

    if (!trimmed) {
      addToast(
        __(
          "Name it first, so you can tell it apart from other credentials later.",
          "notifybay-waitlist-and-stock-alert-woo",
        ),
        "error",
      );
      return;
    }

    setIsBusy(true);
    try {
      const response = (await apiFetch({
        url: endpoint,
        method: "POST",
        data: { name: trimmed },
      })) as McpAppPassword & { password: string };

      /*
       * Core returns the value space-chunked for readability. The spaces are
       * stripped because this gets pasted into shell commands and JSON config,
       * where one breaks it. WordPress strips whitespace before comparing, so
       * both forms authenticate.
       */
      setLiveUuid(response.uuid);
      onSecretChange(response.password.replace(/\s/g, ""));
      setName("");
      await load();
    } catch (error) {
      failed(
        error,
        __(
          "Could not generate the password.",
          "notifybay-waitlist-and-stock-alert-woo",
        ),
      );
    } finally {
      setIsBusy(false);
    }
  };

  const rename = async (uuid: string, next: string) => {
    await apiFetch({
      url: `${endpoint}/${uuid}`,
      method: "PUT",
      data: { name: next },
    });
  };

  /*
   * `editing` is state, so it is not cleared until after the await below --
   * which leaves the guard open for the whole round-trip. Enter fires
   * commitRename, and the blur that follows (from clicking away, or from the
   * input being disabled) fires it again with `editing` still set, sending a
   * second identical PUT whose `finally` then races the first on isBusy.
   * A ref settles synchronously, so it closes the window that state cannot.
   * Clearing `editing` early instead would throw away the user's text if the
   * request fails.
   */
  const renameInFlight = useRef(false);

  const commitRename = async () => {
    if (!editing || !editing.name.trim()) {
      setEditing(null);
      return;
    }

    if (renameInFlight.current) {
      return;
    }
    renameInFlight.current = true;

    setIsBusy(true);
    try {
      await rename(editing.uuid, editing.name.trim());
      setEditing(null);
      await load();
    } catch (error) {
      failed(
        error,
        __("Could not rename it.", "notifybay-waitlist-and-stock-alert-woo"),
      );
    } finally {
      renameInFlight.current = false;
      setIsBusy(false);
    }
  };

  const revoke = async (item: McpAppPassword) => {
    setIsBusy(true);
    try {
      await apiFetch({ url: `${endpoint}/${item.uuid}`, method: "DELETE" });

      /*
       * Revoking the credential that is still filled into the screen has to
       * clear it, or everything below goes on offering a password that now
       * 401s: the snippets still show it, and the Claude Desktop download still
       * bakes it into the bundle. The secret outlives this component -- McpTab
       * holds it in a module binding so it survives a tab switch -- so nothing
       * else will drop it before the page reloads.
       */
      if (getLiveUuid() === item.uuid) {
        setLiveUuid(null);
        onSecretChange(null);
      }

      addToast(
        __(
          "Revoked. Any client still using it will get a 401 on its next request.",
          "notifybay-waitlist-and-stock-alert-woo",
        ),
        "success",
      );
      await load();
    } catch (error) {
      failed(
        error,
        __("Could not revoke it.", "notifybay-waitlist-and-stock-alert-woo"),
      );
    } finally {
      setIsBusy(false);
    }
  };

  const when = (item: McpAppPassword) =>
    item.last_used
      ? sprintf(
          /* translators: %s: date the credential was last used. */
          __("last used %s", "notifybay-waitlist-and-stock-alert-woo"),
          item.last_used.slice(0, 10),
        )
      : __("never used", "notifybay-waitlist-and-stock-alert-woo");

  return (
    <div className="notifybay-flex notifybay-flex-col notifybay-gap-3">
      <div className="notifybay-flex notifybay-flex-wrap notifybay-items-center notifybay-gap-2 notifybay-max-w-2xl">
        <label
          htmlFor="mcp_app_password_name"
          className="notifybay-w-24 notifybay-text-sm notifybay-shrink-0"
        >
          {__("Name", "notifybay-waitlist-and-stock-alert-woo")}
        </label>
        <ClassicInput
          id="mcp_app_password_name"
          value={name}
          onChange={(e) => setName(e.target.value)}
          onKeyDown={(e) => {
            // Same WooCommerce <form id="mainform"> trap as the rename field
            // below: without this, Enter here reloads the settings page instead
            // of generating the password the user was clearly asking for.
            if ("Enter" === e.key) {
              e.preventDefault();
              if (!isBusy) {
                generate();
              }
            }
          }}
          placeholder={__(
            "e.g. Claude on my laptop",
            "notifybay-waitlist-and-stock-alert-woo",
          )}
          disabled={isBusy}
        />
        <ClassicButton onClick={generate} disabled={isBusy}>
          {__("Generate password", "notifybay-waitlist-and-stock-alert-woo")}
        </ClassicButton>
      </div>

      {null !== items && items.length > 0 && (
        <table className="widefat striped notifybay-w-full notifybay-max-w-2xl notifybay-rounded-lg notifybay-overflow-hidden">
          <tbody>
            {items.map((item) => (
              <tr key={item.uuid}>
                <td>
                  {editing && editing.uuid === item.uuid ? (
                    <ClassicInput
                      id={`mcp_rename_${item.uuid}`}
                      value={editing.name}
                      onChange={(e) =>
                        setEditing({ uuid: item.uuid, name: e.target.value })
                      }
                      onBlur={commitRename}
                      onKeyDown={(e) => {
                        if ("Enter" === e.key) {
                          // This panel renders inside WooCommerce's
                          // <form id="mainform">, so a bare Enter submits the
                          // whole settings form and reloads the page. WC ships
                          // its save button disabled to prevent exactly that,
                          // but its own MutationObserver on #mainform re-enables
                          // the button when React mounts, so the guard is gone
                          // by the time anyone types here.
                          e.preventDefault();
                          commitRename();
                        }
                        if ("Escape" === e.key) {
                          setEditing(null);
                        }
                      }}
                      disabled={isBusy}
                    />
                  ) : (
                    <strong>{item.name}</strong>
                  )}
                  <span className="notifybay-mt-0.5 notifybay-block notifybay-text-xs notifybay-text-gray-500">
                    {when(item)}
                  </span>
                </td>
                <td className="notifybay-text-right notifybay-whitespace-nowrap">
                  <span className="notifybay-inline-flex notifybay-gap-2">
                    <ClassicButton
                      variant="secondary"
                      onClick={() =>
                        setEditing({ uuid: item.uuid, name: item.name })
                      }
                      disabled={isBusy || null !== editing}
                    >
                      {__("Rename", "notifybay-waitlist-and-stock-alert-woo")}
                    </ClassicButton>
                    <ClassicButton
                      variant="link-delete"
                      onClick={() => revoke(item)}
                      disabled={isBusy}
                    >
                      {__("Revoke", "notifybay-waitlist-and-stock-alert-woo")}
                    </ClassicButton>
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
};
