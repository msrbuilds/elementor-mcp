import { __ } from '@wordpress/i18n';
import { Button, Card, Icon, PageHeader } from '@emcp/ui';

/**
 * Locked-Pro screen (spec 8.25): what the feature does and how to get it.
 *
 * @param {Object} props      Props.
 * @param {Object} props.data Feature card from EMCP_Tools_Admin_Locked::feature().
 */
export function LockedScreen( { data } ) {
	return (
		<>
			<PageHeader
				title={ data.title }
				tier="pro"
				description={ data.description }
			/>
			<Card title={ __( 'What you get with Pro', 'emcp-tools' ) }>
				<ul className="eui-locked__list">
					{ ( data.bullets || [] ).map( ( b ) => (
						<li key={ b } className="eui-locked__item">
							<Icon name="check" />
							<span>{ b }</span>
						</li>
					) ) }
				</ul>
				<div className="eui-locked__actions">
					<Button
						variant="primary"
						href={ data.upgrade_url }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Upgrade to Pro', 'emcp-tools' ) }
					</Button>
					<Button
						href={ data.compare_url }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Compare plans', 'emcp-tools' ) }
					</Button>
				</div>
			</Card>
		</>
	);
}
