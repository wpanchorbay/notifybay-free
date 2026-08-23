import React, { useState, useEffect, useRef } from "react";
import { __ } from "@wordpress/i18n";
import CustomModal from "../../common/CustomModal";
import { CopyToClipboard } from "../../common/CopyToClipboard";
import { ClassicButton } from "../../classics";
import { McpClientConfig } from "./McpClientConfig";
import { McpSnippet } from "./mcpSnippets";

interface McpConnectionModalProps {
  isOpen: boolean;
  /** The just-minted secret. Shown in full; WordPress keeps only a hash. */
  password: string;
  /** Already filled with `password`, one entry per client. */
  snippets: McpSnippet[];
  username: string;
  /** False means the endpoint will refuse these snippets until it is enabled. */
  mcpEnabled: boolean;
  onClose: () => void;
}

/**
 * The one-time display of a new credential, and everything needed to use it.
 *
 * Why a modal rather than a panel on the tab: the credential exists for exactly
 * one purpose, and the moment it is created is the only moment it can be read.
 * A modal is the one layout that can say "deal with this now" without competing
 * with the settings behind it.
 *
 * Three ways out of a CustomModal normally exist -- the header cross, Escape,
 * and a backdrop click. Two of them are switched off here and the third is
 * guarded, because all three are reflexes and this dialog is the only place the
 * password is ever legible. The guard fires once, and only when nothing has been
 * copied yet: a confirmation that appears after the reader has demonstrably done
 * the thing it is asking about teaches them to dismiss confirmations unread.
 *
 * The wording is careful about what dismissal actually costs. Closing this does
 * not destroy the secret -- the tab behind it keeps the snippets filled for the
 * rest of the page load. Reloading is what destroys it. Saying "you cannot see
 * this again" at the moment of closing would be false, and a warning the reader
 * can catch out is a warning they stop believing.
 *
 * @param root0
 * @param root0.isOpen
 * @param root0.password
 * @param root0.snippets
 * @param root0.username
 * @param root0.mcpEnabled
 * @param root0.onClose
 */
export const McpConnectionModal: React.FC<McpConnectionModalProps> = ({
  isOpen,
  password,
  snippets,
  username,
  mcpEnabled,
  onClose,
}) => {
  const [copied, setCopied] = useState(false);
  const [asking, setAsking] = useState(false);
  const passwordRef = useRef<HTMLInputElement>(null);

  /*
   * CustomModal renders null while closed, but this component stays mounted, so
   * both flags survive a close. They are reset on different signals, and the
   * difference matters.
   *
   * `copied` belongs to the secret: a copy of the previous password says nothing
   * about this one, so generating a second credential must arm the guard again.
   * Without this, the second Done closes on the first press, already satisfied.
   *
   * `asking` belongs to the dialog: a half-finished prompt left over from an
   * earlier visit would close the modal on a single press, silently skipping the
   * warning it exists to show.
   */
  useEffect(() => {
    setCopied(false);
  }, [password]);

  useEffect(() => {
    if (isOpen) {
      setAsking(false);
    }
  }, [isOpen]);

  /*
   * Escape is switched off here, and CustomModal does not move focus when it
   * opens. Together that strands a keyboard user: focus stays on the Generate
   * button behind the overlay, and the usual way out of a dialog does nothing.
   *
   * Focusing the password and selecting it fixes both halves at once -- the
   * dialog now has focus, and the value is already selected, so the keyboard
   * route to copying it is the two keystrokes everybody knows.
   */
  useEffect(() => {
    if (isOpen) {
      passwordRef.current?.focus();
      passwordRef.current?.select();
    }
  }, [isOpen, password]);

  const markCopied = () => setCopied(true);

  const attemptClose = () => {
    if (copied || asking) {
      onClose();
      return;
    }
    setAsking(true);
  };

  const offNotice = !mcpEnabled ? (
    <p className="notifybay-m-0 notifybay-rounded-md notifybay-border notifybay-border-amber-300 notifybay-bg-amber-50 notifybay-p-2 notifybay-text-sm">
      {__(
        "MCP is currently switched off, so this connection will be refused until you turn on Enable MCP. The credential itself is fine — save it now and enable the endpoint when you are ready.",
        "notifybay-waitlist-and-stock-alert-woo",
      )}
    </p>
  ) : undefined;

  return (
    <CustomModal
      isOpen={isOpen}
      onClose={attemptClose}
      title={__(
        "Your connection details",
        "notifybay-waitlist-and-stock-alert-woo",
      )}
      maxWidth="notifybay-max-w-3xl"
      /*
       * Both off deliberately. A backdrop click or a reflexive Escape would
       * dismiss the only legible copy of a credential the reader has not
       * necessarily saved yet, and neither gesture carries any intent worth
       * honouring here.
       */
      closeOnOutsideClick={false}
      closeOnEscape={false}
      footer={
        <div className="notifybay-flex notifybay-items-center notifybay-gap-3">
          {asking && (
            <span className="notifybay-text-sm notifybay-text-amber-800">
              {__(
                "You have not copied anything yet. Press Done again to close.",
                "notifybay-waitlist-and-stock-alert-woo",
              )}
            </span>
          )}
          <ClassicButton onClick={attemptClose}>
            {__("Done", "notifybay-waitlist-and-stock-alert-woo")}
          </ClassicButton>
        </div>
      }
    >
      <div className="notifybay-flex notifybay-flex-col notifybay-gap-4">
        <p className="notifybay-m-0 notifybay-rounded-md notifybay-border notifybay-border-amber-300 notifybay-bg-amber-50 notifybay-p-3 notifybay-text-sm">
          {__(
            "The credential is already active — there is nothing left to save. This is the only time it can be read: WordPress stores just a hash of it. It stays on this screen and on the AI Access tab until you reload the page, and is gone for good after that.",
            "notifybay-waitlist-and-stock-alert-woo",
          )}
        </p>

        <div className="notifybay-flex notifybay-items-center notifybay-gap-2">
          <label
            htmlFor="mcp_modal_password"
            className="notifybay-w-24 notifybay-text-sm notifybay-shrink-0"
          >
            {__("Password", "notifybay-waitlist-and-stock-alert-woo")}
          </label>
          {/*
            Readonly rather than disabled: nobody types this value, but a
            disabled field cannot be selected, and selecting it by hand is a
            fair way to copy it.
          */}
          <input
            ref={passwordRef}
            type="text"
            id="mcp_modal_password"
            className="regular-text notifybay-font-mono"
            value={password}
            readOnly
          />
          <CopyToClipboard text={password} onCopy={markCopied} />
        </div>

        <McpClientConfig
          snippets={snippets}
          username={username}
          notice={offNotice}
          onCopy={markCopied}
        />
      </div>
    </CustomModal>
  );
};
