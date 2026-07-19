/* eslint-disable */
import React, { useState, useRef, useEffect, ReactNode } from 'react';
import { createPortal } from 'react-dom';

export type TooltipPosition = 'top' | 'bottom' | 'left' | 'right';

interface TooltipProps {
	children: ReactNode;
	content: ReactNode;
	position?: TooltipPosition;
	delay?: number;
	className?: string;
	disabled?: boolean;
	classNames?: {
		root?: string;
		trigger?: string;
		content?: string;
		arrow?: string;
	};
	docLink?: string;
}

export const Tooltip: React.FC< TooltipProps > = ( {
	children,
	content,
	position = 'top',
	delay = 200,
	className = '',
	disabled = false,
	classNames,
	docLink,
} ) => {
	const [ isVisible, setIsVisible ] = useState( false );
	const [ coords, setCoords ] = useState( { top: 0, left: 0 } );
	const triggerRef = useRef< HTMLDivElement >( null );
	const tooltipRef = useRef< HTMLDivElement >( null );
	const showTimeoutRef = useRef< NodeJS.Timeout | null >( null );
	const hideTimeoutRef = useRef< NodeJS.Timeout | null >( null );

	const handleMouseEnter = () => {
		if ( disabled ) {
			return;
		}

		// If there's a timeout to hide the tooltip, cancel it
		if ( hideTimeoutRef.current ) {
			clearTimeout( hideTimeoutRef.current );
			hideTimeoutRef.current = null;
		}

		// Set a timeout to show the tooltip if it's not already visible
		if ( ! showTimeoutRef.current && ! isVisible ) {
			showTimeoutRef.current = setTimeout( () => {
				setIsVisible( true );
			}, delay );
		}
	};

	const handleMouseLeave = () => {
		// If there's a timeout to show the tooltip, cancel it
		if ( showTimeoutRef.current ) {
			clearTimeout( showTimeoutRef.current );
			showTimeoutRef.current = null;
		}

		// Set a short timeout to hide the tooltip, allowing the user to move their cursor to it
		hideTimeoutRef.current = setTimeout( () => {
			setIsVisible( false );
		}, 100 ); // A small delay before hiding
	};

	const calculatePosition = () => {
		if ( ! triggerRef.current || ! tooltipRef.current ) {
			return;
		}

		const triggerRect = triggerRef.current.getBoundingClientRect();
		const tooltipRect = tooltipRef.current.getBoundingClientRect();
		const gap = 8;

		let top = 0;
		let left = 0;

		switch ( position ) {
			case 'top':
				top = triggerRect.top - tooltipRect.height - gap;
				left =
					triggerRect.left +
					( triggerRect.width - tooltipRect.width ) / 2;
				break;
			case 'bottom':
				top = triggerRect.bottom + gap;
				left =
					triggerRect.left +
					( triggerRect.width - tooltipRect.width ) / 2;
				break;
			case 'left':
				top =
					triggerRect.top +
					( triggerRect.height - tooltipRect.height ) / 2;
				left = triggerRect.left - tooltipRect.width - gap;
				break;
			case 'right':
				top =
					triggerRect.top +
					( triggerRect.height - tooltipRect.height ) / 2;
				left = triggerRect.right + gap;
				break;
		}

		// Boundary collision checks to keep the tooltip within the viewport
		const padding = 8;
		if ( left < padding ) {
			left = padding;
		}
		if ( left + tooltipRect.width > window.innerWidth - padding ) {
			left = window.innerWidth - tooltipRect.width - padding;
		}
		if ( top < padding ) {
			top = padding;
		}
		if ( top + tooltipRect.height > window.innerHeight - padding ) {
			top = window.innerHeight - tooltipRect.height - padding;
		}

		setCoords( { top, left } );
	};

	useEffect( () => {
		if ( isVisible ) {
			calculatePosition();

			const handleResizeOrScroll = () => calculatePosition();
			window.addEventListener( 'resize', handleResizeOrScroll );
			window.addEventListener( 'scroll', handleResizeOrScroll, true );

			return () => {
				window.removeEventListener( 'resize', handleResizeOrScroll );
				window.removeEventListener(
					'scroll',
					handleResizeOrScroll,
					true
				);
			};
		}
	}, [ isVisible, position ] );

	// Cleanup timeouts on unmount
	useEffect( () => {
		return () => {
			if ( showTimeoutRef.current ) {
				clearTimeout( showTimeoutRef.current );
			}
			if ( hideTimeoutRef.current ) {
				clearTimeout( hideTimeoutRef.current );
			}
		};
	}, [] );

	const arrowClasses = {
		top: 'notifybay-top-full notifybay-left-1/2 notifybay--translate-x-1/2 notifybay-border-t-gray-900',
		bottom: 'notifybay-bottom-full notifybay-left-1/2 notifybay--translate-x-1/2 notifybay-border-b-gray-900',
		left: 'notifybay-left-full notifybay-top-1/2 notifybay--translate-y-1/2 notifybay-border-l-gray-900',
		right: 'notifybay-right-full notifybay-top-1/2 notifybay--translate-y-1/2 notifybay-border-r-gray-900',
	}[ position ];

	return (
		<>
			<div
				ref={ triggerRef }
				onMouseEnter={ handleMouseEnter }
				onMouseLeave={ handleMouseLeave }
				onFocus={ handleMouseEnter }
				onBlur={ handleMouseLeave }
				className={ `notifybay-inline-block ${
					classNames?.trigger || ''
				}` }
			>
				{ children }
			</div>
			{ isVisible &&
				createPortal(
					<div
						ref={ tooltipRef }
						onMouseEnter={ handleMouseEnter }
						onMouseLeave={ handleMouseLeave }
						className={ `
            notifybay-fixed notifybay-z-[60] notifybay-max-w-[300px]
            ${ className }
            ${ classNames?.root || '' }
          ` }
						style={ {
							top: coords.top,
							left: coords.left,
						} }
					>
						<div
							className={ `notifybay-animate-tooltip notifybay-relative notifybay-px-2.5 notifybay-py-1.5 notifybay-bg-gray-900 notifybay-text-white notifybay-text-xs notifybay-rounded notifybay-shadow-lg ${
								classNames?.content || ''
							}` }
						>
							{ content }
							{ docLink && (
								<>
									{ ' ' }
									<a
										href={ docLink }
										target="_blank"
										rel="noopener noreferrer"
										className="notifybay-text-blue-400 hover:notifybay-text-blue-300 notifybay-underline"
									>
										Read More
									</a>
								</>
							) }
							<div
								className={ `
                notifybay-absolute notifybay-border-[5px] notifybay-border-transparent
                ${ arrowClasses }
                ${ classNames?.arrow || '' }
              ` }
							/>
						</div>
					</div>,
					document.body
				) }
		</>
	);
};
