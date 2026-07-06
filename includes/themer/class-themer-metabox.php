<?php
/**
 * Condition metabox for the Themer template edit screen.
 *
 * Renders under the editor in both Gutenberg and classic. Free shows a Type select
 * + a broad scope selector; Pro (via the emcp_themer_selectors filter carrying
 * granular keys + a marker) adds Include/Exclude rows + priority. Saving writes the
 * type + conditions meta (the index rebuilds on save_post).
 *
 * @package EMCP_Tools
 * @since   3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.2.0
 */
class EMCP_Tools_Themer_Metabox {

	const NONCE = 'emcp_themer_conditions_nonce';

	/** Wire admin hooks. */
	public function init(): void {
		add_action( 'add_meta_boxes_' . EMCP_Tools_Themer_CPT::POST_TYPE, array( $this, 'add' ) );
		add_action( 'save_post_' . EMCP_Tools_Themer_CPT::POST_TYPE, array( $this, 'save' ), 10, 2 );
	}

	/** Register the metabox. */
	public function add(): void {
		add_meta_box(
			'emcp-themer-conditions',
			__( 'EMCP Themer — Display Conditions', 'emcp-tools' ),
			array( $this, 'render' ),
			EMCP_Tools_Themer_CPT::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the metabox.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render( $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );
		$type  = (string) get_post_meta( $post->ID, '_emcp_themer_type', true );
		$cond  = get_post_meta( $post->ID, '_emcp_themer_conditions', true );
		$cond  = is_array( $cond ) ? $cond : array();
		$scope = isset( $cond['include'][0]['object'] ) ? (string) $cond['include'][0]['object'] : '';

		$pro = in_array( 'post', (array) apply_filters( 'emcp_themer_selectors', array() ), true );

		echo '<p><label for="emcp-themer-type"><strong>' . esc_html__( 'Template type', 'emcp-tools' ) . '</strong></label><br>';
		echo '<select id="emcp-themer-type" name="emcp_themer_type">';
		foreach ( EMCP_Tools_Themer_CPT::TYPES as $t ) {
			printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $t ), selected( $type, $t, false ) );
		}
		echo '</select></p>';

		echo '<p><label for="emcp-themer-scope"><strong>' . esc_html__( 'Show on', 'emcp-tools' ) . '</strong></label><br>';
		echo '<select id="emcp-themer-scope" name="emcp_themer_scope">';
		foreach ( $this->scope_choices() as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $scope, $value, false ), esc_html( $label ) );
		}
		echo '</select></p>';

		if ( ! $pro ) {
			echo '<p class="description">' . esc_html__( 'Free templates show site-wide by type. Upgrade to EMCP Pro for per-post, per-category, per-author targeting, Exclude rules, priority, and unlimited templates per type.', 'emcp-tools' ) . '</p>';
		}

		/**
		 * Fires inside the metabox after the free fields so Pro can render granular
		 * Include/Exclude rows + priority.
		 *
		 * @param WP_Post $post Current template.
		 * @param array   $cond Current conditions.
		 */
		do_action( 'emcp_themer_metabox_pro_fields', $post, $cond );
	}

	/**
	 * Broad scope choices (free). Pro augments targeting via its own rows.
	 *
	 * @return array<string,string>
	 */
	private function scope_choices(): array {
		$choices = array(
			'entire-site'  => __( 'Entire site', 'emcp-tools' ),
			'all-singular' => __( 'All singular (posts/pages)', 'emcp-tools' ),
			'front-page'   => __( 'Front page', 'emcp-tools' ),
			'all-archives' => __( 'All archives', 'emcp-tools' ),
		);
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			/* translators: %s: post type label */
			$choices[ 'post-type:' . $pt->name ] = sprintf( __( 'All %s (singular)', 'emcp-tools' ), $pt->label );
			/* translators: %s: post type label */
			$choices[ 'post-type-archive:' . $pt->name ] = sprintf( __( '%s archive', 'emcp-tools' ), $pt->label );
		}
		return $choices;
	}

	/**
	 * Persist type + conditions.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post.
	 */
	public function save( $post_id, $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['emcp_themer_type'] ) ) {
			$type = sanitize_text_field( wp_unslash( $_POST['emcp_themer_type'] ) );
			if ( in_array( $type, EMCP_Tools_Themer_CPT::TYPES, true ) ) {
				update_post_meta( $post_id, '_emcp_themer_type', $type );
			}
		}

		// Free path: the single scope select becomes the sole include rule. Pro hooks
		// this action to write richer Include/Exclude rows + priority instead.
		if ( has_action( 'emcp_themer_metabox_save' ) ) {
			/**
			 * Lets Pro persist granular conditions from its own metabox fields.
			 *
			 * @param int $post_id Template id.
			 */
			do_action( 'emcp_themer_metabox_save', $post_id );
		} elseif ( isset( $_POST['emcp_themer_scope'] ) ) {
			$scope   = sanitize_text_field( wp_unslash( $_POST['emcp_themer_scope'] ) );
			$include = '' !== $scope ? array( array( 'object' => $scope ) ) : array();
			update_post_meta(
				$post_id,
				'_emcp_themer_conditions',
				array( 'include' => $include, 'exclude' => array(), 'priority' => 0 )
			);
		}
	}
}
