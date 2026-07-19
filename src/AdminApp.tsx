import React, { useEffect } from 'react';
import Dashboard from './pages/Dashboard';
import { HashRouter, Routes, Route, useNavigate, useLocation } from 'react-router-dom';
import { WpabProvider } from './store/wpabStore';
import { ToastProvider } from './store/toast/use-toast';
import ClassicShowcase from './pages/ClassicShowcase';
import Leads from './pages/Leads';
import Wizard from './pages/Wizard';
import { ToastContainer } from './components/common/ToastContainer';
import { useMenuSync } from './utils/useMenuSync';
import { ClassicLayout } from './components/classics';

function AdminApp() {
	return (
		<WpabProvider>
			<ToastProvider>
				<ToastContainer />
				<HashRouter>
					<MenuSyncProvider>
						<WizardRedirect />
						<Routes>
							{ /*
                BOILERPLATE NOTE:
                - Use <ClassicLayout /> for native WordPress/WooCommerce aesthetics.
                - Use <AppLayout /> (from components/common) for modern, custom dashboard aesthetics.
              */ }
							<Route path="wizard" element={ <Wizard /> } />
							<Route element={ <ClassicLayout /> }>
								<Route path="/" element={ <Leads /> } />
								<Route path="dashboard" element={ <Dashboard /> } />
								<Route
									path="components-classic"
									element={ <ClassicShowcase /> }
								/>
							</Route>
						</Routes>
					</MenuSyncProvider>
				</HashRouter>
			</ToastProvider>
		</WpabProvider>
	);
}

const MenuSyncProvider = ( { children }: { children: React.ReactNode } ) => {
	useMenuSync();
	return <>{ children }</>;
};

/**
 * On first run (show_wizard localized true), route the admin SPA to the setup
 * wizard once. Runs only while sitting at the root path so it never hijacks
 * deliberate navigation elsewhere.
 */
const WizardRedirect = () => {
	const navigate = useNavigate();
	const location = useLocation();

	useEffect( () => {
		const showWizard = !! ( window as any ).notifyBay_Localize?.show_wizard;
		if ( showWizard && ( location.pathname === '/' || location.pathname === '' ) ) {
			navigate( '/wizard' );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	return null;
};

export default AdminApp;
