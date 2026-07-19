/* eslint-disable */
import React, {
	useState,
	useRef,
	useEffect,
	KeyboardEvent,
	useMemo,
} from 'react';
import { ChevronDown, X, Lock, Hourglass } from 'lucide-react';
import { MultiSelectOption } from '../common/MultiSelect';
import apiFetch from '../../utils/apiFetch';

// Hook for click outside
function useClickOutside(
	ref: React.RefObject< HTMLElement >,
	handler: ( event: MouseEvent | TouchEvent ) => void
) {
	useEffect( () => {
		const listener = ( event: MouseEvent | TouchEvent ) => {
			if (
				! ref.current ||
				ref.current.contains( event.target as Node )
			) {
				return;
			}
			handler( event );
		};
		document.addEventListener( 'mousedown', listener );
		document.addEventListener( 'touchstart', listener );
		return () => {
			document.removeEventListener( 'mousedown', listener );
			document.removeEventListener( 'touchstart', listener );
		};
	}, [ ref, handler ] );
}

interface ClassicMultiSelectProps {
	id?: string;
	value: ( string | number )[];
	onChange: ( value: ( string | number )[] ) => void;
	options?: MultiSelectOption[];
	endpoint?: string;
	placeholder?: string;
	disabled?: boolean;
	className?: string;
	label?: string | React.ReactNode;
	enableSearch?: boolean;
	size?: 'short' | 'regular';
	renderOption?: ( option: MultiSelectOption ) => React.ReactNode;
	description?: string;
	differentDropdownWidth?: boolean;
}

