/* eslint-disable */
import React from 'react';

interface ClassicFormFieldProps {
	label?: string;
	description?: string;
	layout?: 'full' | 'first' | 'last';
	children: React.ReactNode;
	className?: string;
	htmlFor?: string;
}

/**
 * WooCommerce-style form field layout wrapper.
 * Aligns label left, input right — just like WC's Product Data fields.
 * @param root0
 * @param root0.label
 * @param root0.description
 * @param root0.layout
 * @param root0.children
 * @param root0.className
 * @param root0.htmlFor
 */
export const ClassicFormField: React.FC< ClassicFormFieldProps > = ( {
	label,
	description,
	layout = 'full',
	children,
	className = '',
	htmlFor,
} ) => {
	const layoutClass = {
		full: 'form-field',
		first: 'form-field form-row form-row-first',
		last: 'form-field form-row form-row-last',
	}[ layout ];

	return (
		<p className={ `${ layoutClass } ${ className }` }>
			{ label && <label htmlFor={ htmlFor }>{ label }</label> }
			{ children }
			{ description && (
				<p className="description notifybay-block notifybay-mt-1">
					{ description }
				</p>
			) }
		</p>
	);
};

interface ClassicOptionsGroupProps {
	children: React.ReactNode;
	className?: string;
}

/**
 * WooCommerce options_group wrapper — adds the standard grey border-bottom
 * and padding between sections.
 * @param root0
 * @param root0.children
 * @param root0.className
 */
export const ClassicOptionsGroup: React.FC< ClassicOptionsGroupProps > = ( {
	children,
	className = '',
} ) => {
	return <div className={ `options_group ${ className }` }>{ children }</div>;
};
