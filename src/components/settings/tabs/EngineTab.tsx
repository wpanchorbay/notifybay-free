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
        title={__("Waitlist Expiry Logic", "notifybay")}
        description={__(
          "Define the rules for cleaning up old or abandoned waitlist subscriptions.",
          "notifybay",
        )}
        fields={[
          {
            id: "appearance_waitlistExpiryEnabled",
            label: __("Auto-Expire Leads", "notifybay"),
            tooltip: __(
              "Automatically remove users from the waitlist after a certain number of days if no restock occurs.",
              "notifybay",
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
                  label={__("Expire old subscriptions", "notifybay")}
                />
                {settings.appearance_waitlistExpiryEnabled && (
                  <>
                    <div className="notifybay-ml-6">
                      <label className="notifybay-block notifybay-text-xs notifybay-font-medium notifybay-text-gray-500 notifybay-mb-1">
                        {__("Default Expiry (Days)", "notifybay")}
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
                            label: `${opt.trim()} ${__("Days", "notifybay")}`,
                          }))}
                      />
                    </div>
                    <div className="notifybay-ml-6">
                      <label className="notifybay-block notifybay-text-xs notifybay-font-medium notifybay-text-gray-500 notifybay-mb-1">
                        {__("Frontend Options", "notifybay")}
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
                          "notifybay",
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
        title={__("Stock Control", "notifybay")}
        description={__(
          "Fine-tune when restock alerts should be triggered based on physical inventory levels.",
          "notifybay",
        )}
        fields={[
          {
            id: "engine_minStockThreshold",
            label: __("Minimum Restock Threshold", "notifybay"),
            tooltip: __(
              "Prevents the system from firing mass alerts if only 1 or 2 items are returned or restocked.",
              "notifybay",
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
        Urgency Alerts (Hurry Logic) is a NotifyBay Pro feature. Pro injects
        its own section here via the `notifybay_settings_extra_sections`
        filter (activeTab === "engine").
      */}

      {/* Notifications Section */}
      <ClassicSettingsTable
        title={__("Admin Notifications", "notifybay")}
        description={__(
          "Stay informed about your customers' intent by receiving alerts for new signups.",
          "notifybay",
        )}
        fields={[
          {
            id: "engine_adminAlerts",
            label: __("Owner Alerts", "notifybay"),
            tooltip: __(
              "Sends a notification to the store administrator whenever a new shopper joins a waitlist or wishlist.",
              "notifybay",
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
                label={__("Notify store owner of new signups", "notifybay")}
              />
            ),
          },
        ]}
      />
    </div>
  );
};
