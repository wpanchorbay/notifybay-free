import React from 'react';

export const SkeletonLogs: React.FC = () => {
	return (
		<div className="notifybay-animate-pulse notifybay-bg-gray-900 notifybay-rounded-lg notifybay-overflow-hidden notifybay-shadow-sm notifybay-w-full">
			<div className="notifybay-p-4 notifybay-bg-gray-800 notifybay-border-b notifybay-border-gray-700 notifybay-flex notifybay-justify-between notifybay-items-center">
				<div className="notifybay-h-3 notifybay-bg-gray-600 notifybay-rounded notifybay-w-24"></div>
			</div>
			<div className="notifybay-p-4 notifybay-h-[400px]">
				{ Array.from( { length: 15 } ).map( ( _, i ) => (
					<div
						key={ i }
						className="notifybay-h-3 notifybay-bg-gray-700 notifybay-rounded notifybay-mb-3"
						style={ { width: `${ Math.random() * 40 + 40 }%` } }
					></div>
				) ) }
			</div>
		</div>
	);
};
