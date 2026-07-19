import React from "react";
import { ClassicSettingsTable, ClassicCheckbox } from "../../classics";
import { __ } from "@wordpress/i18n";
import { PluginSettings } from "../../../utils/types";

interface AdvancedTabProps {
  settings: PluginSettings;
  setSettings: (settings: PluginSettings) => void;
}

export const AdvancedTab: React.FC<AdvancedTabProps> = ({
  settings,
  setSettings,
}) => {
  // The setup wizard lives in the admin SPA (HashRouter, page=notifybay). From
  // the WooCommerce settings page we cross-navigate to its #/wizard route.
  const adminUrl = (window as any).notifyBay_Localize?.admin_url || "";
  const wizardUrl = adminUrl
    ? `${adminUrl}#/wizard`
    : "admin.php?page=notifybay#/wizard";

  return (
    <>
      <ClassicSettingsTable
        title={__("Setup", "notifybay")}
        fields={[
          {
            id: "setup_wizard",
            label: __("Setup Wizard", "notifybay"),
            tooltip: __(
              "Re-run the first-time setup wizard to reconfigure the essentials.",
              "notifybay",
            ),
            render: () => (
              <a href={wizardUrl} className="button button-secondary">
                {__("Run Setup Wizard", "notifybay")}
              </a>
            ),
          },
        ]}
      />
      <ClassicSettingsTable
        title={__("System & Maintenance", "notifybay")}
        fields={[
          {
            id: "debug_enableMode",
            label: __("Debug Mode", "notifybay"),
            tooltip: __(
              "Logs technical data during execution to help developers troubleshoot background jobs and API calls.",
              "notifybay",
            ),
            render: () => (
              <ClassicCheckbox
                checked={settings.debug_enableMode}
                onChange={(val) =>
                  setSettings({
                    ...settings,
                    debug_enableMode: val,
                  })
                }
                label={__("Enable developer logging", "notifybay")}
                description={__(
                  "Only recommended for troubleshooting. Logs are written to the WooCommerce Status > Logs.",
                  "notifybay",
                )}
              />
            ),
          },
          {
            id: "advanced_deleteAllOnUninstall",
            label: __("Deep Uninstall", "notifybay"),
            tooltip: __(
              "Determines if all plugin data should be permanently deleted from the database when the plugin is deleted.",
              "notifybay",
            ),
            render: () => (
              <ClassicCheckbox
                checked={settings.advanced_deleteAllOnUninstall}
                onChange={(val) =>
                  setSettings({
                    ...settings,
                    advanced_deleteAllOnUninstall: val,
                  })
                }
                label={__(
                  "Delete all tables and settings on plugin deletion",
                  "notifybay",
                )}
                description={__(
                  "DANGEROUS: If checked, all leads and settings will be permanently erased and cannot be recovered.",
                  "notifybay",
                )}
              />
            ),
          },
        ]}
      />
      {/*
        License activation is a NotifyBay Pro feature. Pro injects its "License
        Settings" table here via the `notifybay_settings_sections` (or an
        AdvancedTab-specific) @wordpress/hooks filter — see the registry pattern
        in plans/pro-architecture.md. Free renders nothing when Pro is inactive.
      */}
    </>
  );
};
