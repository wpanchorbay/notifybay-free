/* eslint-disable */
import React, { useState, FC } from 'react';
import { BookmarkCheck, CopyCheck, Paperclip } from 'lucide-react';

// Define the interface for the component's props
interface CopyToClipboardProps {
	/** The text content to be copied to the clipboard. */
	text: string;
}

export const CopyToClipboard: FC< CopyToClipboardProps > = ( { text } ) => {
	const [ copied, setCopied ] = useState< boolean >( false );
	const [ copping, setCopping ] = useState< boolean >( false );

	const handleCopy = async () => {
		try {
			await navigator.clipboard.writeText( text );
			setCopied( true );
			setCopping( true );
			setTimeout( () => setCopping( false ), 200 ); // Quick transition from check to bookmark
			setTimeout( () => setCopied( false ), 2000 ); // Reset to original state after 2 seconds
		} catch ( err ) {
			console.error( 'Failed to copy text: ', err );
		}
	};

	return (
		<button
			onClick={ handleCopy }
			className="notifybay-inline-flex notifybay-items-center notifybay-justify-center notifybay-cursor-pointer"
			aria-label={ `Copy "${ text }" to clipboard` }
		>
			{ copied ? (
				<>
					{ copping ? (
						<CopyCheck size={ 14 } aria-live="polite" />
					) : (
						<BookmarkCheck size={ 16 } aria-live="polite" />
					) }
				</>
			) : (
				<Paperclip size={ 14 } />
			) }
		</button>
	);
};

export default CopyToClipboard;
