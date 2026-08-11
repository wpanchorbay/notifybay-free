import React from 'react';
import { __ } from '@wordpress/i18n';
import { ClassicCheckbox } from '../classics/ClassicCheckbox';
import { ClassicInput } from '../classics/ClassicInput';
import { ProLockedSection } from '../common/ProLockedSection';

/**
 * Free-side "buy Pro" previews of every Pro settings section, rendered inline
 * — in the exact tab, in the exact spot — where NotifyBay Pro injects the
 * real section via the `notifybay_settings_extra_sections` filter (see
 * Settings.tsx). Field labels, descriptions, and default values are copied
 * verbatim from `notifybaypro/src/pro-extensions.tsx` / `SettingsExtension.php`
 * so the preview matches what Pro actually renders. Presentation only — every
 * control here is disabled; nothing is read from or written to real settings.
 *
 * Toggle-driven sub-fields (FOMO template, Fair-Play ratio/window, hurry
 * threshold/delay) are shown expanded regardless of the toggle's real default,
 * since the point of a preview is to show what the feature offers.
 */

const noop = () => {};

const disabledCheckbox = ( label: string ) => (
	<ClassicCheckbox checked={ true } onChange={ noop } disabled label={ label } />
);

const disabledInput = (
	value: string,
	description?: string,
	type: string = 'text'
) => (
	<ClassicInput
		type={ type }
		value={ value }
		onChange={ noop }
		disabled
		description={ description }
	/>
);

const GeneralPreview: React.FC = () => (
	<ProLockedSection
		slug="wishlist"
		title={ __( 'Wishlist', 'notifybay-waitlist-and-stock-alert-woo' ) }
		description={ __(
			'Enable wishlist functionality (price-drop notifications) across the store.',
			'notifybay-waitlist-and-stock-alert-woo'
		) }
		fields={ [
			{
				id: 'general_wishlistEnabled',
				label: __( 'Enable Wishlist', 'notifybay-waitlist-and-stock-alert-woo' ),
				render: () =>
					disabledCheckbox(
						__( 'Enable Wishlist features', 'notifybay-waitlist-and-stock-alert-woo' )
					),
			},
		] }
	/>
);

const AppearancePreview: React.FC = () => (
	<>
		<ProLockedSection
			slug="fomo"
			title={ __( 'Social Proof (FOMO)', 'notifybay-waitlist-and-stock-alert-woo' ) }
			description={ __(
				'Show how many other people are waiting for this product.',
				'notifybay-waitlist-and-stock-alert-woo'
			) }
			fields={ [
				{
					id: 'appearance_fomoEnabled',
					label: __( 'Enable FOMO Banner', 'notifybay-waitlist-and-stock-alert-woo' ),
					render: () => (
						<div className="notifybay-flex notifybay-flex-col notifybay-gap-4">
							{ disabledCheckbox(
								__( 'Show waiting count on product page', 'notifybay-waitlist-and-stock-alert-woo' )
							) }
							<div className="notifybay-ml-6 notifybay-flex notifybay-flex-col notifybay-gap-2">
								{ disabledInput(
									'🔥 {count} people are waiting for this',
									__( 'Use {count} as a placeholder.', 'notifybay-waitlist-and-stock-alert-woo' )
								) }
								{ disabledInput(
									'1',
									__(
										'Only show if count is equal or higher than this.',
										'notifybay-waitlist-and-stock-alert-woo'
									),
									'number'
								) }
							</div>
						</div>
					),
				},
			] }
		/>
		<ProLockedSection
			slug="wishlist-display"
			title={ __( 'Wishlist Display', 'notifybay-waitlist-and-stock-alert-woo' ) }
			fields={ [
				{
					id: 'appearance_wishlistButtonText',
					label: __( 'Button Text', 'notifybay-waitlist-and-stock-alert-woo' ),
					render: () => disabledInput( 'Add to Wishlist' ),
				},
				{
					id: 'appearance_wishlistButtonClass',
					label: __( 'Button CSS Class', 'notifybay-waitlist-and-stock-alert-woo' ),
					render: () =>
						disabledInput(
							'',
							__(
								'Optional extra CSS class(es) added to the wishlist button.',
								'notifybay-waitlist-and-stock-alert-woo'
							)
						),
				},
				{
					id: 'appearance_wishlistSuccessMessage',
					label: __( 'Success Message', 'notifybay-waitlist-and-stock-alert-woo' ),
					render: () =>
						disabledInput(
							"Saved! We'll let you know if this goes on sale.",
							__( 'Shown after a product is added to the wishlist.', 'notifybay-waitlist-and-stock-alert-woo' )
						),
				},
				{
					id: 'appearance_showWishlistOnArchives',
					label: __( 'Archive Visibility', 'notifybay-waitlist-and-stock-alert-woo' ),
					render: () =>
						disabledCheckbox( __( 'Show on shop archives', 'notifybay-waitlist-and-stock-alert-woo' ) ),
				},
			] }
		/>
	</>
);

