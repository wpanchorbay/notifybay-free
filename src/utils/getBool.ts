export default function getBool( value: any ): boolean {
	if ( value === 1 || value === true || value === 'true' || value === '1' ) {
		return true;
	}
	return false;
}
