/* eslint-disable */
import { Outlet } from 'react-router-dom';
import { FC } from 'react';
import Navbar from './Navbar';

const AppLayout: FC = () => {
	return (
		<div className="notifybay-cb-container">
			<Navbar />
			<Outlet />
		</div>
	);
};

export default AppLayout;
