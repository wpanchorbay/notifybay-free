import React, { useState, useEffect, useCallback } from "react";
import { __, sprintf } from "@wordpress/i18n";
import apiFetch from "../../../utils/apiFetch";
import { ClassicInput, ClassicButton } from "../../classics";
import { CopyToClipboard } from "../../common/CopyToClipboard";
import { useToast } from "../../../store/toast/use-toast";
import { McpAppPassword } from "../../../utils/types";

interface McpAppPasswordsProps {
  /** Absolute URL of core's `wp/v2/users/me/application-passwords` route. */
  endpoint: string;
  /** Seeds the label field, so the credential and the snippet agree. */
  suggestedName: string;
  /**
   * Reports the live secret upwards while it is on screen, so the connection
   * snippets can be shown complete instead of asking the reader to base64 a
   * credential by hand. Called with null the moment it is dismissed.
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
 * @param root0
 * @param root0.endpoint
 * @param root0.suggestedName
 */
export const McpAppPasswords: React.FC<McpAppPasswordsProps> = ({
  endpoint,
  suggestedName,
  onSecretChange,
}) => {
  const [items, setItems] = useState<McpAppPassword[] | null>(null);
  const [name, setName] = useState(suggestedName);
  const [isBusy, setIsBusy] = useState(false);

  /*
   * The label is what tells one credential from another when revoking, so a
   * generated one must not be a label the reader is stuck with. Core exposes
   * PUT on the same route (verified: HTTP 200, name updated), so renaming
   * needs no more than the uuid already in hand.
   */
  const [editing, setEditing] = useState<{ uuid: string; name: string } | null>(
    null,
  );
  const { addToast } = useToast();

  /*
   * Shown once and never again. Core returns the value space-chunked for
   * readability; the spaces are stripped because this string gets pasted into
   * shell commands and JSON config where a space breaks it. WordPress strips
   * whitespace before comparing, so both forms authenticate.
   *
   * The box below is styled with the plugin's own utilities rather than
   * WordPress's `notice` class, which would be invisible here:
   * `#wpcontent:has(#notifybay) .notice { display: none }` in index.scss:639
   * suppresses admin nags on this plugin's screens, and it cannot tell an
   * unwanted nag from the one thing on the page the reader must not miss.
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
   * The field follows the client picker until the reader edits it. Core
   * permits two passwords with the same name (verified: both creates return
   * ok), so nothing stops a duplicate -- but the label is the only thing
   * distinguishing one row from another when revoking, which is exactly why
   * the reader writes it rather than the machine.
   */
  useEffect(() => {
    setName((current) =>
      "" === current || current.startsWith("NotifyBay MCP")
        ? suggestedName
        : current,
    );
  }, [suggestedName]);

  const create = async () => {
    const trimmed = name.trim();
    if (!trimmed) {
      addToast(
        __(
          "Give the password a name first, so you can tell it apart later.",
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

      const issued = response.password.replace(/\s/g, "");
      setSecret(issued);
      onSecretChange(issued);
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

  const rename = async () => {
    if (!editing || !editing.name.trim()) {
      setEditing(null);
      return;
    }

    setIsBusy(true);
    try {
      await apiFetch({
        url: `${endpoint}/${editing.uuid}`,
        method: "PUT",
        data: { name: editing.name.trim() },
      });
      setEditing(null);
      await load();
    } catch (error: any) {
      addToast(
        error?.message ||
          __("Could not rename it.", "notifybay-waitlist-and-stock-alert-woo"),
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
      <div className="notifybay-flex notifybay-items-center notifybay-gap-2">
        <ClassicInput
          id="mcp_app_password_name"
          value={name}
          onChange={(e) => setName(e.target.value)}
          placeholder={__(
            "e.g. NotifyBay MCP",
            "notifybay-waitlist-and-stock-alert-woo",
          )}
          disabled={isBusy}
        />
        <ClassicButton onClick={create} disabled={isBusy}>
          {__("Generate password", "notifybay-waitlist-and-stock-alert-woo")}
        </ClassicButton>
      </div>

      {secret && (
        <div className="notifybay-flex notifybay-flex-col notifybay-gap-2 notifybay-p-3 notifybay-m-0 notifybay-rounded-lg notifybay-border notifybay-border-amber-300 notifybay-bg-amber-50">
          <p className="notifybay-mt-0 notifybay-font-semibold">
            {__(
              "Copy it now — this is the only time it can be shown.",
              "notifybay-waitlist-and-stock-alert-woo",
            )}
          </p>
          <p className="notifybay-m-0 notifybay-text-sm">
            {__(
              "The connection snippets below now include it, ready to paste. They go back to placeholders once you press Done.",
              "notifybay-waitlist-and-stock-alert-woo",
            )}
          </p>
          <div className="notifybay-flex notifybay-items-center notifybay-gap-2 notifybay-max-w-2xl">
            <code className="notifybay-flex-1 notifybay-rounded-md notifybay-border notifybay-border-amber-300 notifybay-bg-white notifybay-px-3 notifybay-py-2 notifybay-text-base notifybay-tracking-wide notifybay-break-all">
              {secret}
            </code>
            <CopyToClipboard text={secret} />
          </div>
          <ClassicButton
            variant="secondary"
            onClick={() => {
              setSecret(null);
              onSecretChange(null);
            }}
          >
            {__("Done", "notifybay-waitlist-and-stock-alert-woo")}
          </ClassicButton>
        </div>
      )}

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
                      onBlur={rename}
                      onKeyDown={(e) => {
                        if ("Enter" === e.key) {
                          rename();
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
