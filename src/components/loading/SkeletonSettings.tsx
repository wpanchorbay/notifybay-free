import React from 'react';

export const SkeletonSettings: React.FC = () => {
	const SkeleBox = () => (
		<div className="notifybay-mb-8 notifybay-bg-white notifybay-border notifybay-border-gray-200 notifybay-rounded-lg notifybay-overflow-hidden">
			<div className="notifybay-px-6 notifybay-py-5 notifybay-border-b notifybay-border-gray-200">
				<div className="notifybay-h-6 notifybay-bg-gray-200 notifybay-rounded notifybay-w-48 notifybay-mb-2"></div>
				<div className="notifybay-h-4 notifybay-bg-gray-200 notifybay-rounded notifybay-w-96"></div>
			</div>
			<div className="notifybay-px-6 notifybay-py-6 notifybay-flex notifybay-flex-col notifybay-gap-6">
				{ Array.from( { length: 2 } ).map( ( _, i ) => (
					<div key={ i } className="notifybay-flex notifybay-gap-4">
						<div className="notifybay-w-1/3">
							<div className="notifybay-h-5 notifybay-bg-gray-200 notifybay-rounded notifybay-w-32 notifybay-mb-2"></div>
							<div className="notifybay-h-3 notifybay-bg-gray-200 notifybay-rounded notifybay-w-24"></div>
						</div>
						<div className="notifybay-w-2/3">
							<div className="notifybay-h-10 notifybay-bg-gray-200 notifybay-rounded notifybay-w-full"></div>
						</div>
					</div>
				) ) }
			</div>
		</div>
	);

	return (
		<div className="notifybay-animate-pulse notifybay-w-full">
			<SkeleBox />
			<SkeleBox />
			<div className="notifybay-h-10 notifybay-bg-gray-200 notifybay-rounded notifybay-w-32 notifybay-mt-8"></div>
		</div>
	);
};
