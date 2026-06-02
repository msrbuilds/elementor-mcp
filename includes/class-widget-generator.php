<?php
/**
 * Widget Generator — compiles a structured spec into a Widget_Base subclass.
 *
 * This is the ONLY component that emits PHP. The AI never authors PHP: it
 * submits a JSON spec (meta + sections[] of controls + an HTML template using
 * `{{control}}` placeholders and simple `{{#if}}`/`{{#each}}`). Every value that
 * reaches the generated PHP is either a fixed token from this class or is
 * escaped/whitelisted by the control's declared type, so the output is
 * deterministic and safe. The result is structurally lint-checked with
 * `token_get_all( …, TOKEN_PARSE )` before it is ever written to disk.
 *
 * @package Elementor_MCP
 * @since   1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compiles widget specs into Elementor Widget_Base PHP.
 *
 * @since 1.9.0
 */
class Elementor_MCP_Widget_Generator {

	/**
	 * Supported control types mapped to their Controls_Manager constant + notes.
	 * The keys are the `type` values the AI uses in a spec.
	 *
	 * @since 1.9.0
	 *
	 * @return array<string, array{const: string, value: bool, group: string, desc: string}>
	 */
	public static function control_types(): array {
		return array(
			// Text & content.
			'text'          => array( 'const' => 'TEXT', 'value' => true, 'group' => 'content', 'desc' => 'Single-line text. Args: placeholder, input_type (text|email|url|tel|number).' ),
			'textarea'      => array( 'const' => 'TEXTAREA', 'value' => true, 'group' => 'content', 'desc' => 'Multi-line text. Args: rows, placeholder, html:true (allow safe HTML).' ),
			'wysiwyg'       => array( 'const' => 'WYSIWYG', 'value' => true, 'group' => 'content', 'desc' => 'Rich-text editor (rendered with wp_kses_post).' ),
			'number'        => array( 'const' => 'NUMBER', 'value' => true, 'group' => 'content', 'desc' => 'Numeric input. Args: min, max, step, placeholder.' ),
			'code'          => array( 'const' => 'CODE', 'value' => true, 'group' => 'content', 'desc' => 'Code field. Args: language (html|css|javascript|json), rows. Rendered with wp_kses_post.' ),
			'hidden'        => array( 'const' => 'HIDDEN', 'value' => true, 'group' => 'content', 'desc' => 'Stored value with no panel UI (flags/config). Scalar string.' ),
			'date_time'     => array( 'const' => 'DATE_TIME', 'value' => true, 'group' => 'content', 'desc' => 'Date/time picker (MySQL "Y-m-d H:i" string). Format in your markup; output via {{name}}.' ),
			// Choices.
			'select'        => array( 'const' => 'SELECT', 'value' => true, 'group' => 'choice', 'desc' => 'Dropdown. Requires options:[{value,label}]. Add a {value:"",label:"Default"} option for "none".' ),
			'select2'       => array( 'const' => 'SELECT2', 'value' => true, 'group' => 'choice', 'desc' => 'Searchable dropdown. Requires options. Set multiple:true to store an array of values (loop with {{#each}}).' ),
			'choose'        => array( 'const' => 'CHOOSE', 'value' => true, 'group' => 'choice', 'desc' => 'Icon button group (e.g. alignment). Requires options; give each an icon (eicon) or alignment values get defaults. toggle:true allows deselect.' ),
			'visual_choice' => array( 'const' => 'VISUAL_CHOICE', 'value' => true, 'group' => 'choice', 'desc' => 'Image button group. Options need {value,label,image:URL}. Args: columns.' ),
			'switcher'      => array( 'const' => 'SWITCHER', 'value' => true, 'group' => 'choice', 'desc' => 'On/off toggle (value "yes"/""). Use in {{#if}}. Args: label_on, label_off, return_value.' ),
			'url'           => array( 'const' => 'URL', 'value' => true, 'group' => 'choice', 'desc' => 'Link picker (url + new-tab + nofollow). In templates use href="{{name}}" — outputs the escaped URL.' ),
			// Visual / media.
			'media'         => array( 'const' => 'MEDIA', 'value' => true, 'group' => 'visual', 'desc' => 'Image/media. Use in src="{{name}}" — outputs the escaped image URL.' ),
			'image'         => array( 'const' => 'MEDIA', 'value' => true, 'group' => 'visual', 'desc' => 'Alias of media.' ),
			'icon'          => array( 'const' => 'ICONS', 'value' => true, 'group' => 'visual', 'desc' => 'Icon picker. {{name}} renders the icon markup (do not wrap in <i>).' ),
			'gallery'       => array( 'const' => 'GALLERY', 'value' => true, 'group' => 'visual', 'desc' => 'Image gallery (array of {id,url}). Loop with {{#each name}} and use {{url}} / {{id}} inside.' ),
			// Style.
			'color'         => array( 'const' => 'COLOR', 'value' => true, 'group' => 'style', 'desc' => 'Color picker. Args: alpha:false to disable transparency. Best used via selectors.' ),
			'font'          => array( 'const' => 'FONT', 'value' => true, 'group' => 'style', 'desc' => 'Font-family picker (outputs a full CSS family string). Prefer the typography group control, which also enqueues the font.' ),
			'slider'        => array( 'const' => 'SLIDER', 'value' => true, 'group' => 'measure', 'desc' => 'Slider with units. Args: min, max, step, units:["px","%","em"]. Use via selectors ({{SIZE}}{{UNIT}}); {{name}} outputs the size.' ),
			'dimensions'    => array( 'const' => 'DIMENSIONS', 'value' => false, 'group' => 'measure', 'desc' => 'Top/right/bottom/left box (margin/padding/radius). Use via selectors with {{TOP}}{{UNIT}} etc. Args: units.' ),
			// Group controls (style bundles injected into a CSS selector — set "selector").
			'typography'    => array( 'const' => '', 'value' => false, 'group' => 'group', 'group_class' => 'Group_Control_Typography', 'desc' => 'Full typography (font, size, weight, line-height, spacing) on a selector. Auto-enqueues fonts. Set "selector".' ),
			'border'        => array( 'const' => '', 'value' => false, 'group' => 'group', 'group_class' => 'Group_Control_Border', 'desc' => 'Border width/style/color on a selector. Set "selector".' ),
			'box_shadow'    => array( 'const' => '', 'value' => false, 'group' => 'group', 'group_class' => 'Group_Control_Box_Shadow', 'desc' => 'Box shadow on a selector. Set "selector".' ),
			'text_shadow'   => array( 'const' => '', 'value' => false, 'group' => 'group', 'group_class' => 'Group_Control_Text_Shadow', 'desc' => 'Text shadow on a selector. Set "selector".' ),
			'text_stroke'   => array( 'const' => '', 'value' => false, 'group' => 'group', 'group_class' => 'Group_Control_Text_Stroke', 'desc' => 'Text stroke on a selector. Set "selector".' ),
			'background'    => array( 'const' => '', 'value' => false, 'group' => 'group', 'group_class' => 'Group_Control_Background', 'desc' => 'Background (color/gradient/image/video) on a selector. Args: types:["classic","gradient"]. Set "selector".' ),
			'css_filter'    => array( 'const' => '', 'value' => false, 'group' => 'group', 'group_class' => 'Group_Control_Css_Filter', 'desc' => 'CSS filters (blur/brightness/contrast/etc.) on a selector. Set "selector".' ),
			// Animations.
			'animation'       => array( 'const' => 'ANIMATION', 'value' => true, 'group' => 'animation', 'desc' => 'Entrance animation (Animate.css). Usually applied via prefix_class.' ),
			'hover_animation' => array( 'const' => 'HOVER_ANIMATION', 'value' => true, 'group' => 'animation', 'desc' => 'Hover animation (Hover.css). Output as a class with the elementor-animation- prefix.' ),
			// Panel UI (no value, no front-end output).
			'heading'       => array( 'const' => 'HEADING', 'value' => false, 'group' => 'ui', 'desc' => 'A label inside the panel (no value).' ),
			'divider'       => array( 'const' => 'DIVIDER', 'value' => false, 'group' => 'ui', 'desc' => 'A horizontal rule inside the panel (no value).' ),
			'raw_html'      => array( 'const' => 'RAW_HTML', 'value' => false, 'group' => 'ui', 'desc' => 'Static HTML note inside the panel ("html" arg; no value).' ),
			'alert'         => array( 'const' => 'ALERT', 'value' => false, 'group' => 'ui', 'desc' => 'Colored note inside the panel. Args: alert_type (info|success|warning|danger), heading, content.' ),
			// Repeater.
			'repeater'      => array( 'const' => 'REPEATER', 'value' => false, 'group' => 'special', 'desc' => 'Repeatable rows. Requires fields:[...] (one level, no nested repeaters). Loop with {{#each name}}.' ),
		);
	}

