import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Card,
	Field,
	SaveBar,
	TextInput,
	errorMessage,
	request,
	useSettingsForm,
	useToast,
} from '@emcp/ui';

const API = '/emcp-tools/v1/admin/connection';
const GROUPS = () => [
	[
		'stock',
		__( 'Stock images', 'emcp-tools' ),
		__( 'Free API keys for the stock image tools.', 'emcp-tools' ),
	],
	[
		'remote',
		__( 'Live data providers', 'emcp-tools' ),
		__(
			'Keys for widgets that show weather, reviews or JSON data.',
			'emcp-tools'
		),
	],
	[
		'wpcli',
		__( 'WP-CLI', 'emcp-tools' ),
		__( 'The command the WP-CLI tools run over HTTP.', 'emcp-tools' ),
	],
];

/**
 * Form values: '' means unchanged for secrets; null means clear.
 *
 * @param {Object[]} fields Service fields from the payload.
 */
const initial = ( fields ) =>
	Object.fromEntries(
		fields.map( ( f ) => [ f.key, f.secret ? '' : f.value ] )
	);

export function ServicesSection( { data } ) {
	const toast = useToast();
	const [ services, setServices ] = useState( data.services );
	const form = useSettingsForm( initial( data.services ), async ( diff ) => {
		try {
			const res = await request( API + '/services', {
				method: 'POST',
				data: { values: diff },
			} );
			setServices( res.services );
			if ( res.ignored?.length ) {
				toast.error(
					__(
						'Some keys could not be changed: they are set in wp-config.php.',
						'emcp-tools'
					)
				);
			} else {
				toast.success( __( 'Services saved.', 'emcp-tools' ) );
			}
			return initial( res.services );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
			throw e;
		}
	} );
	return (
		<div className="eui-conn__services">
			{ GROUPS().map( ( [ group, title, desc ] ) => {
				const fields = services.filter( ( f ) => f.group === group );
				if ( ! fields.length ) {
					return null;
				}
				return (
					<Card key={ group } title={ title }>
						<p className="eui-conn__muted">{ desc }</p>
						{ fields.map( ( f ) => {
							const cleared = null === form.values[ f.key ];
							let placeholder = '';
							if ( f.fromConstant ) {
								placeholder = __(
									'Set in wp-config.php',
									'emcp-tools'
								);
							} else if ( f.hasValue && ! cleared ) {
								placeholder = __(
									'Saved. Type to replace it.',
									'emcp-tools'
								);
							}
							return (
								<div
									key={ f.key }
									className="eui-conn__service"
								>
									<Field
										label={ f.label }
										help={
											f.fromConstant
												? __(
														'Set in wp-config.php',
														'emcp-tools'
													)
												: f.hint
										}
									>
										{ ( a11y ) => (
											<TextInput
												{ ...a11y }
												type={
													f.secret
														? 'password'
														: 'text'
												}
												autoComplete="off"
												disabled={ f.fromConstant }
												placeholder={ placeholder }
												value={
													form.values[ f.key ] ?? ''
												}
												onChange={ ( e ) =>
													form.setValue(
														f.key,
														e.target.value
													)
												}
											/>
										) }
									</Field>
									{ f.secret &&
										f.hasValue &&
										! f.fromConstant && (
											<Button
												size="sm"
												variant="ghost"
												onClick={ () =>
													form.setValue( f.key, null )
												}
												disabled={ cleared }
												aria-label={ sprintf(
													/* translators: %s: provider. */ __(
														'Clear %s',
														'emcp-tools'
													),
													f.label
												) }
											>
												{ cleared
													? __(
															'Will be cleared',
															'emcp-tools'
														)
													: __(
															'Clear',
															'emcp-tools'
														) }
											</Button>
										) }
									{ f.url && (
										<a
											className="eui-conn__link"
											href={ f.url }
											target="_blank"
											rel="noopener noreferrer"
										>
											{ __( 'Get a key', 'emcp-tools' ) }
										</a>
									) }
								</div>
							);
						} ) }
					</Card>
				);
			} ) }
			<SaveBar
				count={ form.count }
				hint={ __( 'Keys are stored encrypted', 'emcp-tools' ) }
				onDiscard={ form.discard }
				onSave={ form.submit }
				saving={ form.saving }
				saveLabel={ __( 'Save changes', 'emcp-tools' ) }
			/>
		</div>
	);
}
