<?php
/**
 * In-memory transaction for unit tests: begin() snapshots the memory change storage and every
 * participant (an object with snapshot() and restore( $state )), rollback() puts them back.
 * Records the statements it was asked for; begin and commit can be made to fail.
 *
 * @package EMCP_Tools\Tests
 */

final class EMCP_Tools_Change_Memory_Transaction {

	/** @var EMCP_Tools_Change_Memory_Storage */
	private $storage;
	/** @var object[] */
	public $participants = array();
	/** @var string[] begin, commit, rollback. */
	public $statements = array();
	/** @var bool */
	public $supported = true;
	/** @var bool */
	public $fail_begin = false;
	/** @var bool */
	public $fail_commit = false;
	/** @var array|null */
	private $saved = null;

	public function __construct( EMCP_Tools_Change_Memory_Storage $storage ) {
		$this->storage = $storage;
	}

	public function supported(): bool {
		return $this->supported;
	}

	public function begin(): bool {
		$this->statements[] = 'begin';
		if ( $this->fail_begin ) {
			return false;
		}
		$parts = array();
		foreach ( $this->participants as $i => $p ) {
			$parts[ $i ] = $p->snapshot();
		}
		$this->saved = array(
			'rows'  => $this->storage->rows,
			'next'  => $this->storage->next_seq,
			'parts' => $parts,
		);
		return true;
	}

	public function commit(): bool {
		$this->statements[] = 'commit';
		if ( $this->fail_commit ) {
			return false;
		}
		$this->saved = null;
		return true;
	}

	public function rollback(): void {
		$this->statements[] = 'rollback';
		if ( null === $this->saved ) {
			return;
		}
		$this->storage->rows     = $this->saved['rows'];
		$this->storage->next_seq = $this->saved['next'];
		foreach ( $this->participants as $i => $p ) {
			$p->restore( $this->saved['parts'][ $i ] );
		}
		$this->saved = null;
	}
}
