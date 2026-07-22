import React, { useState, useEffect } from "react";
import { applyFilters } from "@wordpress/hooks";
import { ConfirmationModal } from "../components/common/ConfirmationModal";
import { useToast } from "../store/toast/use-toast";
import { __ } from "@wordpress/i18n";
import { SkeletonSettings } from "../components/loading/SkeletonSettings";
import { TopProgressBar } from "../components/loading/TopProgressBar";
import apiFetch from "../utils/apiFetch";
import { PluginSettings } from "../utils/types";

// Import modular tab components
import { GeneralTab } from "../components/settings/tabs/GeneralTab";
import { AppearanceTab } from "../components/settings/tabs/AppearanceTab";
import { EngineTab } from "../components/settings/tabs/EngineTab";
import { EmailTab } from "../components/settings/tabs/EmailTab";
import { StatusTab } from "../components/settings/tabs/StatusTab";
import { AdvancedTab } from "../components/settings/tabs/AdvancedTab";

const Settings: React.FC = () => {
  const [settings, setSettings] = useState<PluginSettings | null>(null);
  const [originalSettings, setOriginalSettings] =
    useState<PluginSettings | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  // Deep-link support: a namespaced ?notifybay_tab= param (used by Pro's
  // license CTA and its "License" plugin action link) opens a specific tab
  // directly. Namespaced to avoid colliding with WooCommerce's own ?tab=/
  // ?section= params on the settings screen.
  const initialTab = (() => {
    try {
      const t = new URLSearchParams(window.location.search).get(
        "notifybay_tab",
      );
      return t || "overview";
    } catch {
      return "overview";
    }
  })();
  const [activeTab, setActiveTab] = useState(initialTab);
  const [systemStatus, setSystemStatus] = useState<any>(null);
  const [isLoadingStatus, setIsLoadingStatus] = useState(false);
  const [showUnsavedModal, setShowUnsavedModal] = useState(false);
  const [pendingTab, setPendingTab] = useState<string | null>(null);
  const { addToast } = useToast();

  useEffect(() => {
    fetchSettings();
    // Force disable the native save button on mount
    const nativeSaveButton = document.querySelector('button[name="save"]');
    if (nativeSaveButton) {
      (nativeSaveButton as HTMLButtonElement).disabled = true;
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (activeTab === "status") {
      fetchSystemStatus();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTab]);

  const fetchSystemStatus = async () => {
    setIsLoadingStatus(true);
    try {
      const response: any = await apiFetch({
        path: "admin/system-status",
      });
      setSystemStatus(response);
    } catch (error) {
      addToast(__("Failed to load system status.", "notifybay-waitlist-and-stock-alert-woo"), "error");
    } finally {
      setIsLoadingStatus(false);
    }
  };

  // Hijack the native WooCommerce save button
  useEffect(() => {
    const form = document.getElementById("mainform") as HTMLFormElement;
    if (!form) {
      return;
    }

    const onFormSubmit = (e: Event) => {
      e.preventDefault();
      handleSave();
    };

    form.addEventListener("submit", onFormSubmit);
    return () => form.removeEventListener("submit", onFormSubmit);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [settings, originalSettings]);

  const fetchSettings = async () => {
    try {
      const response: any = await apiFetch({
        path: "settings",
      });
      if (response.success) {
        setSettings({ ...response.data });
        setOriginalSettings(JSON.parse(JSON.stringify(response.data)));
      }
    } catch (error) {
      // eslint-disable-next-line no-console
      console.error("Error fetching settings:", error);
      addToast(__("Failed to load settings.", "notifybay-waitlist-and-stock-alert-woo"), "error");
    } finally {
      setIsLoading(false);
    }
  };

  const handleSave = async () => {
    if (!settings) {
      return;
    }
    setIsSaving(true);
    try {
      const response: any = await apiFetch({
        path: "settings",
        method: "POST",
        data: settings,
      });
      if (response.success) {
        setSettings({ ...response.data });
        setOriginalSettings(JSON.parse(JSON.stringify(response.data)));
        addToast(__("Settings saved successfully.", "notifybay-waitlist-and-stock-alert-woo"), "success");

        // Prevent WooCommerce "Unsaved Changes" dialog
        const nativeSaveButton = document.querySelector('button[name="save"]');
        if (nativeSaveButton) {
          (nativeSaveButton as HTMLButtonElement).disabled = true;
        }
        window.onbeforeunload = null;
      }
    } catch (error) {
      // eslint-disable-next-line no-console
      console.error("Error saving settings:", error);
      addToast(__("Failed to save settings.", "notifybay-waitlist-and-stock-alert-woo"), "error");
    } finally {
      setIsSaving(false);
      const nativeSaveButton = document.querySelector('button[name="save"]');
      if (nativeSaveButton) {
        nativeSaveButton.classList.remove("is-busy");
      }
    }
  };

  const hasChanges =
    settings && originalSettings
      ? JSON.stringify(settings) !== JSON.stringify(originalSettings)
      : false;

  useEffect(() => {
    const timer = setTimeout(() => {
      const nativeSaveButton = document.querySelector('button[name="save"]');
      if (nativeSaveButton) {
        (nativeSaveButton as HTMLButtonElement).disabled = !hasChanges;
      }
    }, 50);
    return () => clearTimeout(timer);
  }, [hasChanges, activeTab]);

  const handleTabChange = (tabId: string) => {
    if (tabId === activeTab) return;

    if (hasChanges) {
      setPendingTab(tabId);
      setShowUnsavedModal(true);
    } else {
      setActiveTab(tabId);
      // Explicitly force disable on next tick to override WC scripts
      setTimeout(() => {
        const nativeSaveButton = document.querySelector('button[name="save"]');
        if (nativeSaveButton) {
          (nativeSaveButton as HTMLButtonElement).disabled = true;
        }
      }, 100);
    }
  };

  const handleConfirmSave = async () => {
    setShowUnsavedModal(false);
    await handleSave();
    if (pendingTab) {
      setActiveTab(pendingTab);
      setPendingTab(null);
    }
  };

  if (isLoading) {
    return (
      <div className="notifybay-p-page-default">
        <SkeletonSettings />
      </div>
    );
  }

  if (!settings) {
    return (
      <div className="notifybay-p-page-default">
        <p>{__("Failed to load settings.", "notifybay-waitlist-and-stock-alert-woo")}</p>
      </div>
    );
  }

  const baseTabs = [
    { id: "overview", label: __("Overview", "notifybay-waitlist-and-stock-alert-woo") },
    { id: "general", label: __("General", "notifybay-waitlist-and-stock-alert-woo") },
    { id: "appearance", label: __("Display", "notifybay-waitlist-and-stock-alert-woo") },
    { id: "engine", label: __("Engine Logic", "notifybay-waitlist-and-stock-alert-woo") },
    { id: "email", label: __("Email Templates", "notifybay-waitlist-and-stock-alert-woo") },
    { id: "status", label: __("System Status", "notifybay-waitlist-and-stock-alert-woo") },
    { id: "advanced", label: __("Advanced", "notifybay-waitlist-and-stock-alert-woo") },
  ];

  /**
   * Allows NotifyBay Pro to append its own settings tabs (e.g. "Wishlist",
   * "License") to the sub-navigation. Filter callbacks receive and must
   * return an array of `{ id, label }` objects.
   */
  const tabs: { id: string; label: string }[] = applyFilters(
    "notifybay_settings_tabs",
    baseTabs,
  ) as { id: string; label: string }[];

  const knownTabIds = baseTabs.map((tab) => tab.id);

  return (
    <div className="notifybay-ignore-preflight notifybay-flex notifybay-flex-col">
      <input
        type="hidden"
        name="notifybay_has_changes"
        value={hasChanges ? "1" : "0"}
      />
      <TopProgressBar isSaving={isSaving} />

      {/* WooCommerce Native Style Sub-navigation */}
      <ul className="subsubsub notify !notifybay-m-0 !notifybay-p-0 !notifybay-mb-2 notifybay-list-none notifybay-w-full notifybay-block notifybay-clear-both">
        {tabs.map((tab, index) => (
          <li key={tab.id} className="notifybay-inline-block !notifybay-m-0">
            <button
              type="button"
              onClick={() => handleTabChange(tab.id)}
              className={`notifybay-p-0 notifybay-bg-transparent notifybay-border-0 notifybay-cursor-pointer notifybay-text-[13px] ${
                activeTab === tab.id
                  ? "notifybay-text-black notifybay-font-bold"
                  : "notifybay-text-[#2271b1] hover:notifybay-text-[#135e96]"
              }`}
            >
              {tab.label}
            </button>
            {index < tabs.length - 1 && (
              <span className="notifybay-mx-1 notifybay-text-[#c3c4c7]">|</span>
            )}
          </li>
        ))}
      </ul>

      <div className="notifybay-animate-fade-in">
        {activeTab === "overview" && (
          <div className="notifybay-flex notifybay-flex-col notifybay-gap-[16px]">
            {applyFilters(
              "notifybay_dashboard_widgets",
              null,
            ) as React.ReactNode}
          </div>
        )}

        {activeTab === "general" && (
          <GeneralTab settings={settings} setSettings={setSettings} />
        )}

        {activeTab === "appearance" && (
          <AppearanceTab settings={settings} setSettings={setSettings} />
        )}

        {activeTab === "engine" && (
          <EngineTab settings={settings} setSettings={setSettings} />
        )}

        {activeTab === "email" && (
          <EmailTab settings={settings} setSettings={setSettings} />
        )}

        {activeTab === "status" && (
          <StatusTab
            systemStatus={systemStatus}
            isLoadingStatus={isLoadingStatus}
          />
        )}

        {activeTab === "advanced" && (
          <AdvancedTab settings={settings} setSettings={setSettings} />
        )}

        {/*
          NotifyBay Pro renders any tab it added via `notifybay_settings_tabs`
          (e.g. "wishlist", "license") through this filter. Free renders
          nothing for unknown tab ids.
        */}
        {!knownTabIds.includes(activeTab) &&
          (applyFilters(
            "notifybay_settings_tab_content",
            null,
            activeTab,
            settings,
            setSettings,
          ) as React.ReactNode)}

        {/*
          Lets NotifyBay Pro inject extra sections (e.g. a "License Settings"
          table on the Advanced tab, Wishlist fields on General) into a tab
          Free already owns, without taking over the whole tab.
        */}
        {applyFilters(
          "notifybay_settings_extra_sections",
          null,
          activeTab,
          settings,
          setSettings,
        ) as React.ReactNode}
      </div>

      <ConfirmationModal
        isOpen={showUnsavedModal}
        title={__("Unsaved Changes", "notifybay-waitlist-and-stock-alert-woo")}
        message={__(
          "You have unsaved changes. Would you like to save them before switching tabs?",
          "notifybay-waitlist-and-stock-alert-woo",
        )}
        confirmLabel={__("Save", "notifybay-waitlist-and-stock-alert-woo")}
        cancelLabel={__("Discard", "notifybay-waitlist-and-stock-alert-woo")}
        onConfirm={handleConfirmSave}
        onCancel={() => {
          if (pendingTab) {
            setSettings(JSON.parse(JSON.stringify(originalSettings)));
            setActiveTab(pendingTab);
            setPendingTab(null);
          }
          setShowUnsavedModal(false);
        }}
      />
    </div>
  );
};

export default Settings;
