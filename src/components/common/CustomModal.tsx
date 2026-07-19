/* eslint-disable */
import React, { ReactNode, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';

interface CustomModalProps {
	isOpen: boolean;
	onClose: () => void;
	title: ReactNode;
	children: ReactNode;
	footer?: ReactNode;
	maxWidth?: string;
	closeOnOutsideClick?: boolean;
	className?: string;
	showHeader?: boolean;
	classNames?: {
		header?: string;
		body?: string;
		footer?: string;
	};
}

const CustomModal: React.FC< CustomModalProps > = ( {
	isOpen,
	onClose,
	title,
	children,
	footer,
	maxWidth = 'notifybay-max-w-2xl',
	closeOnOutsideClick = true,
	className = '',
	showHeader = true,
	classNames = {
		header: '',
		body: '',
		footer: '',
	},
} ) => {
	// Handle Escape key to close
	useEffect( () => {
		const handleEsc = ( e: KeyboardEvent ) => {
			if ( e.key === 'Escape' ) {
				onClose();
			}
		};

		if ( isOpen ) {
			window.addEventListener( 'keydown', handleEsc );
			// Prevent scrolling on body when modal is open
			document.body.style.overflow = 'hidden';
		}

		return () => {
			window.removeEventListener( 'keydown', handleEsc );
			document.body.style.overflow = '';
		};
	}, [ isOpen, onClose ] );

	if ( ! isOpen ) {
		return null;
	}

	return createPortal(
		<div className="notifybay-fixed notifybay-inset-0 notifybay-z-[9998] notifybay-flex notifybay-items-center notifybay-justify-center notifybay-p-4 notifybay-bg-black/75 notifybay-transition-opacity notifybay-duration-300">
			{ /* Backdrop click handler */ }
			<div
				className="notifybay-absolute notifybay-inset-0"
				onClick={ closeOnOutsideClick ? onClose : undefined }
			/>

			{ /* Modal Content */ }
			<div
				className={ `
          notifybay-relative notifybay-w-full ${ maxWidth } 
          notifybay-bg-white notifybay-shadow-2xl notifybay-rounded-xl 
          notifybay-flex notifybay-flex-col notifybay-max-h-[90vh]
          notifybay-animate-in notifybay-fade-in notifybay-zoom-in-95 notifybay-duration-200
          ${ className }
        ` }
				role="dialog"
				aria-modal="true"
			>
				{ /* Header */ }
				{ showHeader && (
					<div
						className={ `notifybay-flex notifybay-items-center notifybay-justify-between notifybay-px-6 notifybay-py-4 notifybay-border-b notifybay-border-gray-100 ${ classNames.header }` }
					>
						<h3 className="notifybay-text-lg notifybay-font-semibold notifybay-text-gray-900">
							{ title }
						</h3>
						<button
							onClick={ onClose }
							className="notifybay-p-1.5 notifybay-text-gray-400 hover:notifybay-text-gray-600 notifybay-transition-colors hover:notifybay-bg-gray-100 notifybay-rounded-full"
							aria-label="Close modal"
						>
							<X className="notifybay-w-5 notifybay-h-5" />
						</button>
					</div>
				) }

				{ /* Body */ }
				<div
					className={ `notifybay-p-6 notifybay-overflow-y-auto notifybay-flex-1 ${ classNames.body }` }
				>
					{ children }
				</div>

				{ /* Footer */ }
				{ footer && (
					<div
						className={ `notifybay-flex notifybay-items-center notifybay-justify-end notifybay-gap-3 notifybay-px-6 notifybay-py-4 notifybay-bg-gray-50 notifybay-border-t notifybay-border-gray-100 notifybay-rounded-b-xl ${ classNames.footer }` }
					>
						{ footer }
					</div>
				) }
			</div>
		</div>,
		document.body
	);
};

export default CustomModal;
