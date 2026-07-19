/* eslint-disable */
import React, {
	useState,
	useRef,
	useEffect,
	KeyboardEvent,
	useMemo,
} from 'react';
import { createPortal } from 'react-dom';
import { useClickOutside } from './hooks/useClickOutside';
import {
	borderClasses,
	errorClasses,
	errorClassesManual,
	hoverBorderClasses,
	hoverClassesManual,
} from './classes';

// SVGs replacement for lucide-react
const ChevronDown = ( { className }: { className?: string } ) => (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		fill="none"
		stroke="currentColor"
		strokeWidth="2"
		strokeLinecap="round"
		strokeLinejoin="round"
		className={ className }
	>
		<path d="m6 9 6 6 6-6" />
	</svg>
);

const LockKeyhole = ( { className }: { className?: string } ) => (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		fill="none"
		stroke="currentColor"
		strokeWidth="2"
		strokeLinecap="round"
		strokeLinejoin="round"
		className={ className }
	>
		<circle cx="12" cy="16" r="1" />
		<rect x="3" y="10" width="18" height="12" rx="2" />
		<path d="M7 10V7a5 5 0 0 1 10 0v3" />
	</svg>
);

const Hourglass = ( { className }: { className?: string } ) => (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		fill="none"
		stroke="currentColor"
		strokeWidth="2"
		strokeLinecap="round"
		strokeLinejoin="round"
		className={ className }
	>
		<path d="M5 22h14" />
		<path d="M5 2h14" />
		<path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22" />
		<path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2" />
	</svg>
);

export interface SelectOption {
	value: string | number;
	label: string;
	/**
	 * Optional custom classes for this specific option.
	 * Useful for multi-color dropdowns (e.g., badges, status colors).
	 */
	className?: string;
	disabled?: boolean;
	/**
	 * Special variants for the option.
	 * 'buy_pro' will disable the option and show a tooltip.
	 */
	variant?: 'buy_pro' | 'coming_soon';
}

export interface SelectProps {
	id?: string;
	/**
	 * The current selected value(s)
	 */
	value: SelectOption[ 'value' ] | null;
	/**
	 * Callback when an option is selected
	 */
	onChange: ( value: string | number ) => void;
	/**
	 * List of available options
	 */
	options: SelectOption[];
	/**
	 * Placeholder text when no value is selected
	 */
	placeholder?: string;

	/**
	 * Font size for the select options
	 */
	fontSize?: number;

	/**
	 * Font weight for the select options
	 */
	fontWeight?: number;
	/**
	 * Disable the entire interaction
	 */
	disabled?: boolean;
	/**
	 * Custom class for the container
	 */
	className?: string;
	/**
	 * Helper text or label (optional)
	 */
	label?: string;

	/**
	 * Enable search functionality within the dropdown
	 */
	enableSearch?: boolean;
	/**
	 * Reference to the container element
	 */
	con_ref?: React.Ref< HTMLDivElement >;
	/**
	 * Custom border class
	 */
	border?: string;
	/**
	 * Custom hover border class
	 */
	hoverBorder?: string;
	/**
	 * Custom text color class
	 */
	color?: string;

	isError?: boolean;

	errorClassName?: string;

	differentDropdownWidth?: boolean;

	hideIcon?: boolean;

	isCompact?: boolean;

	classNames?: {
		wrapper?: string;
		container?: string;
		label?: string;
		select?: string;
		option?: string;
		dropdown?: string;
		search?: string;
		error?: string;
	};

	/**
	 * Custom render function for option display.
	 * Receives the option object and returns a ReactNode.
	 * Used for both the selected display and the dropdown list.
	 */
	renderOption?: ( option: SelectOption ) => React.ReactNode;
}

