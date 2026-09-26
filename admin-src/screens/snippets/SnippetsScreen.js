import { createInterpolateElement, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Icon, IconButton, Notice } from '@emcp/ui';
import { SandboxList } from '@emcp/sandbox';
import { Findings, SnippetEditor } from './SnippetEditor';
import { ActivateDialog } from './ActivateDialog';

const REVIEW_ICONS = {
	none: 'circle-check',
	notice: 'info',
	warning: 'triangle-alert',
	critical: 'triangle-alert',
	error: 'triangle-alert',
};

function ReviewText( { review } ) {
	return (
		<span className={ `emcp-sn-review is-${ review.level }` }>
			<Icon name={ REVIEW_ICONS[ review.level ] || 'info' } size={ 14 } />
			<span>{ review.text }</span>
		</span>
	);
}

/**
 * PHP Snippets (spec 8.14), on the shared Sandbox list.
 *
 * @param {Object} props      Props.
 * @param {Object} props.data Boot payload (list payload plus codeEditor).
 */
export function SnippetsScreen( { data } ) {
	const [ editing, setEditing ] = useState( null );
	const [ activating, setActivating ] = useState( null );

	const config = {
		title: __( 'PHP Snippets', 'emcp-tools' ),
		tier: 'free',
		description: __(
			'Run small pieces of PHP as an [emcp_snippet] shortcode or on a hook. AI drafts stay inactive until you activate them.',
			'emcp-tools'
		),
		notice: {
			tone: 'danger',
			text: createInterpolateElement(
				__(
					"<b>This runs real PHP on your site.</b> The validator blocks obviously dangerous code, but it's a guardrail, not a guarantee. Only activate code you have read and trust.",
					'emcp-tools'
				),
				{ b: <strong /> }
			),
		},
		noun: {
			one: __( 'snippet', 'emcp-tools' ),
			many: __( 'snippets', 'emcp-tools' ),
		},
		noMatch: __( 'No snippets match this filter.', 'emcp-tools' ),
		searchLabel: __( 'Search snippets', 'emcp-tools' ),
		nameHeader: __( 'Snippet', 'emcp-tools' ),
		identHeader: null,
		nameExtra: ( row ) => (
			<code className="emcp-sn-shortcode">{ row.ident }</code>
		),
		extraFilters: [
			{ value: 'review', label: __( 'Needs reading', 'emcp-tools' ) },
		],
		extraColumns: [
			{
				key: 'runsOn',
				header: __( 'Runs on', 'emcp-tools' ),
				render: ( row ) => (
					<code className="emcp-sb-chip">{ row.runsOn }</code>
				),
			},
			{
				key: 'review',
				header: __( 'Review', 'emcp-tools' ),
				render: ( row ) => <ReviewText review={ row.review } />,
			},
		],
		rowActions: ( row, h ) => (
			<IconButton
				icon="pencil"
				label={ sprintf(
					/* translators: %s: snippet title. */
					__( 'Edit %s', 'emcp-tools' ),
					row.title
				) }
				disabled={ ! h.data.canEdit }
				onClick={ () => setEditing( row ) }
			/>
		),
		headerActions: ( h ) => (
			<Button
				variant="primary"
				icon="plus"
				disabled={ ! h.data.canEdit }
				onClick={ () => setEditing( { id: 0 } ) }
			>
				{ __( 'Add snippet', 'emcp-tools' ) }
			</Button>
		),
		confirmActivate: ( row ) =>
			new Promise( ( resolve ) => setActivating( { row, resolve } ) ),
		codeBelow: ( detail ) => (
			<Findings
				validation={ detail.validation }
				summary={ detail.summary }
			/>
		),
		deleteMessage: __(
			'The snippet and its sandbox file are removed.',
			'emcp-tools'
		),
		emptyTitle: __( 'No PHP snippets yet', 'emcp-tools' ),
		emptyText: __(
			'Ask your AI agent to draft one, or add one yourself.',
			'emcp-tools'
		),
		emptyPrompt: __(
			'Write a PHP snippet that adds a reading-time shortcode for posts',
			'emcp-tools'
		),
		extra: ( h ) => (
			<>
				{ ! h.data.canEdit && (
					<Notice tone="warning">
						{ __(
							'Managing PHP snippets requires the manage_options and unfiltered_html capabilities.',
							'emcp-tools'
						) }
					</Notice>
				) }
				<SnippetEditor
					open={ !! editing }
					item={ editing }
					codeEditor={ data.codeEditor }
					onClose={ () => setEditing( null ) }
					onSave={ async ( fields ) => {
						const id = editing && editing.id ? editing.id : 0;
						const res = await h.write(
							'save',
							`/emcp-tools/v1/admin/sandbox/snippets${
								id ? `/${ id }` : ''
							}`,
							{ data: fields, rethrow: true }
						);
						setEditing( null );
						return res;
					} }
				/>
				<ActivateDialog
					request={ activating }
					onDone={ ( ok ) => {
						activating.resolve( ok );
						setActivating( null );
					} }
				/>
			</>
		),
	};

	return <SandboxList data={ data } config={ config } />;
}
