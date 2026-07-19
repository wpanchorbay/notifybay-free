/* eslint-disable */
import React from 'react';

interface SwitchProps {
	checked: boolean;
	onChange: ( checked: boolean ) => void;
	disabled?: boolean;
	size?: 'small' | 'medium' | 'large';
	className?: string;
	classNames?: {
		root?: string;
		thumb?: string;
	};
}

export const Switch: React.FC< SwitchProps > = ( {
	checked,
	onChange,
	disabled,
	size = 'medium',
	className = '',
	classNames,
} ) => {
	const sizeConfig = {
		small: {
			switch: 'notifybay-h-4 notifybay-w-7',
			thumb: 'notifybay-h-3 notifybay-w-3',
			translate: 'notifybay-translate-x-3',
		},
		medium: {
			switch: 'notifybay-h-6 notifybay-w-11',
			thumb: 'notifybay-h-5 notifybay-w-5',
			translate: 'notifybay-translate-x-5',
		},
		large: {
			switch: 'notifybay-h-7 notifybay-w-14',
			thumb: 'notifybay-h-6 notifybay-w-6',
			translate: 'notifybay-translate-x-7',
		},
	};

	const currentSize = sizeConfig[ size ];

	return (
		<button
			type="button"
			role="switch"
			aria-checked={ checked }
			onClick={ () => ! disabled && onChange( ! checked ) }
			disabled={ disabled }
			className={ `
        notifybay-group notifybay-relative notifybay-inline-flex notifybay-shrink-0 notifybay-cursor-pointer notifybay-items-center notifybay-rounded-full notifybay-border-2 notifybay-border-transparent notifybay-transition-colors notifybay-duration-200 notifybay-ease-in-out focus:notifybay-outline-none focus:notifybay-ring-2 focus:notifybay-ring-primary focus:notifybay-ring-offset-2
        ${ currentSize.switch }
        ${ checked ? 'notifybay-bg-green-500' : 'notifybay-bg-black' }
        ${ disabled ? 'notifybay-opacity-50 notifybay-cursor-not-allowed' : '' }
        ${ className }
        ${ classNames?.root || '' }
      ` }
		>
			<span className="notifybay-sr-only">Toggle setting</span>
			<span
				aria-hidden="true"
				className={ `
          notifybay-pointer-events-none notifybay-inline-block notifybay-transform notifybay-rounded-full notifybay-bg-white notifybay-shadow notifybay-ring-0 notifybay-transition notifybay-duration-200 notifybay-ease-in-out
          ${ currentSize.thumb }
          ${ checked ? currentSize.translate : 'notifybay-translate-x-0' }
          ${ classNames?.thumb || '' }
        ` }
			/>
		</button>
	);
};
