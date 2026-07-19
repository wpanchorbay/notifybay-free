import { createRoot } from 'react-dom/client';
import './styles/index.scss';
import AdminApp from './AdminApp';
import { buildProRegistry } from './utils/proRegistry';

// Expose a curated set of internals for NotifyBay Pro's admin bundle to reuse.
// See src/utils/proRegistry.ts for the contract.
( window as any ).notifybay = buildProRegistry();

const rootElement = document.getElementById( 'notifybay' );
if ( rootElement ) {
	createRoot( rootElement ).render( <AdminApp /> );
}
