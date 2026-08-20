import React, { useState, useEffect, useCallback } from "react";
import { __, sprintf } from "@wordpress/i18n";
import apiFetch from "../../../utils/apiFetch";
import { ClassicButton } from "../../classics";
import { CopyToClipboard } from "../../common/CopyToClipboard";
import { useToast } from "../../../store/toast/use-toast";
import { McpAppPassword } from "../../../utils/types";

interface McpAppPasswordsProps {
  /** Absolute URL of core's `wp/v2/users/me/application-passwords` route. */
  endpoint: string;
  /** Base label for the generated credential, so it and the snippet agree. */
  baseName: string;
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
 * @param root0
 * @param root0.endpoint
 * @param root0.suggestedName
 */
export const McpAppPasswords: React.FC<McpAppPasswordsProps> = ({
  endpoint,
  baseName,
}) => {
  const [items, setItems] = useState<McpAppPassword[] | null>(null);
  const [isBusy, setIsBusy] = useState(false);
  const { addToast } = useToast();

  /*
   * Shown once and never again. Core returns the value space-chunked for
   * readability; the spaces are stripped because this string gets pasted into
   * shell commands and JSON config where a space breaks it. WordPress strips
   * whitespace before comparing, so both forms authenticate.
   */
  const [secret, setSecret] = useState<string | null>(null);

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

  /*
   * Core requires a name and offers no default, but it is a label for the
   * reader rather than anything the protocol reads -- so it is derived here
   * instead of being asked for. Core permits two passwords with the same name
   * (verified: both creates return ok), which would leave the reader guessing
   * which row to revoke, so a repeat of the same client is suffixed.
   */
  const nextName = () => {
    const taken = new Set((items || []).map((i) => i.name));
    if (!taken.has(baseName)) {
      return baseName;
    }
    let n = 2;
    while (taken.has(`${baseName} (${n})`)) {
      n++;
    }
    return `${baseName} (${n})`;
  };

  const create = async () => {
    const trimmed = nextName();

    setIsBusy(true);
    try {
      const response = (await apiFetch({
        url: endpoint,
        method: "POST",
        data: { name: trimmed },
      })) as McpAppPassword & { password: string };

      setSecret(response.password.replace(/\s/g, ""));
      await load();
    } catch (error: any) {
      addToast(
        error?.message ||
          __(
            "Could not create the application password.",
            "notifybay-waitlist-and-stock-alert-woo",
          ),
        "error",
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
    } catch (error: any) {
      addToast(
        error?.message ||
          __("Could not revoke it.", "notifybay-waitlist-and-stock-alert-woo"),
        "error",
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
      <div className="notifybay-flex notifybay-items-center notifybay-gap-3">
        <ClassicButton onClick={create} disabled={isBusy}>
          {__("Generate password", "notifybay-waitlist-and-stock-alert-woo")}
        </ClassicButton>
        <span className="notifybay-text-sm notifybay-text-gray-600">
          {sprintf(
            /* translators: %s: the label the new credential will be filed under. */
            __("will be labelled “%s”", "notifybay-waitlist-and-stock-alert-woo"),
            nextName(),
          )}
        </span>
      </div>

      {secret && (
        <div className="notice notice-warning notifybay-p-3 notifybay-m-0">
          <p className="notifybay-mt-0 notifybay-font-semibold">
            {__(
              "Copy it now — this is the only time it can be shown.",
              "notifybay-waitlist-and-stock-alert-woo",
            )}
          </p>
          <div className="notifybay-flex notifybay-items-center notifybay-gap-2">
            <code className="notifybay-text-sm notifybay-break-all">{secret}</code>
            <CopyToClipboard text={secret} />
          </div>
          <ClassicButton variant="secondary" onClick={() => setSecret(null)}>
            {__("Done", "notifybay-waitlist-and-stock-alert-woo")}
          </ClassicButton>
        </div>
      )}

      {null !== items && items.length > 0 && (
        <table className="widefat striped notifybay-w-full">
          <tbody>
            {items.map((item) => (
              <tr key={item.uuid}>
                <td>
                  <strong>{item.name}</strong>
                  <span className="notifybay-block notifybay-text-xs notifybay-text-gray-500">
                    {when(item)}
                  </span>
                </td>
                <td className="notifybay-text-right notifybay-whitespace-nowrap">
                  <ClassicButton
                    variant="link-delete"
                    onClick={() => revoke(item)}
                    disabled={isBusy}
                  >
                    {__("Revoke", "notifybay-waitlist-and-stock-alert-woo")}
                  </ClassicButton>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
};
