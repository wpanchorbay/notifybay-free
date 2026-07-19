/* eslint-disable */
import React from 'react';

// Icons
const LockKeyhole = ( { className }: { className?: string } ) => (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		fill="none"
		stroke="currentColor"
		strokeWidth="2"
		strokeLinecap="round"
		strokeLinejoin="round"
		className={ className }
	>
		<circle cx="12" cy="16" r="1" />
		<rect x="3" y="10" width="18" height="12" rx="2" />
		<path d="M7 10V7a5 5 0 0 1 10 0v3" />
	</svg>
);

const Hourglass = ( { className }: { className?: string } ) => (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		fill="none"
		stroke="currentColor"
		strokeWidth="2"
		strokeLinecap="round"
		strokeLinejoin="round"
		className={ className }
	>
		<path d="M5 22h14" />
		<path d="M5 2h14" />
		<path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22" />
		<path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2" />
	</svg>
);

interface SelectionCardProps {
	title: string;
	description: string;
	selected: boolean;
	onClick: () => void;
	icon?: React.ReactNode;
	disabled?: boolean;
	variant?: 'buy_pro' | 'coming_soon';
	onMouseEnter?: ( e: React.MouseEvent< HTMLDivElement > ) => void;
	onMouseLeave?: ( e: React.MouseEvent< HTMLDivElement > ) => void;
	classNames?: {
		root?: string;
		iconWrapper?: string;
		circle?: string;
		dot?: string;
		textWrapper?: string;
		title?: string;
		description?: string;
	};
}

export const SelectionCard: React.FC< SelectionCardProps > = ( {
	title,
	description,
	selected,
	onClick,
	icon,
	disabled,
	variant,
	onMouseEnter,
	onMouseLeave,
	classNames,
} ) => {
	const isPro = variant === 'buy_pro';
	const isComingSoon = variant === 'coming_soon';
	const isDisabled = disabled || isPro || isComingSoon;

	return (
		<div
			onClick={ () => ! isDisabled && onClick() }
			onMouseEnter={ onMouseEnter }
			onMouseLeave={ onMouseLeave }
			className={ `
        notifybay-relative notifybay-p-[20px] notifybay-rounded-[8px] notifybay-transition-all notifybay-duration-200
        notifybay-flex notifybay-items-start notifybay-gap-[10px]
        ${
			isDisabled
				? 'notifybay-bg-gray-50 notifybay-border notifybay-border-gray-200 notifybay-cursor-not-allowed'
				: 'notifybay-cursor-pointer'
		}
        ${
			! isDisabled && selected
				? 'notifybay-bg-primary notifybay-border notifybay-border-primary notifybay-shadow-sm notifybay-shadow-primary/20'
				: ! isDisabled
				? 'notifybay-bg-white notifybay-border notifybay-border-gray-100 hover:notifybay-border-gray-300'
				: ''
		}
        ${ classNames?.root || '' }
      ` }
		>
			{ /* Badges */ }
			{ isPro && (
				<div
					className="notifybay-absolute notifybay-top-3 notifybay-right-3"
					title="Upgrade to Pro"
				>
					<LockKeyhole className="notifybay-w-5 notifybay-h-5 notifybay-text-[#f02a74]" />
				</div>
			) }
			{ isComingSoon && (
				<div className="notifybay-absolute notifybay-top-3 notifybay-right-3">
					<span className="notifybay-bg-pink-100 notifybay-text-pink-600 notifybay-px-2 notifybay-py-0.5 notifybay-rounded-full notifybay-text-[10px] notifybay-font-bold notifybay-uppercase notifybay-flex notifybay-items-center notifybay-gap-1">
						<Hourglass className="notifybay-w-3 notifybay-h-3" />
						Soon
					</span>
				</div>
			) }

			<div
				className={ `notifybay-mt-1 ${
					classNames?.iconWrapper || ''
				}` }
			>
				<div
					className={ `
            notifybay-w-5 notifybay-h-5 notifybay-rounded-full notifybay-border-2 notifybay-flex notifybay-items-center notifybay-justify-center
            ${
				isDisabled
					? 'notifybay-border-gray-300 notifybay-bg-gray-100'
					: ''
			}
            ${
				! isDisabled && selected
					? 'notifybay-border-white'
					: ! isDisabled
					? 'notifybay-border-gray-300'
					: ''
			}
            ${ classNames?.circle || '' }
        ` }
				>
					{ ! isDisabled && selected && (
						<div
							className={ `notifybay-w-2.5 notifybay-h-2.5 notifybay-bg-white notifybay-rounded-full ${
								classNames?.dot || ''
							}` }
						/>
					) }
				</div>
			</div>
			<div className={ classNames?.textWrapper || '' }>
				<h3
					className={ `notifybay-text-[15px] notifybay-leading-[24px] notifybay-font-[700] notifybay-mb-1 ${
						! isDisabled && selected
							? 'notifybay-text-white'
							: 'notifybay-text-gray-900'
					} ${ isDisabled ? '!notifybay-text-gray-400' : '' } ${
						classNames?.title || ''
					}` }
				>
					{ title }
				</h3>
				<p
					className={ `notifybay-text-[13px] notifybay-leading-[20px] ${
						! isDisabled && selected
							? 'notifybay-text-blue-100'
							: 'notifybay-text-gray-500'
					} ${ isDisabled ? '!notifybay-text-gray-400' : '' } ${
						classNames?.description || ''
					}` }
				>
					{ description }
				</p>
			</div>
		</div>
	);
};
