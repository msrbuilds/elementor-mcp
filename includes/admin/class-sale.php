<?php
/**
 * Time-boxed sale (Black Friday / Cyber Monday 2026).
 *
 * The upgrade banner and the admin announcement bar show the sale between
 * START and END and fall back to their regular content on their own after
 * END, so nothing needs reverting by hand. Dates and discounts match the
 * website's src/data/bfcm.ts.
 *
 * @package EMCP_Tools
 * @since   3.22.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'EMCP_Tools_Sale' ) ) {

	class EMCP_Tools_Sale {

		/** 2026-11-23T00:00:00Z. */
		const START = 1795392000;

		/** 2026-12-01T00:00:00Z, when the coupons expire. */
		const END = 1796083200;

		const URL = 'https://emcptools.com/bfcm/';

		const PLUGIN_PERCENT = 40;

		const CLOUD_PERCENT = 30;

		/**
		 * Whether the sale is running.
		 *
		 * @param int|null $now Unix time; defaults to now.
		 */
		public static function active( ?int $now = null ): bool {
			$now = null === $now ? time() : $now;
			return $now >= self::START && $now < self::END;
		}
	}
}
