<?php
/**
 * One coordinated cutover from the capped option to the emcp_changes table
 * (spec 9.1 steps 4 to 6 and 8). Nothing is copied outside the named lock,
 * and the single write of the flag to "table" is the publication point.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Change ledger cutover.
 */
final class EMCP_Tools_Change_Cutover {

	const LOCK_WAIT     = 30;
	const LEASE_TTL     = 120;
	const BATCH         = 50;
	const FALLBACK_WAIT = 86400;
	const CRON_HOOK     = 'emcp_tools_changes_migrate';

	/** @var EMCP_Tools_Change_Storage */
	private $s;

	/** @var EMCP_Tools_Lease */
	private $lease;

	/**
	 * @param EMCP_Tools_Change_Storage $s     Storage.
	 * @param EMCP_Tools_Lease|null     $lease Lease.
	 */
	public function __construct( EMCP_Tools_Change_Storage $s, ?EMCP_Tools_Lease $lease = null ) {
		$this->s     = $s;
		$this->lease = $lease ?? new EMCP_Tools_Lease();
	}

	/**
	 * Triggers (spec 9.1 step 4): admin_init for manage_options users, and a
	 * single WP-Cron event scheduled while the migration is not finished.
	 */
	public static function init(): void {
		add_action(
			'admin_init',
			static function () {
				if ( current_user_can( 'manage_options' ) && ! wp_doing_ajax() ) {
					self::maybe_run();
				}
			}
		);
		add_action( self::CRON_HOOK, array( __CLASS__, 'maybe_run' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ), 20 );
	}

