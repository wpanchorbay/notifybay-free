import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

export const useMenuSync = (): void => {
	const location = useLocation();

	useEffect( () => {
		const path = location.pathname;

		const selector = `ul.wp-submenu a[href*="notifybay"], ul.wp-submenu a[href*="tab=notifybay"]`;
		const baseLink =
			document.querySelector< HTMLAnchorElement >( selector );

		if ( baseLink ) {
			const submenu = baseLink.closest( 'ul.wp-submenu' );

			if ( submenu ) {
				submenu.querySelectorAll( 'li' ).forEach( ( li ) => {
					li.classList.remove( 'current' );
				} );
				const allLinks = submenu.querySelectorAll( 'a' );
				allLinks.forEach( ( a ) => {
					a.classList.remove( 'current' );
					a.blur();
				} );

				const targetLink = getNewLink( allLinks, path ) || baseLink;
				// console.log('targetLink',targetLink);
				// console.log('baseLink', baseLink);

				if ( targetLink ) {
					targetLink.classList.add( 'current' );

					const parentLi = targetLink.closest( 'li' );
					if ( parentLi ) {
						parentLi.classList.add( 'current' );
					}
				}
			}
		}
	}, [ location ] );
};

/**
 * Helper to find the anchor tag whose href best matches the current path.
 * @param allLinks
 * @param path
 */
const getNewLink = (
	allLinks: NodeListOf< HTMLAnchorElement >,
	path: string
): HTMLAnchorElement | undefined => {
	const linksArray = Array.from( allLinks );

	return linksArray.find( ( link ) => {
		if ( link.hash && link.hash.includes( path ) && path !== '/' ) {
			return true;
		}

		if ( path !== '/' && link.href.includes( path ) ) {
			return true;
		}

		return false;
	} );
};
