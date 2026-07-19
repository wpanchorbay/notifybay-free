/* eslint-disable */
import { FC, ReactNode } from 'react';

interface SkeletonProps {
	height?: string;
	width?: string;
	borderRadius?: string;
	children?: ReactNode;
	className?: string;
}

const Skeleton: FC< SkeletonProps > = ( {
	height,
	width,
	borderRadius,
	children,
	className,
} ) => {
	// Helpers to handle arbitrary values safely
	const hClass = height
		? height.includes( '[' )
			? `notifybay-h-${ height }`
			: `notifybay-h-[${ height }]`
		: '';
	const wClass = width
		? width.includes( '[' )
			? `notifybay-w-${ width }`
			: `notifybay-w-[${ width }]`
		: '';
	const rClass = borderRadius
		? borderRadius.includes( '[' )
			? `notifybay-rounded-${ borderRadius }`
			: `notifybay-rounded-[${ borderRadius }]`
		: 'notifybay-rounded-[6px]';

	return (
		<div
			className={ `
        notifybay-block notifybay-bg-[#e9e9e9] notifybay-relative notifybay-overflow-hidden
        notifybay-animate-shimmer
        ${ hClass } ${ wClass } ${ rClass }
        ${ className || '' }
        [&>*]:notifybay-opacity-0
      ` }
		>
			{ children }
		</div>
	);
};

export default Skeleton;
