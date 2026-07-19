/* eslint-disable */
import { FC } from 'react';
import { useToast } from '../../store/toast/use-toast';
import { Toast } from './Toast';

export const ToastContainer: FC = () => {
	const { toasts, removeToast } = useToast();
	return (
		<div className="notifybay-fixed notifybay-bottom-[30px] notifybay-right-[10px] notifybay-z-[999999] notifybay-flex notifybay-flex-col notifybay-gap-[10px] notifybay-min-w-[200px] notifybay-pointer-events-none">
			{ toasts.map( ( toast ) => (
				<Toast
					key={ toast.id }
					toast={ toast }
					onDismiss={ removeToast }
				/>
			) ) }
		</div>
	);
};
