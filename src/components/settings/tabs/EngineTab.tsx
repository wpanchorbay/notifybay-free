import React from "react";
import {
  ClassicSettingsTable,
  ClassicCheckbox,
  ClassicInput,
  ClassicSelect,
} from "../../classics";
import { __ } from "@wordpress/i18n";
import { PluginSettings } from "../../../utils/types";

interface EngineTabProps {
  settings: PluginSettings;
  setSettings: (settings: PluginSettings) => void;
}

export const EngineTab: React.FC<EngineTabProps> = ({
  settings,
  setSettings,
}) => {
  return (
    <div className="notifybay-flex notifybay-flex-col">
      {/*
        Fair-Play restock logic is a NotifyBay Pro feature. Pro injects its
        own "Waitlist Automation" section here via the
        `notifybay_settings_extra_sections` filter (activeTab === "engine")
        — see plans/pro-architecture.md.
      */}

      {/* Expiry Logic Section (Moved from Appearance) */}
      <ClassicSettingsTable
        title={__("Waitlist Expiry Logic", "notifybay-waitlist-and-stock-alert-woo")}
        description={__(
          "Define the rules for cleaning up old or abandoned waitlist subscriptions.",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        fields={[
          {
            id: "appearance_waitlistExpiryEnabled",
            label: __("Auto-Expire Leads", "notifybay-waitlist-and-stock-alert-woo"),
            tooltip: __(
              "Automatically remove users from the waitlist after a certain number of days if no restock occurs.",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            render: () => (
              <div className="notifybay-flex notifybay-flex-col notifybay-gap-4">
                <ClassicCheckbox
                  checked={settings.appearance_waitlistExpiryEnabled}
                  onChange={(val) =>
                    setSettings({
                      ...settings,
                      appearance_waitlistExpiryEnabled: val,
                    })
                  }
                  label={__("Expire old subscriptions", "notifybay-waitlist-and-stock-alert-woo")}
                />
                {settings.appearance_waitlistExpiryEnabled && (
                  <>
                    <div className="notifybay-ml-6">
                      <label className="notifybay-block notifybay-text-xs notifybay-font-medium notifybay-text-gray-500 notifybay-mb-1">
                        {__("Default Expiry (Days)", "notifybay-waitlist-and-stock-alert-woo")}
                      </label>
                      <ClassicSelect
                        value={settings.appearance_waitlistExpiryDefault}
                        onChange={(val) =>
                          setSettings({
                            ...settings,
                            appearance_waitlistExpiryDefault: val as string,
                          })
                        }
                        options={settings.appearance_waitlistExpiryOptions
                          .split(",")
                          .map((opt) => ({
                            value: opt.trim(),
                            label: `${opt.trim()} ${__("Days", "notifybay-waitlist-and-stock-alert-woo")}`,
                          }))}
                      />
                    </div>
                    <div className="notifybay-ml-6">
                      <label className="notifybay-block notifybay-text-xs notifybay-font-medium notifybay-text-gray-500 notifybay-mb-1">
                        {__("Frontend Options", "notifybay-waitlist-and-stock-alert-woo")}
                      </label>
                      <ClassicInput
                        value={settings.appearance_waitlistExpiryOptions}
                        onChange={(e) =>
                          setSettings({
                            ...settings,
                            appearance_waitlistExpiryOptions: e.target.value,
                          })
                        }
                        description={__(
                          "Comma-separated list of day options shown to customers.",
                          "notifybay-waitlist-and-stock-alert-woo",
                        )}
                      />
                    </div>
                  </>
                )}
              </div>
            ),
          },
        ]}
      />

      {/*
        Sales Attribution (conversion tracking) is a NotifyBay Pro feature.
        Pro injects its own section here via the
        `notifybay_settings_extra_sections` filter (activeTab === "engine").
      */}

      {/* Stock Control Section */}
      <ClassicSettingsTable
        title={__("Stock Control", "notifybay-waitlist-and-stock-alert-woo")}
        description={__(
          "Fine-tune when restock alerts should be triggered based on physical inventory levels.",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        fields={[
          {
            id: "engine_minStockThreshold",
            label: __("Minimum Restock Threshold", "notifybay-waitlist-and-stock-alert-woo"),
            tooltip: __(
              "Prevents the system from firing mass alerts if only 1 or 2 items are returned or restocked.",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            render: () => (
              <ClassicInput
                type="number"
                value={settings.engine_minStockThreshold.toString()}
                onChange={(e) =>
                  setSettings({
                    ...settings,
                    engine_minStockThreshold: parseInt(e.target.value),
                  })
                }
              />
            ),
          },
        ]}
      />

      {/*
        A premium add-on (NotifyBay Pro) can inject its own section here via the
        `notifybay_settings_extra_sections` filter (activeTab === "engine").
      */}

      {/* Notifications Section */}
      <ClassicSettingsTable
        title={__("Admin Notifications", "notifybay-waitlist-and-stock-alert-woo")}
        description={__(
          "Stay informed about your customers' intent by receiving alerts for new signups.",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        fields={[
          {
            id: "engine_adminAlerts",
            label: __("Owner Alerts", "notifybay-waitlist-and-stock-alert-woo"),
            tooltip: __(
              "Sends a notification to the store administrator whenever a new shopper joins a waitlist.",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            render: () => (
              <ClassicCheckbox
                checked={settings.engine_adminAlerts}
                onChange={(val) =>
                  setSettings({
                    ...settings,
                    engine_adminAlerts: val,
                  })
                }
                label={__("Notify store owner of new signups", "notifybay-waitlist-and-stock-alert-woo")}
              />
            ),
          },
        ]}
      />
    </div>
  );
};
