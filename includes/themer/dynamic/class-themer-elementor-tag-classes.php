<?php
/**
 * The Elementor dynamic tag classes, one concrete class per source.
 *
 * ONE CLASS PER SOURCE IS NOT OPTIONAL, and a shared parameterised class does
 * not work. Elementor's registry keys by `get_name()` but stores only the class
 * name, and re-instantiates it later with the element data alone:
 *
 *     $this->tags_info[ $tag->get_name() ] = [ 'class' => get_class( $tag ), ... ];
 *     ...
 *     return new $tag_class( [ 'settings' => …, 'id' => … ] );
 *
 * So the tag NAME is gone by the time the object is rebuilt. Registering the
 * same class under seventeen names made every one of them rebuild as the same
 * class with no way to know which source it was, and because that constructor
 * took the key first, the front end fataled with a TypeError the moment a
 * widget carrying a dynamic value rendered. The key has to live in the class
 * identity, which is how Elementor Pro's own tags are built too.
 *
 * TWO BASES, CHOSEN BY VALUE TYPE (3.18.0). Text and date sources extend the
 * render Tag. Image and URL sources extend Data_Tag and return a VALUE: the
 * media control reads `{id, url}` (a string there throws "Cannot access offset
 * of type string on string" from Elementor's CSS generator and fatals the
 * page), and the URL control stores the value raw in its `url` property. Tag
 * names never changed, so saved bindings keep working.
 *
 * These extend an Elementor base class, so the file is required only from
 * inside the `elementor/dynamic_tags/register` callback.
 *
 * @package EMCP_Tools
 * @since   3.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'EMCP_Tools_Themer_Elementor_Tag' )
	&& class_exists( '\Elementor\Core\DynamicTags\Tag' )
	&& class_exists( '\Elementor\Core\DynamicTags\Data_Tag' ) ) {

	/**
	 * Identity shared by both tag bases: name, title, group and categories all
	 * come from the source key the concrete class declares.
	 *
	 * @since 3.18.0
	 */
	trait EMCP_Tools_Themer_Elementor_Tag_Identity {

		/**
		 * The catalog key this class renders.
		 *
		 * Declared per subclass rather than passed in, because Elementor rebuilds
		 * a tag from its class name alone.
		 *
		 * @return string
		 */
		abstract protected function source_key(): string;

		/**
		 * @return string
		 */
		public function get_name() {
			return EMCP_Tools_Themer_Elementor_Tags::tag_name( $this->source_key() );
		}

		/**
		 * @return string
		 */
		public function get_title() {
			$def = EMCP_Tools_Themer_Dynamic_Catalog::get( $this->source_key() );
			return $def ? $def['label'] : $this->source_key();
		}

		/**
		 * @return string
		 */
		public function get_group() {
			return EMCP_Tools_Themer_Elementor_Tags::GROUP;
		}

		/**
		 * @return string[]
		 */
		public function get_categories() {
			$def = EMCP_Tools_Themer_Dynamic_Catalog::get( $this->source_key() );
			return EMCP_Tools_Themer_Elementor_Tags::categories_for( $def ? $def['type'] : 'html' );
		}

		/**
		 * The tag's saved settings, as the provider args.
		 *
		 * @return array
		 */
		protected function source_args(): array {
			return method_exists( $this, 'get_settings' ) ? (array) $this->get_settings() : array();
		}
	}

	/**
	 * Render tag base, for text and date sources. Never registered directly:
	 * it has no source of its own.
	 *
	 * @since 3.13.0
	 */
	abstract class EMCP_Tools_Themer_Elementor_Tag extends \Elementor\Core\DynamicTags\Tag {

		use EMCP_Tools_Themer_Elementor_Tag_Identity;

		/**
		 * Class name for a source key, or '' when the source has no tag class.
		 *
		 * The class may extend either this base or the data-tag base below.
		 *
		 * @since 3.13.0
		 * @param string $key Source key.
		 * @return string
		 */
		public static function class_for( string $key ): string {
			$class = 'EMCP_Tools_Themer_Elementor_Tag_' . str_replace( '-', '_', ucwords( $key, '-' ) );
			return class_exists( $class ) ? $class : '';
		}

		/** Echo the resolved value. */
		public function render() {
			$v = EMCP_Tools_Themer_Dynamic::value( $this->source_key(), $this->source_args() );
			// Text and date are plain values; Elementor escapes per control, and
			// this tag is never registered for a markup source. Image and URL
			// sources are data tags (below) and never reach this method.
			echo esc_html( is_scalar( $v['value'] ) ? (string) $v['value'] : '' );
		}
	}

	/**
	 * Data tag base, for image and URL sources.
	 *
	 * Elementor's media control declares `returnType => object` and reads the
	 * dynamic value as `{id, url}`; its CSS path does `$value['url']`. A render
	 * tag hands it a string, which fatals `Control_Base_Multiple::
	 * get_style_value()` with "Cannot access offset of type string on string"
	 * the moment a background image is bound. Its URL control writes the value
	 * into the `url` property raw. Both need the value, not rendered output,
	 * which is what Data_Tag provides and what Elementor Pro's own Featured
	 * Image, Site Logo, Author Profile Picture and Post URL tags extend.
	 *
	 * @since 3.18.0
	 */
	abstract class EMCP_Tools_Themer_Elementor_Data_Tag extends \Elementor\Core\DynamicTags\Data_Tag {

		use EMCP_Tools_Themer_Elementor_Tag_Identity;

		/**
		 * @param array $options Unused; Elementor's signature.
		 * @return array|string {id, url} for an image source, a URL string for a url source.
		 */
		public function get_value( array $options = array() ) {
			unset( $options );
			return EMCP_Tools_Themer_Elementor_Tags::data_value( $this->source_key(), $this->source_args() );
		}
	}

	// -----------------------------------------------------------------------
	// Free sources.
	// -----------------------------------------------------------------------

	/** @since 3.13.0 */
	class EMCP_Tools_Themer_Elementor_Tag_Post_Title extends EMCP_Tools_Themer_Elementor_Tag {
		protected function source_key(): string {
			return 'post-title';
		}
	}

	/** @since 3.13.0 */
	class EMCP_Tools_Themer_Elementor_Tag_Post_Excerpt extends EMCP_Tools_Themer_Elementor_Tag {
		protected function source_key(): string {
			return 'post-excerpt';
		}
	}

	/** @since 3.13.0 */
	class EMCP_Tools_Themer_Elementor_Tag_Post_Url extends EMCP_Tools_Themer_Elementor_Data_Tag {
		protected function source_key(): string {
			return 'post-url';
		}
	}

	/** @since 3.13.0 */
	class EMCP_Tools_Themer_Elementor_Tag_Post_Date extends EMCP_Tools_Themer_Elementor_Tag {
		protected function source_key(): string {
			return 'post-date';
		}
	}

	/** @since 3.13.0 */
	class EMCP_Tools_Themer_Elementor_Tag_Post_Id extends EMCP_Tools_Themer_Elementor_Tag {
		protected function source_key(): string {
			return 'post-id';
		}
	}

	/** @since 3.13.0, a data tag since 3.18.0 */
	class EMCP_Tools_Themer_Elementor_Tag_Featured_Image extends EMCP_Tools_Themer_Elementor_Data_Tag {
		protected function source_key(): string {
			return 'featured-image';
		}
	}

	/** @since 3.13.0 */
	class EMCP_Tools_Themer_Elementor_Tag_Archive_Title extends EMCP_Tools_Themer_Elementor_Tag {
		protected function source_key(): string {
			return 'archive-title';
		}
	}

	/** @since 3.13.0 */
	class EMCP_Tools_Themer_Elementor_Tag_Site_Title extends EMCP_Tools_Themer_Elementor_Tag {
		protected function source_key(): string {
			return 'site-title';
		}
	}

	/** @since 3.13.0 */
	class EMCP_Tools_Themer_Elementor_Tag_Site_Logo extends EMCP_Tools_Themer_Elementor_Data_Tag {
		protected function source_key(): string {
			return 'site-logo';
		}
	}

	/** @since 3.13.0 */
	class EMCP_Tools_Themer_Elementor_Tag_Description extends EMCP_Tools_Themer_Elementor_Tag {
		protected function source_key(): string {
			return 'description';
		}
	}

	// -----------------------------------------------------------------------
	// Pro sources. The classes are free-tier plumbing that only names a key;
	// every value these resolve to comes from the Pro overlay, and the keys are
	// absent from the catalog without a licence, so these are never registered
	// on a free site.
	// -----------------------------------------------------------------------

	/** @since 3.13.0 */
	class EMCP_Tools_Themer_Elementor_Tag_Custom_Field extends EMCP_Tools_Themer_Elementor_Tag {
		protected function source_key(): string {
			return 'custom-field';
		}
	}

	/** @since 3.13.0 */
	class EMCP_Tools_Themer_Elementor_Tag_Author_Name extends EMCP_Tools_Themer_Elementor_Tag {
		protected function source_key(): string {
			return 'author-name';
		}
	}

	/** @since 3.13.0 */
	class EMCP_Tools_Themer_Elementor_Tag_Author_Bio extends EMCP_Tools_Themer_Elementor_Tag {
		protected function source_key(): string {
			return 'author-bio';
		}
	}

	/** @since 3.13.0 */
	class EMCP_Tools_Themer_Elementor_Tag_Author_Url extends EMCP_Tools_Themer_Elementor_Data_Tag {
		protected function source_key(): string {
			return 'author-url';
		}
	}

	/** @since 3.13.0 */
	class EMCP_Tools_Themer_Elementor_Tag_Author_Avatar extends EMCP_Tools_Themer_Elementor_Data_Tag {
		protected function source_key(): string {
			return 'author-avatar';
		}
	}

	/** @since 3.13.0 */
	class EMCP_Tools_Themer_Elementor_Tag_Term_Name extends EMCP_Tools_Themer_Elementor_Tag {
		protected function source_key(): string {
			return 'term-name';
		}
	}

	/** @since 3.13.0 */
	class EMCP_Tools_Themer_Elementor_Tag_Term_Url extends EMCP_Tools_Themer_Elementor_Data_Tag {
		protected function source_key(): string {
			return 'term-url';
		}
	}
}
