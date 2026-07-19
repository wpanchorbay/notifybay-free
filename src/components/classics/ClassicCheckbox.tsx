/* eslint-disable */
import React from 'react';

interface ClassicCheckboxProps {
	label?: string;
	checked: boolean;
	onChange: ( checked: boolean ) => void;
	disabled?: boolean;
	description?: string;
	className?: string;
	labelClassName?: string;
	id?: string;
}

export const ClassicCheckbox: React.FC< ClassicCheckboxProps > = ( {
	label,
	checked,
	onChange,
	disabled,
	description,
	className = '',
	labelClassName = '',
	id,
} ) => {
	const checkboxId =
		id || `classic-cb-${ Math.random().toString( 36 ).slice( 2, 9 ) }`;

	return (
		<div
			className={ `notifybay-flex notifybay-flex-col notifybay-gap-1 ${ className }` }
		>
			<label
				htmlFor={ checkboxId }
				className={ `!notifybay-flex notifybay-items-center notifybay-gap-2 notifybay-cursor-pointer ${ labelClassName } ${
					disabled
						? 'notifybay-opacity-50 notifybay-cursor-not-allowed'
						: ''
				}` }
			>
				<div
					className={ `
          notifybay-flex notifybay-items-center notifybay-justify-center
          notifybay-w-4 notifybay-h-4 notifybay-rounded notifybay-border-2 notifybay-transition-all notifybay-duration-200
          ${
				checked
					? 'notifybay-border-[#2271b1] notifybay-bg-[#2271b1]'
					: 'notifybay-border-[#8c8f94] notifybay-bg-white hover:notifybay-border-[#2271b1]'
			}
        ` }
				>
					<svg
						className={ `notifybay-w-3.5 notifybay-h-3.5 notifybay-text-white notifybay-transform notifybay-transition-transform notifybay-duration-200 ${
							checked
								? 'notifybay-scale-100'
								: 'notifybay-scale-0'
						}` }
						viewBox="0 0 24 24"
						fill="none"
						stroke="currentColor"
						strokeWidth="3"
						strokeLinecap="round"
						strokeLinejoin="round"
					>
						<polyline points="20 6 9 17 4 12"></polyline>
					</svg>
					<input
						id={ checkboxId }
						type="checkbox"
						className="!notifybay-hidden"
						checked={ checked }
						onChange={ ( e ) => onChange( e.target.checked ) }
						disabled={ disabled }
					/>
				</div>
				{ label && <span>{ label }</span> }
			</label>
			{ description && (
				<p className="description notifybay-block notifybay-mt-0 notifybay-pl-6">
					{ description }
				</p>
			) }
		</div>
	);
};
