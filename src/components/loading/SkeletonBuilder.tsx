import React from 'react';

export const SkeletonBuilder: React.FC = () => {
	return (
		<div className="notifybay-animate-pulse notifybay-flex notifybay-flex-col lg:notifybay-flex-row notifybay-gap-6 notifybay-items-start notifybay-w-full">
			{ /* Left side */ }
			<div className="notifybay-w-full notifybay-flex notifybay-flex-col notifybay-gap-6">
				{ /* Title Input */ }
				<div className="notifybay-h-[50px] notifybay-bg-gray-200 notifybay-rounded-md notifybay-w-full"></div>

				{ /* Assignment rules container */ }
				<div className="notifybay-h-[100px] notifybay-bg-gray-200 notifybay-rounded-lg notifybay-w-full"></div>

				{ /* Fields header */ }
				<div>
					<div className="notifybay-h-6 notifybay-bg-gray-200 notifybay-rounded notifybay-w-32 notifybay-mb-2"></div>
					<div className="notifybay-h-4 notifybay-bg-gray-200 notifybay-rounded notifybay-w-64"></div>
				</div>

				{ /* Fields list */ }
				<div className="notifybay-border notifybay-border-gray-200 notifybay-rounded-lg notifybay-p-4">
					{ Array.from( { length: 3 } ).map( ( _, i ) => (
						<div
							key={ i }
							className="notifybay-h-12 notifybay-bg-gray-100 notifybay-rounded-md notifybay-w-full notifybay-mb-2 notifybay-flex notifybay-items-center notifybay-px-4"
						>
							<div className="notifybay-h-4 notifybay-w-4 notifybay-bg-gray-200 notifybay-rounded notifybay-mr-4"></div>
							<div className="notifybay-h-4 notifybay-w-32 notifybay-bg-gray-200 notifybay-rounded notifybay-mr-auto"></div>
							<div className="notifybay-h-4 notifybay-w-24 notifybay-bg-gray-200 notifybay-rounded"></div>
						</div>
					) ) }
				</div>
			</div>

			{ /* Right Sidebar */ }
			<div className="lg:notifybay-w-[320px] notifybay-w-full notifybay-flex-shrink-0">
				<div className="notifybay-h-[500px] notifybay-bg-gray-200 notifybay-rounded-lg notifybay-w-full"></div>
			</div>
		</div>
	);
};
