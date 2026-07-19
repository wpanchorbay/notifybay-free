import React, {
	createContext,
	useState,
	useCallback,
	useContext,
	ReactNode,
	FC,
} from 'react';

export type ToastType = 'success' | 'error' | 'info' | 'warning';

export interface Toast {
	id: number;
	message: string;
	type: ToastType;
}

export interface ToastContextValue {
	toasts: Toast[];
	addToast: ( message: string, type?: ToastType ) => void;
	removeToast: ( id: number ) => void;
}

export const ToastContext = createContext< ToastContextValue | null >( null );

interface ToastProviderProps {
	children: ReactNode;
}

export const ToastProvider: FC< ToastProviderProps > = ( { children } ) => {
	// The state is typed, so `toasts` is always known to be `Toast[]`.
	const [ toasts, setToasts ] = useState< Toast[] >( [] );

	/**
	 * Removes a toast from the state by its ID.
	 */
	const removeToast = useCallback( ( id: number ) => {
		setToasts( ( currentToasts ) =>
			currentToasts.filter( ( toast ) => toast.id !== id )
		);
	}, [] );

	/**
	 * Adds a new toast to the state.
	 */
	const addToast = useCallback(
		( message: string, type: ToastType = 'info' ) => {
			const id = Date.now() + Math.random();
			const newToast: Toast = { id, message, type };
			setToasts( ( currentToasts ) => [ ...currentToasts, newToast ] );
		},
		[]
	);

	// The value object automatically conforms to the `ToastContextValue` interface.
	const value: ToastContextValue = {
		toasts,
		addToast,
		removeToast,
	};

	return (
		<ToastContext.Provider value={ value }>
			{ children }
		</ToastContext.Provider>
	);
};

export const useToast = (): ToastContextValue => {
	const context = useContext( ToastContext );

	// This check acts as a runtime safeguard and a TypeScript type guard.
	if ( context === null ) {
		throw new Error( 'useToast must be used within a ToastProvider' );
	}

	// After the check, TypeScript knows `context` is of type `ToastContextValue`.
	return context;
};
