/* eslint-disable */
import React, {
	useState,
	useRef,
	useEffect,
	KeyboardEvent,
	useMemo,
} from 'react';
import { createPortal } from 'react-dom';
import { ChevronDown, Lock, Hourglass } from 'lucide-react';
import { SelectOption } from '../common/Select';

// Hook for click outside
function useClickOutside(
	refs: React.RefObject<HTMLElement>[],
	handler: (event: MouseEvent | TouchEvent) => void
) {
	useEffect(() => {
		const listener = (event: MouseEvent | TouchEvent) => {
			// If any ref contains the target, don't trigger handler
			const isInside = refs.some(
				(ref) =>
					ref.current && ref.current.contains(event.target as Node)
			);
			if (isInside) {
				return;
			}
			handler(event);
		};
		document.addEventListener('mousedown', listener);
		document.addEventListener('touchstart', listener);
		return () => {
			document.removeEventListener('mousedown', listener);
			document.removeEventListener('touchstart', listener);
		};
	}, [refs, handler]);
}

export interface ClassicSelectClassNames {
	container?: string;
	label?: string;
	innerContainer?: string;
	trigger?: string;
	triggerOpen?: string;
	triggerDisabled?: string;
	value?: string;
	dropdown?: string;
	searchContainer?: string;
	searchInput?: string;
	list?: string;
	option?: string;
	optionHighlighted?: string;
	optionSelected?: string;
	description?: string;
}

interface ClassicSelectProps {
	id?: string;
	value: SelectOption['value'] | null;
	onChange: (value: string | number) => void;
	options: SelectOption[];
	placeholder?: string;
	disabled?: boolean;
	className?: string;
	classNames?: ClassicSelectClassNames;
	label?: string;
	description?: string;
	enableSearch?: boolean;
	size?: 'short' | 'regular';
	renderOption?: (option: SelectOption) => React.ReactNode;
	differentDropdownWidth?: boolean;
}