export const ClassicMultiSelect: React.FC< ClassicMultiSelectProps > = ( {
	id,
	value,
	onChange,
	options = [],
	endpoint,
	placeholder = 'Select options...',
	disabled = false,
	className = '',
	label,
	enableSearch = true,
	size = 'short',
	renderOption,
	description,
	differentDropdownWidth = false,
} ) => {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ highlightedIndex, setHighlightedIndex ] = useState< number >( -1 );
	const [ searchQuery, setSearchQuery ] = useState( '' );
	const [ apiOptions, setApiOptions ] = useState< MultiSelectOption[] >( [] );
	const [ allSeenOptions, setAllSeenOptions ] = useState<
		MultiSelectOption[]
	>( options || [] );
	const [ isLoading, setIsLoading ] = useState( false );
	const initialFetchDone = useRef( false );

	const containerRef = useRef< HTMLDivElement >( null );
	const listRef = useRef< HTMLUListElement >( null );
	const searchInputRef = useRef< HTMLInputElement >( null );
	const interactionType = useRef< 'mouse' | 'keyboard' >( 'keyboard' );

	const [ tooltipState, setTooltipState ] = useState< {
		visible: boolean;
		top: number;
		left: number;
		text: string;
	} | null >( null );
	const hoverTimeoutRef = useRef< number | null >( null );

	useClickOutside( containerRef, () => {
		setIsOpen( false );
		setSearchQuery( '' );
		setTooltipState( null );
	} );

	// Merge fetched options into allSeenOptions so selected items keep their labels
	useEffect( () => {
		if ( apiOptions.length > 0 ) {
			setAllSeenOptions( ( prev ) => {
				const map = new Map( prev.map( ( o ) => [ o.value, o ] ) );
				apiOptions.forEach( ( o ) => map.set( o.value, o ) );
				return Array.from( map.values() );
			} );
		}
	}, [ apiOptions ] );

	// Initial fetch when component mounts with pre-selected values
	// Uses the `ids` parameter so the API returns exactly these items
	useEffect( () => {
		if ( ! endpoint || initialFetchDone.current || value.length === 0 ) {
			return;
		}
		initialFetchDone.current = true;

		const separator = endpoint.includes( '?' ) ? '&' : '?';
		const path = `${ endpoint }${ separator }ids=${ value.join( ',' ) }`;

		apiFetch( { path, method: 'GET' } )
			.then( ( res: any ) => {
				const data = res?.data || res || [];
				setAllSeenOptions( ( prev ) => {
					const map = new Map( prev.map( ( o ) => [ o.value, o ] ) );
					data.forEach( ( o: MultiSelectOption ) =>
						map.set( o.value, o )
					);
					return Array.from( map.values() );
				} );
			} )
			.catch( () => {} );
	}, [ endpoint, value ] );

	const effectiveOptions = endpoint ? apiOptions : options;

	useEffect( () => {
		if ( ! endpoint ) {
			return;
		}
		if ( ! isOpen ) {
			return;
		} // Only fetch when opened

		let active = true;
		const delayDebounceFn = setTimeout( async () => {
			try {
				setIsLoading( true );
				const separator = endpoint.includes( '?' ) ? '&' : '?';
				const path = `${ endpoint }${ separator }search=${ encodeURIComponent(
					searchQuery
				) }`;

				const res: any = await apiFetch( { path, method: 'GET' } );

				if ( active ) {
					setApiOptions( res?.data || res || [] );
					setIsLoading( false );
				}
			} catch {
				if ( active ) {
					setIsLoading( false );
				}
			}
		}, 300 );

		return () => {
			active = false;
			clearTimeout( delayDebounceFn );
		};
	}, [ endpoint, searchQuery, isOpen ] );

	const filteredOptions = useMemo( () => {
		if ( endpoint ) {
			return effectiveOptions;
		}
		if ( ! enableSearch || ! searchQuery ) {
			return effectiveOptions;
		}
		return effectiveOptions.filter( ( opt ) =>
			opt.label.toLowerCase().includes( searchQuery.toLowerCase() )
		);
	}, [ effectiveOptions, searchQuery, enableSearch, endpoint ] );

	// Selected values — use allSeenOptions for endpoint mode so labels persist
	const selectedOptions = useMemo( () => {
		const lookupSource = endpoint ? allSeenOptions : effectiveOptions;
		return value.map( ( v ) => {
			const found = lookupSource.find( ( opt ) => opt.value === v );
			return found || { value: v, label: `${ v }` };
		} );
	}, [ effectiveOptions, allSeenOptions, value, endpoint ] );

	useEffect( () => {
		if ( isOpen ) {
			if ( enableSearch && searchInputRef.current ) {
				requestAnimationFrame( () => searchInputRef.current?.focus() );
			}
			setHighlightedIndex( 0 );
			interactionType.current = 'keyboard';
		} else {
			setSearchQuery( '' );
			setTooltipState( null );
		}
	}, [ isOpen, enableSearch, filteredOptions.length ] );

	useEffect( () => {
		if (
			isOpen &&
			listRef.current &&
			highlightedIndex >= 0 &&
			interactionType.current === 'keyboard'
		) {
			const list = listRef.current;
			const element = list.children[ highlightedIndex ] as HTMLElement;
			if ( element ) {
				const listTop = list.scrollTop;
				const listBottom = listTop + list.clientHeight;
				const elementTop = element.offsetTop;
				const elementBottom = elementTop + element.offsetHeight;
				if ( elementTop < listTop ) {
					list.scrollTop = elementTop;
				} else if ( elementBottom > listBottom ) {
					list.scrollTop = elementBottom - list.clientHeight;
				}
			}
		}
	}, [ highlightedIndex, isOpen ] );

	const handleSelect = ( option: MultiSelectOption ) => {
		// @ts-ignore - sharing variant types from Select for consistency
		const variant = option.variant;
		if (
			option.disabled ||
			variant === 'buy_pro' ||
			variant === 'coming_soon'
		) {
			return;
		}

		if ( value.includes( option.value ) ) {
			onChange( value.filter( ( v ) => v !== option.value ) );
		} else {
			onChange( [ ...value, option.value ] );
		}

		if ( searchInputRef.current ) {
			searchInputRef.current.focus();
		}
	};

	const handleRemove = (
		e: React.MouseEvent,
		valToRemove: string | number
	) => {
		e.stopPropagation();
		onChange( value.filter( ( v ) => v !== valToRemove ) );
	};

	const handleTriggerKeyDown = ( e: KeyboardEvent< HTMLDivElement > ) => {
		if ( disabled ) {
			return;
		}
		if ( isOpen && enableSearch ) {
			return;
		}

		interactionType.current = 'keyboard';
		switch ( e.key ) {
			case 'Enter':
			case ' ':
				e.preventDefault();
				setIsOpen( ! isOpen );
				break;
			case 'ArrowDown':
				e.preventDefault();
				if ( ! isOpen ) {
					setIsOpen( true );
				} else {
					setHighlightedIndex( ( prev ) =>
						prev < filteredOptions.length - 1 ? prev + 1 : 0
					);
				}
				break;
			case 'ArrowUp':
				e.preventDefault();
				if ( ! isOpen ) {
					setIsOpen( true );
				} else {
					setHighlightedIndex( ( prev ) =>
						prev > 0 ? prev - 1 : filteredOptions.length - 1
					);
				}
				break;
			case 'Escape':
				if ( isOpen ) {
					e.preventDefault();
					setIsOpen( false );
				}
				break;
		}
	};

	const handleSearchKeyDown = ( e: KeyboardEvent< HTMLInputElement > ) => {
		interactionType.current = 'keyboard';
		switch ( e.key ) {
			case 'ArrowDown':
				e.preventDefault();
				setHighlightedIndex( ( prev ) =>
					prev < filteredOptions.length - 1 ? prev + 1 : 0
				);
				break;
			case 'ArrowUp':
				e.preventDefault();
				setHighlightedIndex( ( prev ) =>
					prev > 0 ? prev - 1 : filteredOptions.length - 1
				);
				break;
			case 'Enter':
				e.preventDefault();
				if ( filteredOptions[ highlightedIndex ] ) {
					handleSelect( filteredOptions[ highlightedIndex ] );
				}
				break;
			case 'Backspace':
				if ( ! searchQuery && value.length > 0 ) {
					onChange( value.slice( 0, -1 ) );
				}
				break;
			case 'Escape':
				e.preventDefault();
				setIsOpen( false );
				break;
		}
	};

	const selectId =
		id || `classic-multi-${ Math.random().toString( 36 ).slice( 2, 9 ) }`;
	const sizeClass = size === 'short' ? '' : '';
	const explicitWidth =
		size === 'short' ? 'min-content' : size === 'regular' ? 'auto' : '100%';

	return (
		<div
			className={ `${ sizeClass } ${ className } notifybay-align-middle` }
			ref={ containerRef }
		>
			{ label && (
				<label
					htmlFor={ selectId }
					className="notifybay-block notifybay-mb-1"
				>
					{ label }
				</label>
			) }

			<div
				className="notifybay-relative"
				style={ { width: explicitWidth } }
			>
				<div
					id={ selectId }
					tabIndex={ disabled ? -1 : 0 }
					role="combobox"
					aria-expanded={ isOpen }
					onClick={ ( e ) => {
						if ( disabled ) {
							return;
						}
						// Don't toggle closed if clicking inside the search input while already open
						if (
							isOpen &&
							( e.target as HTMLElement ).tagName === 'INPUT'
						) {
							return;
						}
						setIsOpen( ! isOpen );
					} }
					onKeyDown={ handleTriggerKeyDown }
					className={ `notifybay-flex notifybay-flex-wrap notifybay-items-center notifybay-gap-1 notifybay-bg-white notifybay-border notifybay-border-[#8c8f94] notifybay-rounded-[3px] notifybay-p-[3px_24px_3px_6px] notifybay-min-h-[30px] notifybay-transition-shadow notifybay-duration-100 notifybay-relative notifybay-box-border notifybay-w-full ${
						disabled
							? 'notifybay-cursor-not-allowed notifybay-bg-[#f0f0f1]'
							: 'notifybay-cursor-text'
					} ${
						isOpen
							? 'notifybay-border-[#2271b1] notifybay-shadow-[0_0_0_1px_#2271b1] notifybay-outline-none'
							: 'notifybay-shadow-none'
					}` }
				>
					{ selectedOptions.map( ( opt ) => (
						<span
							key={ opt.value }
							className="notifybay-bg-[#f0f0f1] notifybay-border notifybay-border-[#c3c4c7] notifybay-rounded-[3px] notifybay-px-1 notifybay-flex notifybay-items-center notifybay-gap-1 notifybay-text-xs notifybay-text-[#3c434a] notifybay-leading-[20px]"
						>
							{ opt.label }
							<button
								onClick={ ( e ) =>
									handleRemove( e, opt.value )
								}
								className="notifybay-bg-transparent notifybay-border-none notifybay-p-0 notifybay-cursor-pointer notifybay-text-[#8c8f94] notifybay-flex notifybay-items-center"
							>
								<X size={ 12 } />
							</button>
						</span>
					) ) }

					{ ! enableSearch && value.length === 0 && (
						<span className="notifybay-text-[#8c8f94] notifybay-text-[13px] notifybay-pl-1">
							{ placeholder }
						</span>
					) }

					{ /* Chevron icon pointing down */ }
					<span className="notifybay-absolute notifybay-right-1.5 notifybay-top-1/2 -notifybay-translate-y-1/2 notifybay-flex notifybay-pointer-events-none">
						<ChevronDown size={ 14 } color="#50575e" />
					</span>
				</div>

				{ isOpen && (
					<div
						className="notifybay-absolute notifybay-z-[99999] notifybay-bg-white notifybay-border-2 notifybay-border-[#2271b1] notifybay-border-t-0  notifybay-rounded-b-[3px] notifybay-shadow-[0_3px_5px_rgba(0,0,0,0.2)] notifybay-p-0 notifybay-box-border notifybay-top-full notifybay-left-[-1px] notifybay-mt-[-3px]"
						style={ {
							...( differentDropdownWidth
								? { minWidth: 'calc(100% + 2px)' }
								: { width: 'calc(100% + 2px)' } ),
						} }
					>
						{ enableSearch && (
							<input
								ref={ searchInputRef }
								type="text"
								value={ searchQuery }
								onFocus={ () => {
									if ( ! disabled && ! isOpen ) {
										setIsOpen( true );
									}
								} }
								onChange={ ( e ) => {
									setSearchQuery( e.target.value );
									if ( ! isOpen ) {
										setIsOpen( true );
									}
								} }
								onKeyDown={ handleSearchKeyDown }
								placeholder={
									value.length === 0 ? placeholder : ''
								}
								disabled={ disabled }
								className="notifybay-w-[calc(100%-8px)] notifybay-px-2 notifybay-leading-loose notifybay-min-h-[26px] notifybay-border notifybay-border-[#aaaaaa] notifybay-bg-[#fcfcfc] notifybay-rounded-[3px] notifybay-box-border notifybay-text-[13px] focus:notifybay-outline-none focus:notifybay-shadow-none notifybay-m-[4px]"
							/>
						) }
						{ isLoading ? (
							<div className="notifybay-py-2 notifybay-px-3 notifybay-text-[#646970] notifybay-text-[13px] notifybay-flex notifybay-items-center notifybay-gap-2">
								<Hourglass
									size={ 14 }
									className="notifybay-animate-spin"
								/>{ ' ' }
								Loading...
							</div>
						) : (
							<ul
								ref={ listRef }
								role="listbox"
								className="notifybay-max-h-[220px] notifybay-overflow-y-auto notifybay-m-0 notifybay-p-0 notifybay-list-none"
							>
								{ filteredOptions.length === 0 ? (
									<li className="notifybay-px-3 notifybay-py-1.5 notifybay-text-[#646970] notifybay-italic notifybay-text-[13px] notifybay-m-0">
										{ searchQuery
											? 'No results found'
											: 'No options available' }
									</li>
								) : (
									filteredOptions.map( ( opt, index ) => {
										const isSelected = value.includes(
											opt.value
										);
										const isHighlighted =
											highlightedIndex === index;
										// @ts-ignore
										const variant = opt.variant;
										const isPro = variant === 'buy_pro';
										const isComingSoon =
											variant === 'coming_soon';
										const isDisabled =
											opt.disabled ||
											isPro ||
											isComingSoon;

										return (
											<li
												key={ opt.value }
												role="option"
												aria-selected={ isSelected }
												onMouseEnter={ ( e ) => {
													interactionType.current =
														'mouse';
													setHighlightedIndex(
														index
													);
													if (
														isPro ||
														isComingSoon
													) {
														const rect =
															e.currentTarget.getBoundingClientRect();
														setTooltipState( {
															visible: true,
															top: rect.top,
															left:
																rect.left +
																rect.width / 2,
															text: isPro
																? 'Available in Pro'
																: 'Coming Soon',
														} );
													} else {
														setTooltipState( null );
													}
												} }
												onMouseLeave={ () =>
													setTooltipState( null )
												}
												onClick={ ( e ) => {
													e.stopPropagation();
													handleSelect( opt );
												} }
												className={ `notifybay-px-3 notifybay-py-1.5 notifybay-flex notifybay-items-center notifybay-justify-between notifybay-text-[13px] notifybay-m-0 ${
													isDisabled
														? 'notifybay-cursor-not-allowed'
														: 'notifybay-cursor-pointer'
												} ${
													isHighlighted
														? 'notifybay-bg-[#2271b1] notifybay-text-white'
														: isDisabled
														? 'notifybay-bg-transparent notifybay-text-[#a7aaad]'
														: 'notifybay-bg-transparent notifybay-text-[#2c3338]'
												}` }
											>
												<div className="notifybay-flex notifybay-items-center notifybay-gap-2">
													<div
														className={ `
                            notifybay-flex notifybay-items-center notifybay-justify-center
                            notifybay-w-4 notifybay-h-4 notifybay-rounded notifybay-border-2 notifybay-transition-all notifybay-duration-200
                            ${
								isSelected
									? isHighlighted
										? 'notifybay-border-white notifybay-bg-white'
										: 'notifybay-border-[#2271b1] notifybay-bg-[#2271b1]'
									: isHighlighted
									? 'notifybay-border-white notifybay-bg-transparent'
									: 'notifybay-border-[#8c8f94] notifybay-bg-white'
							}
                          ` }
													>
														<svg
															className={ `notifybay-w-3.5 notifybay-h-3.5 notifybay-transform notifybay-transition-transform notifybay-duration-200 ${
																isSelected
																	? 'notifybay-scale-100'
																	: 'notifybay-scale-0'
															} ${
																isHighlighted &&
																isSelected
																	? 'notifybay-text-[#2271b1]'
																	: 'notifybay-text-white'
															}` }
															viewBox="0 0 24 24"
															fill="none"
															stroke="currentColor"
															strokeWidth="3"
															strokeLinecap="round"
															strokeLinejoin="round"
														>
															<polyline points="20 6 9 17 4 12"></polyline>
														</svg>
													</div>
													<span>
														{ renderOption
															? renderOption(
																	opt
															  )
															: opt.label }
													</span>
												</div>

												{ /* Icons for variants */ }
												{ isPro && (
													<span
														className={ `notifybay-flex ${
															isHighlighted
																? 'notifybay-text-white'
																: 'notifybay-text-[#ffb900]'
														}` }
													>
														<Lock size={ 14 } />
													</span>
												) }
												{ isComingSoon && (
													<span
														className={ `notifybay-text-[10px] notifybay-uppercase notifybay-px-1.5 notifybay-py-0.5 notifybay-rounded-[10px] notifybay-font-semibold notifybay-flex notifybay-items-center notifybay-gap-1 ${
															isHighlighted
																? 'notifybay-bg-white/20 notifybay-text-white'
																: 'notifybay-bg-[#f0f0f1] notifybay-text-[#646970]'
														}` }
													>
														<Hourglass
															size={ 10 }
														/>
														Soon
													</span>
												) }
											</li>
										);
									} )
								) }
							</ul>
						) }
					</div>
				) }
			</div>

			{ description && (
				<p className="description notifybay-mt-1">{ description }</p>
			) }

			{ tooltipState?.visible && (
				<div
					className="notifybay-fixed notifybay-bg-[#1d2327] notifybay-text-white notifybay-px-2.5 notifybay-py-1 notifybay-rounded-[3px] notifybay-text-[12px] notifybay-pointer-events-none notifybay-z-[100000] notifybay-whitespace-nowrap"
					style={ {
						top: tooltipState.top - 8,
						left: tooltipState.left,
						transform: 'translate(-50%, -100%)',
					} }
				>
					{ tooltipState.text }
					<div className="notifybay-absolute -notifybay-bottom-1 notifybay-left-1/2 -notifybay-translate-x-1/2 notifybay-border-x-4 notifybay-border-t-4 notifybay-border-x-transparent notifybay-border-b-transparent notifybay-border-t-[#1d2327]" />
				</div>
			) }
		</div>
	);
};
