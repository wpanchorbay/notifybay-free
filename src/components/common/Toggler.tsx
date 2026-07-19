/* eslint-disable */
import React, { useState, useRef, useEffect } from 'react';
import { borderClasses } from './classes';

export interface TogglerOption {
	label: React.ReactNode;
	value: string | number;
}

interface TogglerProps {
	options: TogglerOption[];
	value: string | number;
	onChange: ( value: any ) => void;
	className?: string;
	fullWidth?: boolean;
	size?: 'small' | 'medium' | 'large';
	disabled?: boolean;
	classNames?: {
		root?: string;
		pill?: string;
		button?: string;
	};
}

export const Toggler: React.FC< TogglerProps > = ( {
	options,
	value,
	onChange,
	className = '',
	fullWidth = false,
	size = 'medium',
	disabled = false,
	classNames = {},
} ) => {
	const [ pillStyle, setPillStyle ] = useState< {
		left: number;
		width: number;
	} | null >( null );
	const itemsRef = useRef< ( HTMLButtonElement | null )[] >( [] );

	// Size configuration
	const sizeClasses = {
		small: 'notifybay-px-[8px] notifybay-py-[2px] notifybay-text-[11px]',
		medium: 'notifybay-px-[18px] notifybay-py-[5px] notifybay-text-default',
		large: 'notifybay-px-[20px] notifybay-py-[12px] notifybay-text-[15px]',
	};

	useEffect( () => {
		// Find the currently selected element
		const activeIndex = options.findIndex( ( opt ) => opt.value === value );
		const activeEl = itemsRef.current[ activeIndex ];

		if ( activeEl ) {
			// Update pill position and width based on the active element's dimensions
			setPillStyle( {
				left: activeEl.offsetLeft,
				width: activeEl.offsetWidth,
			} );
		}
	}, [ value, options, size ] ); // Recalculate when value, options, or size changes

	return (
		<div
			className={ `
        notifybay-relative notifybay-inline-flex notifybay-items-center
        notifybay-bg-white notifybay-border ${ borderClasses } notifybay-rounded-[8px]
        notifybay-p-[4px] notifybay-select-none
        ${ fullWidth ? 'notifybay-flex notifybay-w-full' : '' }
        ${
			disabled
				? 'notifybay-opacity-50 notifybay-cursor-not-allowed notifybay-pointer-events-none'
				: ''
		}
        ${ className }
        ${ classNames.root || '' }
      ` }
			role="group"
			aria-disabled={ disabled }
		>
			{ /* Sliding Background Pill */ }
			<div
				className={ `
            notifybay-absolute notifybay-top-[4px] notifybay-bottom-[4px]
            notifybay-bg-primary notifybay-rounded-[6px] notifybay-shadow-sm
            notifybay-transition-all notifybay-duration-300 notifybay-ease-[cubic-bezier(0.4,0,0.2,1)]
            notifybay-pointer-events-none
            ${ classNames.pill || '' }
        ` }
				style={ {
					left: pillStyle?.left ?? 0,
					width: pillStyle?.width ?? 0,
					opacity: pillStyle ? 1 : 0, // Prevent initial flash at wrong position
				} }
			/>

			{ options.map( ( option, index ) => {
				const isSelected = option.value === value;
				return (
					<button
						key={ String( option.value ) }
						ref={ ( el ) => {
							itemsRef.current[ index ] = el;
						} }
						type="button"
						disabled={ disabled }
						onClick={ () => ! disabled && onChange( option.value ) }
						className={ `
              notifybay-relative notifybay-z-10 notifybay-flex-1
              notifybay-font-medium notifybay-text-nowrap notifybay-rounded-[6px]
              notifybay-transition-colors notifybay-duration-300
              focus:notifybay-outline-none focus-visible:notifybay-ring-2 focus-visible:notifybay-ring-primary/20
              ${ sizeClasses[ size ] }
              ${
					isSelected
						? 'notifybay-text-white'
						: 'notifybay-text-secondary hover:notifybay-text-gray-700'
				}
              ${ classNames.button || '' }
            ` }
						aria-pressed={ isSelected }
					>
						{ option.label }
					</button>
				);
			} ) }
		</div>
	);
};
