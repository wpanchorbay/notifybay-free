/* eslint-disable */
import React from 'react';

interface ClassicTextareaProps
	extends React.TextareaHTMLAttributes< HTMLTextAreaElement > {
	description?: string;
	className?: string;
}

export const ClassicTextarea: React.FC< ClassicTextareaProps > = ( {
	description,
	className = '',
	...props
} ) => {
	return (
		<div className="notifybay-w-full">
			<textarea
				className={ `notifybay-w-full notifybay-p-3 notifybay-border notifybay-border-gray-300 notifybay-rounded-md notifybay-text-[14px] ${ className }` }
				{ ...props }
			/>
			{ description && (
				<p className="description notifybay-mt-1 notifybay-text-gray-500">
					{ description }
				</p>
			) }
		</div>
	);
};
