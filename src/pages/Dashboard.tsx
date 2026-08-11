import { FC } from 'react';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { useWpabStore } from '../store/wpabStore';

const cardClass =
	'notifybay-bg-white notifybay-rounded-[12px] notifybay-p-[20px] notifybay-border notifybay-border-gray-200';

// Illustrative sample data only — never fetched, never real. Shapes match
// what NotifyBay Pro's real analytics dashboard renders (see
// notifybaypro/src/pro-extensions.tsx ProDashboard) so the preview looks like
// the real thing.
const SAMPLE_TREND = [
	{ label: 'May', count: 8 },
	{ label: 'Jun', count: 14 },
	{ label: 'Jul', count: 11 },
	{ label: 'Aug', count: 22 },
	{ label: 'Sep', count: 19 },
	{ label: 'Oct', count: 27 },
];
const SAMPLE_RESTOCKS = [
	{ name: 'Classic Leather Wallet', count: 34 },
	{ name: 'Everyday Tote Bag', count: 21 },
	{ name: 'Wireless Earbuds Pro', count: 17 },
];
const SAMPLE_WISHLIST = [
	{ name: 'Ceramic Pour-Over Set', count: 29 },
	{ name: 'Weighted Blanket', count: 18 },
	{ name: 'Standing Desk Converter', count: 12 },
];

const SampleTrendChart: FC = () => {
	const max = Math.max( 1, ...SAMPLE_TREND.map( ( d ) => d.count ) );
	return (
		<div className="notifybay-flex notifybay-flex-col notifybay-gap-[6px]">
			{ SAMPLE_TREND.map( ( d ) => (
				<div key={ d.label } className="notifybay-flex notifybay-items-center notifybay-gap-[8px]">
					<span className="notifybay-text-[12px] notifybay-text-gray-500" style={ { width: 56 } }>
						{ d.label }
					</span>
					<div className="notifybay-flex-1 notifybay-bg-gray-100 notifybay-rounded-[4px]">
						<div
							className="notifybay-bg-blue-300 notifybay-rounded-[4px]"
							style={ { width: `${ Math.round( ( d.count / max ) * 100 ) }%`, height: 16 } }
						/>
					</div>
					<span className="notifybay-text-[12px] notifybay-font-[600]" style={ { width: 32, textAlign: 'right' } }>
						{ d.count }
					</span>
				</div>
			) ) }
		</div>
	);
};

const SampleTopTable: FC< { title: string; rows: Array< { name: string; count: number } > } > = ( {
	title,
	rows,
} ) => (
	<div className={ cardClass }>
		<h4 className="notifybay-font-[600] notifybay-mb-[12px]">{ title }</h4>
		<table className="notifybay-w-full notifybay-text-[13px]">
			<tbody>
				{ rows.map( ( r ) => (
					<tr key={ r.name }>
						<td className="notifybay-py-[6px] notifybay-border-b notifybay-border-gray-100">{ r.name }</td>
						<td className="notifybay-py-[6px] notifybay-border-b notifybay-border-gray-100 notifybay-text-right notifybay-font-[600]">
							{ r.count }
						</td>
					</tr>
				) ) }
			</tbody>
		</table>
	</div>
);

/**
 * The revenue/conversion analytics dashboard is a NotifyBay Pro feature — its
 * REST backend (`/admin/stats`) only exists when Pro is active. Free renders
 * this thin shell and lets Pro fill it in via the `notifybay_dashboard_widgets`
 * filter; when Pro is absent, Free shows a disabled, illustrative replica of
 * the real dashboard instead, with a CTA to buy Pro.
 */
const Dashboard: FC = () => {
	const widgets = applyFilters( 'notifybay_dashboard_widgets', null );
	const store = useWpabStore();
	const buyProUrl = store.pluginData?.buy_pro_url || '#';

	if ( widgets ) {
		return <>{ widgets }</>;
	}

	return (
		<div className="notifybay-animate-fade-in">
			<div className="notifybay-flex notifybay-items-center notifybay-justify-between notifybay-mb-[16px]">
				<div>
					<h3 className="notifybay-text-[16px] notifybay-font-[600] notifybay-text-gray-900 notifybay-mb-[4px]">
						{ __( 'Revenue Analytics is a Pro feature', 'notifybay-waitlist-and-stock-alert-woo' ) }
					</h3>
					<p className="notifybay-text-[13px] notifybay-text-gray-500">
						{ __(
							'See potential vs. recovered revenue, historical demand trends, and your most-wanted products with NotifyBay Pro.',
							'notifybay-waitlist-and-stock-alert-woo'
						) }
					</p>
				</div>
				<a
					href={ buyProUrl }
					target="_blank"
					rel="noopener noreferrer"
					className="button button-primary"
					style={ { backgroundColor: '#f02a74', borderColor: '#e71161', color: '#fff' } }
				>
					{ __( 'Unlock with Pro', 'notifybay-waitlist-and-stock-alert-woo' ) }
				</a>
			</div>

			<div
				data-notifybay-pro-preview="dashboard"
				className="notifybay-pro-locked notifybay-relative notifybay-opacity-60 notifybay-flex notifybay-flex-col notifybay-gap-[16px]"
				style={ { pointerEvents: 'none', cursor: 'not-allowed' } }
			>
				<div className="notifybay-grid notifybay-grid-cols-1 md:notifybay-grid-cols-4 notifybay-gap-[16px]">
					<div className={ cardClass }>
						<h4>{ __( 'Active Waitlist', 'notifybay-waitlist-and-stock-alert-woo' ) }</h4>
						<span className="notifybay-text-[28px] notifybay-font-[700]">128</span>
					</div>
					<div className={ cardClass }>
						<h4>{ __( 'Price Watchers', 'notifybay-waitlist-and-stock-alert-woo' ) }</h4>
						<span className="notifybay-text-[28px] notifybay-font-[700]">64</span>
					</div>
					<div className={ cardClass }>
						<h4>{ __( 'Converted Sales', 'notifybay-waitlist-and-stock-alert-woo' ) }</h4>
						<span className="notifybay-text-[28px] notifybay-font-[700] notifybay-text-green-600">37</span>
					</div>
					<div className={ cardClass }>
						<h4>{ __( 'Potential Recovery', 'notifybay-waitlist-and-stock-alert-woo' ) }</h4>
						<span className="notifybay-text-[22px] notifybay-font-[700] notifybay-text-blue-600">$2,140</span>
					</div>
				</div>

				<div className={ cardClass }>
					<h4 className="notifybay-font-[600] notifybay-mb-[12px]">
						{ __( 'New Leads Trend', 'notifybay-waitlist-and-stock-alert-woo' ) }
					</h4>
					<SampleTrendChart />
				</div>

				<div className="notifybay-grid notifybay-grid-cols-1 md:notifybay-grid-cols-2 notifybay-gap-[16px]">
					<SampleTopTable
						title={ __( 'Top Requested Restocks', 'notifybay-waitlist-and-stock-alert-woo' ) }
						rows={ SAMPLE_RESTOCKS }
					/>
					<SampleTopTable
						title={ __( 'Top Price-Drop Wishes', 'notifybay-waitlist-and-stock-alert-woo' ) }
						rows={ SAMPLE_WISHLIST }
					/>
				</div>
			</div>
		</div>
	);
};

export default Dashboard;
