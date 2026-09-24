import { cx } from '../utils/cx';
import './Charts.css';

export function Meter( { value, max, label, valueText, warnAt } ) {
	const pct = max > 0 ? Math.min( 100, ( value / max ) * 100 ) : 0;
	const warn = undefined !== warnAt && value > warnAt;
	return (
		<div className={ cx( 'eui-meter', warn && 'is-warning' ) }>
			<div className="eui-meter__head">
				<span className="eui-meter__label">{ label }</span>
				<span className="eui-meter__value">{ valueText ?? `${ value } / ${ max }` }</span>
			</div>
			<div className="eui-meter__track" role="meter" aria-label={ label } aria-valuemin={ 0 } aria-valuemax={ max } aria-valuenow={ value } aria-valuetext={ valueText }>
				<div className="eui-meter__fill" style={ { width: `${ pct }%` } } />
			</div>
		</div>
	);
}

const SLOT = 40;
const GAP = 8;

/**
 * Stacked bar chart in plain SVG. The SVG is decorative; the numbers are also
 * rendered as a visually hidden table so screen readers get the data.
 */
export function BarChart( { label, data, series, height = 160 } ) {
	const totals = data.map( ( d ) => series.reduce( ( sum, s ) => sum + ( d.values[ s.key ] || 0 ), 0 ) );
	const max = Math.max( 0, ...totals );
	const scale = max > 0 ? ( height - 4 ) / max : 0;
	const mid = Math.floor( ( data.length - 1 ) / 2 );
	return (
		<figure className="eui-chart">
			<svg className="eui-chart__svg" viewBox={ `0 0 ${ data.length * SLOT } ${ height }` } preserveAspectRatio="none" aria-hidden="true" focusable="false">
				{ data.map( ( d, i ) => {
					let y = height;
					return (
						<g key={ d.label }>
							<title>{ `${ d.label }: ${ series.map( ( s ) => `${ s.label } ${ d.values[ s.key ] || 0 }` ).join( ', ' ) }` }</title>
							{ series.map( ( s ) => {
								const h = ( d.values[ s.key ] || 0 ) * scale;
								y -= h;
								return <rect key={ s.key } x={ i * SLOT + GAP / 2 } y={ y } width={ SLOT - GAP } height={ h } rx={ 3 } style={ { fill: `var(${ s.colorVar })` } } />;
							} ) }
						</g>
					);
				} ) }
			</svg>
			<div className="eui-chart__axis" aria-hidden="true">
				<span>{ data[ 0 ]?.label }</span>
				<span>{ data.length > 2 ? data[ mid ]?.label : '' }</span>
				<span>{ data.length > 1 ? data[ data.length - 1 ]?.label : '' }</span>
			</div>
			<figcaption className="eui-chart__legend">
				{ series.map( ( s ) => (
					<span key={ s.key } className="eui-chart__key">
						<span className="eui-chart__swatch" style={ { background: `var(${ s.colorVar })` } } aria-hidden="true" />
						{ s.label }
					</span>
				) ) }
			</figcaption>
			<table className="eui-visually-hidden">
				<caption>{ label }</caption>
				<thead>
					<tr>
						<th scope="col">{ label }</th>
						{ series.map( ( s ) => <th key={ s.key } scope="col">{ s.label }</th> ) }
					</tr>
				</thead>
				<tbody>
					{ data.map( ( d ) => (
						<tr key={ d.label }>
							<th scope="row">{ d.label }</th>
							{ series.map( ( s ) => <td key={ s.key }>{ d.values[ s.key ] || 0 }</td> ) }
						</tr>
					) ) }
				</tbody>
			</table>
		</figure>
	);
}

export function HBarList( { label, items } ) {
	const max = Math.max( 1, ...items.map( ( it ) => it.value ) );
	return (
		<ul className="eui-hbars" aria-label={ label }>
			{ items.map( ( it ) => (
				<li key={ it.label } className="eui-hbars__item">
					<div className="eui-hbars__row">
						<span className="eui-hbars__label eui-mono">{ it.label }</span>
						<span className="eui-hbars__value">{ it.value }</span>
					</div>
					<div className="eui-hbars__track" aria-hidden="true">
						<div className="eui-hbars__fill" style={ { width: `${ ( it.value / max ) * 100 }%` } } />
					</div>
				</li>
			) ) }
		</ul>
	);
}
