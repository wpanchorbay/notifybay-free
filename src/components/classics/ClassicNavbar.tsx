/* eslint-disable */
import { FC } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { __ } from '@wordpress/i18n';

interface MenuLink {
	label: string;
	path: string;
}

const ClassicNavbar: FC = () => {
	const location = useLocation();
	const navigate = useNavigate();
	const context = ( window as any ).notifyBay_Localize?.context || 'options';

	if ( context === 'settings' ) {
		return null;
	}

	const menus: MenuLink[] = [
		{
			label: __( 'Dashboard', 'notifybay-waitlist-and-stock-alert-woo' ),
			path: '/',
		},
		{
			label: __( 'Items', 'notifybay-waitlist-and-stock-alert-woo' ),
			path: '/items',
		},
		{
			label: __( 'Logs', 'notifybay-waitlist-and-stock-alert-woo' ),
			path: '/logs',
		},
	];

	const isActive = ( path: string ) => {
		// if (path === "/" && currentPath === "/") return true;
		// if (path !== "/" && currentPath.startsWith(path)) return true;
		return false;
	};

	return (
		<nav className="notifybay-flex notifybay-items-center notifybay-gap-6 notifybay-border-b notifybay-border-gray-200 notifybay-mb-8 notifybay-ignore-preflight notifybay-p-x-page-default notifybay-bg-white notifybay-overflow-x-auto notifybay-whitespace-nowrap notifybay-scrollbar-hide">
			{ menus.map( ( menu ) => (
				<a
					key={ menu.path }
					href={ `#${ menu.path }` }
					className={ `
            notifybay-pb-3 notifybay-text-[14px] notifybay-transition-all notifybay-no-underline notifybay-relative focus:notifybay-outline-none
            focus:notifybay-border-t-0 focus:notifybay-border-l-0 focus:notifybay-border-r-0 focus:notifybay-shadow-none
            ${
				isActive( menu.path )
					? 'notifybay-text-gray-900 notifybay-font-bold'
					: 'notifybay-text-gray-600 notifybay-font-normal hover:notifybay-text-[#2271b1]'
			}
          ` }
					onClick={ ( e ) => {
						e.preventDefault();
						navigate( menu.path );
					} }
				>
					{ menu.label }
					{ isActive( menu.path ) && (
						<div className="notifybay-absolute notifybay-bottom-[-1px] notifybay-left-0 notifybay-w-full notifybay-h-[3px] notifybay-bg-[#2271b1]" />
					) }
				</a>
			) ) }
		</nav>
	);
};

export default ClassicNavbar;
