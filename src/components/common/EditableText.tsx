/* eslint-disable */
import { check, edit, Icon } from '@wordpress/icons';
import React, { useState, useRef, useEffect } from 'react';

interface EditableTextProps {
	value: string | null | undefined;
	onChange: ( value: string ) => void;
	placeholder?: string;
	className?: string;
	disabled?: boolean;
	classNames?: {
		root?: string;
		text?: string;
		input?: string;
		iconButton?: string;
		icon?: string;
	};
	error?: string | undefined;
}

export const EditableText: React.FC< EditableTextProps > = ( {
	value,
	onChange,
	placeholder = 'Click to edit...',
	className = '',
	disabled = false,
	classNames,
	error,
} ) => {
	const [ isEditing, setIsEditing ] = useState( false );
	const [ localValue, setLocalValue ] = useState( value ?? '' );
	const inputRef = useRef< HTMLInputElement >( null );

	// Sync local state when prop value changes
	useEffect( () => {
		setLocalValue( value ?? '' );
	}, [ value ] );

	const handleSave = () => {
		if ( localValue !== ( value ?? '' ) ) {
			onChange( localValue );
		}
		setIsEditing( false );
		inputRef.current?.blur();
	};

	const handleKeyDown = ( e: React.KeyboardEvent ) => {
		if ( e.key === 'Enter' ) {
			handleSave();
		} else if ( e.key === 'Escape' ) {
			setLocalValue( value ?? '' );
			setIsEditing( false );
			inputRef.current?.blur();
		}
	};

	const onFocus = () => {
		if ( ! disabled ) {
			setIsEditing( true );
		}
	};

	const onBlur = () => {
		// Trigger save on blur
		handleSave();
	};

	return (
		<div>
			<div
				className={ `notifybay-flex notifybay-items-center notifybay-gap-2 ${ className } ${
					classNames?.root || ''
				}` }
			>
				<input
					ref={ inputRef }
					type="text"
					value={ localValue }
					onChange={ ( e ) => setLocalValue( e.target.value ) }
					onFocus={ onFocus }
					onBlur={ onBlur }
					onKeyDown={ handleKeyDown }
					readOnly={ disabled }
					placeholder={ placeholder }
					className={ `
          !notifybay-bg-transparent !notifybay-shadow-none
          notifybay-text-[#1e1e1e] notifybay-font-[700] notifybay-text-[20px] notifybay-leading-[32px]
          notifybay-px-1 notifybay-py-0.5
          notifybay-w-auto 
          !notifybay-border-t-0 !notifybay-border-l-0 !notifybay-border-r-0 !notifybay-border-b-2
           !notifybay-rounded-[0px]
          focus:notifybay-outline-none
          notifybay-transition-colors notifybay-duration-200 placeholder:notifybay-italic
          ${
				error
					? '!notifybay-border-red-500'
					: '!notifybay-border-transparent focus:!notifybay-border-[#3858e9]'
			}
          ${ isEditing ? '' : 'notifybay-cursor-pointer' }
          ${
				disabled
					? 'notifybay-cursor-not-allowed notifybay-opacity-60'
					: ''
			}
          ${ isEditing ? classNames?.input || '' : classNames?.text || '' }
        ` }
				/>

				{ ! disabled && (
					<button
						type="button"
						// Prevent blur on mousedown so click event fires properly
						onMouseDown={ ( e ) => e.preventDefault() }
						onClick={ ( e ) => {
							e.stopPropagation();
							if ( isEditing ) {
								handleSave();
							} else {
								inputRef.current?.focus();
							}
						} }
						className={ `
            notifybay-p-1 notifybay-rounded-full notifybay-transition-colors
            ${
				isEditing
					? 'notifybay-text-primary hover:notifybay-bg-blue-50'
					: 'notifybay-text-gray-400 hover:notifybay-text-primary hover:notifybay-bg-gray-100'
			}
            ${ classNames?.iconButton || '' }
          ` }
						aria-label={ isEditing ? 'Save' : 'Edit' }
					>
						{ isEditing ? (
							<Icon icon={ check } fill="currentColor" />
						) : (
							<Icon icon={ edit } fill="currentColor" />
						) }
					</button>
				) }
			</div>
			{ error && (
				<span className="notifybay-text-red-500 notifybay-text-sm notifybay-mt-1">
					{ error }
				</span>
			) }
		</div>
	);
};
