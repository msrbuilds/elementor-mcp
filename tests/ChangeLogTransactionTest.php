<?php
/**
 * Transactional undo: a rollback ref with transactional => true runs the handler, the completion
 * mark and the audit entry in one transaction owned by the change log, and commits all or
 * nothing. Each scenario runs in its own process (fixtures/change-log-transaction.php).
 *
 * @package EMCP_Tools\Tests
 */

use PHPUnit\Framework\TestCase;

final class ChangeLogTransactionTest extends TestCase {

	/** @dataProvider scenarios */
	public function test_transactional_undo( string $scenario ): void {
		$process = proc_open(
			array( PHP_BINARY, __DIR__ . '/fixtures/change-log-transaction.php', $scenario ),
			array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
			$pipes
		);
		$this->assertIsResource( $process );
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error  = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$this->assertSame( 0, proc_close( $process ), $output . $error );
		$this->assertSame( "PASS\n", $output, $error );
	}

	public static function scenarios(): array {
		return array(
			'a successful undo commits handler, mark and audit together' => array( 'commit' ),
			'a handler error rolls everything back'                       => array( 'handler-error' ),
			'a throwing handler rolls everything back'                    => array( 'handler-throws' ),
			'a failed completion mark leaves target and History unchanged' => array( 'mark-fails' ),
			'a failed audit entry rolls back the restore'                 => array( 'audit-fails' ),
			'a failed COMMIT is commit_failed and rolls back'             => array( 'commit-fails' ),
			'after_commit callbacks run after the commit, a throw is hook_error' => array( 'after-commit' ),
			'after_commit callbacks are discarded on rollback'            => array( 'after-commit-discarded' ),
			'a plain rollback ref behaves as before'            => array( 'not-transactional-ref' ),
			'an unsupported store is not_transactional'                   => array( 'not-supported' ),
			'a failed START TRANSACTION is not_transactional'             => array( 'begin-fails' ),
			'transaction() commits, refuses nesting and dry-runs'         => array( 'helper' ),
			'record() joins a running transaction'                        => array( 'record-inside' ),
		);
	}
}
