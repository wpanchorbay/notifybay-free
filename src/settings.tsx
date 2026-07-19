import { createRoot } from 'react-dom/client';
import './styles/index.scss';
import SettingsApp from './SettingsApp';
import { buildProRegistry } from './utils/proRegistry';

// Expose a curated set of internals for NotifyBay Pro's settings bundle to
// reuse. See src/utils/proRegistry.ts for the contract.
( window as any ).notifybay = buildProRegistry();

const rootElement = document.getElementById( 'notifybay' );
if ( rootElement ) {
	createRoot( rootElement ).render( <SettingsApp /> );
}
