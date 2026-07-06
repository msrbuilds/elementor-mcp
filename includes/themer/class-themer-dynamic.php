<?php
/**
 * Shared dynamic-content provider for Themer's Gutenberg blocks + Elementor widgets.
 *
 * Every dynamic element (post title, archive title, breadcrumbs, meta, site logo,
 * menu, description, content, archive loop) resolves against the CURRENT main
 * query — Themer renders body templates with the main query intact, so these
 * output the viewed post/archive, not the template. Both builders call the same
 * static methods so the dynamic logic lives in exactly one place. Every method
 * returns an escaped HTML string.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.1.0
 */
class EMCP_Tools_Themer_Dynamic {

	/** Tags an element may render as. */
	const TITLE_TAGS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' );

	/**
	 * Normalize an HTML tag against an allowlist.
	 *
	 * @param string $tag      Requested tag.
	 * @param string $fallback Fallback when not allowed.
	 * @return string
	 */
	private static function tag( string $tag, string $fallback ): string {
		$tag = strtolower( trim( $tag ) );
		return in_array( $tag, self::TITLE_TAGS, true ) ? $tag : $fallback;
	}

	/**
	 * The queried post ID for the current request (0 in a non-singular context).
	 *
	 * @return int
	 */
	private static function queried_id(): int {
		$obj = get_queried_object();
		if ( $obj instanceof WP_Post ) {
			return (int) $obj->ID;
		}
		return in_the_loop() ? (int) get_the_ID() : 0;
	}

	/**
	 * Post / Page title of the queried object.
	 *
	 * @param array $args tag, link (bool).
	 * @return string
	 */
	public static function post_title( array $args = array() ): string {
		$tag  = self::tag( (string) ( $args['tag'] ?? 'h1' ), 'h1' );
		$id   = self::queried_id();
		$text = $id ? get_the_title( $id ) : ( is_home() ? get_the_title( (int) get_option( 'page_for_posts' ) ) : '' );
		if ( '' === $text ) {
			return '';
		}
		$inner = esc_html( $text );
		if ( ! empty( $args['link'] ) && $id ) {
			$inner = '<a href="' . esc_url( (string) get_permalink( $id ) ) . '">' . $inner . '</a>';
		}
		return '<' . $tag . ' class="emcp-dyn emcp-dyn-post-title">' . $inner . '</' . $tag . '>';
	}

	/**
	 * Archive title (category / tag / taxonomy / author / date / post-type archive).
	 *
	 * @param array $args tag, show_prefix (bool — keep the "Category:" prefix).
	 * @return string
	 */
	public static function archive_title( array $args = array() ): string {
		$tag = self::tag( (string) ( $args['tag'] ?? 'h1' ), 'h1' );
		if ( empty( $args['show_prefix'] ) ) {
			// WP 5.5+ exposes the "Category:" / "Tag:" / "Author:" prefix as a
			// filterable string — the robust way to drop it.
			add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
			$title = get_the_archive_title();
			remove_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
		} else {
			$title = get_the_archive_title();
		}
		$title = wp_kses_post( (string) $title );
		if ( '' === trim( wp_strip_all_tags( $title ) ) ) {
			return '';
		}
		return '<' . $tag . ' class="emcp-dyn emcp-dyn-archive-title">' . $title . '</' . $tag . '>';
	}

	/**
	 * Breadcrumb trail. Uses an active SEO plugin's breadcrumb when present
	 * (Yoast / Rank Math / SEOPress), else builds a simple Home > … > current trail.
	 *
	 * @param array $args separator, home_label.
	 * @return string
	 */
	public static function breadcrumbs( array $args = array() ): string {
		// Prefer a well-tested SEO-plugin breadcrumb if the site has one.
		if ( function_exists( 'yoast_breadcrumb' ) ) {
			$out = yoast_breadcrumb( '<nav class="emcp-dyn emcp-dyn-breadcrumbs" aria-label="Breadcrumb">', '</nav>', false );
			if ( is_string( $out ) && '' !== trim( $out ) ) {
				return $out;
			}
		}
		if ( function_exists( 'rank_math_the_breadcrumbs' ) ) {
			$out = do_shortcode( '[rank_math_breadcrumb]' );
			if ( '' !== trim( (string) $out ) ) {
				return '<nav class="emcp-dyn emcp-dyn-breadcrumbs" aria-label="Breadcrumb">' . $out . '</nav>';
			}
		}
		if ( function_exists( 'seopress_display_breadcrumbs' ) ) {
			ob_start();
			seopress_display_breadcrumbs();
			$out = (string) ob_get_clean();
			if ( '' !== trim( $out ) ) {
				return $out;
			}
		}
		return self::fallback_breadcrumbs( $args );
	}

