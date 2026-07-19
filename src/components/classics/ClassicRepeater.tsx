/* eslint-disable */
import React, { useState } from 'react';

interface ClassicRepeaterItem {
	id: string;
	title: string;
}

interface ClassicRepeaterProps< T extends ClassicRepeaterItem > {
	items: T[];
	renderItem: ( item: T, index: number ) => React.ReactNode;
	onAdd?: () => void;
	onRemove?: ( id: string ) => void;
	addLabel?: string;
	className?: string;
}

/**
 * WooCommerce-style accordion/repeater UI.
 * Uses `wc-metaboxes-wrapper`, `wc-metabox`, `wc-metabox-content`, etc.
 * @param root0
 * @param root0.items
 * @param root0.renderItem
 * @param root0.onAdd
 * @param root0.onRemove
 * @param root0.addLabel
 * @param root0.className
 */
export function ClassicRepeater< T extends ClassicRepeaterItem >( {
	items,
	renderItem,
	onAdd,
	onRemove,
	addLabel = 'Add New',
	className = '',
}: ClassicRepeaterProps< T > ) {
	const [ openIds, setOpenIds ] = useState< Set< string > >( new Set() );

	const toggle = ( id: string ) => {
		setOpenIds( ( prev ) => {
			const next = new Set( prev );
			if ( next.has( id ) ) {
				next.delete( id );
			} else {
				next.add( id );
			}
			return next;
		} );
	};

	return (
		<div className={ `wc-metaboxes-wrapper ${ className }` }>
			<div className="wc-metaboxes">
				{ items.map( ( item, index ) => {
					const isOpen = openIds.has( item.id );
					return (
						<div
							key={ item.id }
							className={ `wc-metabox ${
								isOpen ? '' : 'closed'
							}` }
						>
							<h3
								className="notifybay-ignore-preflight notifybay-cursor-pointer"
								onClick={ () => toggle( item.id ) }
							>
								<div
									className="handlediv"
									title="Click to toggle"
								/>
								<strong>{ item.title }</strong>
								{ onRemove && (
									<a
										className="remove_row delete"
										onClick={ ( e ) => {
											e.stopPropagation();
											onRemove( item.id );
										} }
									>
										Remove
									</a>
								) }
							</h3>
							<div
								className="wc-metabox-content"
								style={ { display: isOpen ? 'block' : 'none' } }
							>
								{ renderItem( item, index ) }
							</div>
						</div>
					);
				} ) }
			</div>
			{ onAdd && (
				<p className="toolbar">
					<button
						type="button"
						className="button button-primary"
						onClick={ onAdd }
					>
						{ addLabel }
					</button>
				</p>
			) }
		</div>
	);
}
