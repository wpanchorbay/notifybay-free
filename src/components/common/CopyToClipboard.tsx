/* eslint-disable */
import React, { useState, FC } from 'react';
import { BookmarkCheck, CopyCheck, Paperclip } from 'lucide-react';

// Define the interface for the component's props
interface CopyToClipboardProps {
	/** The text content to be copied to the clipboard. */
	text: string;
	/**
	 * Fired once the clipboard write has actually succeeded. Optional, so no
	 * existing caller changes -- it exists for callers that must know whether
	 * a secret was taken before they clear it off the screen.
	 */
	onCopy?: () => void;
}

export const CopyToClipboard: FC< CopyToClipboardProps > = ( {
	text,
	onCopy,
} ) => {
	const [ copied, setCopied ] = useState< boolean >( false );
	const [ copping, setCopping ] = useState< boolean >( false );

	const handleCopy = async () => {
		try {
			await navigator.clipboard.writeText( text );
			onCopy?.();
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
			/*
			 * Without this the button defaults to type="submit". These render
			 * inside WooCommerce's #mainform on the settings screens, where
			 * Settings.tsx intercepts submit and calls handleSave() -- so
			 * copying a value to the clipboard silently wrote the whole
			 * settings blob to the database and popped a "Settings saved"
			 * toast. Verified live: one click on a Copy button produced
			 * POST /notifybay/v1/settings.
			 */
			type="button"
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