	/**
	 * Built-in breadcrumb trail when no SEO plugin supplies one.
	 *
	 * @param array $args separator, home_label.
	 * @return string
	 */
	private static function fallback_breadcrumbs( array $args ): string {
		$sep   = isset( $args['separator'] ) && '' !== $args['separator'] ? (string) $args['separator'] : '/';
		$home  = isset( $args['home_label'] ) && '' !== $args['home_label'] ? (string) $args['home_label'] : __( 'Home', 'emcp-tools' );
		$crumb = array( '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( $home ) . '</a>' );

		if ( is_singular() ) {
			$id   = self::queried_id();
			$type = get_post_type( $id );
			if ( 'post' === $type ) {
				$cats = get_the_category( $id );
				if ( ! empty( $cats ) ) {
					$crumb[] = '<a href="' . esc_url( (string) get_category_link( $cats[0]->term_id ) ) . '">' . esc_html( $cats[0]->name ) . '</a>';
				}
			} elseif ( $type && ! in_array( $type, array( 'post', 'page' ), true ) ) {
				$obj = get_post_type_object( $type );
				if ( $obj && $obj->has_archive ) {
					$crumb[] = '<a href="' . esc_url( (string) get_post_type_archive_link( $type ) ) . '">' . esc_html( $obj->labels->name ) . '</a>';
				}
			}
			$crumb[] = '<span aria-current="page">' . esc_html( get_the_title( $id ) ) . '</span>';
		} elseif ( is_archive() || is_home() ) {
			$crumb[] = '<span aria-current="page">' . wp_kses_post( self::archive_title_text() ) . '</span>';
		} elseif ( is_search() ) {
			/* translators: %s: search query */
			$crumb[] = '<span aria-current="page">' . esc_html( sprintf( __( 'Search: %s', 'emcp-tools' ), get_search_query() ) ) . '</span>';
		} elseif ( is_404() ) {
			$crumb[] = '<span aria-current="page">' . esc_html__( '404 Not Found', 'emcp-tools' ) . '</span>';
		}

		$html = implode(
			' <span class="emcp-dyn-sep" aria-hidden="true">' . esc_html( $sep ) . '</span> ',
			$crumb
		);
		return '<nav class="emcp-dyn emcp-dyn-breadcrumbs" aria-label="Breadcrumb">' . $html . '</nav>';
	}

	/**
	 * Plain archive title text (no markup wrapper).
	 *
	 * @return string
	 */
	private static function archive_title_text(): string {
		add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
		$title = get_the_archive_title();
		remove_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
		return wp_strip_all_tags( (string) $title );
	}

	/**
	 * Post meta as a list (date, author, categories, tags, comments).
	 *
	 * @param array $args items (ordered array of keys), show_labels (bool).
	 * @return string
	 */
	public static function post_meta( array $args = array() ): string {
		$id = self::queried_id();
		if ( ! $id ) {
			return '';
		}
		$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array( 'date', 'author', 'categories' );
		$out   = array();

		foreach ( $items as $item ) {
			switch ( (string) $item ) {
				case 'date':
					$out[] = '<li class="emcp-dyn-meta-date">' . esc_html( (string) get_the_date( '', $id ) ) . '</li>';
					break;
				case 'author':
					$author = (int) get_post_field( 'post_author', $id );
					$out[]  = '<li class="emcp-dyn-meta-author"><a href="' . esc_url( (string) get_author_posts_url( $author ) ) . '">' . esc_html( (string) get_the_author_meta( 'display_name', $author ) ) . '</a></li>';
					break;
				case 'categories':
					$cats = get_the_category_list( ', ', '', $id );
					if ( '' !== $cats ) {
						$out[] = '<li class="emcp-dyn-meta-cats">' . wp_kses_post( $cats ) . '</li>';
					}
					break;
				case 'tags':
					$tags = get_the_tag_list( '', ', ', '', $id );
					if ( ! is_wp_error( $tags ) && '' !== (string) $tags ) {
						$out[] = '<li class="emcp-dyn-meta-tags">' . wp_kses_post( (string) $tags ) . '</li>';
					}
					break;
				case 'comments':
					$count = (int) get_comments_number( $id );
					$out[] = '<li class="emcp-dyn-meta-comments"><a href="' . esc_url( (string) get_comments_link( $id ) ) . '">' . esc_html( sprintf( _n( '%s comment', '%s comments', $count, 'emcp-tools' ), number_format_i18n( $count ) ) ) . '</a></li>';
					break;
			}
		}

		if ( empty( $out ) ) {
			return '';
		}
		return '<ul class="emcp-dyn emcp-dyn-post-meta">' . implode( '', $out ) . '</ul>';
	}

	/**
	 * Site logo (custom logo) with a text-title fallback.
	 *
	 * @param array $args max_width (px, 0 = none).
	 * @return string
	 */
	public static function site_logo( array $args = array() ): string {
		$style = '';
		$max   = (int) ( $args['max_width'] ?? 0 );
		if ( $max > 0 ) {
			$style = ' style="max-width:' . $max . 'px;height:auto;"';
		}
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$img = wp_get_attachment_image( $logo_id, 'full', false, array( 'class' => 'emcp-dyn-logo-img' ) );
			if ( $img ) {
				return '<a class="emcp-dyn emcp-dyn-site-logo" href="' . esc_url( home_url( '/' ) ) . '"' . $style . '>' . $img . '</a>';
			}
		}
		// Fallback: linked site title.
		return '<a class="emcp-dyn emcp-dyn-site-logo emcp-dyn-site-logo--text" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( (string) get_bloginfo( 'name' ) ) . '</a>';
	}

