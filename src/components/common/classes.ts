export const borderClasses =
	'notifybay-border-[#949494] notifybay-border-[1px] disabled:notifybay-border-[#f8f8f8]';
export const hoverBorderClasses = 'notifybay-border-[#3858e9] ';

export const hoverClasses =
	'focus:!notifybay-ring-[#3858e9] focus:!notifybay-ring-1 hover:!notifybay-border-[#3858e9] focus:!notifybay-border-[#3858e9]';
export const hoverWithInClasses =
	'notifybay-ring-1 notifybay-ring-transparent focus-within:notifybay-border-[#3858e9] focus-within:notifybay-ring-1 focus-within:notifybay-ring-[#3858e9] hover:notifybay-border-[#3858e9]';
export const hoverClassesManual =
	'!notifybay-border-[#3858e9] !notifybay-ring-1 !notifybay-ring-[#3858e9]';

export const errorClasses =
	'notifybay-border-red-500 focus:!notifybay-ring-1 focus:!notifybay-ring-red-500 focus:!notifybay-border-red-500';
export const errorWithInClasses =
	'notifybay-ring-1 notifybay-ring-transparent focus-within:notifybay-border-red-500 focus-within:notifybay-ring-1 focus-within:notifybay-ring-red-500 focus-within:notifybay-border-red-500';
export const errorClassesManual =
	'!notifybay-border-red-500 !notifybay-ring-1 !notifybay-ring-red-500';

export const transitionClasses =
	'notifybay-transition-all notifybay-duration-300 notifybay-ease-in-out';

export const inputClasses =
	borderClasses +
	'hover:' +
	hoverBorderClasses +
	'focus:' +
	hoverBorderClasses +
	'notifybay-outline-none notifybay-transition-all notifybay-duration-300 notifybay-ease-in-out';
