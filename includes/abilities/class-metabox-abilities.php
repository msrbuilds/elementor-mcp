<?php
/**
 * Meta Box (metabox.io) custom-fields MCP abilities.
 *
 * Two dispatcher tools — metabox-read / metabox-write — for reading and writing
 * Meta Box field VALUES on posts (and, via an object_type passthrough, any object
 * type a Meta Box extension registers), plus schema discovery of registered meta
 * boxes. The whole group only registers when Meta Box free core is active.
 *
 * Meta Box free core has NO field builder (fields are declared in PHP via the
 * rwmb_meta_boxes filter), so — unlike the ACF integration — this exposes NO
 * field-group / CPT / taxonomy authoring and NO delete op. Values are read and
 * written; field definitions are only ever read. Note Meta Box's field naming:
 * a field's `id` is the meta key and `name` is the human label.
 *
 * @package EMCP_Tools
 * @since   3.4.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers and implements the Meta Box abilities.
 *
 * @since 3.4.2
 */
class EMCP_Tools_Meta_Box_Abilities {

    /** Max recursion depth for value/field normalization. @since 3.4.2 */
    const MAX_DEPTH = 10;

    /** @since 3.4.2 @var string[] */
    private $ability_names = array();

    /** @since 3.4.2 @return string[] */
    public function get_ability_names(): array {
        return $this->ability_names;
    }

    /**
     * Whether Meta Box free core is active and exposes its registry API.
     *
     * @since 3.4.2
     * @return bool
     */
    public static function metabox_active(): bool {
        return defined( 'RWMB_VER' ) && function_exists( 'rwmb_get_registry' );
    }

    /**
     * Registers the Meta Box domain as two dispatcher tools.
     *
     * @since 3.4.2
     */
    public function register(): void {
        $this->register_read_dispatcher();
        $this->register_write_dispatcher();
    }

    /**
     * The operation map behind the two dispatchers.
     *
     * @since 3.4.2
     * @return array<string,array<string,mixed>>
     */
    private function operations(): array {
        return array(
            'list-field-groups' => array( 'mode' => 'read', 'run' => 'execute_list_field_groups', 'perm' => 'check_read_permission', 'desc' => __( 'List registered Meta Box field groups (id, title, object type, post types, field count). arguments: { search?, object_type? }.', 'emcp-tools' ) ),
            'get-field-group'   => array( 'mode' => 'read', 'run' => 'execute_get_field_group', 'perm' => 'check_read_permission', 'desc' => __( 'Get one Meta Box field group and its fields (id, label, type, settings). arguments: { id }.', 'emcp-tools' ) ),
            'get-fields'        => array( 'mode' => 'read', 'run' => 'execute_get_fields', 'perm' => 'check_fields_permission', 'desc' => __( 'Read Meta Box field values from a post or object. arguments: { post_id } or { object_type, object_id }; optional { fields, include_field_settings }.', 'emcp-tools' ) ),
            'update-fields'     => array( 'mode' => 'write', 'run' => 'execute_update_fields', 'perm' => 'check_fields_permission', 'desc' => __( 'Write Meta Box field values on a post or object. arguments: { post_id | object_type + object_id, fields: { id: value } }.', 'emcp-tools' ) ),
        );
    }

