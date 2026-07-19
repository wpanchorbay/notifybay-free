import { z } from 'zod';
import { __ } from '@wordpress/i18n';

export const conditionRuleSchema = z.object( {
	target_field_id: z
		.string()
		.min( 1, { message: __( 'Target field is required', 'notifybay' ) } ),
	operator: z.string(),
	value: z
		.string()
		.min( 1, { message: __( 'Rule value is required', 'notifybay' ) } ),
} );

export const fieldConditionsSchema = z
	.object( {
		status: z.enum( [ 'active', 'inactive' ] ),
		action: z.enum( [ 'show', 'hide' ] ),
		match: z.enum( [ 'ALL', 'ANY' ] ),
		rules: z.array( conditionRuleSchema ),
	} )
	.superRefine( ( data, ctx ) => {
		if ( data.status === 'active' && data.rules.length === 0 ) {
			ctx.addIssue( {
				code: z.ZodIssueCode.custom,
				message: __(
					'At least one rule is required when logic is active',
					'notifybay'
				),
				path: [ 'rules' ],
			} );
		}
	} );

export const fieldOptionSchema = z.object( {
	label: z
		.string()
		.min( 1, { message: __( 'Choice label is required', 'notifybay' ) } ),
	value: z
		.string()
		.min( 1, { message: __( 'Choice value is required', 'notifybay' ) } ),
	price_type: z.string().optional(),
	price: z.number().optional(),
	weight: z.number().optional(),
} );

export const fieldDefinitionSchema = z
	.object( {
		id: z.string(),
		type: z.string(),
		label: z.string().min( 1, {
			message: __( 'Field label is required', 'notifybay' ),
		} ),
		description: z.string().optional(),
		placeholder: z.string().optional(),
		required: z.boolean(),
		class_name: z.string().optional(),
		price_type: z.string().optional(),
		price: z.number().optional(),
		weight: z.number().optional(),
		options: z.array( fieldOptionSchema ).optional(),
		min_length: z.number().optional(),
		max_length: z.number().optional(),
		min_value: z.number().optional(),
		max_value: z.number().optional(),
		step: z.number().optional(),
		allowed_types: z.string().optional(),
		max_file_size: z.number().optional(),
		conditions: fieldConditionsSchema,
	} )
	.superRefine( ( data, ctx ) => {
		if ( [ 'select', 'radio', 'checkbox' ].includes( data.type ) ) {
			if ( ! data.options || data.options.length === 0 ) {
				ctx.addIssue( {
					code: z.ZodIssueCode.custom,
					message: __(
						'At least one choice is required',
						'notifybay'
					),
					path: [ 'options' ],
				} );
			}
		}
	} );

export const assignmentSchema = z.object( {
	target_type: z.enum( [ 'global', 'product', 'category', 'tag' ] ),
	target_id: z.number(),
	is_exclusion: z.boolean(),
} );

export const addonGroupSchema = z.object( {
	title: z
		.string()
		.min( 1, { message: __( 'Group Title is required', 'notifybay' ) } ),
	status: z.enum( [ 'publish', 'draft' ] ),
	schema: z.array( fieldDefinitionSchema ),
	assignments: z.array( assignmentSchema ),
} );