const EnginePreview: React.FC = () => (
	<>
		<ProLockedSection
			slug="fair-play"
			title={ __( 'Fair-Play Restock', 'notifybay-waitlist-and-stock-alert-woo' ) }
			description={ __(
				'Prevents customer frustration by only notifying a number of people equal to the actual units restocked.',
				'notifybay-waitlist-and-stock-alert-woo'
			) }
			fields={ [
				{
					id: 'engine_fairPlayEnabled',
					label: __( 'Fair-Play', 'notifybay-waitlist-and-stock-alert-woo' ),
					render: () => (
						<div className="notifybay-flex notifybay-flex-col notifybay-gap-4">
							{ disabledCheckbox(
								__(
									'Only notify leads equal to available stock',
									'notifybay-waitlist-and-stock-alert-woo'
								)
							) }
							<div className="notifybay-ml-6 notifybay-flex notifybay-flex-col notifybay-gap-2">
								{ disabledInput(
									'1',
									__( 'Notification multiplier per unit restocked.', 'notifybay-waitlist-and-stock-alert-woo' ),
									'number'
								) }
								{ disabledInput(
									'24',
									__( 'Reservation window, in hours.', 'notifybay-waitlist-and-stock-alert-woo' ),
									'number'
								) }
							</div>
						</div>
					),
				},
			] }
		/>
		<ProLockedSection
			slug="sales-attribution"
			title={ __( 'Sales Attribution', 'notifybay-waitlist-and-stock-alert-woo' ) }
			fields={ [
				{
					id: 'engine_conversionWindow',
					label: __( 'Conversion Window (Days)', 'notifybay-waitlist-and-stock-alert-woo' ),
					render: () => disabledInput( '7', undefined, 'number' ),
				},
			] }
		/>
		<ProLockedSection
			slug="urgency-alerts"
			title={ __( 'Urgency Alerts', 'notifybay-waitlist-and-stock-alert-woo' ) }
			fields={ [
				{
					id: 'engine_hurryEnabled',
					label: __( 'Hurry Logic', 'notifybay-waitlist-and-stock-alert-woo' ),
					render: () => (
						<div className="notifybay-flex notifybay-flex-col notifybay-gap-4">
							{ disabledCheckbox(
								__( 'Enable follow-up urgency alerts', 'notifybay-waitlist-and-stock-alert-woo' )
							) }
							<div className="notifybay-ml-6 notifybay-flex notifybay-flex-col notifybay-gap-2">
								{ disabledInput(
									'3',
									__( 'Trigger alert when stock falls below this level.', 'notifybay-waitlist-and-stock-alert-woo' ),
									'number'
								) }
								{ disabledInput(
									'48',
									__(
										'Minimum hours after restock before the hurry alert fires.',
										'notifybay-waitlist-and-stock-alert-woo'
									),
									'number'
								) }
							</div>
						</div>
					),
				},
			] }
		/>
	</>
);

interface ProPreviewSectionsProps {
	tab: string;
}

/**
 * "License Settings" is deliberately NOT previewed here — a license key box
 * is meaningless without Pro installed and would only confuse a free user.
 */
export const ProPreviewSections: React.FC< ProPreviewSectionsProps > = ( { tab } ) => {
	switch ( tab ) {
		case 'general':
			return <GeneralPreview />;
		case 'appearance':
			return <AppearancePreview />;
		case 'engine':
			return <EnginePreview />;
		default:
			return null;
	}
};
