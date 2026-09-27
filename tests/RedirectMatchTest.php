<?php
/**
 * Redirect query matching, part 2: rule choice and forwarding (spec 9.8).
 *
 * @package EMCP_Tools
 */

require_once dirname( __DIR__ ) . '/includes/redirects/class-redirect-store.php';

class RedirectMatchTest extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		emcp_test_reset();
	}

	private function rule( int $ignore, string $query = '', int $enabled = 1 ): array {
		return array(
			'id'           => 1,
			'source_path'  => '/page',
			'source_query' => $query,
			'ignore_query' => $ignore,
			'enabled'      => $enabled,
			'target'       => '/t',
		);
	}

	public function test_pick_prefers_the_enabled_query_rule(): void {
		$q = $this->rule( 0, 'ref=ad' );
		$p = $this->rule( 1 );
		$this->assertSame( $q, EMCP_Tools_Redirect_Store::pick( $q, $p ) );
		$this->assertSame( $p, EMCP_Tools_Redirect_Store::pick( null, $p ) );
		$this->assertSame( $p, EMCP_Tools_Redirect_Store::pick( $this->rule( 0, 'ref=ad', 0 ), $p ), 'a disabled query rule falls through' );
		$this->assertNull( EMCP_Tools_Redirect_Store::pick( null, $this->rule( 1, '', 0 ) ) );
		$this->assertNull( EMCP_Tools_Redirect_Store::pick( null, $this->rule( 0, 'x=1' ) ), 'a query rule never answers as a path rule' );
	}

	public function test_target_for_forwards_only_for_path_rules(): void {
		$this->assertSame( '/t?ref=other', EMCP_Tools_Redirect_Store::target_for( $this->rule( 1 ), '/t', 'ref=other' ) );
		$this->assertSame( '/t?keep=1', EMCP_Tools_Redirect_Store::target_for( $this->rule( 1 ), '/t?keep=1', 'ref=other' ), 'a target with its own query is used as written' );
		$this->assertSame( '/t', EMCP_Tools_Redirect_Store::target_for( $this->rule( 0, 'ref=ad' ), '/t', 'ref=ad' ) );
		$this->assertSame( '/t', EMCP_Tools_Redirect_Store::target_for( $this->rule( 1 ), '/t', '' ) );
	}

	public function test_rule_loops_lets_a_query_rule_target_its_own_path(): void {
		$this->assertTrue( EMCP_Tools_Redirect_Store::rule_loops( '/page', '', '/page' ) );
		$this->assertFalse( EMCP_Tools_Redirect_Store::rule_loops( '/page', 'ref=ad', '/page' ) );
		$this->assertTrue( EMCP_Tools_Redirect_Store::rule_loops( '/page', 'ref=ad', '/page?ref=ad' ) );
		$this->assertFalse( EMCP_Tools_Redirect_Store::rule_loops( '/page', '', '/other' ) );
	}

	public function test_shadow_warning_names_a_live_post(): void {
		$GLOBALS['emcp_test']['url_to_postid']['/about'] = 42;
		$GLOBALS['emcp_test']['post_status'][42]         = 'publish';
		$warning = EMCP_Tools_Redirect_Store::shadow_warning( '/about' );
		$this->assertStringContainsString( '#42', $warning );
		$this->assertStringNotContainsString( "\u{2014}", $warning, 'no em dash in copy' );
		$GLOBALS['emcp_test']['post_status'][42] = 'draft';
		$this->assertSame( '', EMCP_Tools_Redirect_Store::shadow_warning( '/about' ) );
	}
}