	/**
	 * Schedule the cron run until cleanup is done. Only wp-admin and cron look:
	 * the state options are not autoloaded, so a front-end check would cost a
	 * query on every visit. Nothing is scheduled while a fallback or a failed
	 * copy is waiting out its back-off.
	 */
	public static function schedule(): void {
		$cron = function_exists( 'wp_doing_cron' ) && wp_doing_cron();
		if ( ! $cron && ! is_admin() ) {
			return;
		}
		$state = get_option( EMCP_Tools_Change_Names::cutover() );
		if ( is_array( $state ) && 'done' === ( $state['cleanup'] ?? '' ) ) {
			return;
		}
		if ( '' !== self::waiting( self::instance()->s ) ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 60, self::CRON_HOOK );
		}
	}

	/** The cutover on the ledger's own storage and lease. */
	public static function instance(): self {
		$store = EMCP_Tools_Change_Log::store();
		return new self( $store->storage(), $store->lease() );
	}

	/**
	 * Run from admin_init or cron: nothing once published and clean, and a
	 * fallback decided less than a day ago is not retried yet.
	 */
	public static function maybe_run(): string {
		$c = self::instance();
		if ( 'table' === $c->s->flag() ) {
			$state = $c->s->get_meta( EMCP_Tools_Change_Names::cutover() );
			if ( is_array( $state ) && 'done' === ( $state['cleanup'] ?? '' ) ) {
				return 'done';
			}
		}
		$wait = self::waiting( $c->s );
		if ( '' !== $wait ) {
			return $wait;
		}
		return $c->run();
	}

	/**
	 * 'fallback' or 'retry' while a fallback (a day) or a failed copy (an hour
	 * per failed attempt, up to a day) waits to be retried; '' otherwise.
	 *
	 * @param EMCP_Tools_Change_Storage $s Storage.
	 */
	private static function waiting( EMCP_Tools_Change_Storage $s ): string {
		$fb = $s->get_meta( EMCP_Tools_Change_Names::fallback() );
		if ( is_array( $fb ) && time() - (int) ( $fb['ts'] ?? 0 ) < self::FALLBACK_WAIT ) {
			return 'fallback';
		}
		$err = $s->get_meta( EMCP_Tools_Change_Names::cutover_error() );
		if ( is_array( $err ) ) {
			$wait = min( self::FALLBACK_WAIT, 3600 * max( 1, (int) ( $err['attempts'] ?? 1 ) ) );
			if ( time() - (int) ( $err['ts'] ?? 0 ) < $wait ) {
				return 'retry';
			}
		}
		return '';
	}

	/**
	 * Migrate. Returns done, busy (another owner holds the lease), retry (the
	 * lock was busy or the copy did not verify) or fallback.
	 *
	 * @param callable|null $at_stage Test hook, called with locked, truncated, inserted,
	 *                                verified, published and cleaned.
	 */
	public function run( ?callable $at_stage = null ): string {
		$stage = static function ( string $name ) use ( $at_stage ): void {
			if ( $at_stage ) {
				$at_stage( $name );
			}
		};
		if ( 'table' === $this->s->flag() ) {
			// Published before: the table stays authoritative, only cleanup is left.
			$this->cleanup();
			$stage( 'cleaned' );
			return 'done';
		}
		$owner = 'cutover-' . wp_generate_password( 12, false );
		if ( ! $this->lease->acquire( EMCP_Tools_Change_Names::lease(), $owner, self::LEASE_TTL ) ) {
			return 'busy';
		}
		try {
			if ( ! $this->s->table_exists() && ! $this->s->table_create() ) {
				$this->fallback( 'no_create' );
				return 'fallback';
			}
			$got = $this->s->lock( self::LOCK_WAIT );
			if ( null === $got ) {
				$this->fallback( 'no_locks' );
				return 'fallback';
			}
			if ( false === $got ) {
				return 'retry';
			}
			$published_elsewhere = false;
			try {
				// Re-check inside the lock: a run that read the flag before another
				// process published must never truncate the live table.
				if ( 'table' === $this->s->flag() ) {
					$published_elsewhere = true;
				} else {
					$stage( 'locked' );
					if ( ! $this->s->table_truncate() ) {
						// TRUNCATE needs the DROP privilege; DELETE empties the table as well.
						$this->s->table_delete_upto( PHP_INT_MAX );
					}
					$stage( 'truncated' );
					$raw  = $this->s->option_raw();
					$rows = $this->s->option_rows( $raw );
					foreach ( array_chunk( $rows, self::BATCH ) as $chunk ) {
						if ( ! $this->s->table_insert_many( $chunk ) ) {
							$this->failed( 'copy_failed' );
							return 'retry';
						}
					}
					$stage( 'inserted' );
					$want = array_map(
						static function ( $r ) {
							return (string) ( $r['id'] ?? '' );
						},
						$rows
					);
					if ( $this->s->table_ids() !== array_values( $want ) ) {
						$this->failed( 'verify_failed' );
						return 'retry';
					}
					$stage( 'verified' );
					$this->s->set_meta(
						EMCP_Tools_Change_Names::cutover(),
						array(
							'ts'      => time(),
							'cleanup' => 'pending',
						)
					);
					$this->s->set_flag( 'table' );
					if ( null !== $raw ) {
						// Only an upgrade (a ledger existed) asks admins to reconnect their clients.
						$this->s->set_meta( EMCP_Tools_Change_Names::reconnect_notice(), 1 );
					}
				}
			} finally {
				$this->s->unlock();
			}
			$this->s->delete_meta( EMCP_Tools_Change_Names::fallback() );
			$this->s->delete_meta( EMCP_Tools_Change_Names::cutover_error() );
			if ( ! $published_elsewhere ) {
				$stage( 'published' );
			}
			$this->cleanup();
			$stage( 'cleaned' );
			return 'done';
		} finally {
			$this->lease->release( EMCP_Tools_Change_Names::lease(), $owner );
		}
	}

	/**
	 * After publication, idempotent: whatever is left in the option moves to
	 * the recovery copy (appended when one exists) and is never imported.
	 */
	public function cleanup(): void {
		$raw = $this->s->option_raw();
		if ( null !== $raw ) {
			$copy = $this->s->get_meta( EMCP_Tools_Change_Names::recovery() );
			$copy = is_array( $copy ) ? array_values( $copy ) : array();
			$this->s->set_meta( EMCP_Tools_Change_Names::recovery(), array_merge( $copy, $this->s->option_rows( $raw ) ) );
			$this->s->set_meta( EMCP_Tools_Change_Names::recovery_ts(), time() );
			$this->s->option_remove();
		}
		$state = $this->s->get_meta( EMCP_Tools_Change_Names::cutover() );
		$state = is_array( $state ) ? $state : array( 'ts' => time() );
		if ( 'done' !== ( $state['cleanup'] ?? '' ) ) {
			$state['cleanup'] = 'done';
			$this->s->set_meta( EMCP_Tools_Change_Names::cutover(), $state );
		}
	}

	/**
	 * A copy that failed: remember it so the next attempt backs off.
	 *
	 * @param string $reason copy_failed | verify_failed.
	 */
	private function failed( string $reason ): void {
		$prev = $this->s->get_meta( EMCP_Tools_Change_Names::cutover_error() );
		$this->s->set_meta(
			EMCP_Tools_Change_Names::cutover_error(),
			array(
				'reason'   => $reason,
				'ts'       => time(),
				'attempts' => ( is_array( $prev ) ? (int) ( $prev['attempts'] ?? 0 ) : 0 ) + 1,
			)
		);
	}

	/**
	 * @param string $reason no_create | no_locks.
	 */
	private function fallback( string $reason ): void {
		$this->s->set_meta(
			EMCP_Tools_Change_Names::fallback(),
			array(
				'reason' => $reason,
				'ts'     => time(),
			)
		);
	}
}