	// -------------------------------------------------------------------------
	// Validation
	// -------------------------------------------------------------------------

	/**
	 * Validates a spec's structure. Returns true or a descriptive WP_Error.
	 *
	 * @since 1.9.0
	 *
	 * @param array $spec The widget spec.
	 * @return true|WP_Error
	 */
	public static function validate_spec( $spec ) {
		if ( ! is_array( $spec ) ) {
			return new WP_Error( 'invalid_spec', __( 'Spec must be an object.', 'elementor-mcp' ) );
		}
		if ( empty( $spec['meta']['title'] ) ) {
			return new WP_Error( 'invalid_spec', __( 'meta.title is required.', 'elementor-mcp' ) );
		}
		if ( empty( $spec['sections'] ) || ! is_array( $spec['sections'] ) ) {
			return new WP_Error( 'invalid_spec', __( 'At least one section is required.', 'elementor-mcp' ) );
		}

		$types     = self::control_types();
		$seen_names = array();

		foreach ( $spec['sections'] as $section ) {
			if ( empty( $section['id'] ) || empty( $section['controls'] ) || ! is_array( $section['controls'] ) ) {
				return new WP_Error( 'invalid_spec', __( 'Each section needs an id and a non-empty controls array.', 'elementor-mcp' ) );
			}
			foreach ( $section['controls'] as $control ) {
				$err = self::validate_control( $control, $types, $seen_names );
				if ( is_wp_error( $err ) ) {
					return $err;
				}
			}
		}

		if ( ! isset( $spec['html_template'] ) || ! is_string( $spec['html_template'] ) || '' === trim( $spec['html_template'] ) ) {
			return new WP_Error( 'invalid_spec', __( 'html_template is required.', 'elementor-mcp' ) );
		}

		return true;
	}

