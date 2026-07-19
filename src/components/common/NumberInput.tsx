/* eslint-disable */
import React, { useCallback, useState, useEffect } from 'react';
import {
	borderClasses,
	errorWithInClasses,
	hoverWithInClasses,
	transitionClasses,
} from './classes';

interface NumberInputProps {
	value: number | null | undefined;
	onChange: ( value: number | null ) => void;
	min?: number;
	max?: number;
	step?: number;
	label?: string;
	error?: string;
	className?: string;
	disabled?: boolean;
	placeholder?: string;
	classNames?: {
		wrapper?: string;
		root?: string;
		label?: string;
		input?: string;
		buttonContainer?: string;
		incrementButton?: string;
		decrementButton?: string;
		error?: string;
	};
}

export const NumberInput: React.FC< NumberInputProps > = ( {
	value,
	onChange,
	min = -Infinity,
	max = Infinity,
	step = 1,
	label,
	error,
	className = '',
	disabled = false,
	placeholder = '0',
	classNames,
} ) => {
	// Local state to handle string input (allows empty string, trailing decimals, etc.)
	const [ localValue, setLocalValue ] = useState< string | number >(
		value ?? ''
	);

	// Sync local state when prop value changes externally
	useEffect( () => {
		setLocalValue( ( prev ) => {
			if ( value === null || value === undefined ) {
				return '';
			}
			// If the current local value numerically matches the new prop value,
			// keep the local string to preserve cursor position and formatting (e.g. "1.0" vs 1).
			const parsed = parseFloat( prev.toString() );
			if ( ! isNaN( parsed ) && parsed === value ) {
				return prev;
			}
			return value;
		} );
	}, [ value ] );

	const handleIncrement = useCallback( () => {
		if ( ! disabled ) {
			const currentValue =
				value === null || value === undefined ? 0 : value;
			let newValue = Number( currentValue ) + Number( step );

			if ( newValue < min ) {
				newValue = min;
			}
			if ( newValue > max ) {
				newValue = max;
			}

			onChange( newValue );
		}
	}, [ value, step, max, min, onChange, disabled ] );

	const handleDecrement = useCallback( () => {
		if ( ! disabled ) {
			const currentValue =
				value === null || value === undefined ? 0 : value;
			let newValue = Number( currentValue ) - Number( step );

			if ( newValue < min ) {
				newValue = min;
			}
			if ( newValue > max ) {
				newValue = max;
			}

			onChange( newValue );
		}
	}, [ value, step, min, max, onChange, disabled ] );

	const handleInputChange = ( e: React.ChangeEvent< HTMLInputElement > ) => {
		const inputValue = e.target.value;
		setLocalValue( inputValue );

		if ( inputValue === '' ) {
			onChange( null );
			return;
		}

		if ( inputValue === '-' ) {
			// We allow the input to be just a minus sign locally
			return;
		}

		const newValue = parseFloat( inputValue );
		if ( ! isNaN( newValue ) ) {
			if ( newValue <= max && newValue >= min ) {
				onChange( newValue );
			}
		}
	};

	const handleBlur = () => {
		// On blur, reset to the prop value if the local input is invalid.
		// If local is empty, ensure prop value is respected (which might be null).
		if ( localValue === '' || localValue === '-' ) {
			if ( value !== null && value !== undefined ) {
				setLocalValue( value );
			} else {
				setLocalValue( '' );
			}
			return;
		}

		const parsed = parseFloat( localValue.toString() );

		if ( isNaN( parsed ) ) {
			// Should not happen given regex checks usually, but safety fallback
			setLocalValue( value ?? '' );
			return;
		}

		let finalValue = parsed;

		// Clamp value on blur if it exceeds bounds
		if ( parsed > max ) {
			finalValue = max;
		} else if ( parsed < min ) {
			finalValue = min;
		}

		setLocalValue( finalValue );

		// If the value changed due to clamping or was not synced yet (because it was out of bounds during typing), update parent
		if ( finalValue !== value ) {
			onChange( finalValue );
		}
	};

	return (
		<div className={ `notifybay-w-full ${ classNames?.wrapper || '' }` }>
			{ label && (
				<label
					className={ `notifybay-block notifybay-text-sm notifybay-font-bold notifybay-text-gray-900 notifybay-mb-2 ${
						classNames?.label || ''
					}` }
				>
					{ label }
				</label>
			) }

			<div
				className={ `
          notifybay-flex notifybay-items-center notifybay-justify-between notifybay-overflow-hidden
          notifybay-rounded-[8px] notifybay-bg-white notifybay-min-w-min notifybay-py-[1px]
          ${ borderClasses }
          ${ transitionClasses }
          ${ error ? errorWithInClasses : hoverWithInClasses }
          ${
				disabled
					? 'notifybay-opacity-50 notifybay-cursor-not-allowed notifybay-bg-gray-50'
					: ''
			}
          ${ className }
          ${ classNames?.root || '' }
        ` }
			>
				<input
					type="number"
					value={ localValue }
					onChange={ handleInputChange }
					onBlur={ handleBlur }
					disabled={ disabled }
					className={ `
            !notifybay-border-none !notifybay-outline-none 
            focus:!notifybay-outline-none focus:!notifybay-border-none focus:!notifybay-shadow-none
            notifybay-px-[12px] notifybay-py-[9px] 
            notifybay-text-[13px] notifybay-leading-[20px] 
            notifybay-text-[#1e1e1e] notifybay-font-[400] 
            notifybay-min-w-[60px] notifybay-w-full 
            notifybay-bg-transparent notifybay-border-none notifybay-outline-none 
            notifybay-placeholder-gray-400
            hide-spin-button
            ${ disabled ? 'notifybay-cursor-not-allowed' : '' }
            ${ classNames?.input || '' }
          ` }
					placeholder={ placeholder }
				/>

				<div
					className={ `notifybay-flex notifybay-items-center notifybay-px-2 !notifybay-pl-0.5 notifybay-space-x-1 notifybay-select-none ${
						classNames?.buttonContainer || ''
					}` }
				>
					<button
						type="button"
						onClick={ handleIncrement }
						disabled={
							disabled ||
							( value !== null &&
								value !== undefined &&
								value >= max )
						}
						className={ `
              notifybay-p-2 !notifybay-pr-0.5 notifybay-text-gray-500 notifybay-transition-colors notifybay-duration-150
              hover:notifybay-text-gray-900 focus:notifybay-outline-none active:notifybay-scale-95
              disabled:notifybay-opacity-30 disabled:hover:notifybay-text-gray-500
              ${ classNames?.incrementButton || '' }
            ` }
						aria-label="Increase value"
					>
						<svg
							width="20"
							height="20"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							strokeWidth="2.5"
							strokeLinecap="round"
							strokeLinejoin="round"
						>
							<line x1="12" y1="5" x2="12" y2="19"></line>
							<line x1="5" y1="12" x2="19" y2="12"></line>
						</svg>
					</button>

					<button
						type="button"
						onClick={ handleDecrement }
						disabled={
							disabled ||
							( value !== null &&
								value !== undefined &&
								value <= min )
						}
						className={ `
              notifybay-p-2 notifybay-text-gray-500 notifybay-transition-colors notifybay-duration-150
              hover:notifybay-text-gray-900 focus:notifybay-outline-none active:notifybay-scale-95
              disabled:notifybay-opacity-30 disabled:hover:notifybay-text-gray-500
              ${ classNames?.decrementButton || '' }
            ` }
						aria-label="Decrease value"
					>
						<svg
							width="20"
							height="20"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							strokeWidth="2.5"
							strokeLinecap="round"
							strokeLinejoin="round"
						>
							<line x1="5" y1="12" x2="19" y2="12"></line>
						</svg>
					</button>
				</div>
			</div>

			{ error && (
				<span
					className={ `notifybay-mt-1 notifybay-text-xs notifybay-text-red-500 ${
						classNames?.error || ''
					}` }
				>
					{ error }
				</span>
			) }
		</div>
	);
};