export const ClassicSelect: React.FC<ClassicSelectProps> = ({
	id,
	value,
	onChange,
	options,
	placeholder = 'Select an option...',
	disabled = false,
	className = '',
	classNames,
	label,
	description,
	enableSearch = false,
	size = 'short',
	renderOption,
	differentDropdownWidth = false,
}) => {
	const [isOpen, setIsOpen] = useState(false);
	const [highlightedIndex, setHighlightedIndex] = useState<number>(-1);
	const [searchQuery, setSearchQuery] = useState('');

	const containerRef = useRef<HTMLDivElement>(null);
	const dropdownRef = useRef<HTMLDivElement>(null);
	const listRef = useRef<HTMLUListElement>(null);
	const searchInputRef = useRef<HTMLInputElement>(null);
	const interactionType = useRef<'mouse' | 'keyboard'>('keyboard');

	// Portal coordinates
	const [coords, setCoords] = useState({ top: 0, left: 0, width: 0 });

	// Tooltip state for buy_pro
	const [tooltipState, setTooltipState] = useState<{
		visible: boolean;
		top: number;
		left: number;
		width: number | 'max-content';
		text: string;
	} | null>(null);
	const hoverTimeoutRef = useRef<number | null>(null);

	useClickOutside([containerRef, dropdownRef], () => {
		setIsOpen(false);
		setTooltipState(null);
	});

	const selectedOption = useMemo(
		() => options.find((opt) => opt.value === value),
		[options, value]
	);

	const filteredOptions = useMemo(() => {
		if (!enableSearch || !searchQuery) {
			return options;
		}
		return options.filter((opt) =>
			opt.label.toLowerCase().includes(searchQuery.toLowerCase())
		);
	}, [options, searchQuery, enableSearch]);

	const updateCoords = () => {
		if (!containerRef.current) {
			return;
		}
		const rect = containerRef.current.getBoundingClientRect();
		setCoords({
			top: rect.bottom,
			left: rect.left,
			width: rect.width,
		});
	};

	useEffect(() => {
		if (isOpen) {
			updateCoords();
			window.addEventListener('scroll', updateCoords, true);
			window.addEventListener('resize', updateCoords);
		}
		return () => {
			window.removeEventListener('scroll', updateCoords, true);
			window.removeEventListener('resize', updateCoords);
		};
	}, [isOpen]);

	useEffect(() => {
		if (isOpen) {
			if (enableSearch && searchInputRef.current) {
				requestAnimationFrame(() => searchInputRef.current?.focus());
			}
			const selectedIndex = value
				? filteredOptions.findIndex((opt) => opt.value === value)
				: 0;
			setHighlightedIndex(selectedIndex >= 0 ? selectedIndex : 0);
			interactionType.current = 'keyboard';
		} else {
			setSearchQuery('');
			setTooltipState(null);
		}
	}, [isOpen, value, enableSearch, filteredOptions.length]);

	useEffect(() => {
		if (
			isOpen &&
			listRef.current &&
			highlightedIndex >= 0 &&
			interactionType.current === 'keyboard'
		) {
			const list = listRef.current;
			const element = list.children[highlightedIndex] as HTMLElement;
			if (element) {
				const listTop = list.scrollTop;
				const listBottom = listTop + list.clientHeight;
				const elementTop = element.offsetTop;
				const elementBottom = elementTop + element.offsetHeight;
				if (elementTop < listTop) {
					list.scrollTop = elementTop;
				} else if (elementBottom > listBottom) {
					list.scrollTop = elementBottom - list.clientHeight;
				}
			}
		}
	}, [highlightedIndex, isOpen]);

	const handleSelect = (option: SelectOption) => {
		if (
			option.disabled ||
			option.variant === 'buy_pro' ||
			option.variant === 'coming_soon'
		) {
			return;
		}
		onChange(option.value);
		setIsOpen(false);
		setSearchQuery('');
		setTooltipState(null);
	};

	const handleTriggerKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
		if (disabled) {
			return;
		}
		if (isOpen && enableSearch) {
			return;
		}

		interactionType.current = 'keyboard';
		switch (e.key) {
			case 'Enter':
			case ' ':
				e.preventDefault();
				if (isOpen) {
					if (filteredOptions[highlightedIndex]) {
						handleSelect(filteredOptions[highlightedIndex]);
					}
				} else {
					setIsOpen(!isOpen);
				}
				break;
			case 'ArrowDown':
				e.preventDefault();
				if (!isOpen) {
					setIsOpen(true);
				} else {
					setHighlightedIndex((prev) =>
						prev < filteredOptions.length - 1 ? prev + 1 : 0
					);
				}
				break;
			case 'ArrowUp':
				e.preventDefault();
				if (!isOpen) {
					setIsOpen(true);
				} else {
					setHighlightedIndex((prev) =>
						prev > 0 ? prev - 1 : filteredOptions.length - 1
					);
				}
				break;
			case 'Escape':
				if (isOpen) {
					e.preventDefault();
					setIsOpen(false);
				}
				break;
		}
	};

	const handleSearchKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
		interactionType.current = 'keyboard';
		switch (e.key) {
			case 'ArrowDown':
				e.preventDefault();
				setHighlightedIndex((prev) =>
					prev < filteredOptions.length - 1 ? prev + 1 : 0
				);
				break;
			case 'ArrowUp':
				e.preventDefault();
				setHighlightedIndex((prev) =>
					prev > 0 ? prev - 1 : filteredOptions.length - 1
				);
				break;
			case 'Enter':
				e.preventDefault();
				if (filteredOptions[highlightedIndex]) {
					handleSelect(filteredOptions[highlightedIndex]);
				}
				break;
			case 'Escape':
				e.preventDefault();
				setIsOpen(false);
				break;
		}
	};

	const handleOptionHover = (
		e: React.MouseEvent<HTMLLIElement>,
		index: number,
		option: SelectOption
	) => {
		interactionType.current = 'mouse';
		setHighlightedIndex(index);
		if (hoverTimeoutRef.current) {
			clearTimeout(hoverTimeoutRef.current);
		}

		if (
			option.variant === 'buy_pro' ||
			option.variant === 'coming_soon'
		) {
			const rect = e.currentTarget.getBoundingClientRect();
			setTooltipState({
				visible: true,
				top: rect.top,
				left: rect.left + rect.width / 2,
				width: rect.width,
				text:
					option.variant === 'buy_pro'
						? 'Available in Pro Version'
						: 'Coming Soon',
			});
		} else {
			setTooltipState(null);
		}
	};

	const selectId =
		id || `classic-select-${Math.random().toString(36).slice(2, 9)}`;
	const sizeClass = size === 'short' ? 'min-content' : '';
	const explicitWidth =
		size === 'short' ? 'min-content' : size === 'regular' ? 'auto' : '100%';

	return (
		<div
			className={`${sizeClass} ${className} ${classNames?.container || ''
				} notifybay-align-middle`.trim()}
			ref={containerRef}
		>
			{label && (
				<label
					htmlFor={selectId}
					className={`notifybay-block notifybay-mb-1 ${classNames?.label || ''
						}`.trim()}
				>
					{label}
				</label>
			)}

			<div
				className={`notifybay-relative ${classNames?.innerContainer}`}
				style={{ width: explicitWidth }}
			>
				{ /* Trigger that looks like WP native select */}
				<div
					id={selectId}
					tabIndex={disabled ? -1 : 0}
					role="combobox"
					aria-expanded={isOpen}
					onClick={() => !disabled && setIsOpen(!isOpen)}
					onKeyDown={handleTriggerKeyDown}
					className={`
            notifybay-flex notifybay-items-center notifybay-justify-between 
            notifybay-appearance-none notifybay-border notifybay-border-[#8c8f94] 
            notifybay-rounded-[3px] notifybay-px-2 notifybay-pr-6 notifybay-min-h-[30px] 
            notifybay-leading-loose notifybay-transition-all notifybay-duration-100 
            notifybay-select-none notifybay-relative notifybay-box-border notifybay-w-full 
            ${disabled
							? `notifybay-cursor-not-allowed notifybay-bg-[#f0f0f1] notifybay-text-[#a7aaad] ${classNames?.triggerDisabled || ''
							}`
							: `notifybay-cursor-pointer notifybay-bg-white notifybay-text-[#2c3338]`
						} 
            ${isOpen
							? `!notifybay-border-[#2271b1] notifybay-shadow-[0_0_0_1px_#2271b1] notifybay-outline-none ${classNames?.triggerOpen || ''
							}`
							: 'notifybay-shadow-none'
						} 
            ${classNames?.trigger || ''}
          `.trim()}
				>
					<span
						className={`notifybay-overflow-hidden notifybay-text-ellipsis notifybay-whitespace-nowrap notifybay-flex-1 ${classNames?.value || ''
							}`.trim()}
					>
						{selectedOption
							? renderOption
								? renderOption(selectedOption)
								: selectedOption.label
							: placeholder}
					</span>

					{ /* Native-looking arrow */}
					<span className="notifybay-absolute notifybay-right-1.5 notifybay-flex notifybay-items-center notifybay-pointer-events-none">
						<ChevronDown size={14} color="#50575e" />
					</span>
				</div>

				{ /* Dropdown Menu */}
				{isOpen &&
					createPortal(
						<div
							ref={dropdownRef}
							className={`notifybay-fixed notifybay-z-[999999] notifybay-bg-white notifybay-border-2 notifybay-border-[#2271b1] ${differentDropdownWidth
								? 'notifybay-rounded-[3px]'
								: 'notifybay-rounded-b-[3px]'
								} 
              notifybay-rounded-b-[3px] notifybay-shadow-[0_3px_5px_rgba(0,0,0,0.2)] notifybay-p-0 notifybay-box-border ${classNames?.dropdown || ''
								}`.trim()}
							style={{
								top: coords.top,
								left: coords.left - 1, // Offset for border alignment
								width: coords.width + 2, // Compensate for border
								...(differentDropdownWidth
									? { width: 'max-content' }
									: {}),
							}}
						>
							{enableSearch && (
								<div
									className={`notifybay-p-1.5 ${classNames?.searchContainer || ''
										}`.trim()}
								>
									<input
										ref={searchInputRef}
										type="text"
										value={searchQuery}
										onChange={(e) => {
											setSearchQuery(e.target.value);
											setHighlightedIndex(0);
										}}
										onKeyDown={handleSearchKeyDown}
										onClick={(e) => e.stopPropagation()}
										placeholder="Search..."
										className={`notifybay-w-full notifybay-px-2 notifybay-leading-loose notifybay-min-h-[26px] notifybay-border notifybay-border-[#aaaaaa] notifybay-bg-[#fcfcfc] notifybay-rounded-[3px] notifybay-box-border notifybay-text-[13px] focus:notifybay-outline-none focus:notifybay-shadow-none ${classNames?.searchInput || ''
											}`.trim()}
									/>
								</div>
							)}

							<ul
								ref={listRef}
								role="listbox"
								className={`notifybay-max-h-[220px] notifybay-overflow-y-auto notifybay-m-0 notifybay-p-0 notifybay-list-none ${classNames?.list || ''
									}`.trim()}
								style={{
									scrollbarWidth: 'thin',
								}}
							>
								{filteredOptions.length === 0 ? (
									<li className="notifybay-px-3 notifybay-py-1.5 notifybay-text-[#646970] notifybay-italic notifybay-text-[13px] notifybay-m-0">
										{searchQuery
											? 'No results found'
											: 'No options available'}
									</li>
								) : (
									filteredOptions.map((opt, index) => {
										const isSelected =
											selectedOption?.value === opt.value;
										const isHighlighted =
											highlightedIndex === index;
										const isPro = opt.variant === 'buy_pro';
										const isComingSoon =
											opt.variant === 'coming_soon';
										const isDisabled =
											opt.disabled ||
											isPro ||
											isComingSoon;

										return (
											<li
												key={opt.value}
												role="option"
												aria-selected={isSelected}
												onMouseEnter={(e) =>
													handleOptionHover(
														e,
														index,
														opt
													)
												}
												onMouseLeave={() => {
													hoverTimeoutRef.current =
														window.setTimeout(
															() =>
																setTooltipState(
																	null
																),
															150
														);
												}}
												onClick={(e) => {
													e.stopPropagation();
													handleSelect(opt);
												}}
												className={`
                        notifybay-px-3 notifybay-py-1.5 notifybay-flex notifybay-items-center 
                        notifybay-justify-between notifybay-text-[13px] notifybay-m-0 
                        ${isDisabled
														? 'notifybay-cursor-not-allowed'
														: 'notifybay-cursor-pointer'
													} 
                        ${isHighlighted
														? `notifybay-bg-[#2271b1] notifybay-text-white ${classNames?.optionHighlighted || ''
														}`
														: isDisabled
															? 'notifybay-bg-transparent notifybay-text-[#a7aaad]'
															: `notifybay-bg-transparent notifybay-text-[#2c3338]`
													} 
                        ${isSelected ? classNames?.optionSelected || '' : ''}
                        ${classNames?.option || ''}
                      `.trim()}
											>
												<span className="notifybay-flex notifybay-items-center notifybay-gap-2 notifybay-overflow-hidden notifybay-text-ellipsis notifybay-whitespace-nowrap">
													{renderOption
														? renderOption(opt)
														: opt.label}
												</span>

												{ /* Icons for variants */}
												{isPro && (
													<span
														className={`notifybay-flex ${isHighlighted
															? 'notifybay-text-white'
															: 'notifybay-text-[#ffb900]'
															}`}
													>
														<Lock size={14} />
													</span>
												)}
												{isComingSoon && (
													<span
														className={`notifybay-text-[10px] notifybay-uppercase notifybay-px-1.5 notifybay-py-0.5 notifybay-rounded-[10px] notifybay-font-semibold notifybay-flex notifybay-items-center notifybay-gap-1 ${isHighlighted
															? 'notifybay-bg-white/20 notifybay-text-white'
															: 'notifybay-bg-[#f0f0f1] notifybay-text-[#646970]'
															}`}
													>
														<Hourglass
															size={10}
														/>
														Soon
													</span>
												)}
											</li>
										);
									})
								)}
							</ul>
						</div>,
						document.body
					)}
			</div>

			{description && (
				<p
					className={`description notifybay-mt-1 ${classNames?.description || ''
						}`.trim()}
				>
					{description}
				</p>
			)}

			{ /* Portal Tooltip or absolute Tooltip for variants */}
			{tooltipState?.visible && (
				<div
					className="notifybay-fixed notifybay-bg-[#1d2327] notifybay-text-white notifybay-px-2.5 notifybay-py-1 notifybay-rounded-[3px] notifybay-text-[12px] notifybay-pointer-events-none notifybay-z-[100000] notifybay-whitespace-nowrap"
					style={{
						top: tooltipState.top - 8,
						left: tooltipState.left,
						transform: 'translate(-50%, -100%)',
					}}
				>
					{tooltipState.text}
					{ /* Tooltip caret */}
					<div className="notifybay-absolute -notifybay-bottom-1 notifybay-left-1/2 -notifybay-translate-x-1/2 notifybay-border-x-4 notifybay-border-t-4 notifybay-border-x-transparent notifybay-border-b-transparent notifybay-border-t-[#1d2327]" />
				</div>
			)}
		</div>
	);
};
