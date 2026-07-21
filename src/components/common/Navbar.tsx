/* eslint-disable */
import { useState, useEffect, FC } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { useWpabStore } from '../../store/wpabStore';

interface MenuLink {
	label: string;
	path: string;
}

const Navbar: FC = () => {
	const [ activeTab, setActiveTab ] = useState< string >( 'dashboard' );
	const [ isMobileMenuOpen, setIsMobileMenuOpen ] =
		useState< boolean >( false );
	const store = useWpabStore();

	const menus: MenuLink[] = [
		{
			label: __( 'Dashboard', 'notifybay-waitlist-and-stock-alert-woo' ),
			path: '/',
		},
		// Add your menu items here
		{
			label: __( 'Logs', 'notifybay-waitlist-and-stock-alert-woo' ),
			path: '/logs',
		},
		{
			label: __( 'Components', 'notifybay-waitlist-and-stock-alert-woo' ),
			path: '/components',
		},
		{
			label: __( 'Components (Classic)', 'notifybay-waitlist-and-stock-alert-woo' ),
			path: '/components-classic',
		},
		// {
		//   label: __("Settings", "notifybay-waitlist-and-stock-alert-woo"),
		//   path: "/settings",
		// },
	];

	const location = useLocation();
	const currentPath = location.pathname;
	const navigate = useNavigate();

	useEffect( () => {
		const basePath = '/' + ( currentPath.split( '/' )[ 1 ] || '' );
		setActiveTab( basePath );
	}, [ currentPath ] );

	return (
		<>
			<div className="notifybay-bg-white notifybay-p-0 !notifybay-border-0 !notifybay-border-b !notifybay-border-gray-300 notifybay-z-50 notifybay-relative">
				<div className="notifybay-flex notifybay-px-[12px] notifybay-justify-between notifybay-items-center notifybay-flex-wrap md:notifybay-flex-nowrap notifybay-gap-[4px] notifybay-relative">
					<div className="notifybay-flex notifybay-items-center notifybay-gap-[4px] notifybay-py-[12px]">
						<span className="notifybay-font-[700] notifybay-text-[16px] notifybay-text-gray-900">
							{ store.pluginData?.plugin_name || 'NotifyBay' }
						</span>
					</div>
					<div
						className={ `notifybay-flex-1 md:notifybay-flex-none notifybay-flex-col md:notifybay-flex-row notifybay-justify-stretch md:notifybay-items-center notifybay-absolute md:notifybay-relative notifybay-top-[102%] md:notifybay-top-auto notifybay-left-0 notifybay-w-full md:notifybay-w-auto notifybay-gap-0 md:notifybay-gap-[6px] notifybay-bg-white !notifybay-border-0 ${
							isMobileMenuOpen
								? 'notifybay-flex'
								: 'notifybay-hidden md:notifybay-flex'
						}` }
					>
						<nav className="notifybay-items-stretch md:notifybay-items-center notifybay-gap-0 notifybay-flex notifybay-flex-col md:notifybay-flex-row notifybay-w-full">
							{ menus.map( ( menu ) => (
								<span
									key={ menu.path }
									className={ `notifybay-text-default notifybay-font-[700]
                    notifybay-cursor-pointer notifybay-py-[8px] notifybay-px-[16px] notifybay-border-b md:notifybay-border-b-0 notifybay-border-gray-300 last:notifybay-border-gray-300 ${
						activeTab === menu.path
							? 'notifybay-text-blue-800 notifybay-bg-gray-100 notifybay-rounded-[0] md:notifybay-rounded-[8px]'
							: 'notifybay-text-gray-800 hover:notifybay-text-blue-800'
					}` }
									onClick={ () => {
										navigate( menu.path );
										setIsMobileMenuOpen( false );
									} }
								>
									{ menu.label }
								</span>
							) ) }
						</nav>
					</div>
					<button
						className="notifybay-flex md:notifybay-hidden notifybay-items-center notifybay-gap-[2px] notifybay-text-gray-800 hover:notifybay-text-blue-800"
						onClick={ () =>
							setIsMobileMenuOpen( ! isMobileMenuOpen )
						}
						aria-label={
							isMobileMenuOpen ? 'Close menu' : 'Open menu'
						}
						aria-expanded={ isMobileMenuOpen }
					>
						<svg
							width="24"
							height="24"
							viewBox="0 0 24 24"
							fill="none"
							xmlns="http://www.w3.org/2000/svg"
							className="notifybay-transition-all notifybay-duration-300 notifybay-ease-in-out"
							aria-hidden="true"
						>
							{ isMobileMenuOpen ? (
								<>
									<path
										d="M6 6L18 18"
										stroke="currentColor"
										strokeWidth="2"
										strokeLinecap="round"
										strokeLinejoin="round"
									/>
									<path
										d="M6 18L18 6"
										stroke="currentColor"
										strokeWidth="2"
										strokeLinecap="round"
										strokeLinejoin="round"
									/>
								</>
							) : (
								<>
									<path
										d="M3 12H21"
										stroke="currentColor"
										strokeWidth="2"
										strokeLinecap="round"
										strokeLinejoin="round"
									/>
									<path
										d="M3 6H21"
										stroke="currentColor"
										strokeWidth="2"
										strokeLinecap="round"
										strokeLinejoin="round"
									/>
									<path
										d="M3 18H21"
										stroke="currentColor"
										strokeWidth="2"
										strokeLinecap="round"
										strokeLinejoin="round"
									/>
								</>
							) }
						</svg>
					</button>
				</div>
			</div>
			{ isMobileMenuOpen && (
				<div
					className="notifybay-fixed notifybay-top-0 notifybay-left-0 notifybay-w-full notifybay-h-full notifybay-bg-black notifybay-opacity-60 notifybay-z-40 md:notifybay-hidden"
					onClick={ () => setIsMobileMenuOpen( false ) }
				/>
			) }
		</>
	);
};

export default Navbar;
