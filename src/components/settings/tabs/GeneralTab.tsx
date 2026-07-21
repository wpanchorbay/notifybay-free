import React from "react";
import {
  ClassicSettingsTable,
  ClassicCheckbox,
  ClassicSelect,
} from "../../classics";
import { __ } from "@wordpress/i18n";
import { PluginSettings } from "../../../utils/types";

interface GeneralTabProps {
  settings: PluginSettings;
  setSettings: (settings: PluginSettings) => void;
}

export const GeneralTab: React.FC<GeneralTabProps> = ({
  settings,
  setSettings,
}) => {
  return (
    <ClassicSettingsTable
      title={__("General Logic", "notifybay-waitlist-and-stock-alert-woo")}
      description={__(
        "Configure the core behavior of your revenue recovery system, including wishlist status and verification rules.",
        "notifybay-waitlist-and-stock-alert-woo",
      )}
      fields={[
        {
          id: "general_doubleOptIn",
          label: __("Double Opt-In", "notifybay-waitlist-and-stock-alert-woo"),
          tooltip: __(
            "Requires guest users to verify their email address before their subscription becomes active.",
            "notifybay-waitlist-and-stock-alert-woo",
          ),
          render: () => (
            <ClassicCheckbox
              checked={settings.general_doubleOptIn}
              onChange={(val) =>
                setSettings({
                  ...settings,
                  general_doubleOptIn: val,
                })
              }
              label={__("Require email verification for all leads", "notifybay-waitlist-and-stock-alert-woo")}
              description={__(
                "Highly recommended for GDPR and CAN-SPAM compliance.",
                "notifybay-waitlist-and-stock-alert-woo",
              )}
            />
          ),
        },
        {
          id: "general_backorderWaitlist",
          label: __("Backorder Behavior", "notifybay-waitlist-and-stock-alert-woo"),
          tooltip: __(
            "Choose whether the 'Notify Me' form should appear for products that allow backorders.",
            "notifybay-waitlist-and-stock-alert-woo",
          ),
          render: () => (
            <ClassicSelect
              value={settings.general_backorderWaitlist}
              onChange={(val) =>
                setSettings({
                  ...settings,
                  general_backorderWaitlist: val as string,
                })
              }
              options={[
                {
                  value: "0",
                  label: __("Waitlist only if backorders disabled", "notifybay-waitlist-and-stock-alert-woo"),
                },
                {
                  value: "1",
                  label: __("Show waitlist regardless of backorders", "notifybay-waitlist-and-stock-alert-woo"),
                },
                {
                  value: "2",
                  label: __(
                    "Never show waitlist for backorderable items",
                    "notifybay-waitlist-and-stock-alert-woo",
                  ),
                },
              ]}
              description={__(
                "Select the behavior that matches your store's inventory policy.",
                "notifybay-waitlist-and-stock-alert-woo",
              )}
            />
          ),
        },
      ]}
    />
  );
};
