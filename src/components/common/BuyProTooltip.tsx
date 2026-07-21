/* eslint-disable */
import React, { useState, useRef, ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { LockKeyhole } from 'lucide-react';
import { __ } from '@wordpress/i18n';
import { useWpabStore } from '../../store/wpabStore';

interface BuyProTooltipProps {
	children: ReactNode;
	className?: string;
}

export const BuyProTooltip: React.FC< BuyProTooltipProps > = ( {
	children,
	className = '',
} ) => {
	const store = useWpabStore();
	const [ tooltipState, setTooltipState ] = useState< {
		visible: boolean;
		top: number;
		left: number;
	} | null >( null );

	const hoverTimeoutRef = useRef< number | null >( null );
	const tooltipRef = useRef< HTMLDivElement >( null );

	const handleMouseEnter = ( e: React.MouseEvent< HTMLDivElement > ) => {
		if ( hoverTimeoutRef.current ) {
			clearTimeout( hoverTimeoutRef.current );
			hoverTimeoutRef.current = null;
		}

		const rect = e.currentTarget.getBoundingClientRect();
		setTooltipState( {
			visible: true,
			top: rect.top,
			left: rect.left + rect.width / 2,
		} );
	};

	const handleMouseLeave = () => {
		hoverTimeoutRef.current = window.setTimeout( () => {
			setTooltipState( null );
		}, 150 );
	};

	const handleTooltipMouseEnter = () => {
		if ( hoverTimeoutRef.current ) {
			clearTimeout( hoverTimeoutRef.current );
			hoverTimeoutRef.current = null;
		}
	};

	const handleTooltipMouseLeave = () => {
		hoverTimeoutRef.current = window.setTimeout( () => {
			setTooltipState( null );
		}, 150 );
	};

	return (
		<>
			<div
				className={ `notifybay-relative notifybay-inline-block ${ className }` }
				onMouseEnter={ handleMouseEnter }
				onMouseLeave={ handleMouseLeave }
			>
				{ children }
				<div className="notifybay-absolute notifybay-right-2 notifybay-top-1/2 -notifybay-translate-y-1/2 notifybay-pointer-events-none">
					<LockKeyhole className="notifybay-w-3.5 notifybay-h-3.5 notifybay-text-[#f02a74]" />
				</div>
			</div>
			{ tooltipState?.visible &&
				createPortal(
					<div
						ref={ tooltipRef }
						className="notifybay-fixed notifybay-z-[50001] notifybay-flex notifybay-flex-col notifybay-items-center notifybay-gap-1.5 notifybay-bg-gray-900 notifybay-text-white notifybay-text-xs notifybay-p-2 notifybay-min-w-[140px]"
						style={ {
							top: tooltipState.top + 5, // Adjusted to user preference
							left: tooltipState.left,
							transform: 'translate(-50%, -100%)',
						} }
						onMouseEnter={ handleTooltipMouseEnter }
						onMouseLeave={ handleTooltipMouseLeave }
					>
						<span className="notifybay-font-medium notifybay-whitespace-nowrap">
							{ __( 'Upgrade to unlock', 'notifybay-waitlist-and-stock-alert-woo' ) }
						</span>
						<a
							href={ store.pluginData?.support_uri || '#' }
							target="_blank"
							rel="noopener noreferrer"
							className="notifybay-w-full notifybay-bg-[#f02a74] hover:!notifybay-bg-[#e71161] notifybay-text-white hover:!notifybay-text-white notifybay-font-bold notifybay-py-1.5 notifybay-px-3 notifybay-transition-colors focus:notifybay-outline-none focus:notifybay-ring-0 notifybay-cursor-pointer notifybay-text-center notifybay-no-underline"
						>
							{ __( 'Buy Pro', 'notifybay-waitlist-and-stock-alert-woo' ) }
						</a>
						{ /* Tooltip Arrow */ }
						<div className="notifybay-absolute notifybay-top-full notifybay-left-1/2 -notifybay-translate-x-1/2 notifybay-border-4 notifybay-border-transparent notifybay-border-t-gray-900"></div>
					</div>,
					document.body
				) }
		</>
	);
};
