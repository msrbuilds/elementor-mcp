<?php
/**
 * History retention (spec 9.1): table rows older than the chosen number of
 * days are deleted daily, in batches, with their before-image blobs. Also
 * expires the 30-day pre-migration recovery copy. The option store keeps its
 * row cap instead.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Change retention.
 */
final class EMCP_Tools_Change_Retention {

	const ALLOWED       = array( 30, 90, 180, 365 );
	const DEFAULT_DAYS  = 90;
	const RECOVERY_DAYS = 30;
	const BATCH         = 500;
	const CRON_HOOK     = 'emcp_tools_changes_prune';

	/** Daily prune. */
	public static function init(): void {
		add_action(
			self::CRON_HOOK,
			static function () {
				self::prune();
			}
		);
		add_action( 'init', array( __CLASS__, 'schedule' ), 20 );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * @param mixed $v Submitted days.
	 */
	public static function sanitize_days( $v ): int {
		$v = (int) $v;
		return in_array( $v, self::ALLOWED, true ) ? $v : self::DEFAULT_DAYS;
	}

	public static function days(): int {
		return self::sanitize_days( get_option( EMCP_Tools_Change_Names::retention(), self::DEFAULT_DAYS ) );
	}

	/**
	 * Delete rows older than the retention and expire the recovery copy.
	 *
	 * @param int $now   Clock (tests); 0 for time().
	 * @param int $batch Rows per batch.
	 * @return array{deleted:int, blobs:int}
	 */
	public static function prune( int $now = 0, int $batch = self::BATCH ): array {
		$now   = $now > 0 ? $now : time();
		$store = EMCP_Tools_Change_Log::store();
		$s     = $store->storage();

		$copy_ts = (int) $s->get_meta( EMCP_Tools_Change_Names::recovery_ts() );
		if ( $copy_ts > 0 && $now - $copy_ts > self::RECOVERY_DAYS * DAY_IN_SECONDS ) {
			$s->delete_meta( EMCP_Tools_Change_Names::recovery() );
			$s->delete_meta( EMCP_Tools_Change_Names::recovery_ts() );
		}

		$out = array(
			'deleted' => 0,
			'blobs'   => 0,
		);
		if ( ! $store->is_table() ) {
			return $out;
		}
		$cutoff = $now - self::days() * DAY_IN_SECONDS;
		$batch  = max( 1, $batch );
		do {
			$rows    = $s->table_older_than( $cutoff, $batch );
			$removed = array();
			foreach ( $rows as $r ) {
				if ( null !== $s->table_delete( $r['id'] ) ) {
					$removed[] = $r;
				}
			}
			EMCP_Tools_Change_Log::forget_blobs( $removed );
			foreach ( $removed as $r ) {
				++$out['deleted'];
				$out['blobs'] += empty( $r['rollback']['blob_id'] ) ? 0 : 1;
			}
		} while ( count( $rows ) === $batch && $removed );
		return $out;
	}
}
