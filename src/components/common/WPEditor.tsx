/* eslint-disable */
import { useEffect, useRef } from '@wordpress/element';

interface WPEditorProps {
	id: string;
	value: string;
	onChange: ( content: string ) => void;
}

export const WPEditor = ( { id, value, onChange }: WPEditorProps ) => {
	const textareaRef = useRef< HTMLTextAreaElement >( null );
	const editorInitialized = useRef( false );

	useEffect( () => {
		if ( ! window.wp || ! window.wp.editor || ! textareaRef.current ) {
			console.warn(
				'wp.editor is not available. Using standard textarea fallback.'
			);
			return;
		}

		const initEditor = () => {
			if ( editorInitialized.current ) {
				return;
			}
			window.wp.editor.initialize( id, {
				tinymce: {
					wpautop: true,
					plugins:
						'charmap colorpicker compat3x directionality fullscreen hr image lists media paste tabfocus textcolor wordpress wpautoresize wpdialogs wpeditimage wpemoji wpgallery wplink wptextpattern wpview',
					toolbar1:
						'formatselect bold italic | bullist numlist | blockquote | alignleft aligncenter alignright | link unlink | wp_more | spellchecker | fullscreen wp_adv',
					setup: ( editor: any ) => {
						editor.on( 'change keyup', () => {
							const content = editor.getContent();
							onChange( content );
						} );
					},
				},
				quicktags: true,
			} );
			editorInitialized.current = true;
		};

		// Initialize after a tiny delay so DOM settles
		const timeout = setTimeout( initEditor, 100 );

		return () => {
			clearTimeout( timeout );
			if ( editorInitialized.current && window.wp && window.wp.editor ) {
				window.wp.editor.remove( id );
				editorInitialized.current = false;
			}
		};
	}, [ id, onChange ] );

	return (
		<textarea
			id={ id }
			ref={ textareaRef }
			defaultValue={ value }
			onChange={ ( e ) => onChange( e.target.value ) }
			className="notifybay-w-full notifybay-border notifybay-border-gray-300 notifybay-p-2"
			rows={ 5 }
		/>
	);
};
