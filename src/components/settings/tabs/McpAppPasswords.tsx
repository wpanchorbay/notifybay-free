import React, { useState, useEffect, useCallback } from "react";
import { __, sprintf } from "@wordpress/i18n";
import apiFetch from "../../../utils/apiFetch";
import { ClassicInput, ClassicButton } from "../../classics";
import { ConfirmationModal } from "../../common/ConfirmationModal";
import { CopyToClipboard } from "../../common/CopyToClipboard";
import { useToast } from "../../../store/toast/use-toast";
import { McpAppPassword } from "../../../utils/types";

interface McpAppPasswordsProps {
  /** Absolute URL of core's `wp/v2/users/me/application-passwords` route. */
  endpoint: string;
  /**
   * Reports the live secret upwards while it is on screen, so the connection
   * snippets can be shown complete instead of asking the reader to base64 a
   * credential by hand. Called with null the moment the form is cleared.
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
 * One honest limitation in the two-step form below. WordPress mints the secret
 * itself, inside the call that stores it -- there is no way to produce a
 * password locally and save it afterwards. So Generate is what creates the
 * credential, and it works from that moment. Save applies any edit to the name
 * and closes the form, after making sure the secret has been copied, because
 * that is the step people lose things at.
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

  // The new credential being set up. `name` starts empty on purpose: a
  // prefilled one gets accepted without being read, and the name is the only
  // thing that tells two rows apart when deciding which to revoke.
  const [name, setName] = useState("");
  const [secret, setSecret] = useState<string | null>(null);
  const [issued, setIssued] = useState<{ uuid: string; name: string } | null>(
    null,
  );
  const [confirming, setConfirming] = useState(false);

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
      const value = response.password.replace(/\s/g, "");

      setSecret(value);
      setIssued({ uuid: response.uuid, name: trimmed });
      onSecretChange(value);
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

  // Reached only through the confirmation, which is the point of it.
  const save = async () => {
    setConfirming(false);
    setIsBusy(true);

    try {
      const trimmed = name.trim();

      // The name is editable after generating, so a change made in between
      // still has to land somewhere.
      if (issued && trimmed && trimmed !== issued.name) {
        await rename(issued.uuid, trimmed);
      }

      setSecret(null);
      setIssued(null);
      setName("");
      onSecretChange(null);
      await load();

      addToast(
        __("Saved.", "notifybay-waitlist-and-stock-alert-woo"),
        "success",
      );
    } catch (error) {
      failed(
        error,
        __(
          "The password works, but the name could not be updated.",
          "notifybay-waitlist-and-stock-alert-woo",
        ),
      );
    } finally {
      setIsBusy(false);
    }
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
      <div className="notifybay-flex notifybay-flex-col notifybay-gap-2 notifybay-max-w-2xl">
        <div className="notifybay-flex notifybay-items-center notifybay-gap-2">
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
        </div>

        <div className="notifybay-flex notifybay-items-center notifybay-gap-2">
          <label
            htmlFor="mcp_app_password_value"
            className="notifybay-w-24 notifybay-text-sm notifybay-shrink-0"
          >
            {__("Password", "notifybay-waitlist-and-stock-alert-woo")}
          </label>

          {/*
            Readonly rather than disabled: WordPress generates the value, so it
            is never typed -- but a disabled field cannot be selected, and
            selecting it by hand is a fair way to copy.
          */}
          <input
            type="text"
            id="mcp_app_password_value"
            className="regular-text notifybay-font-mono"
            value={secret || ""}
            readOnly
            placeholder={__(
              "generated for you",
              "notifybay-waitlist-and-stock-alert-woo",
            )}
          />

          {secret && <CopyToClipboard text={secret} />}
        </div>

        <div className="notifybay-flex notifybay-items-center notifybay-gap-2 notifybay-pl-[6.5rem]">
          <ClassicButton
            variant={secret ? "secondary" : "primary"}
            onClick={generate}
            disabled={isBusy}
          >
            {secret
              ? __(
                  "Generate another",
                  "notifybay-waitlist-and-stock-alert-woo",
                )
              : __(
                  "Generate password",
                  "notifybay-waitlist-and-stock-alert-woo",
                )}
          </ClassicButton>

          {secret && (
            <ClassicButton onClick={() => setConfirming(true)} disabled={isBusy}>
              {__("Save", "notifybay-waitlist-and-stock-alert-woo")}
            </ClassicButton>
          )}
        </div>

        {secret && (
          <p className="notifybay-m-0 notifybay-rounded-md notifybay-border notifybay-border-amber-300 notifybay-bg-amber-50 notifybay-p-2 notifybay-text-sm">
            {__(
              "Copy this password now. It is the only time it can be shown, and saving clears it.",
              "notifybay-waitlist-and-stock-alert-woo",
            )}
          </p>
        )}
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

      <ConfirmationModal
        isOpen={confirming}
        title={__(
          "Have you copied the password?",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        message={__(
          "Saving clears it from the screen, and WordPress cannot show it again — it stores only a hash. If you have not copied it, cancel, copy it, then save. Losing it costs nothing but a new password: revoke this one and generate another.",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        confirmLabel={__(
          "I have copied it — save",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        cancelLabel={__(
          "Not yet",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        /*
         * ConfirmationModal's own prop, not the DOM attribute the a11y rule is
         * aimed at. It focuses the confirm button by default, so without this,
         * opening the dialog and pressing Enter would dismiss the one warning
         * standing between the reader and a lost secret.
         */
        // eslint-disable-next-line jsx-a11y/no-autofocus
        autoFocus="cancel"
        onConfirm={save}
        onCancel={() => setConfirming(false)}
      />
    </div>
  );
};
