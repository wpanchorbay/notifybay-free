/* eslint-disable */
import React from 'react';

export interface ListItem {
	label: string;
	value: string;
}

interface ListSelectProps {
	items: ListItem[];
	selectedValues: string[];
	onChange: ( value: string ) => void;
	className?: string;
	size?: 'small' | 'medium' | 'large';
	classNames?: {
		root?: string;
		item?: string;
		iconWrapper?: string;
		icon?: string;
		label?: string;
	};
}

export const ListSelect: React.FC< ListSelectProps > = ( {
	items,
	selectedValues,
	onChange,
	className = '',
	size = 'medium',
	classNames,
} ) => {
	const sizeStyles = {
		small: {
			item: 'notifybay-px-3 notifybay-py-2',
			label: 'notifybay-text-xs',
			iconWrapper: 'notifybay-w-4',
			icon: 'notifybay-w-3 notifybay-h-3',
		},
		medium: {
			item: 'notifybay-px-4 notifybay-py-3',
			label: 'notifybay-text-sm',
			iconWrapper: 'notifybay-w-5',
			icon: 'notifybay-w-4 notifybay-h-4',
		},
		large: {
			item: 'notifybay-px-5 notifybay-py-4',
			label: 'notifybay-text-base',
			iconWrapper: 'notifybay-w-6',
			icon: 'notifybay-w-5 notifybay-h-5',
		},
	};

	const currentSize = sizeStyles[ size ];

	return (
		<div
			className={ `notifybay-flex notifybay-flex-col notifybay-border notifybay-border-default notifybay-rounded-lg notifybay-bg-white notifybay-overflow-hidden ${ className } ${
				classNames?.root || ''
			}` }
		>
			{ items.map( ( item, index ) => {
				const isSelected = selectedValues.includes( item.value );
				return (
					<div
						key={ item.value }
						onClick={ () => onChange( item.value ) }
						className={ `
              notifybay-flex notifybay-items-center notifybay-gap-3 
              notifybay-cursor-pointer notifybay-transition-colors
              hover:notifybay-bg-gray-50
              ${ currentSize.item }
              ${
					index !== items.length - 1
						? 'notifybay-border-b notifybay-border-gray-100'
						: ''
				}
              ${ classNames?.item || '' }
            ` }
					>
						<div
							className={ `notifybay-flex notifybay-justify-center ${
								currentSize.iconWrapper
							} ${ classNames?.iconWrapper || '' }` }
						>
							{ isSelected && (
								<svg
									className={ `notifybay-text-gray-900 ${
										currentSize.icon
									} ${ classNames?.icon || '' }` }
									viewBox="0 0 24 24"
									fill="none"
									stroke="currentColor"
									strokeWidth="2.5"
									strokeLinecap="round"
									strokeLinejoin="round"
								>
									<polyline points="20 6 9 17 4 12"></polyline>
								</svg>
							) }
						</div>
						<span
							className={ `${ currentSize.label } ${
								isSelected
									? 'notifybay-text-gray-900 notifybay-font-medium'
									: 'notifybay-text-gray-500'
							} ${ classNames?.label || '' }` }
						>
							{ item.label }
						</span>
					</div>
				);
			} ) }
		</div>
	);
};