	/**
	 * Validates a single control (recurses one level for repeater fields).
	 *
	 * @since 1.9.0
	 *
	 * @param mixed $control    The control definition.
	 * @param array $types      The supported types map.
	 * @param array $seen_names By-ref set of already-used control names.
	 * @return true|WP_Error
	 */
	private static function validate_control( $control, array $types, array &$seen_names ) {
		if ( ! is_array( $control ) || empty( $control['name'] ) || empty( $control['type'] ) ) {
			return new WP_Error( 'invalid_spec', __( 'Each control needs a name and a type.', 'elementor-mcp' ) );
		}
		$name = sanitize_key( $control['name'] );
		if ( '' === $name ) {
			return new WP_Error( 'invalid_spec', __( 'Control names must be lowercase letters, numbers, and underscores.', 'elementor-mcp' ) );
		}
		if ( isset( $seen_names[ $name ] ) ) {
			/* translators: %s: control name */
			return new WP_Error( 'invalid_spec', sprintf( __( 'Duplicate control name: %s.', 'elementor-mcp' ), $name ) );
		}
		$seen_names[ $name ] = true;

		if ( ! isset( $types[ $control['type'] ] ) ) {
			/* translators: %s: control type */
			return new WP_Error( 'invalid_spec', sprintf( __( 'Unsupported control type: %s.', 'elementor-mcp' ), $control['type'] ) );
		}

		if ( in_array( $control['type'], array( 'select', 'select2', 'choose', 'visual_choice' ), true ) && empty( $control['options'] ) ) {
			/* translators: %s: control name */
			return new WP_Error( 'invalid_spec', sprintf( __( 'Control "%s" needs an options array.', 'elementor-mcp' ), $name ) );
		}

		// Group controls (typography, border, background, …) style a CSS selector.
		if ( ! empty( $types[ $control['type'] ]['group_class'] ) && empty( $control['selector'] ) ) {
			/* translators: %s: control name */
			return new WP_Error( 'invalid_spec', sprintf( __( 'Group control "%s" needs a "selector" (a class to style, e.g. ".my-title").', 'elementor-mcp' ), $name ) );
		}

		if ( 'repeater' === $control['type'] ) {
			if ( empty( $control['fields'] ) || ! is_array( $control['fields'] ) ) {
				/* translators: %s: control name */
				return new WP_Error( 'invalid_spec', sprintf( __( 'Repeater "%s" needs a fields array.', 'elementor-mcp' ), $name ) );
			}
			$field_names = array();
			foreach ( $control['fields'] as $field ) {
				// Repeater fields cannot themselves be repeaters.
				if ( is_array( $field ) && 'repeater' === ( $field['type'] ?? '' ) ) {
					return new WP_Error( 'invalid_spec', __( 'Repeater fields cannot be repeaters.', 'elementor-mcp' ) );
				}
				$err = self::validate_control( $field, $types, $field_names );
				if ( is_wp_error( $err ) ) {
					return $err;
				}
			}
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// Generation
	// -------------------------------------------------------------------------

	/**
	 * Generates the full PHP source for a widget class.
	 *
	 * @since 1.9.0
	 *
	 * @param array  $spec        The (validated) widget spec.
	 * @param string $class_name  Unique PHP class name (e.g. EMCP_Widget_12).
	 * @param string $widget_name Unique Elementor widget machine name.
	 * @param array  $opts        Optional: 'style_handle' / 'script_handle' to wire
	 *                            get_style_depends()/get_script_depends().
	 * @return string|WP_Error PHP source, or WP_Error on invalid spec / lint failure.
	 */
	public static function generate( array $spec, string $class_name, string $widget_name, array $opts = array() ) {
		$valid = self::validate_spec( $spec );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$class_name  = preg_replace( '/[^A-Za-z0-9_]/', '', $class_name );
		$widget_name = sanitize_key( $widget_name );

		$title    = self::php_str( sanitize_text_field( (string) $spec['meta']['title'] ) );
		$icon     = self::sanitize_icon( $spec['meta']['icon'] ?? 'eicon-code' );
		$keywords = self::build_keywords( $spec['meta']['keywords'] ?? array() );

		$register = self::build_register_body( $spec );
		$render   = self::build_render_body( $spec );

		$src  = "<?php\n";
		$src .= "/**\n * Generated by MCP Tools for Elementor. DO NOT EDIT.\n";
		$src .= " * Source of truth: the emcp_widget post's _emcp_spec meta. Regenerate via the plugin.\n */\n";
		$src .= "if ( ! defined( 'ABSPATH' ) ) { exit; }\n";
		$src .= "if ( ! class_exists( '{$class_name}' ) && class_exists( '\\\\Elementor\\\\Widget_Base' ) ) {\n";
		$src .= "class {$class_name} extends \\Elementor\\Widget_Base {\n";
		$src .= "\tpublic function get_name() { return '{$widget_name}'; }\n";
		$src .= "\tpublic function get_title() { return {$title}; }\n";
		$src .= "\tpublic function get_icon() { return '{$icon}'; }\n";
		$src .= "\tpublic function get_categories() { return array( 'emcp-custom' ); }\n";
		$src .= "\tpublic function get_keywords() { return array( {$keywords} ); }\n";

		// Per-widget asset depends — Elementor enqueues these handles only when
		// the widget is on the page. The handles are registered by the loader.
		$style_handle  = isset( $opts['style_handle'] ) ? sanitize_key( (string) $opts['style_handle'] ) : '';
		$script_handle = isset( $opts['script_handle'] ) ? sanitize_key( (string) $opts['script_handle'] ) : '';
		if ( '' !== $style_handle ) {
			$src .= "\tpublic function get_style_depends() { return array( '{$style_handle}' ); }\n";
		}
		if ( '' !== $script_handle ) {
			$src .= "\tpublic function get_script_depends() { return array( '{$script_handle}' ); }\n";
		}

		$src .= "\tprotected function register_controls() {\n{$register}\t}\n";
		$src .= "\tprotected function render() {\n";
		$src .= "\t\t\$settings = \$this->get_settings_for_display();\n";
		$src .= "{$render}\t}\n";
		$src .= "}\n}\n";

		// Structural lint — throws ParseError on invalid syntax (PHP 7+).
		try {
			token_get_all( $src, TOKEN_PARSE );
		} catch ( \ParseError $e ) {
			return new WP_Error( 'generate_parse_error', __( 'The generated widget code failed a syntax check.', 'elementor-mcp' ), array( 'detail' => $e->getMessage() ) );
		}

		return $src;
	}

	// -------------------------------------------------------------------------
	// register_controls()
	// -------------------------------------------------------------------------

	/**
	 * Builds the body of register_controls().
	 *
	 * @since 1.9.0
	 *
	 * @param array $spec The spec.
	 * @return string PHP.
	 */
	private static function build_register_body( array $spec ): string {
		$out = '';
		foreach ( $spec['sections'] as $i => $section ) {
			$sid  = sanitize_key( $section['id'] ?? ( 'section_' . $i ) );
			$slab = self::php_str( sanitize_text_field( (string) ( $section['label'] ?? 'Section' ) ) );
			$tab  = self::map_tab( $section['tab'] ?? 'content' );

			$out .= "\t\t\$this->start_controls_section( '{$sid}', array( 'label' => {$slab}, 'tab' => {$tab} ) );\n";

			foreach ( $section['controls'] as $control ) {
				$out .= self::build_control( $control );
			}

			$out .= "\t\t\$this->end_controls_section();\n";
		}
		return $out;
	}

	/**
	 * Builds a single add_control() (or a repeater block).
	 *
	 * @since 1.9.0
	 *
	 * @param array $control The control def.
	 * @return string PHP.
	 */
	private static function build_control( array $control ): string {
		$name  = sanitize_key( $control['name'] );
		$type  = $control['type'];
		$types = self::control_types();

		if ( 'repeater' === $type ) {
			return self::build_repeater( $control, $name );
		}
		if ( ! empty( $types[ $type ]['group_class'] ) ) {
			return self::build_group_control( $control, $name, $types[ $type ]['group_class'] );
		}

		$args   = self::build_control_args( $control );
		$method = ! empty( $control['responsive'] ) ? 'add_responsive_control' : 'add_control';
		return "\t\t\$this->{$method}( '{$name}', {$args} );\n";
	}

	/**
	 * Builds an add_group_control() call (typography, border, background, etc.).
	 * These inject CSS into a selector — no render() reading is needed.
	 *
	 * @since 1.9.0
	 *
	 * @param array  $control The control def.
	 * @param string $name    Sanitized name (becomes the field-key prefix).
	 * @param string $class   Group control class (e.g. Group_Control_Typography).
	 * @return string PHP.
	 */
	private static function build_group_control( array $control, string $name, string $class ): string {
		$parts   = array();
		$parts[] = "'name' => '{$name}'";

		if ( isset( $control['label'] ) ) {
			$parts[] = "'label' => " . self::php_str( sanitize_text_field( (string) $control['label'] ) );
		}

		if ( 'image_size' === $control['type'] ) {
			if ( isset( $control['default'] ) && is_string( $control['default'] ) ) {
				$parts[] = "'default' => " . self::php_str( sanitize_text_field( $control['default'] ) );
			}
		} else {
			$selector = self::wrap_selector( isset( $control['selector'] ) ? (string) $control['selector'] : '' );
			$parts[]  = "'selector' => " . self::php_str( $selector );

			if ( 'background' === $control['type'] && ! empty( $control['types'] ) && is_array( $control['types'] ) ) {
				$tt = array();
				foreach ( $control['types'] as $t ) {
					$tt[] = self::php_str( sanitize_key( (string) $t ) );
				}
				if ( $tt ) {
					$parts[] = "'types' => array( " . implode( ', ', $tt ) . ' )';
				}
			}
			if ( ! empty( $control['exclude'] ) && is_array( $control['exclude'] ) ) {
				$ex = array();
				foreach ( $control['exclude'] as $e ) {
					$ex[] = self::php_str( sanitize_key( (string) $e ) );
				}
				if ( $ex ) {
					$parts[] = "'exclude' => array( " . implode( ', ', $ex ) . ' )';
				}
			}
		}

		$cond = self::build_condition( $control );
		if ( '' !== $cond ) {
			$parts[] = $cond;
		}

		return "\t\t\$this->add_group_control( \\Elementor\\{$class}::get_type(), array( " . implode( ', ', $parts ) . " ) );\n";
	}

	/**
	 * Builds a repeater control block using \Elementor\Repeater.
	 *
	 * @since 1.9.0
	 *
	 * @param array  $control The repeater control def.
	 * @param string $name    Sanitized control name.
	 * @return string PHP.
	 */
	private static function build_repeater( array $control, string $name ): string {
		$var  = '$repeater_' . $name;
		$out  = "\t\t{$var} = new \\Elementor\\Repeater();\n";
		$title_field = '';
		foreach ( $control['fields'] as $field ) {
			$fname = sanitize_key( $field['name'] );
			$args  = self::build_control_args( $field );
			$out  .= "\t\t{$var}->add_control( '{$fname}', {$args} );\n";
			if ( '' === $title_field ) {
				$title_field = $fname;
			}
		}

		$label = self::php_str( sanitize_text_field( (string) ( $control['label'] ?? $name ) ) );
		$out  .= "\t\t\$this->add_control( '{$name}', array( ";
		$out  .= "'label' => {$label}, ";
		$out  .= "'type' => \\Elementor\\Controls_Manager::REPEATER, ";
		$out  .= "'fields' => {$var}->get_controls()";
		if ( '' !== $title_field ) {
			$out .= ", 'title_field' => '{{{ ' . '{$title_field}' . ' }}}'";
		}
		$out  .= " ) );\n";
		return $out;
	}

	/**
	 * Builds the args array literal for an add_control() call.
	 *
	 * @since 1.9.0
	 *
	 * @param array $control The control def.
	 * @return string PHP array literal.
	 */
	private static function build_control_args( array $control ): string {
		$types = self::control_types();
		$type  = $control['type'];
		$const = '\\Elementor\\Controls_Manager::' . $types[ $type ]['const'];
		$label = self::php_str( sanitize_text_field( (string) ( $control['label'] ?? $control['name'] ) ) );

		$parts   = array();
		$parts[] = "'label' => {$label}";
		$parts[] = "'type' => {$const}";

		// raw_html uses 'raw' instead of label.
		if ( 'raw_html' === $type && isset( $control['html'] ) ) {
			$parts[] = "'raw' => " . self::php_str( wp_kses_post( (string) $control['html'] ) );
		}

		// ALERT panel note.
		if ( 'alert' === $type ) {
			$at = isset( $control['alert_type'] ) ? sanitize_key( (string) $control['alert_type'] ) : 'info';
			if ( ! in_array( $at, array( 'info', 'success', 'warning', 'danger' ), true ) ) {
				$at = 'info';
			}
			$parts[] = "'alert_type' => '{$at}'";
			if ( isset( $control['heading'] ) ) {
				$parts[] = "'heading' => " . self::php_str( sanitize_text_field( (string) $control['heading'] ) );
			}
			if ( isset( $control['content'] ) ) {
				$parts[] = "'content' => " . self::php_str( wp_kses_post( (string) $control['content'] ) );
			}
		}

		// SELECT / SELECT2 options: value => label.
		if ( in_array( $type, array( 'select', 'select2' ), true ) && ! empty( $control['options'] ) ) {
			$opts = array();
			foreach ( (array) $control['options'] as $opt ) {
				if ( is_array( $opt ) && isset( $opt['value'] ) ) {
					$ov = self::php_str( sanitize_text_field( (string) $opt['value'] ) );
					$ol = self::php_str( sanitize_text_field( (string) ( $opt['label'] ?? $opt['value'] ) ) );
					$opts[] = "{$ov} => {$ol}";
				}
			}
			if ( $opts ) {
				$parts[] = "'options' => array( " . implode( ', ', $opts ) . ' )';
			}
		}
		if ( 'select2' === $type && ! empty( $control['multiple'] ) ) {
			$parts[] = "'multiple' => true";
			$parts[] = "'label_block' => true";
		}

		// VISUAL_CHOICE: options carry images instead of icons.
		if ( 'visual_choice' === $type && ! empty( $control['options'] ) ) {
			$opts = array();
			foreach ( (array) $control['options'] as $opt ) {
				if ( is_array( $opt ) && isset( $opt['value'] ) ) {
					$val   = (string) $opt['value'];
					$ov    = self::php_str( sanitize_text_field( $val ) );
					$title = self::php_str( sanitize_text_field( (string) ( $opt['label'] ?? $val ) ) );
					$img   = self::php_str( sanitize_text_field( (string) ( $opt['image'] ?? '' ) ) );
					$opts[] = "{$ov} => array( 'title' => {$title}, 'image' => {$img} )";
				}
			}
			if ( $opts ) {
				$parts[] = "'options' => array( " . implode( ', ', $opts ) . ' )';
			}
			if ( isset( $control['columns'] ) && is_numeric( $control['columns'] ) ) {
				$parts[] = "'columns' => " . ( 0 + $control['columns'] );
			}
		}

		// SWITCHER labels / return value.
		if ( 'switcher' === $type ) {
			if ( isset( $control['label_on'] ) ) {
				$parts[] = "'label_on' => " . self::php_str( sanitize_text_field( (string) $control['label_on'] ) );
			}
			if ( isset( $control['label_off'] ) ) {
				$parts[] = "'label_off' => " . self::php_str( sanitize_text_field( (string) $control['label_off'] ) );
			}
			if ( isset( $control['return_value'] ) ) {
				$parts[] = "'return_value' => " . self::php_str( sanitize_text_field( (string) $control['return_value'] ) );
			}
		}

		// COLOR alpha (on by default; only emit when disabled).
		if ( 'color' === $type && isset( $control['alpha'] ) && false === $control['alpha'] ) {
			$parts[] = "'alpha' => false";
		}

		// TEXT input_type; CODE language; TEXTAREA/CODE rows.
		if ( 'text' === $type && isset( $control['input_type'] ) ) {
			$it = sanitize_key( (string) $control['input_type'] );
			if ( in_array( $it, array( 'text', 'email', 'url', 'tel', 'number', 'password' ), true ) ) {
				$parts[] = "'input_type' => '{$it}'";
			}
		}
		if ( in_array( $type, array( 'textarea', 'code' ), true ) && isset( $control['rows'] ) && is_numeric( $control['rows'] ) ) {
			$parts[] = "'rows' => " . ( 0 + $control['rows'] );
		}
		if ( 'code' === $type && isset( $control['language'] ) ) {
			$lang = sanitize_key( (string) $control['language'] );
			if ( '' !== $lang ) {
				$parts[] = "'language' => '{$lang}'";
			}
		}

		// CHOOSE renders icon buttons, so each option needs an icon (eicon).
		// Use the spec's per-option icon if given, else a sensible eicon for
		// common alignment-style values — otherwise the buttons render empty.
		if ( 'choose' === $type && ! empty( $control['options'] ) ) {
			$opts = array();
			foreach ( (array) $control['options'] as $opt ) {
				if ( is_array( $opt ) && isset( $opt['value'] ) ) {
					$val   = (string) $opt['value'];
					$ov    = self::php_str( sanitize_text_field( $val ) );
					$title = self::php_str( sanitize_text_field( (string) ( $opt['label'] ?? $val ) ) );
					$icon  = isset( $opt['icon'] ) ? self::sanitize_icon( $opt['icon'] ) : self::guess_choose_icon( $val );
					$opts[] = "{$ov} => array( 'title' => {$title}, 'icon' => '{$icon}' )";
				}
			}
			if ( $opts ) {
				$parts[] = "'options' => array( " . implode( ', ', $opts ) . ' )';
				$parts[] = "'toggle' => true";
			}
		}

		// Scalar default for value-bearing scalar controls. Controls whose
		// Elementor default is an ARRAY (slider, dimensions, media, url, icon,
		// repeater) must NOT receive a scalar default — Elementor array_merges
		// its array default with this value and fatals on a string (controls.php
		// add_control_to_stack). Slider is handled separately just below.
		if ( isset( $control['default'] ) && is_scalar( $control['default'] )
			&& ! in_array( $type, array( 'media', 'image', 'icon', 'url', 'repeater', 'dimensions', 'slider', 'gallery', 'select2' ), true ) ) {
			$parts[] = "'default' => " . self::php_str( (string) $control['default'] );
		}

		// Slider: default must be a {size, unit} array; bounds belong in `range`.
		if ( 'slider' === $type ) {
			$units = self::size_units( $control );
			if ( isset( $control['default'] ) && is_numeric( $control['default'] ) ) {
				$u0      = $units[0];
				$parts[] = "'default' => array( 'size' => " . ( 0 + $control['default'] ) . ", 'unit' => '{$u0}' )";
			}
			$range = array();
			if ( isset( $control['min'] ) && is_numeric( $control['min'] ) ) {
				$range[] = "'min' => " . ( 0 + $control['min'] );
			}
			if ( isset( $control['max'] ) && is_numeric( $control['max'] ) ) {
				$range[] = "'max' => " . ( 0 + $control['max'] );
			}
			if ( isset( $control['step'] ) && is_numeric( $control['step'] ) ) {
				$range[] = "'step' => " . ( 0 + $control['step'] );
			}
			if ( $range ) {
				$rparts = array();
				foreach ( $units as $u ) {
					$rparts[] = "'{$u}' => array( " . implode( ', ', $range ) . ' )';
				}
				$parts[] = "'range' => array( " . implode( ', ', $rparts ) . ' )';
			}
			$parts[] = "'size_units' => " . self::units_literal( $units );
		}

		// Dimensions: declare available units.
		if ( 'dimensions' === $type ) {
			$parts[] = "'size_units' => " . self::units_literal( self::size_units( $control ) );
		}

		// Placeholder for text-like controls.
		if ( isset( $control['placeholder'] ) && in_array( $type, array( 'text', 'textarea', 'number', 'url' ), true ) ) {
			$parts[] = "'placeholder' => " . self::php_str( sanitize_text_field( (string) $control['placeholder'] ) );
		}

		// Numeric bounds for number.
		if ( 'number' === $type ) {
			if ( isset( $control['min'] ) && is_numeric( $control['min'] ) ) {
				$parts[] = "'min' => " . ( 0 + $control['min'] );
			}
			if ( isset( $control['max'] ) && is_numeric( $control['max'] ) ) {
				$parts[] = "'max' => " . ( 0 + $control['max'] );
			}
			if ( isset( $control['step'] ) && is_numeric( $control['step'] ) ) {
				$parts[] = "'step' => " . ( 0 + $control['step'] );
			}
		}

		// prefix_class (e.g. animations apply a CSS class from the value).
		if ( isset( $control['prefix_class'] ) && is_string( $control['prefix_class'] ) ) {
			$parts[] = "'prefix_class' => " . self::php_str( $control['prefix_class'] );
		}

		// CSS selectors: a map of selector => 'css: {{VALUE}};'. Keys are scoped to
		// {{WRAPPER}}; the {{VALUE}}/{{SIZE}}{{UNIT}} tokens are fixed Elementor syntax.
		if ( ! empty( $control['selectors'] ) && is_array( $control['selectors'] ) ) {
			$sel = array();
			foreach ( $control['selectors'] as $k => $v ) {
				$sel[] = self::php_str( self::wrap_selector( (string) $k ) ) . ' => ' . self::php_str( (string) $v );
			}
			if ( $sel ) {
				$parts[] = "'selectors' => array( " . implode( ', ', $sel ) . ' )';
			}
		}

		// Common args available on every control.
		if ( isset( $control['description'] ) ) {
			$parts[] = "'description' => " . self::php_str( sanitize_text_field( (string) $control['description'] ) );
		}
		if ( isset( $control['separator'] ) && in_array( $control['separator'], array( 'before', 'after', 'default', 'none' ), true ) ) {
			$parts[] = "'separator' => '" . $control['separator'] . "'";
		}
		if ( isset( $control['label_block'] ) ) {
			$parts[] = "'label_block' => " . ( $control['label_block'] ? 'true' : 'false' );
		}
		if ( isset( $control['show_label'] ) ) {
			$parts[] = "'show_label' => " . ( $control['show_label'] ? 'true' : 'false' );
		}
		$cond = self::build_condition( $control );
		if ( '' !== $cond ) {
			$parts[] = $cond;
		}

		return 'array( ' . implode( ', ', $parts ) . ' )';
	}

	/**
	 * Returns the validated CSS units for a slider/dimensions control.
	 *
	 * @since 1.9.0
	 *
	 * @param array $control The control def.
	 * @return string[] Non-empty list of units (defaults to ['px']).
	 */
	private static function size_units( array $control ): array {
		$raw = array();
		if ( ! empty( $control['units'] ) && is_array( $control['units'] ) ) {
			$raw = $control['units'];
		} elseif ( ! empty( $control['size_units'] ) && is_array( $control['size_units'] ) ) {
			$raw = $control['size_units'];
		}
		$allowed = array( 'px', '%', 'em', 'rem', 'vw', 'vh', 'vmin', 'vmax', 'deg', 'rad', 'custom' );
		$out     = array();
		foreach ( $raw as $u ) {
			$u = is_string( $u ) ? trim( $u ) : '';
			if ( in_array( $u, $allowed, true ) ) {
				$out[] = $u;
			}
		}
		return $out ? array_values( array_unique( $out ) ) : array( 'px' );
	}

	/**
	 * PHP array-literal for a list of units.
	 *
	 * @param string[] $units Units.
	 * @return string
	 */
	private static function units_literal( array $units ): string {
		$q = array();
		foreach ( $units as $u ) {
			$q[] = self::php_str( $u );
		}
		return 'array( ' . implode( ', ', $q ) . ' )';
	}

	/**
	 * Builds a "'condition' => array(...)" / "'conditions' => array(...)" fragment
	 * from the spec, or '' when none. Values are emitted as safe PHP literals.
	 *
	 * @since 1.9.0
	 *
	 * @param array $control The control def.
	 * @return string
	 */
	private static function build_condition( array $control ): string {
		$out = array();
		if ( ! empty( $control['condition'] ) && is_array( $control['condition'] ) ) {
			$out[] = "'condition' => " . self::php_value( $control['condition'] );
		}
		if ( ! empty( $control['conditions'] ) && is_array( $control['conditions'] ) ) {
			$out[] = "'conditions' => " . self::php_value( $control['conditions'] );
		}
		return implode( ', ', $out );
	}

	/**
	 * Ensures a CSS selector is scoped to the widget. Prepends `{{WRAPPER}} ` when
	 * the selector doesn't already reference a `{{...}}` placeholder.
	 *
	 * @since 1.9.0
	 *
	 * @param string $selector The selector.
	 * @return string
	 */
	private static function wrap_selector( string $selector ): string {
		$selector = trim( $selector );
		if ( '' === $selector ) {
			return '{{WRAPPER}}';
		}
		if ( false !== strpos( $selector, '{{' ) ) {
			return $selector;
		}
		return '{{WRAPPER}} ' . $selector;
	}

	/**
	 * Serializes a JSON-decoded value to a safe PHP literal (strings, numbers,
	 * booleans, null, and nested arrays). Used for condition fragments.
	 *
	 * @since 1.9.0
	 *
	 * @param mixed $value The value.
	 * @return string PHP literal.
	 */
	private static function php_value( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) ( 0 + $value );
		}
		if ( is_null( $value ) ) {
			return 'null';
		}
		if ( is_array( $value ) ) {
			$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
			$items   = array();
			foreach ( $value as $k => $v ) {
				if ( $is_list ) {
					$items[] = self::php_value( $v );
				} else {
					$items[] = self::php_str( (string) $k ) . ' => ' . self::php_value( $v );
				}
			}
			return 'array( ' . implode( ', ', $items ) . ' )';
		}
		return self::php_str( (string) $value );
	}

