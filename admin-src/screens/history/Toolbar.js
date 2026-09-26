import { __ } from '@wordpress/i18n';
import { SearchInput, Segmented, Select } from '@emcp/ui';

const KINDS = [
	{ value: 'all', label: __( 'All', 'emcp-tools' ) },
	{ value: 'content', label: __( 'Content', 'emcp-tools' ) },
	{ value: 'design', label: __( 'Design', 'emcp-tools' ) },
	{ value: 'settings', label: __( 'Settings', 'emcp-tools' ) },
];

const RANGES = [
	{ value: 'all', label: __( 'All time', 'emcp-tools' ) },
	{ value: '24h', label: __( 'Last 24 hours', 'emcp-tools' ) },
	{ value: '7d', label: __( 'Last 7 days', 'emcp-tools' ) },
	{ value: '14d', label: __( 'Last 14 days', 'emcp-tools' ) },
	{ value: '30d', label: __( 'Last 30 days', 'emcp-tools' ) },
];

/**
 * History filters (spec 8.19).
 *
 * @param {Object}     props
 * @param {Object}     props.filters  { search, kind, client, range }.
 * @param {string[]}   props.clients  Clients seen.
 * @param {() => void} props.onChange ( key, value ).
 */
export function Toolbar( { filters, clients, onChange } ) {
	return (
		<div className="emcp-history__toolbar">
			<SearchInput
				label={ __( 'Search changes', 'emcp-tools' ) }
				placeholder={ __( 'Search changes', 'emcp-tools' ) }
				value={ filters.search }
				debounce={ 250 }
				onChange={ ( v ) => onChange( 'search', v ) }
			/>
			<Segmented
				label={ __( 'Kind', 'emcp-tools' ) }
				options={ KINDS }
				value={ filters.kind }
				onChange={ ( v ) => onChange( 'kind', v ) }
			/>
			<Select
				aria-label={ __( 'Client', 'emcp-tools' ) }
				options={ [
					{ value: '', label: __( 'All clients', 'emcp-tools' ) },
					...clients.map( ( c ) => ( { value: c, label: c } ) ),
				] }
				value={ filters.client }
				onChange={ ( v ) => onChange( 'client', v ) }
			/>
			<Select
				aria-label={ __( 'Time range', 'emcp-tools' ) }
				options={ RANGES }
				value={ filters.range }
				onChange={ ( v ) => onChange( 'range', v ) }
			/>
		</div>
	);
}