	/**
	 * Site title (linked) and/or tagline.
	 *
	 * @param array $args tag, show_tagline (bool).
	 * @return string
	 */
	public static function site_title( array $args = array() ): string {
		$tag  = self::tag( (string) ( $args['tag'] ?? 'span' ), 'span' );
		$html = '<' . $tag . ' class="emcp-dyn emcp-dyn-site-title"><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( (string) get_bloginfo( 'name' ) ) . '</a></' . $tag . '>';
		if ( ! empty( $args['show_tagline'] ) ) {
			$desc = (string) get_bloginfo( 'description' );
			if ( '' !== $desc ) {
				$html .= '<span class="emcp-dyn emcp-dyn-site-tagline">' . esc_html( $desc ) . '</span>';
			}
		}
		return $html;
	}

	/**
	 * A WordPress nav menu by theme location or menu id.
	 *
	 * @param array $args location, menu (id/slug).
	 * @return string
	 */
	public static function nav_menu( array $args = array() ): string {
		$menu_args = array(
			'container'      => 'nav',
			'container_class' => 'emcp-dyn emcp-dyn-nav-menu',
			'menu_class'     => 'emcp-dyn-menu',
			'echo'           => false,
			'fallback_cb'    => false,
			'depth'          => 0,
		);
		if ( ! empty( $args['location'] ) ) {
			$menu_args['theme_location'] = (string) $args['location'];
		} elseif ( ! empty( $args['menu'] ) ) {
			$menu_args['menu'] = $args['menu'];
		} else {
			// No selection: first available menu (so the editor preview isn't blank).
			$menus = wp_get_nav_menus();
			if ( ! empty( $menus ) ) {
				$menu_args['menu'] = $menus[0]->term_id;
			}
		}
		$out = wp_nav_menu( $menu_args );
		return is_string( $out ) ? $out : '';
	}

