import React, { createContext, useContext, useReducer, ReactNode } from 'react';
import { __ } from '@wordpress/i18n';

// ─── Types ───────────────────────────────────────────────────────────────

export interface ConditionRule {
	target_field_id: string;
	operator: string;
	value: string;
}

export interface FieldConditions {
	status: 'active' | 'inactive';
	action: 'show' | 'hide';
	match: 'ALL' | 'ANY';
	rules: ConditionRule[];
}

export interface FieldOption {
	label: string;
	value: string;
	price_type: string;
	price: number;
	weight: number;
	formula?: string;
	color?: string;
	image_url?: string;
}

export interface FieldDefinition {
	id: string;
	type: string;
	label: string;
	description: string;
	placeholder: string;
	required: boolean;
	class_name: string;
	price_type: string;
	price: number;
	weight: number;
	options?: FieldOption[];
	min_length?: number;
	max_length?: number;
	min_value?: number;
	max_value?: number;
	step?: number;
	formula?: string;
	allowed_types?: string;
	max_file_size?: number;
	content?: string;
	conditions: FieldConditions;
	display_style?: 'swatch_only' | 'swatch_label';
}

export interface Assignment {
	target_type: 'global' | 'product' | 'category' | 'tag';
	target_id: number;
	is_exclusion: boolean;
}

export interface AddonGroupState {
	id: number | null;
	title: string;
	status: 'publish' | 'draft';
	schema: FieldDefinition[];
	assignments: Assignment[];
	isLoading: boolean;
	isSaving: boolean;
	isDirty: boolean;
	error: string | null;
	errors: Record< string, string >;
	expandedFieldId: string | null;
}

// ─── Actions ─────────────────────────────────────────────────────────────

type AddonAction =
	| { type: 'SET_GROUP'; payload: Partial< AddonGroupState > }
	| { type: 'SET_TITLE'; payload: string }
	| { type: 'SET_STATUS'; payload: 'publish' | 'draft' }
	| { type: 'SET_ASSIGNMENTS'; payload: Assignment[] }
	| { type: 'ADD_FIELD'; payload: FieldDefinition }
	| { type: 'REMOVE_FIELD'; payload: string }
	| {
			type: 'UPDATE_FIELD';
			payload: { id: string; updates: Partial< FieldDefinition > };
	  }
	| { type: 'REORDER_FIELDS'; payload: FieldDefinition[] }
	| { type: 'MOVE_UP'; payload: number }
	| { type: 'MOVE_DOWN'; payload: number }
	| { type: 'ADD_OPTION'; payload: { fieldId: string; option: FieldOption } }
	| {
			type: 'REMOVE_OPTION';
			payload: { fieldId: string; optionIndex: number };
	  }
	| {
			type: 'UPDATE_OPTION';
			payload: {
				fieldId: string;
				optionIndex: number;
				updates: Partial< FieldOption >;
			};
	  }
	| { type: 'SET_LOADING'; payload: boolean }
	| { type: 'SET_SAVING'; payload: boolean }
	| { type: 'SET_ERROR'; payload: string | null }
	| { type: 'SET_ERRORS'; payload: Record< string, string > }
	| { type: 'MARK_CLEAN' }
	| { type: 'TOGGLE_EXPAND_FIELD'; payload: string | null }
	| { type: 'DUPLICATE_FIELD'; payload: string }
	| { type: 'RESET' };

// ─── Defaults ────────────────────────────────────────────────────────────

const initialState: AddonGroupState = {
	id: null,
	title: '',
	status: 'publish',
	schema: [],
	assignments: [],
	isLoading: false,
	isSaving: false,
	isDirty: false,
	error: null,
	errors: {},
	expandedFieldId: null,
};

// ─── Helpers ─────────────────────────────────────────────────────────────

/** Generate a unique field ID: ob_ + 8 random alphanumeric chars */
export function generateFieldId(): string {
	const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
	let result = 'ob_';
	for ( let i = 0; i < 8; i++ ) {
		result += chars.charAt( Math.floor( Math.random() * chars.length ) );
	}
	return result;
}

/**
 * Get default field definition for a given type
 * @param type
 */
export function getDefaultField( type: string ): FieldDefinition {
	const base: FieldDefinition = {
		id: generateFieldId(),
		type,
		label: '',
		description: '',
		placeholder: '',
		required: false,
		class_name: '',
		price_type: 'none',
		price: 0,
		weight: 0,
		conditions: {
			status: 'inactive',
			action: 'show',
			match: 'ALL',
			rules: [],
		},
	};

	// Add type-specific defaults
	switch ( type ) {
		case 'select':
		case 'radio':
		case 'checkbox':
			base.options = [
				{
					label: '',
					value: '',
					price_type: 'flat',
					price: 0,
					weight: 0,
				},
			];
			break;
		case 'color_swatch':
			base.display_style = 'swatch_only';
			base.options = [
				{
					label: '',
					value: '',
					price_type: 'flat',
					price: 0,
					weight: 0,
					color: '#3498db',
				},
			];
			break;
		case 'image_swatch':
			base.display_style = 'swatch_label';
			base.options = [
				{
					label: '',
					value: '',
					price_type: 'flat',
					price: 0,
					weight: 0,
					image_url: '',
				},
			];
			break;
		case 'text':
		case 'textarea':
			base.min_length = 0;
			base.max_length = 0;
			break;
		case 'number':
			base.min_value = 0;
			base.max_value = 100;
			base.step = 1;
			break;
		case 'file':
			base.allowed_types = '.jpg,.png,.pdf';
			base.max_file_size = 5; // MB
			break;
		case 'static_content':
			base.content = '';
			break;
	}

	return base;
}

