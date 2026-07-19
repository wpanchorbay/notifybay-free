/* eslint-disable */
const Header = ( {
	children,
	className = '',
}: {
	children: React.ReactNode;
	className?: string;
} ) => {
	return (
		<div
			className={ `notifybay-text-[20px] notifybay-font-[700] notifybay-leading-[30px] notifybay-text-[#000000]  ${ className }` }
		>
			{ children }
		</div>
	);
};
export default Header;
