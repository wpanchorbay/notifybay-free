/* eslint-disable */
import React from 'react';
import {
	borderClasses,
	errorClasses,
	hoverClasses,
	transitionClasses,
} from './classes';

interface InputProps
	extends Omit< React.InputHTMLAttributes< HTMLInputElement >, 'size' > {
	label?: string;
	error?: string;
	size?: 'small' | 'medium' | 'large';
	classNames?: {
		root?: string;
		label?: string;
		input?: string;
		error?: string;
	};
}

export const Input: React.FC< InputProps > = ( {
	label,
	error,
	size = 'medium',
	className = '',
	classNames,
	...props
} ) => {
	const sizeClasses = {
		small: 'notifybay-px-[8px] !notifybay-py-[7px] notifybay-text-[13px] notifybay-leading-[20px]',
		medium: 'notifybay-px-[12px] !notifybay-py-[9px] !notifybay-text-[13px] !notifybay-leading-[20px]',
		large: 'notifybay-px-[12px] !notifybay-py-[11px] notifybay-text-[13px] notifybay-leading-[20px]',
	};

	return (
		<div className={ `notifybay-w-full ${ classNames?.root || '' }` }>
			{ label && (
				<label
					className={ `notifybay-block notifybay-text-sm notifybay-font-bold notifybay-text-gray-900 notifybay-mb-2 ${
						classNames?.label || ''
					}` }
				>
					{ label }
				</label>
			) }
			<input
				className={ `
          notifybay-w-full notifybay-outline-none
          notifybay-bg-white notifybay-border notifybay-rounded-[8px]
          notifybay-text-[#1e1e1e] notifybay-placeholder-gray-400
          ${ sizeClasses[ size ] }
          ${ borderClasses }
          ${ transitionClasses }
          ${ error ? errorClasses : hoverClasses }
          ${
				props.disabled
					? 'notifybay-opacity-50 notifybay-cursor-not-allowed'
					: ''
			}
          ${ className }
          ${ classNames?.input || '' }
        ` }
				{ ...props }
			/>
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
