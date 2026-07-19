/* eslint-disable */
import React, { Dispatch, SetStateAction } from 'react';

interface StepperProps {
	steps: string[];
	currentStep: number;
	setStep: ( step: number ) => void | Dispatch< SetStateAction< number > >;
	classNames?: {
		root?: string;
		container?: string;
		backgroundLine?: string;
		progressLine?: string;
		stepContainer?: string;
		stepCircle?: string;
		stepLabel?: string;
	};
}

export const Stepper: React.FC< StepperProps > = ( {
	steps,
	currentStep,
	setStep,
	classNames,
} ) => {
	// Calculate width percentage for the green progress line
	// Total segments = steps.length - 1
	// If currentStep is 1, progress is 0%
	// If currentStep is 2, progress covers the first segment
	const progressPercentage = Math.max(
		0,
		Math.min( 100, ( ( currentStep - 1 ) / ( steps.length - 1 ) ) * 100 )
	);

	return (
		<div
			className={ `notifybay-w-full notifybay-py-6 ${
				classNames?.root || ''
			}` }
		>
			<div
				className={ `notifybay-flex notifybay-justify-between notifybay-items-start notifybay-relative ${
					classNames?.container || ''
				}` }
			>
				{ /* Background Grey Line */ }
				{ /* Positioned with left-16 and right-16 (4rem) to start/end at the center of the first/last circles (w-32 items) */ }
				<div
					className={ `notifybay-absolute notifybay-top-5 notifybay-left-16 notifybay-right-16 notifybay-h-[2px] notifybay-bg-gray-200 notifybay-z-0 ${
						classNames?.backgroundLine || ''
					}` }
				>
					{ /* Foreground Green Line */ }
					<div
						className={ `notifybay-h-full notifybay-bg-green-500 notifybay-transition-all notifybay-duration-500 notifybay-ease-out ${
							classNames?.progressLine || ''
						}` }
						style={ { width: `${ progressPercentage }%` } }
					/>
				</div>

				{ steps.map( ( step, index ) => {
					const stepNum = index + 1;
					const isCompleted = stepNum < currentStep;
					const isActive = stepNum === currentStep;

					return (
						<div
							key={ step }
							className={ `notifybay-flex notifybay-flex-col notifybay-items-center notifybay-relative notifybay-z-10 notifybay-w-32  ${
								classNames?.stepContainer || ''
							}` }
						>
							<div
								onClick={
									isCompleted
										? () => setStep( stepNum )
										: undefined
								}
								className={ `
                  notifybay-w-10 notifybay-h-10 notifybay-rounded-full notifybay-flex notifybay-items-center notifybay-justify-center
                  notifybay-transition-colors notifybay-duration-300 notifybay-border-2
                  ${
						isCompleted
							? 'notifybay-cursor-pointer'
							: 'notifybay-cursor-not-allowed'
					}
                  ${
						isCompleted || isActive
							? 'notifybay-bg-green-500 notifybay-border-green-500 notifybay-text-white'
							: 'notifybay-bg-gray-300 notifybay-border-gray-300 notifybay-text-white'
					}
                  ${ classNames?.stepCircle || '' }
                ` }
							>
								{ isCompleted ? (
									<svg
										className="notifybay-w-6 notifybay-h-6"
										fill="none"
										viewBox="0 0 24 24"
										stroke="currentColor"
									>
										<path
											strokeLinecap="round"
											strokeLinejoin="round"
											strokeWidth={ 2 }
											d="M5 13l4 4L19 7"
										/>
									</svg>
								) : (
									<span className="notifybay-text-sm notifybay-font-bold">
										{ stepNum
											.toString()
											.padStart( 2, '0' ) }
									</span>
								) }
							</div>
							<div
								className={ `notifybay-mt-3 notifybay-text-xs notifybay-font-bold notifybay-text-center notifybay-transition-colors ${
									isActive || isCompleted
										? 'notifybay-text-gray-900'
										: 'notifybay-text-gray-500'
								} ${ classNames?.stepLabel || '' }` }
							>
								{ step }
							</div>
						</div>
					);
				} ) }
			</div>
		</div>
	);
};
