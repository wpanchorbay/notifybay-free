/* eslint-disable */
import React, { forwardRef } from 'react';

interface ButtonProps extends React.ButtonHTMLAttributes< HTMLButtonElement > {
	children: React.ReactNode;
	className?: string;
	size?: 'small' | 'medium' | 'large';
	color?: 'primary' | 'secondary' | 'danger';
	variant?: 'solid' | 'outline' | 'ghost';
}

const Button = forwardRef< HTMLButtonElement, ButtonProps >(
	(
		{
			children,
			className = '',
			size = 'medium',
			color = 'primary',
			variant = 'solid',
			...props
		},
		ref
	) => {
		const sizeClasses = {
			small: 'notifybay-px-[8px] notifybay-py-[5px]',
			medium: 'notifybay-px-[12px] notifybay-py-[6px]',
			large: 'notifybay-px-[16px] notifybay-py-[10px]',
		};

		const colorClasses = {
			primary: {
				solid: 'notifybay-bg-primary notifybay-text-white notifybay-border notifybay-border-primary hover:notifybay-bg-primary-hovered hover:notifybay-border-primary-hovered',
				outline:
					'notifybay-bg-transparent notifybay-border notifybay-border-primary notifybay-text-primary hover:notifybay-bg-primary hover:notifybay-text-white',
				ghost: 'notifybay-bg-transparent notifybay-text-primary hover:notifybay-text-primary-hovered hover:notifybay-bg-primary/10',
			},
			secondary: {
				solid: 'notifybay-bg-secondary notifybay-text-white notifybay-border notifybay-border-secondary hover:notifybay-bg-secondary-hovered',
				outline:
					'notifybay-bg-transparent notifybay-border notifybay-border-secondary notifybay-text-secondary hover:notifybay-bg-secondary hover:notifybay-text-white',
				ghost: 'notifybay-bg-transparent notifybay-text-[#1e1e1e] hover:!notifybay-text-primary',
			},
			danger: {
				solid: 'notifybay-bg-red-500 notifybay-text-white notifybay-border notifybay-border-red-500 hover:notifybay-bg-red-600 hover:notifybay-border-red-600',
				outline:
					'notifybay-bg-transparent notifybay-border notifybay-border-red-500 notifybay-text-red-500 hover:notifybay-bg-red-500 hover:notifybay-text-white',
				ghost: 'notifybay-bg-transparent notifybay-text-red-500 hover:notifybay-bg-red-500/10',
			},
		};

		// Safely access nested properties
		const variantClasses =
			colorClasses[ color ]?.[ variant ] ?? colorClasses.primary.solid;
		const finalSizeClass = sizeClasses[ size ] ?? sizeClasses.medium;

		return (
			<button
				ref={ ref }
				className={ `
                notifybay-flex notifybay-items-center notifybay-justify-center notifybay-gap-[6px]
                notifybay-text-default notifybay-rounded-[8px] notifybay-transition-all notifybay-duration-200
                disabled:notifybay-opacity-50 disabled:notifybay-cursor-not-allowed
                ${ finalSizeClass } 
                ${ variantClasses } 
                ${ className }
            ` }
				{ ...props }
			>
				{ children }
			</button>
		);
	}
);

export default Button;
