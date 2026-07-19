/* eslint-disable */
const HeaderContainer = ( {
	children,
	className = '',
}: {
	children: React.ReactNode;
	className?: string;
} ) => {
	return (
		<div
			className={ `notifybay-flex notifybay-justify-between ${ className }` }
		>
			{ children }
		</div>
	);
};

export default HeaderContainer;