const Select: React.FC< SelectProps > = ( {
	id,
	value,
	con_ref,
	onChange,
	options,
	placeholder = 'Select an option...',
	disabled = false,
	className = '',
	fontSize = 13,
	fontWeight = 500,
	label,
	enableSearch = false,
	border = borderClasses,
	hoverBorder = hoverBorderClasses,
	color = 'notifybay-text-[#0a4b78]',
	isError = false,
	errorClassName = errorClasses,
	differentDropdownWidth = false,
	hideIcon = false,
	isCompact = false,
	classNames = {} as NonNullable< SelectProps[ 'classNames' ] >,
	renderOption,
} ) => {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ highlightedIndex, setHighlightedIndex ] = useState< number >( -1 );
	const [ searchQuery, setSearchQuery ] = useState( '' );

	// Tooltip state
	const [ tooltipState, setTooltipState ] = useState< {
		visible: boolean;
		top: number;
		left: number;
		width: number;
		index: number;
	} | null >( null );

	const containerRef = useRef< HTMLDivElement >( null );
	const listRef = useRef< HTMLUListElement >( null );
	const searchInputRef = useRef< HTMLInputElement >( null );
	const hoverTimeoutRef = useRef< number | null >( null );
	const tooltipRef = useRef< HTMLDivElement >( null );

	// Track interaction type to prevent auto-scrolling on mouse hover
	const interactionType = useRef< 'mouse' | 'keyboard' >( 'keyboard' );

	// Close dropdown when clicking outside
	useClickOutside( containerRef, ( event ) => {
		if (
			tooltipRef.current &&
			tooltipRef.current.contains( event.target as Node )
		) {
			return;
		}
		setIsOpen( false );
		setTooltipState( null );
	} );

	const selectedOption = useMemo( () => {
		return options.find( ( option ) => option.value === value );
	}, [ options, value ] );

	// Filter options based on search query
	const filteredOptions = useMemo( () => {
		if ( ! enableSearch || ! searchQuery ) {
			return options;
		}
		return options.filter( ( option ) =>
			option.label.toLowerCase().includes( searchQuery.toLowerCase() )
		);
	}, [ options, searchQuery, enableSearch ] );

	// Reset search and highlighted index when opening/closing
	useEffect( () => {
		if ( isOpen ) {
			if ( enableSearch && searchInputRef.current ) {
				// Wait for render, then focus
				requestAnimationFrame( () => {
					searchInputRef.current?.focus();
				} );
			}

			// Highlight the currently selected item in the filtered list if present
			const selectedIndex = value
				? filteredOptions.findIndex( ( opt ) => opt.value === value )
				: 0;
			const initialIndex = selectedIndex >= 0 ? selectedIndex : 0;
			setHighlightedIndex( initialIndex );

			// Ensure we allow scrolling to the initial selection
			interactionType.current = 'keyboard';
		} else {
			// Clear search when closed
			setSearchQuery( '' );
			setTooltipState( null );
		}
	}, [ isOpen, value, enableSearch, filteredOptions.length ] );

	// Scroll highlighted item into view (Only if interaction was keyboard or initial open)
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

	const handleSelect = ( option: SelectOption ) => {
		if (
			option.disabled ||
			option.variant === 'buy_pro' ||
			option.variant === 'coming_soon'
		) {
			return;
		}
		onChange( option.value );
		setIsOpen( false );
		setSearchQuery( '' );
		setTooltipState( null );
	};

	// Keyboard handler for the Main Trigger Div
	const handleTriggerKeyDown = ( e: KeyboardEvent< HTMLDivElement > ) => {
		if ( disabled ) {
			return;
		}
		// If search is enabled and open, the input handles keys, not this div
		// But if closed, or if search is disabled, this handles keys.
		if ( isOpen && enableSearch ) {
			return;
		}

		interactionType.current = 'keyboard';

		switch ( e.key ) {
			case 'Enter':
			case ' ':
				e.preventDefault();
				if ( isOpen ) {
					if ( filteredOptions[ highlightedIndex ] ) {
						handleSelect( filteredOptions[ highlightedIndex ] );
					}
				} else {
					setIsOpen( ( prev ) => ! prev );
				}
				break;
			case 'ArrowDown':
				e.preventDefault();
				if ( ! isOpen ) {
					setIsOpen( true );
				} else {
					// Navigation when open but search disabled
					setHighlightedIndex( ( prev ) => {
						const next =
							prev < filteredOptions.length - 1 ? prev + 1 : 0;
						return next;
					} );
				}
				break;
			case 'ArrowUp':
				e.preventDefault();
				if ( ! isOpen ) {
					setIsOpen( true );
				} else {
					// Navigation when open but search disabled
					setHighlightedIndex( ( prev ) => {
						const next =
							prev > 0 ? prev - 1 : filteredOptions.length - 1;
						return next;
					} );
				}
				break;
			case 'Tab':
				setIsOpen( false );
				break;
			case 'Escape':
				if ( isOpen ) {
					e.preventDefault();
					setIsOpen( false );
				}
				break;
			default:
				break;
		}
	};

	// Keyboard handler for the Search Input
	const handleSearchKeyDown = ( e: KeyboardEvent< HTMLInputElement > ) => {
		interactionType.current = 'keyboard';
		switch ( e.key ) {
			case 'ArrowDown':
				e.preventDefault();
				setHighlightedIndex( ( prev ) => {
					const next =
						prev < filteredOptions.length - 1 ? prev + 1 : 0;
					return next;
				} );
				break;
			case 'ArrowUp':
				e.preventDefault();
				setHighlightedIndex( ( prev ) => {
					const next =
						prev > 0 ? prev - 1 : filteredOptions.length - 1;
					return next;
				} );
				break;
			case 'Enter':
				e.preventDefault();
				if ( filteredOptions[ highlightedIndex ] ) {
					handleSelect( filteredOptions[ highlightedIndex ] );
				}
				break;
			case 'Escape':
				e.preventDefault();
				setIsOpen( false );
				// Return focus to the trigger
				if ( containerRef.current ) {
					const trigger = containerRef.current.querySelector(
						'[role="combobox"]'
					) as HTMLElement;
					trigger?.focus();
				}
				break;
			default:
				break;
		}
	};

	// Handle showing tooltip with delay logic
	const handleOptionMouseEnter = (
		e: React.MouseEvent< HTMLLIElement >,
		index: number,
		isPro: boolean
	) => {
		interactionType.current = 'mouse';
		setHighlightedIndex( index );

		if ( hoverTimeoutRef.current ) {
			clearTimeout( hoverTimeoutRef.current );
			hoverTimeoutRef.current = null;
		}

		if ( isPro ) {
			const rect = e.currentTarget.getBoundingClientRect();
			setTooltipState( {
				visible: true,
				top: rect.top,
				left: rect.left + rect.width / 2,
				width: rect.width,
				index,
			} );
		} else {
			// If moving to a non-pro item, close tooltip immediately
			setTooltipState( null );
		}
	};

	const handleOptionMouseLeave = () => {
		// Delay hiding tooltip to allow moving mouse into the tooltip itself
		hoverTimeoutRef.current = window.setTimeout( () => {
			setTooltipState( null );
		}, 150 );
	};

	const handleTooltipMouseEnter = () => {
		if ( hoverTimeoutRef.current ) {
			clearTimeout( hoverTimeoutRef.current );
			hoverTimeoutRef.current = null;
		}
	};

	const handleTooltipMouseLeave = () => {
		hoverTimeoutRef.current = window.setTimeout( () => {
			setTooltipState( null );
		}, 150 );
	};
	return (
		<div
			className={ `notifybay-relative notifybay-w-full ${ className } ${
				classNames.wrapper || ''
			}` }
			ref={ containerRef }
		>
			{ label && (
				<label
					className={ `notifybay-block notifybay-text-sm notifybay-font-medium notifybay-text-gray-700 notifybay-mb-1 ${
						classNames.label || ''
					}` }
				>
					{ label }
				</label>
			) }

			{ /* Trigger Button */ }
			<div
				id={ id }
				ref={ con_ref }
				tabIndex={ disabled ? -1 : 0 }
				role="combobox"
				aria-expanded={ isOpen }
				aria-haspopup="listbox"
				aria-controls="custom-select-list"
				aria-disabled={ disabled }
				onClick={ () => ! disabled && setIsOpen( ( prev ) => ! prev ) }
				onKeyDown={ handleTriggerKeyDown }
				className={ `
          notifybay-ring-1 notifybay-ring-transparent
          notifybay-relative notifybay-flex notifybay-flex-wrap  notifybay-items-center notifybay-justify-between notifybay-w-full notifybay-gap-0 notifybay-px-4  notifybay-text-left !notifybay-cursor-pointer 
          notifybay-transition-all notifybay-duration-200 notifybay-ease-in-out notifybay-border notifybay-rounded-[8px] notifybay-bg-white ${ border } 
          ${ isCompact ? 'notifybay-py-[5px]' : 'notifybay-py-[9px]' }
          ${ ! disabled && ! isOpen ? ` ${ color } ` : '' }
          ${
				disabled
					? 'notifybay-bg-gray-100 notifybay-cursor-not-allowed notifybay-text-gray-400 notifybay-border-gray-200'
					: isError
					? ''
					: 'hover:!notifybay-border-[#3858e9]'
			}
          ${
				isOpen
					? isError
						? errorClassesManual
						: hoverClassesManual
					: ''
			}
          ${ isError ? `${ errorClassName } ${ classNames.error || '' }` : '' }
          ${ classNames.select || '' } ${ classNames.container || '' }
        ` }
			>
				<div className="notifybay-flex-1 notifybay-min-w-0">
					{ enableSearch && isOpen ? (
						<input
							ref={ searchInputRef }
							type="text"
							className={ `notifybay-w-full !notifybay-bg-transparent !notifybay-border-none !notifybay-shadow-none !notifybay-outline-none !notifybay-p-0 !notifybay-font-[${ fontWeight }] !notifybay-text-[${ fontSize }px] !notifybay-leading-[20px] !notifybay-min-h-[unset] ${
								classNames.search || ''
							}` }
							value={ searchQuery }
							onChange={ ( e ) => {
								setSearchQuery( e.target.value );
								setHighlightedIndex( 0 ); // Reset highlight on search
							} }
							onClick={ ( e ) => e.stopPropagation() } // Prevent closing when clicking input
							onKeyDown={ handleSearchKeyDown }
							placeholder="Search..."
						/>
					) : (
						<span
							className={ `notifybay-block notifybay-truncate ${ color } hover:!notifybay-text-[#3858e9] notifybay-text-[${ fontSize }px] notifybay-font-[${ fontWeight }]` }
						>
							{ value ? (
								<span
									className={ `notifybay-flex notifybay-items-center notifybay-gap-2 ` }
								>
									{ renderOption && selectedOption
										? renderOption( selectedOption )
										: selectedOption?.label }
								</span>
							) : (
								placeholder
							) }
						</span>
					) }
				</div>

				{ /* Chevron Icon */ }
				{ ! hideIcon ? (
					<span className="notifybay-flex-shrink-0 notifybay-ml-2 notifybay-flex notifybay-items-center">
						<ChevronDown
							className={ `notifybay-h-4 notifybay-w-4 notifybay-text-gray-700 notifybay-transition-transform notifybay-duration-200 ${
								isOpen
									? 'notifybay-transform notifybay-rotate-180'
									: ''
							}` }
						/>
					</span>
				) : null }
			</div>

			{ /* Dropdown Panel */ }
			{ isOpen && (
				<div
					className={ `notifybay-absolute notifybay-z-[50000] notifybay-bg-white notifybay-border notifybay-border-gray-200 notifybay-rounded-[12px] notifybay-shadow-[0_2px_2px_rgba(0,0,0,0.3)] -notifybay-mt-[1px] notifybay-p-1 ${
						differentDropdownWidth ? '' : 'notifybay-w-full'
					} ${ classNames.dropdown || '' }` }
				>
					{ /* Options List */ }
					<ul
						ref={ listRef }
						id="custom-select-list"
						role="listbox"
						tabIndex={ -1 }
						onScroll={ () => setTooltipState( null ) } // Hide tooltip on scroll to prevent detachment
						className={ `notifybay-max-h-60 notifybay-overflow-auto focus:notifybay-outline-none notifybay-scrollbar-hide notifybay-relative ${ color } notifybay-font-[${ fontWeight }] notifybay-text-[${ fontSize }px]` }
						style={ { scrollbarWidth: 'none' } }
					>
						{ filteredOptions.length === 0 ? (
							<li className="notifybay-relative notifybay-cursor-default notifybay-select-none notifybay-p-1  notifybay-italic notifybay-text-center notifybay-rounded-[8px]">
								{ searchQuery
									? 'No results found'
									: 'No options available' }
							</li>
						) : (
							filteredOptions.map( ( option, index ) => {
								const isSelected =
									selectedOption?.value === option.value;
								// Highlight if matched index OR if it's the item keeping the tooltip open
								const isHighlighted =
									highlightedIndex === index ||
									( tooltipState?.visible &&
										tooltipState.index === index );
								const isPro = option.variant === 'buy_pro';
								const isComingSoon =
									option.variant === 'coming_soon';
								const isDisabled = option.disabled || isPro;

								return (
									<li
										key={ `${ option.value }-${ index }` }
										id={ `option-${ index }` }
										role="option"
										aria-selected={ isSelected }
										onMouseEnter={ ( e ) =>
											handleOptionMouseEnter(
												e,
												index,
												!! isPro
											)
										}
										onMouseLeave={ handleOptionMouseLeave }
										onClick={ ( e ) => {
											e.stopPropagation();
											handleSelect( option );
										} }
										className={ `
                      notifybay-group notifybay-relative notifybay-cursor-pointer notifybay-select-none notifybay-px-3  notifybay-flex notifybay-flex-nowrap notifybay-justify-between notifybay-min-h-[36px]notifybay-font-medium notifybay-transition-colors notifybay-duration-150 !notifybay-mb-0 notifybay-border-b-[1px] notifybay-border-gray-100  notifybay-rounded-[8px] 
                      ${
							isDisabled
								? 'notifybay-opacity-100 !notifybay-cursor-not-allowed notifybay-text-gray-500 notifybay-bg-gray-200'
								: ''
						}
                      ${
							isComingSoon
								? 'notifybay-opacity-100 !notifybay-cursor-not-allowed !notifybay-text-pink-500 hover:!notifybay-text-pink-600 notifybay-bg-gray-200'
								: ''
						}
                      ${
							isHighlighted && ! isDisabled
								? 'notifybay-bg-blue-600 notifybay-text-white'
								: isDisabled
								? 'notifybay-text-gray-400'
								: ''
						}
                      ${
							! isHighlighted && ! isDisabled
								? option.className || ''
								: ''
						}
                      ${ classNames.option || '' }
                    ` }
									>
										<div className="notifybay-flex notifybay-items-center notifybay-justify-between notifybay-min-h-[36px] notifybay-w-full notifybay-gap-4">
											<span
												className={ `notifybay-block notifybay-truncate ${
													isSelected
														? 'notifybay-font-semibold'
														: 'notifybay-font-normal'
												}` }
											>
												{ renderOption
													? renderOption( option )
													: option.label }
											</span>

											{ /* Lock Icon for Buy Pro */ }
											{ isPro && (
												<LockKeyhole className="notifybay-w-3.5 notifybay-h-3.5 notifybay-text-[#f02a74]" />
											) }
											{ isComingSoon && (
												<span className="notifybay-bg-pink-600 notifybay-text-white notifybay-p-1 notifybay-px-2 notifybay-rounded-full notifybay-text-xs notifybay-flex notifybay-items-center notifybay-gap-1 notifybay-flex-nowrap">
													<Hourglass className="notifybay-w-3.5 notifybay-h-3.5 notifybay-text-white" />
													<span className="notifybay-whitespace-nowrap">
														Coming Soon
													</span>
												</span>
											) }
										</div>

										{ /* Checkmark for selected item */ }
										{ isSelected &&
											! isPro &&
											! isComingSoon && (
												<span
													className={ `notifybay-px-3 notifybay-pr-0 notifybay-flex-nowrap notifybay-flex notifybay-items-center notifybay-pr-4 ${
														isHighlighted &&
														! isDisabled
															? 'notifybay-text-white'
															: 'notifybay-text-blue-600'
													}` }
												>
													<svg
														className="notifybay-h-5 notifybay-w-5"
														xmlns="http://www.w3.org/2000/svg"
														viewBox="0 0 20 20"
														fill="currentColor"
														aria-hidden="true"
													>
														<path
															fillRule="evenodd"
															d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
															clipRule="evenodd"
														/>
													</svg>
												</span>
											) }
									</li>
								);
							} )
						) }
					</ul>
				</div>
			) }

			{ /* Tooltip Portal - Renders to body to avoid clipping and stacking issues */ }
			{ isOpen &&
				tooltipState?.visible &&
				createPortal(
					<div
						ref={ tooltipRef }
						className="notifybay-fixed notifybay-z-[50001] notifybay-flex notifybay-flex-col notifybay-items-center notifybay-gap-1.5 notifybay-bg-gray-900 notifybay-text-white notifybay-text-xs notifybay-p-2 notifybay-min-w-[140px] notifybay-rounded-md notifybay-shadow-lg"
						style={ {
							top: tooltipState.top + 5, // Adjusted to user preference
							left: tooltipState.left,
							transform: 'translate(-50%, -100%)',
						} }
						onMouseEnter={ handleTooltipMouseEnter }
						onMouseLeave={ handleTooltipMouseLeave }
					>
						<span className="notifybay-font-medium notifybay-whitespace-nowrap">
							Upgrade to unlock
						</span>
						<a
							href="#"
							target="_blank"
							onClick={ ( e ) => e.preventDefault() }
							className="notifybay-w-full notifybay-bg-[#f02a74] hover:!notifybay-bg-[#e71161] notifybay-text-white hover:!notifybay-text-white notifybay-font-bold notifybay-py-1.5 notifybay-px-3 notifybay-transition-colors focus:notifybay-outline-none focus:notifybay-ring-0 notifybay-cursor-pointer notifybay-text-center notifybay-rounded"
						>
							Buy Pro
						</a>
						{ /* Tooltip Arrow */ }
						<div className="notifybay-absolute notifybay-top-full notifybay-left-1/2 -notifybay-translate-x-1/2 notifybay-border-4 notifybay-border-transparent notifybay-border-t-gray-900"></div>
					</div>,
					document.body
				) }
		</div>
	);
};

export default Select;