	/**
	 * Description — the archive description on archives, the post excerpt on
	 * singular views.
	 *
	 * @param array $args length (words, 0 = default).
	 * @return string
	 */
	public static function description( array $args = array() ): string {
		if ( is_archive() || is_home() ) {
			$desc = get_the_archive_description();
			$desc = is_string( $desc ) ? trim( $desc ) : '';
			return '' !== $desc ? '<div class="emcp-dyn emcp-dyn-description">' . wp_kses_post( $desc ) . '</div>' : '';
		}
		$id = self::queried_id();
		if ( ! $id ) {
			return '';
		}
		$excerpt = get_the_excerpt( $id );
		$excerpt = is_string( $excerpt ) ? trim( $excerpt ) : '';
		if ( '' === $excerpt ) {
			return '';
		}
		$len = (int) ( $args['length'] ?? 0 );
		if ( $len > 0 ) {
			$excerpt = wp_trim_words( $excerpt, $len, '&hellip;' );
		}
		return '<div class="emcp-dyn emcp-dyn-description">' . esc_html( $excerpt ) . '</div>';
	}

	/**
	 * The queried post's content (filtered).
	 *
	 * @return string
	 */
	public static function post_content(): string {
		$id = self::queried_id();
		if ( ! $id ) {
			return '';
		}
		$content = get_post_field( 'post_content', $id );
		$content = apply_filters( 'the_content', (string) $content );
		$content = str_replace( ']]>', ']]&gt;', $content );
		return '<div class="emcp-dyn emcp-dyn-post-content entry-content">' . $content . '</div>';
	}

	/**
	 * Archive posts loop (list / grid) with optional pagination.
	 *
	 * On a real front-end archive it loops the MAIN query (that archive's posts).
	 * In the editor / a block or Elementor preview (or any non-archive context)
	 * there is no archive query, so it shows a SAMPLE of recent posts instead —
	 * otherwise the widget would just say "No posts found." while you design it.
	 *
	 * @param array $args layout (grid|list), columns, show_image, show_title,
	 *                    show_excerpt, show_meta, show_more, more_text, pagination.
	 * @return string
	 */
	public static function archive_loop( array $args = array() ): string {
		global $wp_query;

		$layout   = 'list' === ( $args['layout'] ?? 'grid' ) ? 'list' : 'grid';
		$columns  = max( 1, min( 6, (int) ( $args['columns'] ?? 3 ) ) );
		$defaults = array(
			'show_image'   => true,
			'show_title'   => true,
			'show_excerpt' => true,
			'show_meta'    => true,
			'show_more'    => true,
		);
		$args      = array_merge( $defaults, $args );
		$more_text = isset( $args['more_text'] ) && '' !== $args['more_text'] ? (string) $args['more_text'] : __( 'Read more', 'emcp-tools' );

		// Real archive front-end → the main query. Editor/preview or anything that
		// isn't an archive → a sample recent-posts query so the preview isn't empty.
		$main_is_archive = ( $wp_query instanceof WP_Query ) && ( is_archive() || is_home() || is_search() );
		if ( $main_is_archive && ! self::is_preview_context() ) {
			$query    = $wp_query;
			$own      = false;
			$paginate = ! empty( $args['pagination'] );
		} else {
			$query = new WP_Query(
				array(
					'post_type'           => 'post',
					'posts_per_page'      => max( 3, $columns * 2 ),
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
				)
			);
			$own      = true;
			$paginate = false;
		}

		if ( ! $query->have_posts() ) {
			if ( $own ) {
				wp_reset_postdata();
			}
			return '<div class="emcp-dyn emcp-dyn-archive-loop"><p class="emcp-dyn-empty">' . esc_html__( 'No posts found.', 'emcp-tools' ) . '</p></div>';
		}

		$cards = array();
		while ( $query->have_posts() ) {
			$query->the_post();
			$cards[] = self::loop_card( $args, $more_text );
		}

		$grid_style = 'grid' === $layout ? ' style="--emcp-cols:' . $columns . ';"' : '';
		$html       = '<div class="emcp-dyn emcp-dyn-archive-loop emcp-dyn-archive-loop--' . $layout . '"' . $grid_style . '>' . implode( '', $cards ) . '</div>';

		if ( $paginate ) {
			$links = paginate_links(
				array(
					'total'   => (int) $query->max_num_pages,
					'current' => max( 1, (int) get_query_var( 'paged' ) ),
					'type'    => 'list',
				)
			);
			if ( $links ) {
				$html .= '<div class="emcp-dyn emcp-dyn-pagination">' . wp_kses_post( (string) $links ) . '</div>';
			}
		}

		wp_reset_postdata();
		return $html;
	}

