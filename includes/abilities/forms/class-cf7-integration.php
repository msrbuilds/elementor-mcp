<?php
/**
 * Contact Form 7 integration (free) — two dispatcher tools (cf7-read /
 * cf7-write) over the WPCF7_ContactForm API.
 *
 * Verified against Contact Form 7 6.1.6:
 *  - WPCF7_ContactForm::find( $args ) → WPCF7_ContactForm[] (post_type wpcf7_contact_form).
 *  - WPCF7_ContactForm::get_instance( int $id ) → WPCF7_ContactForm|null.
 *  - ->id() / ->name() (slug) / ->title().
 *  - ->scan_form_tags() → WPCF7_FormTag[] (props: name, type, basetype, values,
 *    labels; is_required() = trailing "*").
 *  - ->prop('mail'|'mail_2'|'messages'|'additional_settings'|'form').
 *  - ->set_properties( array ) + ->save().
 *  - mail array keys: active, subject, sender, recipient, body,
 *    additional_headers, attachments, use_html, exclude_blank.
 *  - caps: wpcf7_read_contact_forms (= edit_posts), wpcf7_edit_contact_forms
 *    (= publish_pages).
 *
 * CF7 stores no submissions, so there are no entry operations.
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.5.0
 */
class EMCP_Tools_CF7_Integration extends EMCP_Tools_Form_Integration {

	/**
	 * @return string
	 */
	public function id(): string {
		return 'cf7';
	}

	/**
	 * @return string
	 */
	public function label(): string {
		return 'Contact Form 7';
	}

	/**
	 * @return bool
	 */
	public function is_active(): bool {
		return class_exists( 'WPCF7_ContactForm' ) || defined( 'WPCF7_VERSION' );
	}

