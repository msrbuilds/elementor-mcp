<?php
/**
 * Contact Form 7 adapter (free) — dispatch, catalog, field/mail read, and the
 * merge-then-save write path. Fixture-driven via the WPCF7 stubs in bootstrap.
 */

use PHPUnit\Framework\TestCase;

final class Cf7IntegrationTest extends TestCase {

	private EMCP_Tools_CF7_Integration $cf7;

	protected function setUp(): void {
		emcp_test_reset();
		$GLOBALS['emcp_test']['caps'] = array( 'wpcf7_read_contact_forms', 'wpcf7_edit_contact_forms' );
		$GLOBALS['emcp_test']['cf7']  = array(
			'forms' => array(
				42 => array(
					'title' => 'Contact',
					'slug'  => 'contact',
					'props' => array(
						'mail'                => array( 'recipient' => 'a@b.test', 'subject' => 'Hi', 'body' => 'msg' ),
						'mail_2'              => array( 'active' => false ),
						'messages'            => array( 'mail_sent_ok' => 'Thanks!' ),
						'additional_settings' => 'demo_mode: on',
					),
					'tags'  => array(
						array( 'name' => 'your-name', 'type' => 'text*', 'basetype' => 'text' ),
						array( 'name' => 'your-email', 'type' => 'email*', 'basetype' => 'email' ),
						array( 'name' => '', 'type' => 'submit', 'basetype' => 'submit' ),
					),
				),
			),
		);
		$this->cf7 = new EMCP_Tools_CF7_Integration();
	}

	public function test_id_and_tools(): void {
		$this->assertSame( 'cf7', $this->cf7->id() );
		$this->assertSame( array( 'emcp-tools/cf7-read', 'emcp-tools/cf7-write' ), $this->cf7->get_ability_names() );
		$this->assertTrue( $this->cf7->is_active() );
	}

	public function test_read_catalog_lists_read_ops_only(): void {
		$out   = $this->cf7->run_read( array() );
		$names = array_column( $out['operations'], 'operation' );
		$this->assertContains( 'list-forms', $names );
		$this->assertContains( 'get-form', $names );
		$this->assertNotContains( 'update-notification', $names );
	}

	public function test_write_catalog_lists_write_ops(): void {
		$out   = $this->cf7->run_write( array() );
		$names = array_column( $out['operations'], 'operation' );
		$this->assertEqualsCanonicalizing(
			array( 'update-notification', 'update-messages', 'update-form-settings' ),
			$names
		);
	}

	public function test_list_forms(): void {
		$out = $this->cf7->run_read( array( 'operation' => 'list-forms' ) );
		$this->assertCount( 1, $out['forms'] );
		$this->assertSame( 42, $out['forms'][0]['id'] );
		$this->assertSame( 'contact', $out['forms'][0]['slug'] );
		$this->assertSame( 2, $out['forms'][0]['field_count'], 'nameless submit tag excluded' );
	}

	public function test_get_form_returns_fields_and_mail(): void {
		$out = $this->cf7->run_read( array( 'operation' => 'get-form', 'arguments' => array( 'form_id' => 42 ) ) );
		$this->assertSame( 'Contact', $out['title'] );
		$this->assertSame( 'a@b.test', $out['mail']['recipient'] );
		$names = array_column( $out['fields'], 'name' );
		$this->assertSame( array( 'your-name', 'your-email' ), $names );
		$this->assertTrue( $out['fields'][0]['required'] );
	}

	public function test_get_form_unknown_id_errors(): void {
		$out = $this->cf7->run_read( array( 'operation' => 'get-form', 'arguments' => array( 'form_id' => 999 ) ) );
		$this->assertInstanceOf( WP_Error::class, $out );
		$this->assertSame( 'form_not_found', $out->get_error_code() );
	}

	public function test_update_notification_merges_and_saves(): void {
		$out = $this->cf7->run_write(
			array(
				'operation'  => 'update-notification',
				'arguments'  => array( 'form_id' => 42, 'notification' => 'mail', 'mail' => array( 'subject' => 'Changed' ) ),
			)
		);
		$this->assertTrue( $out['updated'] );
		$this->assertSame( 'Changed', $out['mail']['subject'] );
		$this->assertSame( 'a@b.test', $out['mail']['recipient'], 'untouched keys preserved' );
	}

	public function test_update_messages_merges(): void {
		$out = $this->cf7->run_write(
			array(
				'operation' => 'update-messages',
				'arguments' => array( 'form_id' => 42, 'messages' => array( 'mail_sent_ok' => 'Done' ) ),
			)
		);
		$this->assertSame( 'Done', $out['messages']['mail_sent_ok'] );
	}

	public function test_update_form_settings_replaces(): void {
		$out = $this->cf7->run_write(
			array(
				'operation' => 'update-form-settings',
				'arguments' => array( 'form_id' => 42, 'additional_settings' => 'skip_mail: on' ),
			)
		);
		$this->assertSame( 'skip_mail: on', $out['additional_settings'] );
	}
}
