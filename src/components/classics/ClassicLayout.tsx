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
			return __( 'Logs', 'notifybay' );
		}
		if ( path === '/dashboard' ) {
			return __( 'Dashboard', 'notifybay' );
		}
		if ( path === '/' ) {
			return __( 'Leads', 'notifybay' );
		}
		if ( path === '/settings' ) {
			return __( 'Settings', 'notifybay' );
		}
		return store.pluginData?.plugin_name || __( 'NotifyBay', 'notifybay' );
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
