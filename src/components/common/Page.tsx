/* eslint-disable */
const Page = ( {
	children,
	className,
}: {
	children: React.ReactNode;
	className?: string;
} ) => {
	return (
		<div className={ `notifybay-p-x-page-default ${ className }` }>
			{ children }
		</div>
	);
};

export default Page;
