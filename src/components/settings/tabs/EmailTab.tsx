import React from "react";
import { ClassicSettingsTable, ClassicInput } from "../../classics";
import { __ } from "@wordpress/i18n";
import { PluginSettings } from "../../../utils/types";

interface EmailTabProps {
  settings: PluginSettings;
  setSettings: (settings: PluginSettings) => void;
}

export const EmailTab: React.FC<EmailTabProps> = ({
  settings,
  setSettings,
}) => {
  const wcEmailsUrl = "admin.php?page=wc-settings&tab=email";

  return (
    <div className="notifybay-flex notifybay-flex-col">
      {/* Email Sender Identity */}
      <ClassicSettingsTable
        title={__("Email Identity", "notifybay")}
        description={__(
          "Configure the global 'From' name and email address for all notifications sent by this plugin.",
          "notifybay",
        )}
        fields={[
          {
            id: "email_fromName",
            label: __("From Name", "notifybay"),
            tooltip: __(
              "The name that will appear in the customer's inbox.",
              "notifybay",
            ),
            render: () => (
              <ClassicInput
                value={settings.email_fromName}
                onChange={(e) =>
                  setSettings({
                    ...settings,
                    email_fromName: e.target.value,
                  })
                }
                placeholder={__("e.g. My Store Team", "notifybay")}
              />
            ),
          },
          {
            id: "email_fromEmail",
            label: __("From Email", "notifybay"),
            tooltip: __(
              "The email address used to send notifications.",
              "notifybay",
            ),
            render: () => (
              <ClassicInput
                type="email"
                value={settings.email_fromEmail}
                onChange={(e) =>
                  setSettings({
                    ...settings,
                    email_fromEmail: e.target.value,
                  })
                }
                placeholder={__("e.g. sales@mystore.com", "notifybay")}
              />
            ),
          },
        ]}
      />

      {/*
        Email content (subject, heading, body, enable/disable, preview & test
        send) for every NotifyBay email — restock, verification, and the Pro
        price-drop / hurry alerts — is now managed as native WooCommerce emails
        under WooCommerce → Settings → Emails. See app/Emails/.
      */}
      <div
        className="notifybay-rounded-[8px] notifybay-border notifybay-border-gray-200 notifybay-bg-gray-50 notifybay-p-[16px] notifybay-mt-[16px]"
        style={{ maxWidth: 720 }}
      >
        <h3 className="notifybay-font-[600] notifybay-mb-[8px]">
          {__("Email content & templates", "notifybay")}
        </h3>
        <p className="notifybay-text-gray-600 notifybay-mb-[12px]">
          {__(
            "Subject lines, headings, body content, enable/disable, and test sends for each email are now managed as native WooCommerce emails.",
            "notifybay",
          )}
        </p>
        <a href={wcEmailsUrl} className="button button-secondary">
          {__("Open WooCommerce → Emails", "notifybay")}
        </a>
      </div>
    </div>
  );
};
