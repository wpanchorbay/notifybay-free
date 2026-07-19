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
        title={__("Waitlist Display", "notifybay")}
        description={__(
          "Customize how the 'Notify Me' button and social proof elements appear on your product pages.",
          "notifybay",
        )}
        fields={[
          {
            id: "appearance_waitlistButtonText",
            label: __("Button Text", "notifybay"),
            tooltip: __(
              "The text shown on the 'Notify Me' button.",
              "notifybay",
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
            label: __("Button CSS Class", "notifybay"),
            tooltip: __(
              "Additional CSS classes for the Waitlist button.",
              "notifybay",
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
                placeholder={__("e.g. my-custom-btn", "notifybay")}
              />
            ),
          },
          {
            id: "appearance_waitlistSuccessMessage",
            label: __("Success Message", "notifybay"),
            tooltip: __(
              "The message shown after a user successfully joins the waitlist.",
              "notifybay",
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
        Wishlist Display and Social Proof (FOMO) are NotifyBay Pro features.
        Pro injects its own sections here via the
        `notifybay_settings_extra_sections` filter (activeTab === "appearance")
        — see plans/pro-architecture.md.
      */}

      {/* Custom Styles Section */}
      <ClassicSettingsTable
        title={__("Custom Styles", "notifybay")}
        description={__(
          "Advanced styling options. Add custom CSS to fine-tune the look and feel of the plugin's frontend elements.",
          "notifybay",
        )}
        fields={[
          {
            id: "appearance_customCss",
            label: __("Additional CSS", "notifybay"),
            tooltip: __(
              "Add custom CSS to style the waitlist and wishlist components. Do not include <style> tags.",
              "notifybay",
            ),
            render: () => (
              <ClassicTextarea
                value={settings.appearance_customCss}
                onChange={(e) =>
                  setSettings({
                    ...settings,
                    appearance_customCss: e.target.value,
                  })
                }
                placeholder={__(
                  ".notifybay-btn { background: #000; }",
                  "notifybay",
                )}
                rows={8}
              />
            ),
          },
        ]}
      />
    </div>
  );
};
