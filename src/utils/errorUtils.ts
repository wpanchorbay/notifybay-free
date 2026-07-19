/**
 * Flattens a nested object into a single-level object with dot-notation keys.
 * This is used to map API validation errors (which might be nested) to the
 * flat structure expected by the frontend components.
 *
 * Example:
 * { schema: [{ label: "Error" }] } -> { "schema.0.label": "Error" }
 * @param errors
 * @param prefix
 */
export function flattenErrors(
	errors: any,
	prefix = ''
): Record< string, string > {
	const flattened: Record< string, string > = {};

	if ( ! errors || typeof errors !== 'object' ) {
		return flattened;
	}

	Object.keys( errors ).forEach( ( key ) => {
		const value = errors[ key ];
		const newKey = prefix ? `${ prefix }.${ key }` : key;

		if ( typeof value === 'string' ) {
			flattened[ newKey ] = value;
		} else if ( Array.isArray( value ) ) {
			value.forEach( ( item, index ) => {
				if ( typeof item === 'string' ) {
					flattened[ `${ newKey }.${ index }` ] = item;
				} else {
					Object.assign(
						flattened,
						flattenErrors( item, `${ newKey }.${ index }` )
					);
				}
			} );
		} else if ( typeof value === 'object' && value !== null ) {
			Object.assign( flattened, flattenErrors( value, newKey ) );
		}
	} );

	return flattened;
}
