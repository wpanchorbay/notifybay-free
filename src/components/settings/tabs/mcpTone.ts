import { __ } from "@wordpress/i18n";

/**
 * The colour rule for this screen, in one place.
 *
 * Four tones, each with a single meaning. Nothing on the tab picks a colour
 * for looks; it asks this module what the situation is.
 *
 *   neutral (grey)   nothing is in effect
 *   safe    (green)  live, and cannot change data
 *   caution (amber)  live, and can change data
 *   danger  (red)    live, and can destroy data irreversibly
 *
 * Two rules follow from that, and both correct something the screen used to
 * get wrong.
 *
 * A green "Live" pill was reassuring regardless of what was switched on --
 * including full access, which is the most dangerous state the screen can be
 * in. State and consequence are not separable here, so the pill takes the tone
 * of the selected level and is only green when the endpoint really is
 * harmless.
 *
 * And risk is only real when it is reachable. With the endpoint off, nothing
 * can be called, so the pill is grey no matter which level is stored -- the
 * level card still shows its own tone, because that is the policy the owner
 * has chosen and it is about to matter the moment they flip the switch.
 *
 * Unselected levels are deliberately muted. Three saturated badges arguing for
 * attention at once means none of them reads as a warning.
 */
export type Tone = "neutral" | "safe" | "caution" | "danger";

/**
 * What each rung of the kit's ladder can do. A level the kit adds later is
 * absent here and falls back to neutral, which understates it rather than
 * colouring it wrongly -- an unknown level is not evidence of safety, but a
 * confident green or red would be a guess.
 */
export const LEVEL_TONE: Record<string, Tone> = {
  read: "safe",
  "read+modify": "caution",
  full: "danger",
};

interface ToneClasses {
  /** Filled pill: background, text and border together. */
  pill: string;
  /** The small dot inside a pill. */
  dot: string;
  /** A selected level card. */
  card: string;
  /** The badge on a selected level card. */
  badge: string;
}

export const TONE: Record<Tone, ToneClasses> = {
  neutral: {
    pill: "notifybay-bg-gray-100 notifybay-text-gray-600 notifybay-border-gray-200",
    dot: "notifybay-bg-gray-400",
    card: "notifybay-border-gray-300 notifybay-bg-gray-50",
    badge:
      "notifybay-bg-gray-100 notifybay-text-gray-600 notifybay-border-gray-200",
  },
  safe: {
    pill: "notifybay-bg-green-50 notifybay-text-green-800 notifybay-border-green-300",
    dot: "notifybay-bg-green-500",
    card: "notifybay-border-green-400 notifybay-bg-green-50",
    badge:
      "notifybay-bg-green-100 notifybay-text-green-800 notifybay-border-green-300",
  },
  caution: {
    pill: "notifybay-bg-amber-50 notifybay-text-amber-900 notifybay-border-amber-300",
    dot: "notifybay-bg-amber-500",
    card: "notifybay-border-amber-400 notifybay-bg-amber-50",
    badge:
      "notifybay-bg-amber-100 notifybay-text-amber-900 notifybay-border-amber-300",
  },
  danger: {
    pill: "notifybay-bg-red-50 notifybay-text-red-800 notifybay-border-red-300",
    dot: "notifybay-bg-red-500",
    card: "notifybay-border-red-400 notifybay-bg-red-50",
    badge: "notifybay-bg-red-100 notifybay-text-red-800 notifybay-border-red-300",
  },
};

/** An unselected card, and the muted badge that sits on it. */
export const IDLE_CARD =
  "notifybay-border-gray-200 notifybay-bg-white hover:notifybay-border-gray-400";
export const IDLE_BADGE =
  "notifybay-bg-gray-100 notifybay-text-gray-500 notifybay-border-gray-200";

/**
 * What the badge on a level card says: the capability itself, independent of
 * whether the endpoint is running.
 *
 * @param level Raw access level from the kit.
 */
export function levelBadge(level: string): string {
  switch (level) {
    case "read":
      return __("READ ONLY", "notifybay-waitlist-and-stock-alert-woo");
    case "read+modify":
      return __("CAN EDIT", "notifybay-waitlist-and-stock-alert-woo");
    case "full":
      return __("CAN DELETE", "notifybay-waitlist-and-stock-alert-woo");
    default:
      return "";
  }
}

/**
 * The endpoint's state as one pill: its tone and its wording.
 *
 * @param enabled Whether the endpoint is switched on.
 * @param level   The stored access level.
 */
export function endpointState(
  enabled: boolean,
  level: string,
): { tone: Tone; text: string } {
  if (!enabled) {
    return {
      tone: "neutral",
      text: __("Off", "notifybay-waitlist-and-stock-alert-woo"),
    };
  }

  switch (LEVEL_TONE[level]) {
    case "safe":
      return {
        tone: "safe",
        text: __("Live · read only", "notifybay-waitlist-and-stock-alert-woo"),
      };
    case "caution":
      return {
        tone: "caution",
        text: __("Live · can edit", "notifybay-waitlist-and-stock-alert-woo"),
      };
    case "danger":
      return {
        tone: "danger",
        text: __("Live · can delete", "notifybay-waitlist-and-stock-alert-woo"),
      };
    default:
      return {
        tone: "neutral",
        text: __("Live", "notifybay-waitlist-and-stock-alert-woo"),
      };
  }
}
