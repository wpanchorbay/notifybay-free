/* eslint-disable */
import React, { useState, useRef, useEffect } from 'react';

export type PopoverAlign =
	| 'top'
	| 'top-left'
	| 'top-right'
	| 'bottom'
	| 'bottom-left'
	| 'bottom-right'
	| 'left'
	| 'right';

interface PopoverProps {
	trigger: React.ReactNode;
	content: React.ReactNode;
	align?: PopoverAlign;
	className?: string;
	classNames?: {
		root?: string;
		triggerWrapper?: string;
		content?: string;
	};
}

export const Popover: React.FC< PopoverProps > = ( {
	trigger,
	content,
	align = 'bottom-left',
	className = '',
	classNames,
} ) => {
	const [ isOpen, setIsOpen ] = useState( false );
	const containerRef = useRef< HTMLDivElement >( null );

	// Close when clicking outside
	useEffect( () => {
		const handleClickOutside = ( event: MouseEvent ) => {
			if (
				containerRef.current &&
				! containerRef.current.contains( event.target as Node )
			) {
				setIsOpen( false );
			}
		};

		if ( isOpen ) {
			document.addEventListener( 'mousedown', handleClickOutside );
		}
		return () => {
			document.removeEventListener( 'mousedown', handleClickOutside );
		};
	}, [ isOpen ] );

	const toggle = () => setIsOpen( ! isOpen );

	// Position & Origin Logic
	let positionClasses = '';
	let originClass = '';

	switch ( align ) {
		case 'top':
			positionClasses =
				'notifybay-bottom-full notifybay-mb-2 notifybay-left-1/2 notifybay--translate-x-1/2';
			originClass = 'notifybay-origin-bottom';
			break;
		case 'top-left':
			positionClasses =
				'notifybay-bottom-full notifybay-mb-2 notifybay-left-0';
			originClass = 'notifybay-origin-bottom-left';
			break;
		case 'top-right':
			positionClasses =
				'notifybay-bottom-full notifybay-mb-2 notifybay-right-0';
			originClass = 'notifybay-origin-bottom-right';
			break;
		case 'bottom':
			positionClasses =
				'notifybay-top-full notifybay-mt-2 notifybay-left-1/2 notifybay--translate-x-1/2';
			originClass = 'notifybay-origin-top';
			break;
		case 'bottom-left':
			positionClasses =
				'notifybay-top-full notifybay-mt-2 notifybay-left-0';
			originClass = 'notifybay-origin-top-left';
			break;
		case 'bottom-right':
			positionClasses =
				'notifybay-top-full notifybay-mt-2 notifybay-right-0';
			originClass = 'notifybay-origin-top-right';
			break;
		case 'left':
			positionClasses =
				'notifybay-right-full notifybay-mr-2 notifybay-top-1/2 notifybay--translate-y-1/2';
			originClass = 'notifybay-origin-right';
			break;
		case 'right':
			positionClasses =
				'notifybay-left-full notifybay-ml-2 notifybay-top-1/2 notifybay--translate-y-1/2';
			originClass = 'notifybay-origin-left';
			break;
		default:
			positionClasses =
				'notifybay-top-full notifybay-mt-2 notifybay-left-0';
			originClass = 'notifybay-origin-top-left';
	}

	// Transition classes (Opacity + Scale)
	const transitionClasses = isOpen
		? 'notifybay-opacity-100 notifybay-scale-100 notifybay-pointer-events-auto'
		: 'notifybay-opacity-0 notifybay-scale-95 notifybay-pointer-events-none';

	return (
		<div
			ref={ containerRef }
			className={ `notifybay-relative notifybay-inline-block ${ className } ${
				classNames?.root || ''
			}` }
		>
			{ /* Trigger Wrapper */ }
			<div
				onClick={ toggle }
				className={ `notifybay-cursor-pointer notifybay-inline-flex ${
					classNames?.triggerWrapper || ''
				}` }
			>
				{ trigger }
			</div>

			{ /* Dropdown Content */ }
			<div
				className={ `
          notifybay-absolute notifybay-z-50 notifybay-w-48
          notifybay-bg-white notifybay-rounded-xl notifybay-shadow-xl notifybay-border notifybay-border-default
          notifybay-transition-all notifybay-duration-200 notifybay-ease-out
          ${ positionClasses }
          ${ originClass }
          ${ transitionClasses }
          ${ classNames?.content || '' }
        ` }
			>
				{ content }
			</div>
		</div>
	);
};
