import { __ } from '@wordpress/i18n';
import { Icon } from './Icon';
import { IconButton } from './Button';
import { cx } from '../utils/cx';
import './Layout.css';

export function Card( {
	title,
	actions,
	children,
	padded = true,
	interactive = false,
	as: Tag = 'section',
	className,
	...rest
} ) {
	return (
		<Tag
			className={ cx(
				'eui-card',
				interactive && 'eui-card--interactive',
				className
			) }
			{ ...rest }
		>
			{ ( title || actions ) && (
				<header className="eui-card__head">
					{ title && <h2 className="eui-card__title">{ title }</h2> }
					{ actions && (
						<div className="eui-card__actions">{ actions }</div>
					) }
				</header>
			) }
			<div className={ padded ? 'eui-card__body' : undefined }>
				{ children }
			</div>
		</Tag>
	);
}

function badgeText( kind, value ) {
	const map = {
		tier: {
			pro: __( 'Pro', 'emcp-tools' ),
			free: __( 'Free', 'emcp-tools' ),
		},
		risk: {
			'read-only': __( 'Read-only', 'emcp-tools' ),
			writes: __( 'Writes', 'emcp-tools' ),
			destructive: __( 'Destructive', 'emcp-tools' ),
		},
		change: {
			new: __( 'New', 'emcp-tools' ),
			fixed: __( 'Fixed', 'emcp-tools' ),
		},
	};
	return map[ kind ]?.[ value ] ?? value;
}

export function Badge( { kind = 'status', value, children, dot = false } ) {
	return (
		<span
			className={ cx(
				'eui-badge',
				`eui-badge--${ kind }`,
				value && `eui-badge--${ kind }-${ value }`
			) }
		>
			{ dot && <span className="eui-badge__dot" aria-hidden="true" /> }
			{ children ?? badgeText( kind, value ) }
		</span>
	);
}

export function PageHeader( { title, tier, description, actions, back } ) {
	return (
		// A div, not <header>: the frame sits inside core's role="main", and a
		// nested banner landmark fails axe (landmark-banner-is-top-level).
		<div className="eui-page-header">
			{ back && (
				<a className="eui-page-header__back" href={ back.href }>
					<Icon name="arrow-left" size={ 14 } />
					{ back.label }
				</a>
			) }
			<div className="eui-page-header__row">
				<div className="eui-page-header__text">
					<h1 className="eui-page-header__title">
						{ title }
						{ tier && <Badge kind="tier" value={ tier } /> }
					</h1>
					{ description && (
						<p className="eui-page-header__desc">{ description }</p>
					) }
				</div>
				{ actions && (
					<div className="eui-page-header__actions">{ actions }</div>
				) }
			</div>
		</div>
	);
}

const NOTICE_ICONS = {
	info: 'info',
	success: 'circle-check',
	warning: 'triangle-alert',
	danger: 'circle-alert',
};

export function Notice( {
	tone = 'info',
	title,
	children,
	actions,
	onDismiss,
} ) {
	return (
		<div
			className={ cx( 'eui-notice', `eui-notice--${ tone }` ) }
			role={ 'danger' === tone ? 'alert' : 'status' }
		>
			<Icon name={ NOTICE_ICONS[ tone ] } className="eui-notice__icon" />
			<div className="eui-notice__body">
				{ title && (
					<strong className="eui-notice__title">{ title } </strong>
				) }
				{ children }
			</div>
			{ actions && (
				<div className="eui-notice__actions">{ actions }</div>
			) }
			{ onDismiss && (
				<IconButton
					icon="x"
					size="sm"
					label={ __( 'Dismiss', 'emcp-tools' ) }
					onClick={ onDismiss }
				/>
			) }
		</div>
	);
}

export function EmptyState( { icon = 'info', title, children, actions } ) {
	return (
		<div className="eui-empty">
			<span className="eui-empty__icon" aria-hidden="true">
				<Icon name={ icon } size={ 20 } />
			</span>
			<p className="eui-empty__title">{ title }</p>
			{ children && <p className="eui-empty__text">{ children }</p> }
			{ actions && <div className="eui-empty__actions">{ actions }</div> }
		</div>
	);
}

export function Skeleton( { lines = 3, label } ) {
	return (
		<div className="eui-skeleton" role={ label ? 'status' : undefined }>
			{ label && <span className="eui-visually-hidden">{ label }</span> }
			{ Array.from( { length: lines }, ( _, i ) => (
				<span
					key={ i }
					className="eui-skeleton__line"
					aria-hidden="true"
				/>
			) ) }
		</div>
	);
}
