import React from "react";
import { ClassicSettingsTable } from "../../classics";
import { __ } from "@wordpress/i18n";

interface StatusTabProps {
  systemStatus: any;
  isLoadingStatus: boolean;
}

export const StatusTab: React.FC<StatusTabProps> = ({
  systemStatus,
  isLoadingStatus,
}) => {
  if (isLoadingStatus) {
    return <p>{__("Loading system status...", "notifybay-waitlist-and-stock-alert-woo")}</p>;
  }

  return (
    <div className="notifybay-flex notifybay-flex-col notifybay-gap-6">
      <ClassicSettingsTable
        title={__("Background Engine Status", "notifybay-waitlist-and-stock-alert-woo")}
        fields={[
          {
            id: "as_jobs",
            label: __("Action Scheduler Queue", "notifybay-waitlist-and-stock-alert-woo"),
            tooltip: __("Current email job queue", "notifybay-waitlist-and-stock-alert-woo"),
            render: () => (
              <div className="notifybay-flex notifybay-flex-wrap notifybay-gap-4">
                {["pending", "in-progress", "complete", "failed"].map((s) => (
                  <div
                    key={s}
                    className="notifybay-bg-gray-50 notifybay-border notifybay-border-gray-200 notifybay-p-3 notifybay-rounded-lg notifybay-min-w-[120px]"
                  >
                    <div className="notifybay-text-xs notifybay-text-gray-500 notifybay-uppercase notifybay-mb-1">
                      {s}
                    </div>
                    <div className="notifybay-text-xl notifybay-font-bold">
                      {systemStatus?.jobs?.[s] || 0}
                    </div>
                  </div>
                ))}
              </div>
            ),
          },
          {
            id: "lead_status",
            label: __("Lead Health", "notifybay-waitlist-and-stock-alert-woo"),
            tooltip: __("Diagnostic database check", "notifybay-waitlist-and-stock-alert-woo"),
            render: () => (
              <div className="notifybay-flex notifybay-gap-4">
                <div className="notifybay-bg-red-50 notifybay-border notifybay-border-red-100 notifybay-p-3 notifybay-rounded-lg notifybay-min-w-[120px]">
                  <div className="notifybay-text-xs notifybay-text-red-500 notifybay-uppercase notifybay-mb-1">
                    {__("Failed Leads", "notifybay-waitlist-and-stock-alert-woo")}
                  </div>
                  <div className="notifybay-text-xl notifybay-font-bold notifybay-text-red-700">
                    {systemStatus?.failed_leads || 0}
                  </div>
                </div>
                <div className="notifybay-bg-blue-50 notifybay-border notifybay-border-blue-100 notifybay-p-3 notifybay-rounded-lg notifybay-min-w-[120px]">
                  <div className="notifybay-text-xs notifybay-text-blue-500 notifybay-uppercase notifybay-mb-1">
                    {__("In Processing", "notifybay-waitlist-and-stock-alert-woo")}
                  </div>
                  <div className="notifybay-text-xl notifybay-font-bold notifybay-text-blue-700">
                    {systemStatus?.processing_leads || 0}
                  </div>
                </div>
              </div>
            ),
          },
        ]}
      />

      <ClassicSettingsTable
        title={__("Environment Information", "notifybay-waitlist-and-stock-alert-woo")}
        fields={[
          {
            id: "env_info",
            label: __("Versions", "notifybay-waitlist-and-stock-alert-woo"),
            tooltip: __("Server environment details", "notifybay-waitlist-and-stock-alert-woo"),
            render: () => (
              <div className="notifybay-text-sm notifybay-text-gray-600">
                <p>
                  <strong>PHP:</strong> {systemStatus?.php_version}
                </p>
                <p>
                  <strong>WordPress:</strong> {systemStatus?.wp_version}
                </p>
                <p>
                  <strong>WooCommerce:</strong> {systemStatus?.wc_version}
                </p>
              </div>
            ),
          },
        ]}
      />
    </div>
  );
};