	/**
	 * One archive-loop card for the post currently in the loop.
	 *
	 * @param array  $args      Loop args.
	 * @param string $more_text Read-more label.
	 * @return string
	 */
	private static function loop_card( array $args, string $more_text ): string {
		$card = '<article class="emcp-dyn-card">';
		if ( ! empty( $args['show_image'] ) && has_post_thumbnail() ) {
			$card .= '<a class="emcp-dyn-card-media" href="' . esc_url( (string) get_permalink() ) . '">' . get_the_post_thumbnail( null, 'medium_large' ) . '</a>';
		}
		$card .= '<div class="emcp-dyn-card-body">';
		if ( ! empty( $args['show_meta'] ) ) {
			$card .= '<div class="emcp-dyn-card-meta">' . esc_html( (string) get_the_date() ) . '</div>';
		}
		if ( ! empty( $args['show_title'] ) ) {
			$card .= '<h3 class="emcp-dyn-card-title"><a href="' . esc_url( (string) get_permalink() ) . '">' . esc_html( (string) get_the_title() ) . '</a></h3>';
		}
		if ( ! empty( $args['show_excerpt'] ) ) {
			$card .= '<div class="emcp-dyn-card-excerpt">' . esc_html( wp_trim_words( (string) get_the_excerpt(), 24, '&hellip;' ) ) . '</div>';
		}
		if ( ! empty( $args['show_more'] ) ) {
			$card .= '<a class="emcp-dyn-card-more" href="' . esc_url( (string) get_permalink() ) . '">' . esc_html( $more_text ) . '</a>';
		}
		return $card . '</div></article>';
	}