// ─── Reducer ─────────────────────────────────────────────────────────────

function addonReducer(
	state: AddonGroupState,
	action: AddonAction
): AddonGroupState {
	switch ( action.type ) {
		case 'SET_GROUP':
			return { ...state, ...action.payload, isDirty: false };

		case 'SET_TITLE':
			return { ...state, title: action.payload, isDirty: true };

		case 'SET_STATUS':
			return { ...state, status: action.payload, isDirty: true };

		case 'SET_ASSIGNMENTS':
			return { ...state, assignments: action.payload, isDirty: true };

		case 'ADD_FIELD':
			return {
				...state,
				schema: [ ...state.schema, action.payload ],
				expandedFieldId: action.payload.id,
				isDirty: true,
			};

		case 'REMOVE_FIELD':
			return {
				...state,
				schema: state.schema.filter( ( f ) => f.id !== action.payload ),
				isDirty: true,
			};

		case 'UPDATE_FIELD':
			return {
				...state,
				schema: state.schema.map( ( f ) =>
					f.id === action.payload.id
						? { ...f, ...action.payload.updates }
						: f
				),
				isDirty: true,
			};

		case 'REORDER_FIELDS':
			return {
				...state,
				schema: action.payload,
				isDirty: true,
			};

		case 'MOVE_UP': {
			const idx = action.payload;
			if ( idx <= 0 ) {
				return state;
			}
			const newSchema = [ ...state.schema ];
			const [ moved ] = newSchema.splice( idx, 1 );
			newSchema.splice( idx - 1, 0, moved );
			return { ...state, schema: newSchema, isDirty: true };
		}

		case 'DUPLICATE_FIELD': {
			const fieldIndex = state.schema.findIndex(
				( f ) => f.id === action.payload
			);
			if ( fieldIndex === -1 ) {
				return state;
			}

			const originalField = state.schema[ fieldIndex ];
			const newField = {
				...JSON.parse( JSON.stringify( originalField ) ), // Deep clone
				id: generateFieldId(),
				label:
					( originalField.label || '' ) +
					' ' +
					__( '(Copy)', 'notifybay-waitlist-and-stock-alert-woo' ),
			};

			const newSchema = [ ...state.schema ];
			newSchema.splice( fieldIndex + 1, 0, newField );

			return {
				...state,
				schema: newSchema,
				isDirty: true,
				expandedFieldId: newField.id, // Auto expand the new field
			};
		}

		case 'MOVE_DOWN': {
			const idx = action.payload;
			if ( idx >= state.schema.length - 1 ) {
				return state;
			}
			const newSchema = [ ...state.schema ];
			const [ moved ] = newSchema.splice( idx, 1 );
			newSchema.splice( idx + 1, 0, moved );
			return { ...state, schema: newSchema, isDirty: true };
		}

		case 'ADD_OPTION': {
			return {
				...state,
				schema: state.schema.map( ( f ) =>
					f.id === action.payload.fieldId
						? {
								...f,
								options: [
									...( f.options || [] ),
									action.payload.option,
								],
						  }
						: f
				),
				isDirty: true,
			};
		}

		case 'REMOVE_OPTION': {
			return {
				...state,
				schema: state.schema.map( ( f ) =>
					f.id === action.payload.fieldId
						? {
								...f,
								options: ( f.options || [] ).filter(
									( _, i ) => i !== action.payload.optionIndex
								),
						  }
						: f
				),
				isDirty: true,
			};
		}

		case 'UPDATE_OPTION': {
			return {
				...state,
				schema: state.schema.map( ( f ) =>
					f.id === action.payload.fieldId
						? {
								...f,
								options: ( f.options || [] ).map( ( opt, i ) =>
									i === action.payload.optionIndex
										? { ...opt, ...action.payload.updates }
										: opt
								),
						  }
						: f
				),
				isDirty: true,
			};
		}

		case 'SET_LOADING':
			return { ...state, isLoading: action.payload };

		case 'SET_SAVING':
			return { ...state, isSaving: action.payload };

		case 'SET_ERROR':
			return { ...state, error: action.payload };

		case 'SET_ERRORS':
			return { ...state, errors: action.payload };

		case 'MARK_CLEAN':
			return { ...state, isDirty: false };

		case 'TOGGLE_EXPAND_FIELD':
			return {
				...state,
				expandedFieldId:
					state.expandedFieldId === action.payload
						? null
						: action.payload,
			};

		case 'RESET':
			return { ...initialState };

		default:
			return state;
	}
}

// ─── Context ─────────────────────────────────────────────────────────────

interface AddonContextValue {
	state: AddonGroupState;
	dispatch: React.Dispatch< AddonAction >;
}

const AddonContext = createContext< AddonContextValue | undefined >(
	undefined
);

export function AddonProvider( { children }: { children: ReactNode } ) {
	const [ state, dispatch ] = useReducer( addonReducer, initialState );
	return (
		<AddonContext.Provider value={ { state, dispatch } }>
			{ children }
		</AddonContext.Provider>
	);
}

export function useAddonContext(): AddonContextValue {
	const context = useContext( AddonContext );
	if ( ! context ) {
		throw new Error( 'useAddonContext must be used within AddonProvider' );
	}
	return context;
}
