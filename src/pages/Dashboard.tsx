import { FC } from 'react';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { BarChart3, Lock } from 'lucide-react';

/**
 * The revenue/conversion analytics dashboard is a premium add-on (NotifyBay Pro)
 * feature — its REST backend (`/admin/stats`) only exists when the add-on is
 * active. Free renders this thin shell and lets the add-on fill it in via the
 * `notifybay_dashboard_widgets` filter.
 */
const Dashboard: FC = () => {
	const widgets = applyFilters( 'notifybay_dashboard_widgets', null );

	if ( widgets ) {
		return <>{ widgets }</>;
	}

	return (
		<div className="notifybay-animate-fade-in">
			<div className="notifybay-bg-white notifybay-rounded-[12px] notifybay-p-[40px] notifybay-border notifybay-border-gray-200 notifybay-text-center">
				<div className="notifybay-flex notifybay-justify-center notifybay-mb-[16px]">
					<div className="notifybay-w-[56px] notifybay-h-[56px] notifybay-rounded-full notifybay-bg-blue-50 notifybay-flex notifybay-items-center notifybay-justify-center">
						<BarChart3 className="notifybay-w-[28px] notifybay-h-[28px] notifybay-text-blue-500" />
					</div>
				</div>
				<h3 className="notifybay-text-[18px] notifybay-font-[600] notifybay-text-gray-900 notifybay-mb-[8px]">
					{ __( 'Revenue Analytics is a Pro feature', 'notifybay-waitlist-and-stock-alert-woo' ) }
				</h3>
				<p className="notifybay-text-[14px] notifybay-text-gray-500 notifybay-max-w-[420px] notifybay-mx-auto notifybay-mb-[20px]">
					{ __(
						'See potential vs. recovered revenue, historical demand trends, and your most-wanted products with NotifyBay Pro.',
						'notifybay-waitlist-and-stock-alert-woo'
					) }
				</p>
				<div className="notifybay-inline-flex notifybay-items-center notifybay-gap-[6px] notifybay-text-[12px] notifybay-text-gray-400">
					<Lock className="notifybay-w-[14px] notifybay-h-[14px]" />
					{ __( 'Activate NotifyBay Pro to unlock this dashboard.', 'notifybay-waitlist-and-stock-alert-woo' ) }
				</div>
			</div>
		</div>
	);
};

export default Dashboard;
