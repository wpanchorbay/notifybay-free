/* eslint-disable */
import React from 'react';

interface RadioProps {
	label: string;
	checked: boolean;
	onChange: () => void;
	disabled?: boolean;
	classNames?: {
		root?: string;
		circle?: string;
		dot?: string;
		label?: string;
	};
}

export const Radio: React.FC< RadioProps > = ( {
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
        notifybay-relative notifybay-flex notifybay-items-center notifybay-justify-center
        notifybay-w-5 notifybay-h-5 notifybay-rounded-full notifybay-border-2 notifybay-transition-all notifybay-duration-200
        ${
			checked
				? 'notifybay-border-primary notifybay-bg-primary'
				: 'notifybay-border-gray-300 notifybay-bg-white hover:notifybay-border-primary'
		}
        ${ classNames?.circle || '' }
      ` }
			>
				{ /* Inner white dot for selected state */ }
				<div
					className={ `
                notifybay-w-2 notifybay-h-2 notifybay-bg-white notifybay-rounded-full notifybay-transform notifybay-transition-transform notifybay-duration-200
                ${ checked ? 'notifybay-scale-100' : 'notifybay-scale-0' }
                ${ classNames?.dot || '' }
            ` }
				/>
				<input
					type="radio"
					className="notifybay-hidden"
					checked={ checked }
					onChange={ onChange }
					disabled={ disabled }
				/>
			</div>
			<span
				className={ `notifybay-text-[15px] notifybay-font-semibold ${
					checked
						? 'notifybay-text-gray-900'
						: 'notifybay-text-gray-700'
				} ${ classNames?.label || '' }` }
			>
				{ label }
			</span>
		</label>
	);
};
