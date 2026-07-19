/* eslint-disable */
import React from 'react';

interface CheckboxProps {
	label?: string | React.ReactNode;
	checked: boolean;
	onChange: ( checked: boolean ) => void;
	disabled?: boolean;
	classNames?: {
		root?: string;
		box?: string;
		icon?: string;
		label?: string;
	};
}

export const Checkbox: React.FC< CheckboxProps > = ( {
	label,
	checked,
	onChange,
	disabled,
	classNames,
} ) => {
	return (
		<label
			className={ `notifybay-flex notifybay-items-center notifybay-gap-3 notifybay-cursor-pointer ${
				disabled
					? 'notifybay-opacity-50 notifybay-cursor-not-allowed'
					: ''
			} ${ classNames?.root || '' }` }
		>
			<div
				className={ `
        notifybay-flex notifybay-items-center notifybay-justify-center
        notifybay-w-4 notifybay-h-4 notifybay-rounded notifybay-border-2 notifybay-transition-all notifybay-duration-200
        ${
			checked
				? 'notifybay-border-primary notifybay-bg-primary'
				: 'notifybay-border-[#949494] notifybay-bg-transparent hover:notifybay-border-primary'
		}
        ${ classNames?.box || '' }
      ` }
			>
				<svg
					className={ `notifybay-w-3.5 notifybay-h-3.5 notifybay-text-white notifybay-transform notifybay-transition-transform notifybay-duration-200 ${
						checked ? 'notifybay-scale-100' : 'notifybay-scale-0'
					} ${ classNames?.icon || '' }` }
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
					type="checkbox"
					className="!notifybay-hidden"
					checked={ checked }
					onChange={ ( e ) => onChange( e.target.checked ) }
					disabled={ disabled }
				/>
			</div>
			{ label && (
				<span
					className={ `notifybay-text-[13px] notifybay-font-[400] notifybay-leading-[20px] notifybay-text-[#1e1e1e] ${
						classNames?.label || ''
					}` }
				>
					{ label }
				</span>
			) }
		</label>
	);
};