	/**
	 * @return array<string,array>
	 */
	protected function operations(): array {
		$can_read  = static function (): bool {
			return current_user_can( 'wpcf7_read_contact_forms' );
		};
		$can_write = static function (): bool {
			return current_user_can( 'wpcf7_edit_contact_forms' );
		};

		return array(
			'list-forms'           => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_list_forms' ),
				'perm' => $can_read,
				'desc' => 'List all Contact Form 7 forms (id, title, slug, field count).',
			),
			'get-form'             => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_form' ),
				'perm' => $can_read,
				'desc' => 'Get one form by { form_id }: fields, mail templates, messages, and settings.',
			),
			'list-notifications'   => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_list_notifications' ),
				'perm' => $can_read,
				'desc' => 'List a form\'s mail templates (mail, mail_2) by { form_id }.',
			),
			'get-settings'         => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_settings' ),
				'perm' => $can_read,
				'desc' => 'Get a form\'s messages and additional settings by { form_id }.',
			),
			'update-notification'  => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_notification' ),
				'perm' => $can_write,
				'desc' => 'Update a mail template: { form_id, notification: "mail"|"mail_2", mail: { subject?, sender?, recipient?, body?, additional_headers?, attachments?, use_html?, active? } }. Only provided keys change.',
			),
			'update-messages'      => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_messages' ),
				'perm' => $can_write,
				'desc' => 'Update validation/response messages: { form_id, messages: { key: value, ... } }. Only provided keys change.',
			),
			'update-form-settings' => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_form_settings' ),
				'perm' => $can_write,
				'desc' => 'Replace the Additional Settings block: { form_id, additional_settings: string }.',
			),
		);
	}

	/**
	 * Load a form or return a WP_Error.
	 *
	 * @param array $args Operation arguments.
	 * @return \WPCF7_ContactForm|WP_Error
	 */
	private function form( array $args ) {
		$id = isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0;
		if ( ! $id ) {
			return new WP_Error( 'missing_argument', __( 'Missing required argument: form_id.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$form = \WPCF7_ContactForm::get_instance( $id );
		if ( ! $form ) {
			return new WP_Error(
				'form_not_found',
				sprintf(
					/* translators: %d: form id */
					__( 'No Contact Form 7 form with id %d.', 'emcp-tools' ),
					$id
				),
				array( 'status' => 404 )
			);
		}
		return $form;
	}

	/**
	 * @param array $args Unused.
	 * @return array
	 */
	public function op_list_forms( array $args ): array {
		$out = array();
		foreach ( \WPCF7_ContactForm::find( array() ) as $form ) {
			$out[] = array(
				'id'          => $form->id(),
				'title'       => $form->title(),
				'slug'        => $form->name(),
				'field_count' => count( $this->fields( $form ) ),
			);
		}
		return array( 'forms' => $out );
	}

	/**
	 * @param array $args { form_id }.
	 * @return array|WP_Error
	 */
	public function op_get_form( array $args ) {
		$form = $this->form( $args );
		if ( is_wp_error( $form ) ) {
			return $form;
		}
		return array(
			'id'                  => $form->id(),
			'title'               => $form->title(),
			'slug'                => $form->name(),
			'fields'              => $this->fields( $form ),
			'mail'                => $form->prop( 'mail' ),
			'mail_2'              => $form->prop( 'mail_2' ),
			'messages'            => $form->prop( 'messages' ),
			'additional_settings' => $form->prop( 'additional_settings' ),
		);
	}

	/**
	 * @param array $args { form_id }.
	 * @return array|WP_Error
	 */
	public function op_list_notifications( array $args ) {
		$form = $this->form( $args );
		if ( is_wp_error( $form ) ) {
			return $form;
		}
		return array(
			'notifications' => array(
				array( 'id' => 'mail', 'mail' => $form->prop( 'mail' ) ),
				array( 'id' => 'mail_2', 'mail' => $form->prop( 'mail_2' ) ),
			),
		);
	}

	/**
	 * @param array $args { form_id }.
	 * @return array|WP_Error
	 */
	public function op_get_settings( array $args ) {
		$form = $this->form( $args );
		if ( is_wp_error( $form ) ) {
			return $form;
		}
		return array(
			'messages'            => $form->prop( 'messages' ),
			'additional_settings' => $form->prop( 'additional_settings' ),
		);
	}

	/**
	 * @param array $args { form_id, notification, mail }.
	 * @return array|WP_Error
	 */
	public function op_update_notification( array $args ) {
		$form = $this->form( $args );
		if ( is_wp_error( $form ) ) {
			return $form;
		}
		$which = isset( $args['notification'] ) ? (string) $args['notification'] : 'mail';
		if ( ! in_array( $which, array( 'mail', 'mail_2' ), true ) ) {
			return new WP_Error( 'invalid_argument', __( 'notification must be "mail" or "mail_2".', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$patch = ( isset( $args['mail'] ) && is_array( $args['mail'] ) ) ? $args['mail'] : array();
		if ( ! $patch ) {
			return new WP_Error( 'missing_argument', __( 'Missing required argument: mail (object of fields to change).', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$current = $form->prop( $which );
		$current = is_array( $current ) ? $current : array();
		$merged  = array_merge( $current, $patch );
		$form->set_properties( array( $which => $merged ) );
		$form->save();
		return array( 'updated' => true, 'notification' => $which, 'mail' => \WPCF7_ContactForm::get_instance( $form->id() )->prop( $which ) );
	}

	/**
	 * @param array $args { form_id, messages }.
	 * @return array|WP_Error
	 */
	public function op_update_messages( array $args ) {
		$form = $this->form( $args );
		if ( is_wp_error( $form ) ) {
			return $form;
		}
		$patch = ( isset( $args['messages'] ) && is_array( $args['messages'] ) ) ? $args['messages'] : array();
		if ( ! $patch ) {
			return new WP_Error( 'missing_argument', __( 'Missing required argument: messages (object of message keys to change).', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$current = $form->prop( 'messages' );
		$current = is_array( $current ) ? $current : array();
		$merged  = array_merge( $current, array_map( 'strval', $patch ) );
		$form->set_properties( array( 'messages' => $merged ) );
		$form->save();
		return array( 'updated' => true, 'messages' => \WPCF7_ContactForm::get_instance( $form->id() )->prop( 'messages' ) );
	}

	/**
	 * @param array $args { form_id, additional_settings }.
	 * @return array|WP_Error
	 */
	public function op_update_form_settings( array $args ) {
		$form = $this->form( $args );
		if ( is_wp_error( $form ) ) {
			return $form;
		}
		if ( ! isset( $args['additional_settings'] ) || ! is_string( $args['additional_settings'] ) ) {
			return new WP_Error( 'missing_argument', __( 'Missing required argument: additional_settings (string).', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$form->set_properties( array( 'additional_settings' => (string) $args['additional_settings'] ) );
		$form->save();
		return array( 'updated' => true, 'additional_settings' => \WPCF7_ContactForm::get_instance( $form->id() )->prop( 'additional_settings' ) );
	}

	/**
	 * Extract a compact field list from a form's tags (skips nameless tags like
	 * submit).
	 *
	 * @param \WPCF7_ContactForm $form Form.
	 * @return array<int,array>
	 */
	private function fields( $form ): array {
		$out = array();
		foreach ( $form->scan_form_tags() as $tag ) {
			if ( '' === (string) $tag->name ) {
				continue;
			}
			$field = array(
				'name'     => $tag->name,
				'type'     => $tag->basetype,
				'required' => $tag->is_required(),
			);
			if ( ! empty( $tag->values ) ) {
				$field['values'] = array_values( (array) $tag->values );
			}
			if ( ! empty( $tag->labels ) ) {
				$field['labels'] = array_values( (array) $tag->labels );
			}
			$out[] = $field;
		}
		return $out;
	}
}
