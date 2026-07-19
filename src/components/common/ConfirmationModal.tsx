/* eslint-disable */
import React, { useEffect, useRef } from "react";
import Button from "./Button";

interface ConfirmationModalProps {
  isOpen: boolean;
  title: string;
  message: string;
  onConfirm: () => void;
  onCancel: () => void;
  confirmLabel?: string;
  cancelLabel?: string;
  autoFocus?: "confirm" | "cancel";
  classNames?: {
    overlay?: string;
    content?: string;
    title?: string;
    message?: string;
    footer?: string;
    button?: {
      cancelClassName?: string;
      confirmClassName?: string;
      cancelVariant?: "solid" | "outline" | "ghost";
      confirmVariant?: "solid" | "outline" | "ghost";
      cancelColor?: "primary" | "secondary" | "danger";
      confirmColor?: "primary" | "secondary" | "danger";
    };
  };
}
export const ConfirmationModal: React.FC<ConfirmationModalProps> = ({
  isOpen,
  title,
  message,
  onConfirm,
  onCancel,
  confirmLabel = "Confirm",
  cancelLabel = "Cancel",
  autoFocus = "confirm",
  classNames = {},
}) => {
  const confirmRef = useRef<HTMLButtonElement>(null);
  const cancelRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (isOpen) {
      if (autoFocus === "cancel") {
        cancelRef.current?.focus();
      } else {
        confirmRef.current?.focus();
      }
    }
  }, [isOpen, autoFocus]);

  if (!isOpen) {
    return null;
  }

  return (
    <div
      className={`notifybay-fixed notifybay-inset-0 notifybay-z-[60000] notifybay-flex notifybay-items-center notifybay-justify-center notifybay-bg-black/50 notifybay-backdrop-blur-sm notifybay-transition-opacity ${
        classNames.overlay || ""
      }`}
    >
      <div
        className={`notifybay-bg-white notifybay-rounded-lg notifybay-shadow-xl notifybay-p-4 notifybay-max-w-sm notifybay-w-full notifybay-mx-4 notifybay-transform notifybay-transition-all notifybay-scale-100 ${
          classNames.content || ""
        }`}
      >
        <h3
          className={`notifybay-ignore-preflight notifybay-mt-0 notifybay-mb-2  ${
            classNames.title || ""
          }`}
        >
          {title}
        </h3>
        <p
          className={`notifybay-text-gray-600 notifybay-mb-6 notifybay-text-sm notifybay-leading-relaxed ${
            classNames.message || ""
          }`}
        >
          {message}
        </p>
        <div
          className={`notifybay-flex notifybay-justify-end notifybay-gap-3 ${
            classNames.footer || ""
          }`}
        >
          <Button
            ref={cancelRef}
            className={classNames.button?.cancelClassName || ""}
            variant={classNames.button?.cancelVariant || "ghost"}
            color={classNames.button?.cancelColor || "secondary"}
            onClick={onCancel}
          >
            {cancelLabel}
          </Button>
          <Button
            ref={confirmRef}
            className={classNames.button?.confirmClassName || ""}
            variant={classNames.button?.confirmVariant || "solid"}
            color={classNames.button?.confirmColor || "primary"}
            onClick={onConfirm}
          >
            {confirmLabel}
          </Button>
        </div>
      </div>
    </div>
  );
};
