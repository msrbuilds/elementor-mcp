import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '../components/Button';
import { CopyButton } from '../components/Code';

/**
 * Catches render errors after mount (spec 5.2 layer 2). The server-rendered
 * fallback in the frame covers bundles that never load.
 */
export class ScreenBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { error: null, stack: '' };
	}

	static getDerivedStateFromError( error ) {
		return { error };
	}

	componentDidCatch( error, info ) {
		this.setState( {
			stack: `${ error.stack || error.message }\n${ info?.componentStack || '' }`,
		} );
		this.props.onError?.( error );
	}

	render() {
		if ( ! this.state.error ) {
			return this.props.children;
		}
		return (
			<div className="eui-card eui-card__body" role="alert">
				<h2 className="eui-card__title">
					{ __( 'This screen ran into a problem', 'emcp-tools' ) }
				</h2>
				<p>{ this.state.error.message }</p>
				<div style={ { display: 'flex', gap: '10px' } }>
					<Button
						variant="primary"
						onClick={ () => window.location.reload() }
					>
						{ __( 'Reload', 'emcp-tools' ) }
					</Button>
					<CopyButton
						text={ this.state.stack || this.state.error.message }
						label={ __( 'Copy details', 'emcp-tools' ) }
						className="eui-btn eui-btn--secondary eui-btn--md"
					/>
				</div>
			</div>
		);
	}
}
