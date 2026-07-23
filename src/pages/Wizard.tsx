import React, { useState } from "react";
import { useNavigate } from "react-router-dom";
import { __ } from "@wordpress/i18n";
import { ClassicButton, ClassicInput, ClassicCheckbox, ClassicSettingsTable } from "../components/classics";
import customApiFetch from "../utils/apiFetch";

interface Localize {
  plugin_settings?: Record<string, any>;
  is_pro?: boolean;
  wc_emails_url?: string;
  settings_url?: string;
}

const localize: Localize = (window as any).notifyBay_Localize || {};
const isPro = !!localize.is_pro;

/**
 * First-run setup wizard. Walks a new merchant through sender identity, feature
 * enablement, button copy, adding the block, and (when Pro is active) license
 * activation — persisting settings and marking the wizard complete at the end.
 */
const Wizard: React.FC = () => {
  const navigate = useNavigate();
  const [step, setStep] = useState(0);
  const [saving, setSaving] = useState(false);

  const [settings, setSettings] = useState<Record<string, any>>({
    email_fromName: "",
    email_fromEmail: "",
    general_doubleOptIn: false,
    appearance_waitlistButtonText: "Notify me",
    ...(localize.plugin_settings || {}),
  });

  const set = (patch: Record<string, any>) =>
    setSettings((prev) => ({ ...prev, ...patch }));

  // Build the ordered list of steps (the license step only appears for Pro).
  const steps: Array<{ title: string; body: React.ReactNode }> = [
    {
      title: __("Welcome to NotifyBay", "notifybay-waitlist-and-stock-alert-woo"),
      body: (
        <p className="notifybay-text-gray-600">
          {__(
            "Let's get your back-in-stock alerts set up in a few quick steps.",
            "notifybay-waitlist-and-stock-alert-woo",
          )}
        </p>
      ),
    },
    {
      title: __("Sender identity", "notifybay-waitlist-and-stock-alert-woo"),
      body: (
        <ClassicSettingsTable
          fields={[
            {
              id: "email_fromName",
              label: __("From name", "notifybay-waitlist-and-stock-alert-woo"),
              render: () => (
                <ClassicInput
                  value={settings.email_fromName}
                  onChange={(e: any) => set({ email_fromName: e.target.value })}
                  placeholder={__("e.g. My Store Team", "notifybay-waitlist-and-stock-alert-woo")}
                />
              ),
            },
            {
              id: "email_fromEmail",
              label: __("From email", "notifybay-waitlist-and-stock-alert-woo"),
              render: () => (
                <ClassicInput
                  type="email"
                  value={settings.email_fromEmail}
                  onChange={(e: any) => set({ email_fromEmail: e.target.value })}
                  placeholder={__("e.g. sales@mystore.com", "notifybay-waitlist-and-stock-alert-woo")}
                />
              ),
            },
          ]}
        />
      ),
    },
    {
      title: __("Choose features", "notifybay-waitlist-and-stock-alert-woo"),
      body: (
        <ClassicSettingsTable
          fields={[
            {
              id: "general_doubleOptIn",
              label: __("Double opt-in", "notifybay-waitlist-and-stock-alert-woo"),
              render: () => (
                <ClassicCheckbox
                  checked={settings.general_doubleOptIn}
                  onChange={(v: boolean) => set({ general_doubleOptIn: v })}
                  label={__("Require email confirmation", "notifybay-waitlist-and-stock-alert-woo")}
                />
              ),
            },
          ]}
        />
      ),
    },
    {
      title: __("Button text", "notifybay-waitlist-and-stock-alert-woo"),
      body: (
        <ClassicSettingsTable
          fields={[
            {
              id: "appearance_waitlistButtonText",
              label: __("Waitlist button label", "notifybay-waitlist-and-stock-alert-woo"),
              render: () => (
                <>
                  <ClassicInput
                    value={settings.appearance_waitlistButtonText}
                    onChange={(e: any) =>
                      set({ appearance_waitlistButtonText: e.target.value })
                    }
                  />
                  <p className="description">
                    {__(
                      "This button shows on out-of-stock products so shoppers can join the waitlist.",
                      "notifybay-waitlist-and-stock-alert-woo",
                    )}
                  </p>
                </>
              ),
            },
          ]}
        />
      ),
    },
    {
      title: __("Add it to your store", "notifybay-waitlist-and-stock-alert-woo"),
      body: (
        <div className="notifybay-flex notifybay-flex-col notifybay-gap-[8px]">
          <p className="notifybay-text-gray-600">
            {__(
              "NotifyBay adds its button to product pages automatically. To place it elsewhere, use the block or shortcode:",
              "notifybay-waitlist-and-stock-alert-woo",
            )}
          </p>
          <code className="notifybay-bg-gray-100 notifybay-p-[8px] notifybay-rounded-[6px]">
            [notifybay_waitlist]
          </code>
        </div>
      ),
    },
    ...(isPro
      ? [
          {
            title: __("Activate your license", "notifybay-waitlist-and-stock-alert-woo"),
            body: (
              <div className="notifybay-flex notifybay-flex-col notifybay-gap-[8px]">
                <p className="notifybay-text-gray-600">
                  {__(
                    "Activate your NotifyBay Pro license to receive automatic updates and unlock all Pro features.",
                    "notifybay-waitlist-and-stock-alert-woo",
                  )}
                </p>
                <a
                  href={`${localize.settings_url || "#"}&section=advanced`}
                  className="button button-secondary"
                >
                  {__("Open license settings", "notifybay-waitlist-and-stock-alert-woo")}
                </a>
              </div>
            ),
          },
        ]
      : []),
    {
      title: __("You're all set!", "notifybay-waitlist-and-stock-alert-woo"),
      body: (
        <p className="notifybay-text-gray-600">
          {__(
            "NotifyBay is ready. Email content is managed under WooCommerce → Settings → Emails.",
            "notifybay-waitlist-and-stock-alert-woo",
          )}
        </p>
      ),
    },
  ];

  const isLast = step === steps.length - 1;

  const persist = async () => {
    setSaving(true);
    try {
      await customApiFetch({
        path: "settings",
        method: "POST",
        data: settings,
      });
    } finally {
      setSaving(false);
    }
  };

  const next = async () => {
    // Persist settings once, before the final "done" screen.
    if (step === steps.length - 2) {
      await persist();
    }
    setStep((s) => Math.min(s + 1, steps.length - 1));
  };

  const finish = async () => {
    setSaving(true);
    try {
      await customApiFetch({ path: "admin/wizard/complete", method: "POST" });
    } finally {
      setSaving(false);
    }
    navigate("/dashboard");
  };

  const skip = async () => {
    await customApiFetch({ path: "admin/wizard/complete", method: "POST" });
    navigate("/");
  };

  return (
    <div className="wrap notifybay-wizard">
      <h1>{__("NotifyBay Setup Wizard", "notifybay-waitlist-and-stock-alert-woo")}</h1>

      <ol
        className="notifybay-wizard__steps"
        aria-label={__("Setup progress", "notifybay-waitlist-and-stock-alert-woo")}
      >
        {steps.map((s, index) => {
          const stepNum = index + 1;
          const isActive = index === step;
          const isComplete = index < step;
          const state = isActive
            ? "active"
            : isComplete
            ? "complete"
            : "upcoming";
          const status = isActive
            ? __("Current", "notifybay-waitlist-and-stock-alert-woo")
            : isComplete
            ? __("Complete", "notifybay-waitlist-and-stock-alert-woo")
            : __("Upcoming", "notifybay-waitlist-and-stock-alert-woo");

          return (
            <li
              key={index}
              className={`notifybay-wizard__step notifybay-wizard__step--${state}`}
              aria-current={isActive ? "step" : undefined}
            >
              <span className="notifybay-wizard__step-number">{stepNum}</span>
              <span className="notifybay-wizard__step-body">
                <span className="notifybay-wizard__step-label">{s.title}</span>
                <span className="notifybay-wizard__step-status">{status}</span>
              </span>
            </li>
          );
        })}
      </ol>

      <div className="notifybay-wizard__content">
        <h2>{steps[step].title}</h2>
        <div style={{ marginBottom: "24px" }}>{steps[step].body}</div>

        <div className="notifybay-wizard__actions">
          {step > 0 && (
            <button
              type="button"
              className="button notifybay-wizard__back"
              disabled={saving}
              onClick={() => setStep((s) => Math.max(0, s - 1))}
            >
              {__("Back", "notifybay-waitlist-and-stock-alert-woo")}
            </button>
          )}

          {!isLast && (
            <button
              type="button"
              className="button-link"
              style={{ marginRight: "12px" }}
              onClick={skip}
            >
              {__("Skip setup", "notifybay-waitlist-and-stock-alert-woo")}
            </button>
          )}

          {isLast ? (
            <button
              type="button"
              className="button button-primary"
              onClick={finish}
              disabled={saving}
            >
              {__("Finish Setup", "notifybay-waitlist-and-stock-alert-woo")}
            </button>
          ) : (
            <button
              type="button"
              className="button button-primary"
              onClick={next}
              disabled={saving}
            >
              {saving ? __("Saving…", "notifybay-waitlist-and-stock-alert-woo") : __("Next", "notifybay-waitlist-and-stock-alert-woo")}
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

export default Wizard;
