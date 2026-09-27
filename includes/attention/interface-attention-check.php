<?php
/**
 * One "Needs your attention" check (spec 9.7).
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface EMCP_Tools_Attention_Check {

	/** Stable id, used for dismissal. */
	public function id(): string;

	/** Whether the item shows now. */
	public function applies(): bool;

	/**
	 * A short fingerprint of the underlying condition (a count, an id): a
	 * dismissed item returns when this changes.
	 */
	public function state(): string;

	/**
	 * The item.
	 *
	 * @return array{id: string, icon: string, title: string, body: string, action_label: string, action_url: string, severity: string}
	 */
	public function item(): array;
}
