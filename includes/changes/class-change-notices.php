<?php
/**
 * Ledger notices (spec 9.1 step 7): an AI connection still running the
 * previous version (it keeps writing the old option after cleanup), and the
 * one-time "reconnect every client" notice after the upgrade.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Change ledger notices.
 */
final class EMCP_Tools_Change_Notices {

	private static function s(): EMCP_Tools_Change_Storage {
		return EMCP_Tools_Change_Log::store()->storage();
	}

	/** Only after cleanup does a reappearing option mean an old-code writer. */
	public static function stray_option(): bool {
		$s     = self::s();
		$state = $s->get_meta( EMCP_Tools_Change_Names::cutover() );
		return is_array( $state ) && 'done' === ( $state['cleanup'] ?? '' ) && null !== $s->option_raw();
	}
}