	// -------------------------------------------------------------------------
	// render() — template compiler
	// -------------------------------------------------------------------------

	/**
	 * Builds the body of render() by compiling the html_template.
	 *
	 * @since 1.9.0
	 *
	 * @param array $spec The spec.
	 * @return string PHP.
	 */
	private static function build_render_body( array $spec ): string {
		$top = self::collect_field_map( $spec );
		$context = array(
			array(
				'var'    => '$settings',
				'fields' => $top,
			),
		);

		$template = (string) $spec['html_template'];
		$parts    = preg_split( '/(\{\{.*?\}\})/s', $template, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) ) {
			$parts = array();
		}

		$out          = '';
		$last_literal = '';
		$indent       = "\t\t";

		foreach ( $parts as $part ) {
			if ( preg_match( '/^\{\{(.*)\}\}$/s', $part, $m ) ) {
				$token = trim( $m[1] );
				$out  .= self::compile_token( $token, $context, $last_literal, $indent );
			} else {
				$out         .= $indent . 'echo ' . self::php_str( $part ) . ";\n";
				$last_literal = $part;
			}
		}

		return $out;
	}

	/**
	 * Compiles one `{{…}}` token into PHP, mutating the context/indent stack.
	 *
	 * @since 1.9.0
	 *
	 * @param string $token        The inner token text.
	 * @param array  $context      By-ref context stack.
	 * @param string $last_literal The preceding literal (for attribute detection).
	 * @param string $indent       By-ref current indent.
	 * @return string PHP.
	 */
	private static function compile_token( string $token, array &$context, string $last_literal, string &$indent ): string {
		$ctx    = $context[ count( $context ) - 1 ];
		$fields = $ctx['fields'];
		$var    = $ctx['var'];

		// {{#if name}}
		if ( preg_match( '/^#if\s+([a-z0-9_]+)$/i', $token, $m ) ) {
			$name = sanitize_key( $m[1] );
			$type = $fields[ $name ]['type'] ?? 'text';
			$expr = "{$var}['{$name}']";
			$cond = ( 'switcher' === $type )
				? "isset( {$expr} ) && 'yes' === {$expr}"
				: "! empty( {$expr} )";
			$line   = $indent . "if ( {$cond} ) {\n";
			$indent .= "\t";
			return $line;
		}

		// {{/if}}
		if ( '/if' === $token ) {
			$indent = substr( $indent, 0, -1 );
			return $indent . "}\n";
		}

		// {{#each repeater}}
		if ( preg_match( '/^#each\s+([a-z0-9_]+)$/i', $token, $m ) ) {
			$name   = sanitize_key( $m[1] );
			$rfields = $fields[ $name ]['fields'] ?? array();
			$expr    = "{$var}['{$name}']";
			$line    = $indent . "if ( ! empty( {$expr} ) && is_array( {$expr} ) ) { foreach ( {$expr} as \$emcp_item ) {\n";
			$indent .= "\t";
			$context[] = array(
				'var'    => '$emcp_item',
				'fields' => $rfields,
			);
			return $line;
		}

		// {{/each}}
		if ( '/each' === $token ) {
			array_pop( $context );
			$indent = substr( $indent, 0, -1 );
			return $indent . "} }\n";
		}

		// Plain value placeholder.
		if ( preg_match( '/^[a-z0-9_]+$/i', $token ) ) {
			$name = sanitize_key( $token );
			$def  = $fields[ $name ] ?? array( 'type' => 'text' );
			return $indent . self::value_statement( $def, "{$var}['{$name}']", self::is_attr_context( $last_literal ) ) . "\n";
		}

		// Unknown token — emit nothing (do not pass through).
		return '';
	}

	/**
	 * Produces the PHP statement that outputs one control value, escaped by type.
	 *
	 * @since 1.9.0
	 *
	 * @param array  $def  The control def (needs 'type').
	 * @param string $base The PHP access expression (e.g. $settings['name']).
	 * @param bool   $attr Whether the placeholder sits inside an HTML attribute.
	 * @return string A complete PHP statement.
	 */
	private static function value_statement( array $def, string $base, bool $attr ): string {
		$type = $def['type'] ?? 'text';

		switch ( $type ) {
			case 'icon':
				return "if ( ! empty( {$base} ) ) { \\Elementor\\Icons_Manager::render_icon( {$base}, array( 'aria-hidden' => 'true' ) ); }";

			case 'url':
				return "echo esc_url( {$base}['url'] ?? '' );";

			case 'media':
			case 'image':
				return "echo esc_url( {$base}['url'] ?? '' );";

			case 'raw_url':
				// A plain URL string (e.g. a gallery item's url), not a {url:…} array.
				return "echo esc_url( {$base} ?? '' );";

			case 'wysiwyg':
			case 'code':
				return "echo wp_kses_post( {$base} ?? '' );";

			case 'textarea':
				if ( ! empty( $def['html'] ) ) {
					return "echo wp_kses_post( {$base} ?? '' );";
				}
				return $attr
					? "echo esc_attr( {$base} ?? '' );"
					: "echo nl2br( esc_html( {$base} ?? '' ) );";

			case 'slider':
				return $attr
					? "echo esc_attr( {$base}['size'] ?? '' );"
					: "echo esc_html( {$base}['size'] ?? '' );";

			case 'number':
				return "echo esc_html( {$base} ?? '' );";

			default:
				// text, select, choose, color, switcher, heading, etc.
				return $attr
					? "echo esc_attr( {$base} ?? '' );"
					: "echo esc_html( {$base} ?? '' );";
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Builds a flat name => def map of all value controls (top level), including
	 * repeater defs (with their 'fields' sub-map attached).
	 *
	 * @since 1.9.0
	 *
	 * @param array $spec The spec.
	 * @return array<string, array>
	 */
	private static function collect_field_map( array $spec ): array {
		$map = array();
		foreach ( $spec['sections'] as $section ) {
			foreach ( (array) ( $section['controls'] ?? array() ) as $control ) {
				if ( empty( $control['name'] ) || empty( $control['type'] ) ) {
					continue;
				}
				$name = sanitize_key( $control['name'] );
				$def  = array( 'type' => $control['type'] );
				if ( isset( $control['html'] ) ) {
					$def['html'] = (bool) $control['html'];
				}
				if ( 'repeater' === $control['type'] ) {
					$sub = array();
					foreach ( (array) ( $control['fields'] ?? array() ) as $field ) {
						if ( empty( $field['name'] ) || empty( $field['type'] ) ) {
							continue;
						}
						$fdef = array( 'type' => $field['type'] );
						if ( isset( $field['html'] ) ) {
							$fdef['html'] = (bool) $field['html'];
						}
						$sub[ sanitize_key( $field['name'] ) ] = $fdef;
					}
					$def['fields'] = $sub;
				}
				// A gallery is an array of {id,url}; expose those as loop fields so
				// {{#each gallery}}…{{url}}/{{id}}…{{/each}} works.
				if ( 'gallery' === $control['type'] ) {
					$def['fields'] = array(
						'url' => array( 'type' => 'raw_url' ),
						'id'  => array( 'type' => 'number' ),
					);
				}
				$map[ $name ] = $def;
			}
		}
		return $map;
	}

	/**
	 * Whether the placeholder that follows this literal is inside an HTML
	 * attribute value (so esc_attr should be used instead of esc_html).
	 *
	 * @since 1.9.0
	 *
	 * @param string $literal The preceding literal text.
	 * @return bool
	 */
	private static function is_attr_context( string $literal ): bool {
		return (bool) preg_match( '/=\s*("|\')[^"\']*$/s', $literal );
	}

	/**
	 * Maps a spec tab name to its Controls_Manager constant.
	 *
	 * @since 1.9.0
	 *
	 * @param string $tab The tab name.
	 * @return string PHP constant reference.
	 */
	private static function map_tab( string $tab ): string {
		switch ( $tab ) {
			case 'style':
				return '\\Elementor\\Controls_Manager::TAB_STYLE';
			case 'advanced':
				return '\\Elementor\\Controls_Manager::TAB_ADVANCED';
			default:
				return '\\Elementor\\Controls_Manager::TAB_CONTENT';
		}
	}

	/**
	 * Picks a sensible eicon for a CHOOSE option value when the spec doesn't
	 * supply one, so alignment-style button groups aren't rendered empty.
	 *
	 * @since 1.9.0
	 *
	 * @param string $value The option value.
	 * @return string An eicon class.
	 */
	private static function guess_choose_icon( string $value ): string {
		$v   = strtolower( trim( $value ) );
		$map = array(
			'left'          => 'eicon-text-align-left',
			'flex-start'    => 'eicon-text-align-left',
			'start'         => 'eicon-text-align-left',
			'center'        => 'eicon-text-align-center',
			'justify'       => 'eicon-text-align-justify',
			'space-between' => 'eicon-justify-space-between-h',
			'space-around'  => 'eicon-justify-space-around-h',
			'right'         => 'eicon-text-align-right',
			'flex-end'      => 'eicon-text-align-right',
			'end'           => 'eicon-text-align-right',
			'top'           => 'eicon-v-align-top',
			'middle'        => 'eicon-v-align-middle',
			'bottom'        => 'eicon-v-align-bottom',
			'stretch'       => 'eicon-v-align-stretch',
			'row'           => 'eicon-arrow-right',
			'column'        => 'eicon-arrow-down',
			'none'          => 'eicon-ban',
		);
		return $map[ $v ] ?? 'eicon-dot-circle-o';
	}

	/**
	 * Validates an Elementor icon class against an allow-pattern.
	 *
	 * @since 1.9.0
	 *
	 * @param mixed $icon The icon class.
	 * @return string A safe icon class.
	 */
	private static function sanitize_icon( $icon ): string {
		$icon = is_string( $icon ) ? trim( $icon ) : '';
		if ( preg_match( '/^(eicon|fa|fas|far|fab)[a-z0-9 _-]*$/i', $icon ) ) {
			return $icon;
		}
		return 'eicon-code';
	}

	/**
	 * Builds the PHP for the keywords array contents.
	 *
	 * @since 1.9.0
	 *
	 * @param mixed $keywords Array of keyword strings.
	 * @return string Comma-separated quoted PHP strings.
	 */
	private static function build_keywords( $keywords ): string {
		if ( ! is_array( $keywords ) ) {
			return '';
		}
		$out = array();
		foreach ( array_slice( $keywords, 0, 12 ) as $kw ) {
			if ( is_scalar( $kw ) ) {
				$out[] = self::php_str( sanitize_text_field( (string) $kw ) );
			}
		}
		return implode( ', ', $out );
	}

	/**
	 * Encodes a string as a safe single-quoted PHP string literal.
	 *
	 * @since 1.9.0
	 *
	 * @param string $s The raw string.
	 * @return string A PHP string literal (including the surrounding quotes).
	 */
	private static function php_str( string $s ): string {
		return "'" . str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $s ) . "'";
	}
}
