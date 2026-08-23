import React, { useState, useEffect, useCallback } from "react";
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
      setItems([]);
    }
  }, [endpoint]);

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

  const commitRename = async () => {
    if (!editing || !editing.name.trim()) {
      setEditing(null);
      return;
    }

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
      setIsBusy(false);
    }
  };

  const revoke = async (item: McpAppPassword) => {
    setIsBusy(true);
    try {
      await apiFetch({ url: `${endpoint}/${item.uuid}`, method: "DELETE" });
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
