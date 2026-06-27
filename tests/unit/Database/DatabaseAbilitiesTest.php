<?php
/**
 * @group database
 * @package EMCP_Tools\Tests\Database
 */
namespace EMCP_Tools\Tests\Database;

use PHPUnit\Framework\TestCase;

class DatabaseAbilitiesTest extends TestCase {

	/** @test */
	public function registers_the_six_tools(): void {
		$a = new \EMCP_Tools_Database_Abilities();
		$a->register();
		$this->assertSame(
			array(
				'emcp-tools/list-tables',
				'emcp-tools/describe-table',
				'emcp-tools/query',
				'emcp-tools/insert-row',
				'emcp-tools/update-rows',
				'emcp-tools/delete-rows',
			),
			$a->get_ability_names()
		);
	}

	/** @test */
	public function query_rejects_writes(): void {
		$res = ( new \EMCP_Tools_Database_Abilities() )->execute_query( array( 'sql' => 'DELETE FROM wp_options' ) );
		$this->assertInstanceOf( \WP_Error::class, $res );
		$this->assertSame( 'not_read_only', $res->get_error_code() );
	}

	/** @test */
	public function update_requires_non_empty_where(): void {
		$res = ( new \EMCP_Tools_Database_Abilities() )->execute_update_rows( array( 'table' => 'wp_options', 'data' => array( 'a' => 1 ), 'where' => array() ) );
		$this->assertInstanceOf( \WP_Error::class, $res );
		$this->assertSame( 'where_required', $res->get_error_code() );
	}

	/** @test */
	public function delete_requires_confirm_then_where(): void {
		$a = new \EMCP_Tools_Database_Abilities();
		$no_confirm = $a->execute_delete_rows( array( 'table' => 'wp_options', 'where' => array( 'option_id' => 1 ) ) );
		$this->assertSame( 'confirm_required', $no_confirm->get_error_code() );
		$no_where = $a->execute_delete_rows( array( 'table' => 'wp_options', 'where' => array(), 'confirm' => true ) );
		$this->assertSame( 'where_required', $no_where->get_error_code() );
	}
}
