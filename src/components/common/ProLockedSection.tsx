import React from 'react';
import { __ } from '@wordpress/i18n';
import { Lock } from 'lucide-react';
import {
	ClassicSettingsTable,
	SettingsField,
} from '../classics/ClassicSettingsTable';
import { useWpabStore } from '../../store/wpabStore';
import { BuyProTooltip } from './BuyProTooltip';

/**
 * A single "Pro" badge, appended next to a locked section's title.
 */
const ProBadge: React.FC = () => (
	<span className="notifybay-inline-flex notifybay-items-center notifybay-gap-1 notifybay-ml-2 notifybay-align-middle notifybay-text-[10px] notifybay-font-bold notifybay-uppercase notifybay-tracking-wide notifybay-text-white notifybay-bg-[#f02a74] notifybay-rounded-full notifybay-px-2 notifybay-py-0.5">
		<Lock className="notifybay-w-2.5 notifybay-h-2.5" />
		{ __( 'Pro', 'notifybay-waitlist-and-stock-alert-woo' ) }
	</span>
);

interface ProLockedSectionProps {
	/** Stable slug used for the `data-notifybay-pro-preview` test hook. */
	slug: string;
	title: string;
	description?: string;
	fields: SettingsField[];
}

/**
 * Renders a disabled, greyed-out replica of a NotifyBay Pro settings section
 * using the same `ClassicSettingsTable` primitive Pro itself uses, so a free
 * user sees exactly what the real section looks like. Presentation only — no
 * value here is ever saved; every field passed in must already be disabled.
 */
export const ProLockedSection: React.FC< ProLockedSectionProps > = ( {
	slug,
	title,
	description,
	fields,
} ) => {
	const store = useWpabStore();
	const buyProUrl = store.pluginData?.buy_pro_url || '#';

	return (
		<div
			data-notifybay-pro-preview={ slug }
			className="notifybay-pro-locked notifybay-relative notifybay-opacity-60"
			style={ { cursor: 'not-allowed' } }
		>
			<BuyProTooltip>
				<ClassicSettingsTable
					title={
						<>
							{ title }
							<ProBadge />
						</>
					}
					description={ description }
					fields={ fields }
				/>
			</BuyProTooltip>
			<p className="notifybay-mt-2">
				<a
					href={ buyProUrl }
					target="_blank"
					rel="noopener noreferrer"
					className="button button-primary"
					style={ {
						backgroundColor: '#f02a74',
						borderColor: '#e71161',
						color: '#fff',
					} }
				>
					{ __( 'Unlock with Pro', 'notifybay-waitlist-and-stock-alert-woo' ) }
				</a>
			</p>
		</div>
	);
};