	/**
	 * Whether we're rendering in an editor / preview context (Elementor editor or
	 * preview, the block editor's ServerSideRender REST call, or wp-admin) rather
	 * than a live front-end request. Dynamic elements that depend on the main
	 * query fall back to sample content here so the preview isn't empty.
	 *
	 * @return bool
	 */
	public static function is_preview_context(): bool {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		if ( is_admin() ) {
			return true;
		}
		if ( class_exists( '\\Elementor\\Plugin' ) ) {
			$plugin = \Elementor\Plugin::$instance;
			if ( isset( $plugin->editor ) && $plugin->editor->is_edit_mode() ) {
				return true;
			}
			if ( isset( $plugin->preview ) && $plugin->preview->is_preview_mode() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The dynamic elements catalog: key => label + which template types they suit.
	 * Drives both the Gutenberg registration and the Elementor widget list.
	 *
	 * @return array<string,array{label:string,icon:string}>
	 */
	public static function catalog(): array {
		return array(
			'post-title'    => array( 'label' => __( 'Post/Page Title', 'emcp-tools' ), 'icon' => 'heading' ),
			'archive-title' => array( 'label' => __( 'Archive Title', 'emcp-tools' ), 'icon' => 'archive' ),
			'breadcrumbs'   => array( 'label' => __( 'Breadcrumbs', 'emcp-tools' ), 'icon' => 'admin-links' ),
			'post-meta'     => array( 'label' => __( 'Post Meta', 'emcp-tools' ), 'icon' => 'list-view' ),
			'site-logo'     => array( 'label' => __( 'Site Logo', 'emcp-tools' ), 'icon' => 'format-image' ),
			'site-title'    => array( 'label' => __( 'Site Title', 'emcp-tools' ), 'icon' => 'admin-home' ),
			'nav-menu'      => array( 'label' => __( 'Menu', 'emcp-tools' ), 'icon' => 'menu' ),
			'description'   => array( 'label' => __( 'Description', 'emcp-tools' ), 'icon' => 'text' ),
			'post-content'  => array( 'label' => __( 'Post Content', 'emcp-tools' ), 'icon' => 'media-document' ),
			'archive-loop'  => array( 'label' => __( 'Archive Posts', 'emcp-tools' ), 'icon' => 'grid-view' ),
		);
	}

	/**
	 * Translate a builder's attributes/settings into provider args. Shared by the
	 * Gutenberg blocks and the Elementor widgets — both use the same attribute
	 * keys (`tag`, `link`, `showPrefix`, `showDate`, …). Truthy values from either
	 * builder (block `true`/`false` or Elementor `'yes'`/`''`) normalize correctly.
	 *
	 * @param string $key Catalog key.
	 * @param array  $a   Raw attributes/settings.
	 * @return array
	 */
	public static function args_from( string $key, array $a ): array {
		switch ( $key ) {
			case 'post-title':
				return array( 'tag' => (string) ( $a['tag'] ?? 'h1' ), 'link' => ! empty( $a['link'] ) );
			case 'archive-title':
				return array( 'tag' => (string) ( $a['tag'] ?? 'h1' ), 'show_prefix' => ! empty( $a['showPrefix'] ) );
			case 'breadcrumbs':
				return array( 'separator' => (string) ( $a['separator'] ?? '/' ), 'home_label' => (string) ( $a['homeLabel'] ?? '' ) );
			case 'post-meta':
				$items = array();
				if ( ! empty( $a['showDate'] ) ) { $items[] = 'date'; }
				if ( ! empty( $a['showAuthor'] ) ) { $items[] = 'author'; }
				if ( ! empty( $a['showCategories'] ) ) { $items[] = 'categories'; }
				if ( ! empty( $a['showTags'] ) ) { $items[] = 'tags'; }
				if ( ! empty( $a['showComments'] ) ) { $items[] = 'comments'; }
				return array( 'items' => $items );
			case 'site-logo':
				return array( 'max_width' => (int) ( $a['maxWidth'] ?? 0 ) );
			case 'site-title':
				return array( 'tag' => (string) ( $a['tag'] ?? 'span' ), 'show_tagline' => ! empty( $a['showTagline'] ) );
			case 'nav-menu':
				return array( 'menu' => (int) ( $a['menuId'] ?? 0 ) );
			case 'description':
				return array( 'length' => (int) ( $a['length'] ?? 0 ) );
			case 'archive-loop':
				return array(
					'layout'       => (string) ( $a['layout'] ?? 'grid' ),
					'columns'      => (int) ( $a['columns'] ?? 3 ),
					'show_image'   => ! empty( $a['showImage'] ),
					'show_title'   => ! empty( $a['showTitle'] ),
					'show_excerpt' => ! empty( $a['showExcerpt'] ),
					'show_meta'    => ! empty( $a['showMeta'] ),
					'show_more'    => ! empty( $a['showMore'] ),
					'more_text'    => (string) ( $a['moreText'] ?? '' ),
					'pagination'   => ! empty( $a['pagination'] ),
				);
		}
		return array();
	}

	/**
	 * Dispatch a catalog key to its provider method (shared by both builders).
	 *
	 * @param string $key  Catalog key.
	 * @param array  $args Element args.
	 * @return string
	 */
	public static function render( string $key, array $args = array() ): string {
		switch ( $key ) {
			case 'post-title':
				return self::post_title( $args );
			case 'archive-title':
				return self::archive_title( $args );
			case 'breadcrumbs':
				return self::breadcrumbs( $args );
			case 'post-meta':
				return self::post_meta( $args );
			case 'site-logo':
				return self::site_logo( $args );
			case 'site-title':
				return self::site_title( $args );
			case 'nav-menu':
				return self::nav_menu( $args );
			case 'description':
				return self::description( $args );
			case 'post-content':
				return self::post_content();
			case 'archive-loop':
				return self::archive_loop( $args );
		}
		return '';
	}
}
