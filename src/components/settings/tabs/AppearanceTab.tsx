import React from "react";
import {
  ClassicSettingsTable,
  ClassicInput,
  ClassicTextarea,
} from "../../classics";
import { __ } from "@wordpress/i18n";
import { PluginSettings } from "../../../utils/types";

interface AppearanceTabProps {
  settings: PluginSettings;
  setSettings: (settings: PluginSettings) => void;
}

export const AppearanceTab: React.FC<AppearanceTabProps> = ({
  settings,
  setSettings,
}) => {
  return (
    <div className="notifybay-flex notifybay-flex-col ">
      {/* Waitlist Display Section */}
      <ClassicSettingsTable
        title={__("Waitlist Display", "notifybay-waitlist-and-stock-alert-woo")}
        description={__(
          "Customize how the 'Notify Me' button and social proof elements appear on your product pages.",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        fields={[
          {
            id: "appearance_waitlistButtonText",
            label: __("Button Text", "notifybay-waitlist-and-stock-alert-woo"),
            tooltip: __(
              "The text shown on the 'Notify Me' button.",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            render: () => (
              <ClassicInput
                value={settings.appearance_waitlistButtonText}
                onChange={(e) =>
                  setSettings({
                    ...settings,
                    appearance_waitlistButtonText: e.target.value,
                  })
                }
              />
            ),
          },
          {
            id: "appearance_waitlistButtonClass",
            label: __("Button CSS Class", "notifybay-waitlist-and-stock-alert-woo"),
            tooltip: __(
              "Additional CSS classes for the Waitlist button.",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            render: () => (
              <ClassicInput
                value={settings.appearance_waitlistButtonClass}
                onChange={(e) =>
                  setSettings({
                    ...settings,
                    appearance_waitlistButtonClass: e.target.value,
                  })
                }
                placeholder={__("e.g. my-custom-btn", "notifybay-waitlist-and-stock-alert-woo")}
              />
            ),
          },
          {
            id: "appearance_waitlistSuccessMessage",
            label: __("Success Message", "notifybay-waitlist-and-stock-alert-woo"),
            tooltip: __(
              "The message shown after a user successfully joins the waitlist.",
              "notifybay-waitlist-and-stock-alert-woo",
            ),
            render: () => (
              <ClassicTextarea
                value={settings.appearance_waitlistSuccessMessage}
                onChange={(e) =>
                  setSettings({
                    ...settings,
                    appearance_waitlistSuccessMessage: e.target.value,
                  })
                }
              />
            ),
          },
        ]}
      />

      {/*
        Waitlist appearance is configured above. A premium add-on (NotifyBay Pro)
        injects its own extra sections here via the
        `notifybay_settings_extra_sections` filter (activeTab === "appearance").
      */}
    </div>
  );
};
