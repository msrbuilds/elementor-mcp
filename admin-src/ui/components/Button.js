import { forwardRef } from '@wordpress/element';
import { Icon } from './Icon';
import { cx } from '../utils/cx';
import './Button.css';

/**
 * The one button. Primary is the only filled colour (design rule).
 */
export const Button = forwardRef( function Button(
	{ variant = 'secondary', size = 'md', icon, loading = false, href, disabled = false, type = 'button', className, children, ...rest },
	ref
) {
	const classes = cx( 'eui-btn', `eui-btn--${ variant }`, `eui-btn--${ size }`, loading && 'is-loading', className );
	const content = (
		<>
			{ loading ? <Icon name="loader-circle" className="eui-spin" /> : icon && <Icon name={ icon } /> }
			{ children !== undefined && children !== null && <span className="eui-btn__label">{ children }</span> }
		</>
	);
	if ( href && ! disabled ) {
		return (
			<a ref={ ref } href={ href } className={ classes } { ...rest }>
				{ content }
			</a>
		);
	}
	return (
		<button ref={ ref } type={ type } className={ classes } disabled={ disabled || loading } aria-busy={ loading ? 'true' : undefined } { ...rest }>
			{ content }
		</button>
	);
} );

/**
 * A square icon-only button. `label` is required: it is the accessible name
 * and the tooltip.
 */
export const IconButton = forwardRef( function IconButton( { icon, label, size = 'md', className, ...rest }, ref ) {
	if ( ! label ) {
		throw new Error( 'IconButton requires a label' );
	}
	return (
		<button ref={ ref } type="button" aria-label={ label } title={ label } className={ cx( 'eui-iconbtn', `eui-iconbtn--${ size }`, className ) } { ...rest }>
			<Icon name={ icon } />
		</button>
	);
} );
