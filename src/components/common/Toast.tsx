/* eslint-disable */
import React, { useEffect, useState, FC } from 'react';

import { Toast as ToastType } from '../../store/toast/use-toast';
import { close, Icon } from '@wordpress/icons';

interface ToastProps {
	toast: ToastType;
	onDismiss: ( id: number ) => void;
}
export const Toast: FC< ToastProps > = ( { toast, onDismiss } ) => {
	const [ isClosing, setIsClosing ] = useState< boolean >( false );

	const handleDismiss = () => {
		setIsClosing( true );
		setTimeout( () => {
			onDismiss( toast.id );
		}, 300 ); // 300ms animation
	};

	useEffect( () => {
		const timer = setTimeout( () => {
			handleDismiss();
		}, 5000 ); // 5 seconds
		return () => {
			clearTimeout( timer );
		};
	}, [ toast.id ] );

	const getToastTypeClasses = () => {
		switch ( toast.type ) {
			case 'success':
				return 'notifybay-bg-[#f0fff4] notifybay-border-l-[#228b22] notifybay-text-[#1a472a]';
			case 'error':
				return 'notifybay-bg-[#fff5f5] notifybay-border-l-[#cc0000] notifybay-text-[#5c2121]';
			case 'info':
			default:
				return 'notifybay-bg-white notifybay-border-l-[#2271b1] notifybay-text-[#1d2327]';
		}
	};

	const toastClasses = `
    notifybay-relative notifybay-p-5 notifybay-rounded-[4px] notifybay-shadow-[0_4px_12px_rgba(0,0,0,0.15)] 
    notifybay-flex notifybay-items-center notifybay-justify-between notifybay-gap-[15px] 
    notifybay-border-l-[5px] notifybay-backdrop-blur-[3px]
    ${
		isClosing ? 'notifybay-animate-slide-out' : 'notifybay-animate-slide-in'
	}
    ${ getToastTypeClasses() }
  `;

	return (
		<div className={ toastClasses }>
			<p className="notifybay-m-0 notifybay-text-[14px] notifybay-leading-[1.5] notifybay-flex-1 ">
				{ toast.message }
			</p>
			<button
				className="notifybay-bg-none notifybay-border-none notifybay-text-inherit notifybay-opacity-60 hover:notifybay-opacity-100 notifybay-cursor-pointer notifybay-text-[20px] notifybay-leading-none notifybay-px-[5px] notifybay-self-start -notifybay-mt-[5px] -notifybay-mr-[5px] -notifybay-mb-[5px] notifybay-ml-0"
				onClick={ handleDismiss }
				aria-label="Dismiss"
			>
				<Icon icon={ close } />
			</button>
		</div>
	);
};

export default Toast;