    private function register_read_dispatcher(): void {
        $this->ability_names[] = 'emcp-tools/metabox-read';
        emcp_tools_register_ability(
            'emcp-tools/metabox-read',
            array(
                'label'               => __( 'Meta Box Read', 'emcp-tools' ),
                'description'         => __( 'Read Meta Box (metabox.io) custom-field data: registered field groups, their field definitions, and field values on a post or object. Call with no "operation" to list the available read operations and their arguments, then call again with { operation, arguments }.', 'emcp-tools' ),
                'category'            => 'emcp-tools',
                'execute_callback'    => array( $this, 'run_metabox_read' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'operation' => array( 'type' => 'string', 'description' => __( 'The read operation to run. Omit to list operations. One of: list-field-groups, get-field-group, get-fields.', 'emcp-tools' ) ),
                        'arguments' => array( 'type' => 'object', 'description' => __( 'Arguments for the chosen operation (see the catalog returned when operation is omitted).', 'emcp-tools' ) ),
                    ),
                ),
                'meta'                => array(
                    'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
                    'show_in_rest' => true,
                ),
            )
        );
    }

    private function register_write_dispatcher(): void {
        $this->ability_names[] = 'emcp-tools/metabox-write';
        emcp_tools_register_ability(
            'emcp-tools/metabox-write',
            array(
                'label'               => __( 'Meta Box Write', 'emcp-tools' ),
                'description'         => __( 'Write Meta Box (metabox.io) custom-field values. Disabled by default — enable under EMCP Tools → Tools → Plugins → Meta Box. Call with no "operation" to list the available write operations and their arguments, then call again with { operation, arguments }.', 'emcp-tools' ),
                'category'            => 'emcp-tools',
                'execute_callback'    => array( $this, 'run_metabox_write' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'operation' => array( 'type' => 'string', 'description' => __( 'The write operation to run. Omit to list operations. One of: update-fields.', 'emcp-tools' ) ),
                        'arguments' => array( 'type' => 'object', 'description' => __( 'Arguments for the chosen operation (see the catalog returned when operation is omitted).', 'emcp-tools' ) ),
                    ),
                ),
                'meta'                => array(
                    'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
                    'show_in_rest' => true,
                ),
            )
        );
    }

    /** @since 3.4.2 */
    public function run_metabox_read( $input ) {
        return $this->dispatch( 'read', $input );
    }

    /** @since 3.4.2 */
    public function run_metabox_write( $input ) {
        return $this->dispatch( 'write', $input );
    }

    /**
     * Routes an operation to its executor after gating.
     *
     * @since 3.4.2
     * @param string $mode  'read' or 'write'.
     * @param mixed  $input Dispatcher input ({ operation, arguments }).
     * @return mixed
     */
    private function dispatch( string $mode, $input ) {
        $operation = isset( $input['operation'] ) ? str_replace( '_', '-', sanitize_key( (string) $input['operation'] ) ) : '';
        if ( '' === $operation ) {
            return $this->operations_catalog( $mode );
        }
        $ops = $this->operations();
        if ( ! isset( $ops[ $operation ] ) || $ops[ $operation ]['mode'] !== $mode ) {
            return new \WP_Error(
                'unknown_operation',
                sprintf(
                    /* translators: 1: mode (read/write), 2: operation name */
                    __( 'Unknown Meta Box %1$s operation "%2$s". Call metabox-%1$s with no operation to list the available operations.', 'emcp-tools' ),
                    $mode,
                    $operation
                )
            );
        }
        $op   = $ops[ $operation ];
        $args = ( isset( $input['arguments'] ) && is_array( $input['arguments'] ) ) ? $input['arguments'] : array();
        $perm = $op['perm'];
        if ( ! $this->$perm( $args ) ) {
            return new \WP_Error( 'forbidden', __( 'You do not have permission to perform this Meta Box operation.', 'emcp-tools' ) );
        }
        $run = $op['run'];
        return $this->$run( $args );
    }

    /**
     * Builds the discovery catalog for a mode.
     *
     * @since 3.4.2
     * @param string $mode 'read' or 'write'.
     * @return array
     */
    private function operations_catalog( string $mode ): array {
        $list = array();
        foreach ( $this->operations() as $name => $op ) {
            if ( $op['mode'] !== $mode ) {
                continue;
            }
            $list[] = array( 'operation' => $name, 'description' => $op['desc'] );
        }
        return array(
            'mode'       => $mode,
            'operations' => $list,
            'usage'      => sprintf(
                /* translators: %s: mode (read/write) */
                __( 'Call metabox-%s again with { "operation": "<name>", "arguments": { ... } }.', 'emcp-tools' ),
                $mode
            ),
        );
    }

    // ---------------------------------------------------------------
    // list-field-groups
    // ---------------------------------------------------------------

    /**
     * @since 3.4.2
     * @param array $input
     * @return array
     */
    public function execute_list_field_groups( $input ): array {
        $search    = isset( $input['search'] ) ? strtolower( sanitize_text_field( (string) $input['search'] ) ) : '';
        $ot_filter = isset( $input['object_type'] ) ? sanitize_key( (string) $input['object_type'] ) : '';

        $rows = array();
        foreach ( $this->all_meta_boxes() as $mb ) {
            $object_type = method_exists( $mb, 'get_object_type' ) ? (string) $mb->get_object_type() : 'post';
            if ( '' !== $ot_filter && $object_type !== $ot_filter ) {
                continue;
            }
            $id    = (string) ( $mb->id ?? '' );
            $title = (string) ( $mb->title ?? '' );
            if ( '' !== $search && false === strpos( strtolower( $title . ' ' . $id ), $search ) ) {
                continue;
            }
            $rows[] = array(
                'id'          => $id,
                'title'       => $title,
                'object_type' => $object_type,
                'post_types'  => array_values( (array) ( $mb->post_types ?? array() ) ),
                'field_count' => count( (array) ( $mb->fields ?? array() ) ),
            );
        }

        return array( 'field_groups' => $rows, 'total' => count( $rows ) );
    }

    // ---------------------------------------------------------------
    // get-field-group
    // ---------------------------------------------------------------

    /**
     * @since 3.4.2
     * @param array $input
     * @return array|\WP_Error
     */
    public function execute_get_field_group( $input ) {
        $id = sanitize_text_field( (string) ( $input['id'] ?? '' ) );
        if ( '' === $id ) {
            return new \WP_Error( 'missing_params', __( 'A field group "id" is required.', 'emcp-tools' ) );
        }
        $found = null;
        foreach ( $this->all_meta_boxes() as $mb ) {
            if ( (string) ( $mb->id ?? '' ) === $id ) {
                $found = $mb;
                break;
            }
        }
        if ( ! $found ) {
            return new \WP_Error( 'group_not_found', __( 'Field group not found.', 'emcp-tools' ) );
        }
        $object_type = method_exists( $found, 'get_object_type' ) ? (string) $found->get_object_type() : 'post';
        return array(
            'id'          => $id,
            'title'       => (string) ( $found->title ?? '' ),
            'object_type' => $object_type,
            'post_types'  => array_values( (array) ( $found->post_types ?? array() ) ),
            'fields'      => array_map( array( $this, 'format_field' ), array_values( (array) ( $found->fields ?? array() ) ) ),
        );
    }

    /**
     * Compact view of one Meta Box field definition (nested `fields` included).
     * Meta Box stores the meta key in `id` and the human label in `name`.
     *
     * @since 3.4.2
     * @param mixed $field Field config array.
     * @param int   $depth Recursion guard.
     * @return array
     */
    private function format_field( $field, int $depth = 0 ): array {
        $field = (array) $field;
        $out   = array(
            'id'    => (string) ( $field['id'] ?? '' ),
            'label' => (string) ( $field['name'] ?? '' ),
            'type'  => (string) ( $field['type'] ?? '' ),
        );
        foreach ( array( 'desc', 'std', 'multiple', 'clone', 'required', 'options', 'min', 'max', 'post_type', 'taxonomy' ) as $setting ) {
            if ( isset( $field[ $setting ] ) && '' !== $field[ $setting ] && array() !== $field[ $setting ] ) {
                $out[ $setting ] = $field[ $setting ];
            }
        }
        if ( $depth < self::MAX_DEPTH && ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
            $out['fields'] = array();
            foreach ( $field['fields'] as $sub ) {
                $out['fields'][] = $this->format_field( $sub, $depth + 1 );
            }
        }
        return $out;
    }

    /**
     * All registered meta boxes (empty when Meta Box is inactive).
     *
     * @since 3.4.2
     * @return array
     */
    private function all_meta_boxes(): array {
        if ( ! function_exists( 'rwmb_get_registry' ) ) {
            return array();
        }
        $registry = rwmb_get_registry( 'meta_box' );
        return method_exists( $registry, 'all' ) ? (array) $registry->all() : array();
    }

    // ---------------------------------------------------------------
    // get-fields
    // ---------------------------------------------------------------

    /**
     * @since 3.4.2
     * @param array $input
     * @return array|\WP_Error
     */
    public function execute_get_fields( $input ) {
        $target = $this->resolve_target( $input );
        if ( is_wp_error( $target ) ) {
            return $target;
        }
        $fields = $this->applicable_fields( $target['object_type'], $target['object_id'] );
        $only   = array();
        if ( ! empty( $input['fields'] ) && is_array( $input['fields'] ) ) {
            $only = array_map( 'strval', $input['fields'] );
        }
        $with_settings = ! empty( $input['include_field_settings'] );

        $out = array();
        foreach ( $fields as $id => $field ) {
            if ( $only && ! in_array( (string) $id, $only, true ) ) {
                continue;
            }
            $value = function_exists( 'rwmb_meta' )
                ? rwmb_meta( $id, array( 'object_type' => $target['object_type'] ), $target['object_id'] )
                : null;
            $value = $this->normalize_value( $value );
            if ( $with_settings ) {
                $out[ $id ] = array(
                    'type'  => (string) ( $field['type'] ?? '' ),
                    'label' => (string) ( $field['name'] ?? '' ),
                    'value' => $value,
                );
            } else {
                $out[ $id ] = $value;
            }
        }

        return array( 'target' => $target, 'fields' => $out );
    }

    /**
     * Resolves the target: exactly one of { post_id } or { object_type, object_id }.
     *
     * @since 3.4.2
     * @param array $input
     * @return array|\WP_Error {object_type, object_id}
     */
    private function resolve_target( $input ) {
        $post_id     = absint( $input['post_id'] ?? 0 );
        $object_type = isset( $input['object_type'] ) ? sanitize_key( (string) $input['object_type'] ) : '';
        $object_id   = $input['object_id'] ?? null;

        if ( $post_id && '' !== $object_type ) {
            return new \WP_Error( 'invalid_target', __( 'Pass either "post_id" or "object_type"+"object_id", not both.', 'emcp-tools' ) );
        }
        // Normalize { object_type:'post', object_id:N } onto the post_id path so
        // it gets the same 404 check and edit_post gating as { post_id:N }.
        if ( ! $post_id && 'post' === $object_type && null !== $object_id && '' !== $object_id ) {
            $post_id     = absint( $object_id );
            $object_type = '';
        }
        if ( $post_id ) {
            if ( ! get_post( $post_id ) ) {
                return new \WP_Error( 'post_not_found', __( 'Post not found.', 'emcp-tools' ) );
            }
            return array( 'object_type' => 'post', 'object_id' => $post_id );
        }
        if ( '' !== $object_type ) {
            if ( null === $object_id || '' === $object_id ) {
                return new \WP_Error( 'invalid_target', __( 'An "object_id" is required with "object_type".', 'emcp-tools' ) );
            }
            $object_id = is_numeric( $object_id ) ? absint( $object_id ) : sanitize_text_field( (string) $object_id );
            return array( 'object_type' => $object_type, 'object_id' => $object_id );
        }
        return new \WP_Error( 'invalid_target', __( 'A target is required: pass "post_id" or "object_type"+"object_id".', 'emcp-tools' ) );
    }

    /**
     * The fields applicable to a target, keyed by field id. For posts, only meta
     * boxes whose post_types include the post's type are considered.
     *
     * @since 3.4.2
     * @param string     $object_type
     * @param int|string $object_id
     * @return array<string,array>
     */
    private function applicable_fields( string $object_type, $object_id ): array {
        $out = array();
        if ( ! function_exists( 'rwmb_get_registry' ) ) {
            return $out;
        }
        $registry = rwmb_get_registry( 'meta_box' );
        $boxes    = method_exists( $registry, 'get_by' )
            ? (array) $registry->get_by( array( 'object_type' => $object_type ) )
            : ( method_exists( $registry, 'all' ) ? (array) $registry->all() : array() );

        $post_type = '';
        if ( 'post' === $object_type && is_numeric( $object_id ) ) {
            $post      = get_post( (int) $object_id );
            $post_type = $post ? (string) $post->post_type : '';
        }

        foreach ( $boxes as $mb ) {
            if ( method_exists( $mb, 'get_object_type' ) && (string) $mb->get_object_type() !== $object_type ) {
                continue;
            }
            if ( 'post' === $object_type && '' !== $post_type ) {
                $pts = (array) ( $mb->post_types ?? array() );
                if ( $pts && ! in_array( $post_type, $pts, true ) ) {
                    continue;
                }
            }
            foreach ( (array) ( $mb->fields ?? array() ) as $field ) {
                $field = (array) $field;
                $id    = (string) ( $field['id'] ?? '' );
                if ( '' === $id || isset( $out[ $id ] ) ) {
                    continue;
                }
                $out[ $id ] = $field;
            }
        }
        return $out;
    }

    /**
     * Makes a Meta Box value JSON-friendly and compact: WP objects and MB
     * image/file arrays become small summaries; nesting is depth-capped.
     *
     * @since 3.4.2
     * @param mixed $value
     * @param int   $depth
     * @return mixed
     */
    private function normalize_value( $value, int $depth = 0 ) {
        if ( $depth >= self::MAX_DEPTH ) {
            return is_scalar( $value ) || null === $value ? $value : '[max depth reached]';
        }
        if ( $value instanceof \WP_Post ) {
            return array( 'id' => (int) $value->ID, 'title' => (string) $value->post_title, 'post_type' => (string) $value->post_type );
        }
        if ( class_exists( '\WP_Term' ) && $value instanceof \WP_Term ) {
            return array( 'id' => (int) $value->term_id, 'name' => (string) $value->name, 'taxonomy' => (string) $value->taxonomy );
        }
        if ( class_exists( '\WP_User' ) && $value instanceof \WP_User ) {
            return array( 'id' => (int) $value->ID, 'display_name' => (string) $value->display_name );
        }
        if ( is_object( $value ) ) {
            $value = get_object_vars( $value );
        }
        if ( is_array( $value ) ) {
            // Meta Box single image/file value: assoc array with ID + url (+ path/full_url/mime).
            if ( isset( $value['ID'], $value['url'] ) && ( isset( $value['full_url'] ) || isset( $value['path'] ) || isset( $value['mime_type'] ) ) ) {
                return array(
                    'id'    => (int) $value['ID'],
                    'url'   => (string) $value['url'],
                    'alt'   => (string) ( $value['alt'] ?? '' ),
                    'title' => (string) ( $value['title'] ?? '' ),
                );
            }
            $out = array();
            foreach ( $value as $k => $v ) {
                $out[ $k ] = $this->normalize_value( $v, $depth + 1 );
            }
            return $out;
        }
        return $value;
    }

    // ---------------------------------------------------------------
    // update-fields
    // ---------------------------------------------------------------

    /**
     * @since 3.4.2
     * @param array $input
     * @return array|\WP_Error
     */
    public function execute_update_fields( $input ) {
        $target = $this->resolve_target( $input );
        if ( is_wp_error( $target ) ) {
            return $target;
        }
        $fields = $input['fields'] ?? null;
        if ( ! is_array( $fields ) || array() === $fields ) {
            return new \WP_Error( 'missing_params', __( 'A non-empty "fields" map is required.', 'emcp-tools' ) );
        }

        $known   = $this->applicable_fields( $target['object_type'], $target['object_id'] );
        $updated = array();
        $skipped = array();
        $values  = array();

        foreach ( $fields as $id => $value ) {
            $id = (string) $id;
            if ( ! isset( $known[ $id ] ) ) {
                $skipped[] = array( 'field' => $id, 'reason' => 'field_not_found' );
                continue;
            }
            if ( function_exists( 'rwmb_set_meta' ) ) {
                rwmb_set_meta( $target['object_id'], $id, $value, array( 'object_type' => $target['object_type'] ) );
            }
            // rwmb_set_meta() returns void, so confirm by re-reading.
            $updated[]      = $id;
            $values[ $id ]  = $this->normalize_value(
                function_exists( 'rwmb_meta' ) ? rwmb_meta( $id, array( 'object_type' => $target['object_type'] ), $target['object_id'] ) : null
            );
        }

        return array(
            'target'  => $target,
            'updated' => $updated,
            'skipped' => $skipped,
            'values'  => $values,
        );
    }

    // ---------------------------------------------------------------
    // Permission callbacks
    // ---------------------------------------------------------------

    /** @since 3.4.2 @return bool */
    public function check_read_permission(): bool {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Field-value permission: edit_post on a target post; manage_options for
     * non-post object types (site-wide data).
     *
     * @since 3.4.2
     * @param array|null $input
     * @return bool
     */
    public function check_fields_permission( $input = null ): bool {
        $object_type = isset( $input['object_type'] ) ? sanitize_key( (string) $input['object_type'] ) : '';
        if ( '' !== $object_type && 'post' !== $object_type ) {
            return current_user_can( 'manage_options' );
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            return false;
        }
        // A post target can arrive as either post_id or object_type=post+object_id;
        // gate edit_post on whichever one actually names the target post.
        $post_id = absint( $input['post_id'] ?? 0 );
        if ( ! $post_id && 'post' === $object_type ) {
            $post_id = absint( $input['object_id'] ?? 0 );
        }
        return ! $post_id || current_user_can( 'edit_post', $post_id );
    }
}
