import React from 'react';

export const SkeletonAddonList: React.FC = () => {
	const skeletonRows = Array.from( { length: 5 }, ( _, i ) => i );

	return (
		<>
			{ skeletonRows.map( ( index ) => (
				<tr
					key={ index }
					className="notifybay-border-b notifybay-border-gray-200 notifybay-animate-pulse"
				>
					<td className="notifybay-p-2">
						<div className="notifybay-w-4 notifybay-h-4 notifybay-bg-gray-200 notifybay-rounded"></div>
					</td>
					<td className="notifybay-p-2">
						<div className="notifybay-h-4 notifybay-bg-gray-200 notifybay-rounded notifybay-w-3/4"></div>
						<div className="notifybay-h-3 notifybay-bg-gray-200 notifybay-rounded notifybay-w-1/2 notifybay-mt-2"></div>
					</td>
					<td className="notifybay-p-2">
						<div className="notifybay-h-4 notifybay-bg-gray-200 notifybay-rounded notifybay-w-6"></div>
					</td>
					<td className="notifybay-p-2">
						<div className="notifybay-h-4 notifybay-bg-gray-200 notifybay-rounded notifybay-w-1/3"></div>
					</td>
					<td className="notifybay-p-2">
						<div className="notifybay-h-5 notifybay-bg-gray-200 notifybay-rounded notifybay-w-12"></div>
					</td>
				</tr>
			) ) }
		</>
	);
};
