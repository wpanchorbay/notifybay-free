/* eslint-disable */
import React from 'react';

interface ClassicToggleProps {
	checked: boolean;
	onChange: ( checked: boolean ) => void;
	disabled?: boolean;
	className?: string;
	id?: string;
}

export const ClassicToggle: React.FC< ClassicToggleProps > = ( {
	checked,
	onChange,
	disabled,
	className = '',
	id,
} ) => {
	const toggleId =
		id || `classic-toggle-${ Math.random().toString( 36 ).slice( 2, 9 ) }`;

	return (
		<div
			className={ `notifybay-relative notifybay-inline-block notifybay-w-10 notifybay-align-middle notifybay-select-none notifybay-transition notifybay-duration-200 notifybay-ease-in ${ className }` }
		>
			<input
				type="checkbox"
				id={ toggleId }
				checked={ checked }
				onChange={ ( e ) => onChange( e.target.checked ) }
				disabled={ disabled }
				className="notifybay-toggle-checkbox notifybay-absolute notifybay-block notifybay-w-5 notifybay-h-5 notifybay-rounded-full notifybay-bg-white notifybay-border-4 notifybay-appearance-none notifybay-cursor-pointer checked:notifybay-right-0 checked:notifybay-border-[#2271b1] notifybay-right-5 notifybay-border-[#8c8f94] notifybay-transition-all notifybay-duration-200"
			/>
			<label
				htmlFor={ toggleId }
				className={ `notifybay-toggle-label notifybay-block notifybay-overflow-hidden notifybay-h-5 notifybay-rounded-full notifybay-cursor-pointer transition-colors duration-200 ${
					checked
						? 'notifybay-bg-[#2271b1]'
						: 'notifybay-bg-[#8c8f94]'
				} ${
					disabled
						? 'notifybay-opacity-50 notifybay-cursor-not-allowed'
						: ''
				}` }
			></label>
			<style>{ `
        .notifybay-toggle-checkbox:checked {
          right: 0;
          border-color: #2271b1;
        }
        .notifybay-toggle-checkbox:focus {
            outline: none;
        }
      ` }</style>
		</div>
	);
};
