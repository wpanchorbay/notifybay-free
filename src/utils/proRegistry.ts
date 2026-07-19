import apiFetch from './apiFetch';
import { useWpabStore, useWpabStoreActions } from '../store/wpabStore';
import { useToast } from '../store/toast/use-toast';
import {
	ClassicSettingsTable,
	ClassicCheckbox,
	ClassicInput,
	ClassicSelect,
	ClassicButton,
} from '../components/classics';

/**
 * Bump this whenever a breaking change is made to the shape of anything
 * exported here, or to a `notifybay_*` @wordpress/hooks filter/slot's
 * arguments. NotifyBay Pro should check this before relying on the registry
 * and fall back to its own upsell/placeholder UI on a mismatch, rather than
 * crashing against a Free version it wasn't built for.
 *
 * @see plans/pro-architecture.md ("Registry version-drift & the JS contract")
 */
export const NOTIFYBAY_JS_API_VERSION = 1;

/**
 * The curated set of Free internals NotifyBay Pro is allowed to reuse so its
 * settings/dashboard bundle doesn't need to bundle its own React or
 * re-implement Free's design system. Treat every key here as public API:
 * only ever add to it, never rename or remove a key, and never change an
 * exported function's signature without bumping NOTIFYBAY_JS_API_VERSION.
 */
export function buildProRegistry() {
	return {
		apiVersion: NOTIFYBAY_JS_API_VERSION,
		apiFetch,
		useWpabStore,
		useWpabStoreActions,
		useToast,
		components: {
			ClassicSettingsTable,
			ClassicCheckbox,
			ClassicInput,
			ClassicSelect,
			ClassicButton,
		},
	};
}
