/* eslint-disable */
import { FC, ReactNode } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { useWpabStore } from '../../store/wpabStore';

const ClassicLayout: FC = () => {
	const store = useWpabStore();
	const location = useLocation();

	// Determine page title based on route
	const getPageTitle = () => {
		const path = location.pathname;
		if ( path === '/logs' ) {
			return __( 'Logs', 'notifybay-waitlist-and-stock-alert-woo' );
		}
		if ( path === '/dashboard' ) {
			return __( 'Dashboard', 'notifybay-waitlist-and-stock-alert-woo' );
		}
		if ( path === '/' ) {
			return __( 'Leads', 'notifybay-waitlist-and-stock-alert-woo' );
		}
		if ( path === '/settings' ) {
			return __( 'Settings', 'notifybay-waitlist-and-stock-alert-woo' );
		}
		return store.pluginData?.plugin_name || __( 'NotifyBay', 'notifybay-waitlist-and-stock-alert-woo' );
	};

	const context = ( window as any ).notifyBay_Localize?.context || 'admin';

	return (
		<div className="">
			{ context !== 'settings' && (
				<h1 className="notifybay-ignore-preflight notifybay-font-[600] notifybay-text-[16px] notifybay-p-x-page-default notifybay-bg-white notifybay-m-0 notifybay-py-[18px]">
					{ getPageTitle() }
				</h1>
			) }
			<div className="notifybay-mt-2 notifybay-p-x-page-default">
				<Outlet />
			</div>
		</div>
	);
};

export default ClassicLayout;
