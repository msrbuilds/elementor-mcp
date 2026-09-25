import { __ } from '@wordpress/i18n';
import { IconButton } from './Button';
import { Skeleton } from './Layout';
import { cx } from '../utils/cx';
import './Table.css';

/**
 * A data table. Columns without visible header text should pass a
 * visually hidden header (for example "Actions") so screen readers can name them.
 *
 * @param {Object}   props
 * @param {Array}    props.columns        [ { key, header, render, align, width, mono } ].
 * @param {Array}    props.rows           Row objects.
 * @param {string}   [props.rowKey]       Key holding each row's unique id.
 * @param {*}        [props.empty]        Content shown when there are no rows.
 * @param {boolean}  [props.loading]      Show skeleton rows.
 * @param {string}   [props.caption]      Visually hidden table caption.
 * @param {Function} [props.rowClassName] ( row ) => class name.
 */
export function Table( {
	columns,
	rows,
	rowKey = 'id',
	empty,
	loading = false,
	caption,
	rowClassName,
} ) {
	let body;
	if ( loading ) {
		body = [ 0, 1, 2 ].map( ( i ) => (
			<tr key={ `skeleton-${ i }` } aria-hidden="true">
				<td colSpan={ columns.length }>
					<Skeleton lines={ 1 } />
				</td>
			</tr>
		) );
	} else if ( ! rows.length ) {
		body = (
			<tr>
				<td colSpan={ columns.length } className="eui-table__empty">
					{ empty }
				</td>
			</tr>
		);
	} else {
		body = rows.map( ( row ) => (
			<tr
				key={ row[ rowKey ] }
				className={ rowClassName ? rowClassName( row ) : undefined }
			>
				{ columns.map( ( c ) => (
					<td
						key={ c.key }
						className={ cx(
							'end' === c.align && 'is-end',
							c.mono && 'is-mono'
						) }
					>
						{ c.render ? c.render( row ) : row[ c.key ] }
					</td>
				) ) }
			</tr>
		) );
	}
	return (
		<div className="eui-table-wrap">
			<table className="eui-table">
				{ caption && (
					<caption className="eui-visually-hidden">
						{ caption }
					</caption>
				) }
				<thead>
					<tr>
						{ columns.map( ( c ) => (
							<th
								key={ c.key }
								scope="col"
								style={
									c.width ? { width: c.width } : undefined
								}
								className={ cx(
									'end' === c.align && 'is-end'
								) }
							>
								{ c.header }
							</th>
						) ) }
					</tr>
				</thead>
				<tbody>{ body }</tbody>
			</table>
		</div>
	);
}

/**
 * Page numbers to show, with 'gap' markers, for `page` of `total`.
 *
 * @param {number} page  Current page (1-based).
 * @param {number} total Total pages.
 * @return {Array<number|string>} Pages and 'gap' markers.
 */
export function pageList( page, total ) {
	if ( total <= 7 ) {
		return Array.from( { length: total }, ( _, i ) => i + 1 );
	}
	const wanted = [ ...new Set( [ 1, 2, total, page - 1, page, page + 1 ] ) ]
		.filter( ( n ) => n >= 1 && n <= total )
		.filter( ( n ) => n !== 2 || page <= 3 )
		.sort( ( a, b ) => a - b );
	const out = [];
	wanted.forEach( ( n, i ) => {
		if ( i && n - wanted[ i - 1 ] > 1 ) {
			out.push( 'gap' );
		}
		out.push( n );
	} );
	return out;
}

export function Pagination( { page, totalPages, onChange, label } ) {
	if ( totalPages <= 1 ) {
		return null;
	}
	return (
		<nav
			className="eui-pagination"
			aria-label={ label || __( 'Pagination', 'emcp-tools' ) }
		>
			<IconButton
				icon="chevron-left"
				label={ __( 'Previous page', 'emcp-tools' ) }
				disabled={ page <= 1 }
				onClick={ () => onChange( page - 1 ) }
			/>
			{ pageList( page, totalPages ).map( ( p, i ) =>
				'gap' === p ? (
					<span
						key={ `gap-${ i }` }
						className="eui-pagination__gap"
						aria-hidden="true"
					>
						...
					</span>
				) : (
					<button
						key={ p }
						type="button"
						className={ cx(
							'eui-pagination__page',
							p === page && 'is-current'
						) }
						aria-current={ p === page ? 'page' : undefined }
						onClick={ () => onChange( p ) }
					>
						{ p }
					</button>
				)
			) }
			<IconButton
				icon="chevron-right"
				label={ __( 'Next page', 'emcp-tools' ) }
				disabled={ page >= totalPages }
				onClick={ () => onChange( page + 1 ) }
			/>
		</nav>
	);
}
