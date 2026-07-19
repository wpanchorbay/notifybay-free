/* eslint-disable */
import React, { useState, useRef } from 'react';
import { createPortal } from 'react-dom';
import { SelectionCard } from './SelectionCard';

export interface CardOption {
	value: string;
	title: string;
	description: string;
	icon?: React.ReactNode;
	disabled?: boolean;
	variant?: 'buy_pro' | 'coming_soon';
}

interface CardRadioGroupProps {
	options: CardOption[];
	value: string;
	onChange: ( value: string ) => void;
	layout?: 'vertical' | 'horizontal' | 'responsive';
	className?: string;
	classNames?: {
		root?: string;
		card?: {
			root?: string;
			iconWrapper?: string;
			circle?: string;
			dot?: string;
			textWrapper?: string;
			title?: string;
			description?: string;
		};
	};
}

export const CardRadioGroup: React.FC< CardRadioGroupProps > = ( {
	options,
	value,
	onChange,
	layout = 'responsive',
	className = '',
	classNames,
} ) => {
	let containerClass = '';

	switch ( layout ) {
		case 'vertical':
			containerClass =
				'notifybay-flex notifybay-flex-col notifybay-gap-4';
			break;
		case 'horizontal':
			containerClass =
				'notifybay-flex notifybay-flex-row notifybay-gap-4 notifybay-overflow-x-auto notifybay-pb-2'; // Added overflow handling for safe horizontal scrolling if needed
			break;
		case 'responsive':
		default:
			containerClass =
				'notifybay-grid notifybay-grid-cols-1 md:!notifybay-grid-cols-2 notifybay-gap-4';
			break;
	}

	// Tooltip State
	const [ tooltipState, setTooltipState ] = useState< {
		visible: boolean;
		top: number;
		left: number;
		width: number;
	} | null >( null );

	const hoverTimeoutRef = useRef< number | null >( null );
	const tooltipRef = useRef< HTMLDivElement >( null );

	const handleCardMouseEnter = (
		e: React.MouseEvent< HTMLDivElement >,
		isPro: boolean
	) => {
		if ( hoverTimeoutRef.current ) {
			clearTimeout( hoverTimeoutRef.current );
			hoverTimeoutRef.current = null;
		}

		if ( isPro ) {
			const rect = e.currentTarget.getBoundingClientRect();
			// Calculate center position
			const centerX = rect.left + rect.width / 2;
			// Position above the card
			const topY = rect.top;

			setTooltipState( {
				visible: true,
				top: topY,
				left: centerX,
				width: rect.width,
			} );
		} else {
			setTooltipState( null );
		}
	};

	const handleCardMouseLeave = () => {
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
				className={ `${ containerClass } ${ className } ${
					classNames?.root || ''
				}` }
			>
				{ options.map( ( option ) => (
					<SelectionCard
						key={ option.value }
						title={ option.title }
						description={ option.description }
						selected={ value === option.value }
						onClick={ () => onChange( option.value ) }
						icon={ option.icon }
						disabled={ option.disabled }
						variant={ option.variant }
						onMouseEnter={ ( e ) =>
							handleCardMouseEnter(
								e,
								option.variant === 'buy_pro'
							)
						}
						onMouseLeave={ handleCardMouseLeave }
						classNames={ classNames?.card }
					/>
				) ) }
			</div>

			{ /* Tooltip Portal */ }
			{ tooltipState?.visible &&
				createPortal(
					<div
						ref={ tooltipRef }
						className="notifybay-fixed notifybay-z-[50001] notifybay-flex notifybay-flex-col notifybay-items-center notifybay-gap-1.5 notifybay-bg-gray-900 notifybay-text-white notifybay-text-xs notifybay-p-2 notifybay-min-w-[140px] notifybay-rounded-md notifybay-shadow-lg"
						style={ {
							top: tooltipState.top - 10, // Slight offset upwards from the card top
							left: tooltipState.left,
							transform: 'translate(-50%, -100%)',
						} }
						onMouseEnter={ handleTooltipMouseEnter }
						onMouseLeave={ handleTooltipMouseLeave }
					>
						<span className="notifybay-font-medium notifybay-whitespace-nowrap">
							Upgrade to unlock
						</span>
						<a
							href="#"
							target="_blank"
							onClick={ ( e ) => e.preventDefault() }
							className="notifybay-w-full notifybay-bg-[#f02a74] hover:!notifybay-bg-[#e71161] notifybay-text-white hover:!notifybay-text-white notifybay-font-bold notifybay-py-1.5 notifybay-px-3 notifybay-transition-colors focus:notifybay-outline-none focus:notifybay-ring-0 notifybay-cursor-pointer notifybay-text-center notifybay-rounded"
						>
							Buy Pro
						</a>
						{ /* Tooltip Arrow */ }
						<div className="notifybay-absolute notifybay-top-full notifybay-left-1/2 -notifybay-translate-x-1/2 notifybay-border-4 notifybay-border-transparent notifybay-border-t-gray-900"></div>
					</div>,
					document.body
				) }
		</>
	);
};
