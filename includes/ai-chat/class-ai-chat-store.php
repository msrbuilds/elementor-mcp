<?php
/**
 * AI Chat conversation store — per-user saved conversations.
 *
 * Each conversation is a private `emcp_ai_conversation` post owned by the user:
 * post_title = title, post_content = JSON of the neutral message array, with the
 * provider/model recorded in meta. Private (no UI, not in REST), with ownership
 * enforced on every read/write.
 *
 * @package EMCP_Tools
 * @since   2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Saved-conversation CRUD.
 *
 * @since 2.2.0
 */
class EMCP_Tools_AI_Chat_Store {

	const CPT       = 'emcp_ai_conversation';
	const META_PROV = '_emcp_ai_provider';
	const META_MODL = '_emcp_ai_model';

	/**
	 * @since 2.2.0
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::CPT,
			array(
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'supports'            => array( 'title', 'editor', 'author' ),
			)
		);
	}

	/**
	 * Lists the current/given user's conversations, newest first.
	 *
	 * @since 2.2.0
	 * @param int|null $user_id User.
	 * @return array<int,array> [{ id, title, provider, model, updated }]
	 */
	public static function list_conversations( ?int $user_id = null ): array {
		$user_id = $user_id ?? get_current_user_id();
		$posts   = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'private',
				'author'         => $user_id,
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		$out = array();
		foreach ( $posts as $p ) {
			$out[] = array(
				'id'       => $p->ID,
				'title'    => $p->post_title,
				'provider' => (string) get_post_meta( $p->ID, self::META_PROV, true ),
				'model'    => (string) get_post_meta( $p->ID, self::META_MODL, true ),
				'updated'  => get_post_modified_time( 'c', true, $p ),
			);
		}
		return $out;
	}

	/**
	 * Loads a single conversation (ownership-checked).
	 *
	 * @since 2.2.0
	 * @param int      $id      Conversation post ID.
	 * @param int|null $user_id User.
	 * @return array|null { id, title, provider, model, messages }
	 */
	public static function get( int $id, ?int $user_id = null ): ?array {
		$user_id = $user_id ?? get_current_user_id();
		$post    = get_post( $id );
		if ( ! $post || self::CPT !== $post->post_type || (int) $post->post_author !== $user_id ) {
			return null;
		}
		$messages = json_decode( $post->post_content, true );
		return array(
			'id'       => $post->ID,
			'title'    => $post->post_title,
			'provider' => (string) get_post_meta( $post->ID, self::META_PROV, true ),
			'model'    => (string) get_post_meta( $post->ID, self::META_MODL, true ),
			'messages' => is_array( $messages ) ? $messages : array(),
		);
	}

	/**
	 * Creates or updates a conversation (ownership-checked on update).
	 *
	 * @since 2.2.0
	 * @param int|null $id       Existing id, or null to create.
	 * @param string   $title    Title.
	 * @param string   $provider Provider id.
	 * @param string   $model    Model id.
	 * @param array    $messages Neutral message array.
	 * @param int|null $user_id  User.
	 * @return int|\WP_Error The conversation id.
	 */
	public static function save( ?int $id, string $title, string $provider, string $model, array $messages, ?int $user_id = null ) {
		$user_id = $user_id ?? get_current_user_id();
		$title   = '' !== trim( $title ) ? wp_strip_all_tags( $title ) : __( 'Untitled chat', 'emcp-tools' );
		if ( mb_strlen( $title ) > 120 ) {
			$title = mb_substr( $title, 0, 117 ) . '…';
		}
		$content = wp_json_encode( $messages );
		if ( false === $content ) {
			return new \WP_Error( 'encode_failed', __( 'Could not serialize the conversation.', 'emcp-tools' ) );
		}

		$data = array(
			'post_type'    => self::CPT,
			'post_status'  => 'private',
			'post_author'  => $user_id,
			'post_title'   => $title,
			'post_content' => wp_slash( $content ),
		);

		if ( $id ) {
			$existing = get_post( $id );
			if ( ! $existing || self::CPT !== $existing->post_type || (int) $existing->post_author !== $user_id ) {
				return new \WP_Error( 'not_found', __( 'Conversation not found.', 'emcp-tools' ), array( 'status' => 404 ) );
			}
			$data['ID'] = $id;
			$result     = wp_update_post( $data, true );
		} else {
			$result = wp_insert_post( $data, true );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		update_post_meta( $result, self::META_PROV, sanitize_key( $provider ) );
		update_post_meta( $result, self::META_MODL, sanitize_text_field( $model ) );
		return (int) $result;
	}

	/**
	 * Deletes a conversation (ownership-checked).
	 *
	 * @since 2.2.0
	 * @param int      $id      Conversation id.
	 * @param int|null $user_id User.
	 * @return bool
	 */
	public static function delete( int $id, ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		$post    = get_post( $id );
		if ( ! $post || self::CPT !== $post->post_type || (int) $post->post_author !== $user_id ) {
			return false;
		}
		return (bool) wp_delete_post( $id, true );
	}

	/**
	 * Deletes every saved conversation (uninstall).
	 *
	 * @since 2.2.0
	 */
	public static function uninstall_cleanup(): void {
		$ids = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}
}
