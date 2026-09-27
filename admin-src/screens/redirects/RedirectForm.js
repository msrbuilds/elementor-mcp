import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Checkbox, Field, Select } from '@emcp/ui';
import { TargetPicker, looksLikeUrl } from './TargetPicker';

const CODES = [
	{ value: '301', label: __( '301 Permanent', 'emcp-tools' ) },
	{ value: '302', label: __( '302 Temporary', 'emcp-tools' ) },
];

/**
 * Add / edit redirect form (spec 8.21, 9.8). The parent remounts it (a new
 * `key`) to reset it after a successful add.
 *
 * @param {Object}                          props
 * @param {string}                          props.label       Accessible form name.
 * @param {Object}                          props.initial     { source, target, targetPostId, targetLabel, code, ignoreQuery }.
 * @param {string}                          props.submitLabel Submit button text.
 * @param {boolean}                         props.busy        A request is running.
 * @param {boolean}                         props.focusTarget Focus the To field on mount.
 * @param {boolean}                         props.stacked     One column (the edit drawer).
 * @param {(body: Object) => Promise<void>} props.onSubmit    Submit handler.
 */
export function RedirectForm( {
	label,
	initial,
	submitLabel,
	busy,
	focusTarget = false,
	stacked = false,
	onSubmit,
} ) {
	const [ source, setSource ] = useState( initial.source || '' );
	const [ target, setTarget ] = useState( {
		value: initial.targetPostId
			? initial.targetLabel || ''
			: initial.target || '',
		postId: initial.targetPostId || 0,
	} );
	const [ code, setCode ] = useState( String( initial.code || 301 ) );
	const [ ignoreQuery, setIgnoreQuery ] = useState(
		false !== initial.ignoreQuery
	);
	const [ errors, setErrors ] = useState( {} );
	const targetRef = useRef();

	useEffect( () => {
		if ( focusTarget && targetRef.current ) {
			targetRef.current.focus();
		}
	}, [ focusTarget ] );

	const submit = async ( e ) => {
		e.preventDefault();
		const next = {};
		if ( ! source.trim() ) {
			next.source = __(
				'Enter the old path to redirect from.',
				'emcp-tools'
			);
		} else if ( ! ignoreQuery && ! source.includes( '?' ) ) {
			next.source = __(
				'Add the query string to match, for example /page?ref=ad.',
				'emcp-tools'
			);
		}
		if ( ! target.postId && ! target.value.trim() ) {
			next.target = __(
				'Choose a page or enter a URL to redirect to.',
				'emcp-tools'
			);
		} else if ( ! target.postId && ! looksLikeUrl( target.value.trim() ) ) {
			// Typed text that was never picked from the list is not a URL.
			next.target = __(
				'Choose a page from the list, or enter a path such as /new-page or a full URL.',
				'emcp-tools'
			);
		}
		setErrors( next );
		if ( Object.keys( next ).length ) {
			return;
		}
		await onSubmit( {
			source: source.trim(),
			...( target.postId
				? { targetPostId: target.postId }
				: { target: target.value.trim() } ),
			code: Number( code ),
			ignoreQuery,
		} );
	};

	return (
		<form
			className={
				stacked
					? 'emcp-redirects__form is-stacked'
					: 'emcp-redirects__form'
			}
			aria-label={ label }
			onSubmit={ submit }
			noValidate
		>
			<Field label={ __( 'From', 'emcp-tools' ) } error={ errors.source }>
				{ ( p ) => (
					<input
						{ ...p }
						type="text"
						className="eui-input eui-mono"
						placeholder="/old-page"
						value={ source }
						onChange={ ( e ) => setSource( e.target.value ) }
					/>
				) }
			</Field>
			<TargetPicker
				value={ target }
				onChange={ setTarget }
				error={ errors.target }
				inputRef={ targetRef }
			/>
			<Field label={ __( 'Type', 'emcp-tools' ) }>
				{ ( p ) => (
					<Select
						{ ...p }
						options={ CODES }
						value={ code }
						onChange={ setCode }
					/>
				) }
			</Field>
			<div className="emcp-redirects__submit">
				<Checkbox
					checked={ ignoreQuery }
					onChange={ setIgnoreQuery }
					label={ __(
						'Match regardless of query string',
						'emcp-tools'
					) }
				/>
				<Button type="submit" variant="primary" loading={ busy }>
					{ submitLabel }
				</Button>
			</div>
		</form>
	);
}
