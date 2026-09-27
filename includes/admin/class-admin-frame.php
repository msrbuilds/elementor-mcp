<?php
/**
 * Frame markup for the admin redesign (spec 5.1, 5.2).
 *
 * Every method returns a string built only from escaped parts, so views can
 * print it and tests can assert on it.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-rendered admin frame.
 */
final class EMCP_Tools_Admin_Frame {


	/**
	 * Announcements for the promo bar, in display order.
	 *
	 * @param bool $show_upgrade Whether the site has no Pro licence.
	 * @return array[]
	 */
	public static function announcements( bool $show_upgrade ): array {
		$list = array(
			array(
				'key'   => 'cloud',
				'badge' => __( 'New', 'emcp-tools' ),
				'icon'  => 'cloud',
				'title' => __( 'EMCP Cloud is live.', 'emcp-tools' ),
				'text'  => __( 'Back up, sync and sell your blocks, widgets and snippets across every site you run.', 'emcp-tools' ),
				'cta'   => __( 'Explore Cloud', 'emcp-tools' ),
				'url'   => 'https://emcptools.com/cloud',
			),
		);
		if ( $show_upgrade ) {
			$upgrade = function_exists( 'emcp_tools_upgrade_url' ) ? emcp_tools_upgrade_url() : 'https://emcptools.com/pricing';
			$list[]  = array(
				'key'   => 'pro',
				'badge' => __( 'Pro', 'emcp-tools' ),
				'icon'  => 'crown',
				'title' => __( 'Upgrade to EMCP Pro.', 'emcp-tools' ),
				'text'  => __( 'Unlock AI Chat, the Widget and Block Builder, Templates, Skills and scheduled backups.', 'emcp-tools' ),
				'cta'   => __( 'Upgrade to Pro', 'emcp-tools' ),
				'url'   => $upgrade,
			);
			$list[]  = array(
				'key'   => 'ltd',
				'badge' => __( 'Limited', 'emcp-tools' ),
				'icon'  => 'sparkles',
				'title' => __( 'Lifetime deal ends soon.', 'emcp-tools' ),
				'text'  => __( 'Pay once and own EMCP Pro forever. This lifetime deal is going away for good.', 'emcp-tools' ),
				'cta'   => __( 'Get the LTD', 'emcp-tools' ),
				'url'   => $upgrade,
			);
		}
		$list[] = array(
			'key'   => 'whatsnew',
			'badge' => EMCP_TOOLS_VERSION,
			'icon'  => 'gift',
			/* translators: %s: plugin version. */
			'title' => sprintf( __( 'What\'s new in %s.', 'emcp-tools' ), EMCP_TOOLS_VERSION ),
			'text'  => __( 'A redesigned admin with a Dashboard, History sessions and undo, an MCP Log and scheduled backups.', 'emcp-tools' ),
			'cta'   => __( 'See what\'s new', 'emcp-tools' ),
			'url'   => admin_url( 'admin.php?page=' . EMCP_Tools_Admin::PAGE_SLUG . '-changelog' ),
		);
		return $list;
	}

	/**
	 * The announcement bar: every announcement as a slide, rotated by the shell
	 * (promo.js), with previous/next arrows and dots beside the CTA. It cannot
	 * be dismissed. Slides after the first are hidden until the shell shows them.
	 * Each slide names its colour (data-emcp-promo-tone, the announcement key);
	 * the bar's data-tone follows the slide on show.
	 *
	 * @param array[] $announcements From announcements().
	 */
	public static function promo( array $announcements ): string {
		$announcements = array_values( $announcements );
		$count         = count( $announcements );
		if ( 0 === $count ) {
			return '';
		}
		$slides = '';
		$dots   = '';
		foreach ( $announcements as $i => $a ) {
			$external = 0 === strpos( (string) $a['url'], 'http' ) && 0 !== strpos( (string) $a['url'], admin_url() );
			$slides  .= '<div class="eui-frame-promo__slide' . ( 0 === $i ? ' is-active"' : '" hidden' )
				. ' data-emcp-promo-tone="' . esc_attr( $a['key'] ) . '"'
				. ' role="group" aria-roledescription="slide" aria-label="' . esc_attr(
					/* translators: 1: slide number, 2: slide count. */
					sprintf( __( '%1$d of %2$d', 'emcp-tools' ), $i + 1, $count )
				) . '">'
				. '<span class="eui-frame-promo__badge">' . esc_html( $a['badge'] ) . '</span>'
				. EMCP_Tools_Admin_Icons::svg( $a['icon'] ?? 'cloud', 16 )
				. '<p class="eui-frame-promo__text"><strong>' . esc_html( $a['title'] ) . '</strong> ' . esc_html( $a['text'] ) . '</p>'
				. '<a class="eui-frame-promo__cta" href="' . esc_url( $a['url'] ) . '"' . ( $external ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html( $a['cta'] ) . EMCP_Tools_Admin_Icons::svg( 'arrow-right', 14 ) . '</a>'
				. '</div>';
			$dots .= '<button type="button" class="eui-frame-promo__dot" data-emcp-promo-dot="' . (int) $i . '"' . ( 0 === $i ? ' aria-current="true"' : '' )
				. ' aria-label="' . esc_attr(
					/* translators: %d: announcement number. */
					sprintf( __( 'Show announcement %d', 'emcp-tools' ), $i + 1 )
				) . '"></button>';
		}
		$nav = '';
		if ( $count > 1 ) {
			$nav = '<div class="eui-frame-promo__nav">'
				. '<button type="button" class="eui-frame-promo__arrow" data-emcp-promo-prev aria-label="' . esc_attr__( 'Previous announcement', 'emcp-tools' ) . '">' . EMCP_Tools_Admin_Icons::svg( 'chevron-left', 16 ) . '</button>'
				. '<span class="eui-frame-promo__dots">' . $dots . '</span>'
				. '<button type="button" class="eui-frame-promo__arrow" data-emcp-promo-next aria-label="' . esc_attr__( 'Next announcement', 'emcp-tools' ) . '">' . EMCP_Tools_Admin_Icons::svg( 'chevron-right', 16 ) . '</button>'
				. '</div>';
		}
		return '<div class="eui-frame-promo" data-emcp-promo data-tone="' . esc_attr( $announcements[0]['key'] ) . '" role="region" aria-roledescription="carousel" aria-label="' . esc_attr__( 'Announcements', 'emcp-tools' ) . '">'
			. '<div class="eui-frame-promo__slides">' . $slides . '</div>'
			. $nav
			. '</div>';
	}

	/**
	 * The sidebar.
	 *
	 * @param EMCP_Tools_Admin_Nav $nav        Registry.
	 * @param string               $active_tab Current tab id.
	 * @param string               $version    Plugin version.
	 * @param bool                 $premium    Whether Pro is licensed.
	 */
	public static function sidebar( EMCP_Tools_Admin_Nav $nav, string $active_tab, string $version, bool $premium ): string {
		// A div, not <aside>: the frame sits inside core's role="main" (#wpbody-content),
		// where a nested complementary landmark is an accessibility error.
		$html  = '<div class="eui-frame__sidebar">';
		$html .= '<div class="eui-frame-brand"><span class="eui-frame-brand__logo" aria-hidden="true">' . EMCP_Tools_Admin_Icons::svg( 'blocks', 18 ) . '</span>'
			. '<span class="eui-frame-brand__text"><span class="eui-frame-brand__name">' . esc_html__( 'EMCP Tools', 'emcp-tools' ) . '</span>'
			. '<span class="eui-frame-brand__version eui-mono">v' . esc_html( $version ) . ' · ' . esc_html( $premium ? __( 'Pro', 'emcp-tools' ) : __( 'Free', 'emcp-tools' ) ) . '</span></span></div>';
		$html .= '<button type="button" class="eui-frame-search" data-emcp-palette-open>' . EMCP_Tools_Admin_Icons::svg( 'search', 16 )
			. '<span class="eui-frame-search__label">' . esc_html__( 'Search...', 'emcp-tools' ) . '</span>'
			. '<kbd class="eui-frame-search__kbd">' . esc_html__( 'Ctrl K', 'emcp-tools' ) . '</kbd></button>';
		$html .= '<nav class="eui-frame-nav" aria-label="' . esc_attr__( 'Main', 'emcp-tools' ) . '">';
		foreach ( $nav->groups() as $group ) {
			$gid   = 'eui-frame-group-' . $group['id'];
			$html .= '<p class="eui-frame-nav__group" id="' . esc_attr( $gid ) . '">' . esc_html( $group['label'] ) . '</p><ul class="eui-frame-nav__list" aria-labelledby="' . esc_attr( $gid ) . '">';
			foreach ( $group['items'] as $item ) {
				$html .= '<li>' . self::nav_link( $item, $item['id'] === $active_tab ) . '</li>';
			}
			$html .= '</ul>';
		}
		$html .= '</nav><ul class="eui-frame-nav__list eui-frame-nav__footer">';
		foreach ( $nav->footer() as $item ) {
			$html .= '<li>' . self::nav_link( $item, $item['id'] === $active_tab ) . '</li>';
		}
		return $html . '</ul></div>';
	}

	/**
	 * One sidebar link.
	 *
	 * @param array $item   Entry or footer item.
	 * @param bool  $active Whether it is the current page.
	 */
	private static function nav_link( array $item, bool $active ): string {
		$external = ! empty( $item['external'] );
		$attrs    = ' href="' . esc_url( $item['url'] ) . '" class="eui-frame-nav__item' . ( $active ? ' is-active' : '' ) . '" data-emcp-nav';
		$attrs   .= $active ? ' aria-current="page"' : '';
		$attrs   .= $external ? ' target="_blank" rel="noopener noreferrer"' : '';
		$tail     = '';
		if ( isset( $item['count'] ) && null !== $item['count'] ) {
			$tail = '<span class="eui-frame-nav__count eui-mono">' . (int) $item['count'] . '</span>';
		} elseif ( ! empty( $item['badge'] ) ) {
			$tail = '<span class="eui-frame-nav__badge">' . esc_html( $item['badge'] ) . '</span>';
		}
		return '<a' . $attrs . '>' . EMCP_Tools_Admin_Icons::svg( $item['icon'], 16 ) . '<span class="eui-frame-nav__label">' . esc_html( $item['label'] ) . '</span>' . $tail . '</a>';
	}

	/**
	 * The top bar.
	 *
	 * @param string[] $crumbs Breadcrumb labels, current last.
	 * @param array    $status EMCP_Tools_Admin_Bar::status().
	 * @param int      $unread Unread notification count.
	 * @param array    $user   user_summary().
	 */
	public static function topbar( array $crumbs, array $status, int $unread, array $user ): string {
		// A div, not <header>: a banner landmark cannot sit inside core's role="main".
		$html = '<div class="eui-frame__topbar"><nav aria-label="' . esc_attr__( 'Breadcrumb', 'emcp-tools' ) . '"><ol class="eui-frame-crumbs">';
		$last = count( $crumbs ) - 1;
		foreach ( array_values( $crumbs ) as $i => $label ) {
			$html .= $i === $last ? '<li aria-current="page">' . esc_html( $label ) . '</li>' : '<li>' . esc_html( $label ) . '</li>';
		}
		$html .= '</ol></nav><div class="eui-frame__topbar-actions">';

		$color  = $status['color'] ?? 'grey';
		$labels = array(
			'green' => __( 'Server online', 'emcp-tools' ),
			'grey'  => __( 'Server off', 'emcp-tools' ),
			'red'   => __( 'Abilities API missing', 'emcp-tools' ),
		);
		if ( ! isset( $labels[ $color ] ) ) {
			$color = 'grey';
		}
		$html .= '<a class="eui-frame-status is-' . esc_attr( $color ) . '" href="' . esc_url( EMCP_Tools_Admin_Nav::url( 'connection' ) ) . '"><span class="eui-frame-status__dot" aria-hidden="true"></span>' . esc_html( $labels[ $color ] ) . '</a>';
		$html .= '<a class="eui-frame-iconlink" href="' . esc_url( EMCP_Tools_Admin_Nav::url( 'changelog' ) ) . '" aria-label="' . esc_attr__( 'Changelog', 'emcp-tools' ) . '" title="' . esc_attr__( 'Changelog', 'emcp-tools' ) . '">' . EMCP_Tools_Admin_Icons::svg( 'gift', 18 ) . '</a>';
		$bell_label = $unread > 0
			/* translators: %d: number of unread notifications. */
			? sprintf( _n( 'Notifications (%d unread)', 'Notifications (%d unread)', $unread, 'emcp-tools' ), $unread )
			: __( 'Notifications', 'emcp-tools' );
		$html      .= '<button type="button" class="eui-frame-iconlink eui-frame-bell" data-emcp-notifications-open aria-label="' . esc_attr( $bell_label ) . '" title="' . esc_attr( $bell_label ) . '">' . EMCP_Tools_Admin_Icons::svg( 'bell', 18 )
			. '<span class="eui-frame-bell__dot" data-emcp-unread' . ( $unread > 0 ? '' : ' hidden' ) . '></span></button>';
		$avatar     = '' !== ( $user['avatar'] ?? '' )
			? '<img class="eui-frame-avatar" src="' . esc_url( $user['avatar'] ) . '" alt="" width="32" height="32" />'
			: '<span class="eui-frame-avatar" aria-hidden="true">' . esc_html( $user['initials'] ?? '' ) . '</span>';
		return $html . '<span class="eui-frame-user" title="' . esc_attr( $user['name'] ?? '' ) . '">' . $avatar . '<span class="eui-visually-hidden">' . esc_html( $user['name'] ?? '' ) . '</span></span></div></div>';
	}

	/** The card for a tab with no screen: switched off, or an unknown slug. */
	public static function unavailable(): string {
		return '<div class="eui-card emcp-unavailable"><div class="eui-card__body">'
			. '<h1>' . esc_html__( 'This page isn’t available', 'emcp-tools' ) . '</h1>'
			. '<p>' . esc_html__( 'This screen is switched off or needs a module that isn’t active.', 'emcp-tools' ) . '</p>'
			. '<p><a class="eui-btn eui-btn--primary eui-btn--md" href="' . esc_url( admin_url( 'admin.php?page=' . EMCP_Tools_Admin::PAGE_SLUG ) ) . '">' . esc_html__( 'Go to the Dashboard', 'emcp-tools' ) . '</a></p>'
			. '</div></div>';
	}

	/**
	 * The React mount point with its server-rendered fallback (spec 5.2 layer 1).
	 *
	 * @param string $screen_id Screen id.
	 */
	public static function screen_container( string $screen_id ): string {
		return '<div id="emcp-screen" class="eui-frame-screen" data-screen="' . esc_attr( $screen_id ) . '">'
			. '<div data-emcp-fallback class="eui-frame-fallback">'
			. '<div class="eui-skeleton" aria-hidden="true"><span class="eui-skeleton__line"></span><span class="eui-skeleton__line"></span><span class="eui-skeleton__line"></span></div>'
			. '<div class="eui-card eui-card__body eui-frame-recovery" data-emcp-recovery hidden role="alert">'
			. '<h2 class="eui-card__title">' . esc_html__( "This screen didn't load", 'emcp-tools' ) . '</h2>'
			. '<p>' . esc_html__( 'Reload the page. If it keeps happening, copy the details below and send them to support.', 'emcp-tools' ) . '</p>'
			. '<pre class="eui-frame-recovery__details eui-mono" data-emcp-recovery-details></pre>'
			. '<div class="eui-frame-recovery__actions">'
			. '<button type="button" class="eui-btn eui-btn--primary eui-btn--md" data-emcp-reload>' . esc_html__( 'Reload', 'emcp-tools' ) . '</button>'
			. '<button type="button" class="eui-btn eui-btn--secondary eui-btn--md" data-emcp-copy>' . esc_html__( 'Copy details', 'emcp-tools' ) . '</button>'
			. '</div></div>'
			. '<noscript><div class="eui-notice eui-notice--warning">' . esc_html__( 'This screen needs JavaScript. Turn it on in your browser and reload.', 'emcp-tools' ) . '</div></noscript>'
			. '</div><div data-emcp-root></div></div>';
	}

	/**
	 * Contents of the built fallback script (printed inline after the container).
	 */
	public static function fallback_script(): string {
		$file = EMCP_TOOLS_DIR . 'assets/admin/build/fallback.js';
		return is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Body classes for EMCP screens: fold the WordPress menu unless the user expanded it.
	 *
	 * @param string $classes Existing admin body classes.
	 * @param string $mfold   get_user_setting( 'mfold' ): 'o' = expanded, 'f' = folded.
	 */
	public static function body_class( string $classes, string $mfold ): string {
		$classes .= ' emcp-admin-frame';
		if ( 'o' !== $mfold && false === strpos( ' ' . $classes . ' ', ' folded ' ) ) {
			$classes .= ' folded';
		}
		return $classes;
	}

	/**
	 * Name, initials and avatar of a user.
	 *
	 * @param object $user WP_User-like (ID, display_name).
	 * @return array{name:string,initials:string,avatar:string}
	 */
	public static function user_summary( $user ): array {
		$name  = (string) ( $user->display_name ?? '' );
		$parts = preg_split( '/\s+/', trim( $name ) );
		$init  = '';
		foreach ( array_slice( array_filter( (array) $parts ), 0, 2 ) as $part ) {
			$init .= function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $part, 0, 1 ) ) : strtoupper( substr( $part, 0, 1 ) );
		}
		return array(
			'name'     => $name,
			'initials' => $init,
			'avatar'   => function_exists( 'get_avatar_url' ) ? (string) get_avatar_url( (int) ( $user->ID ?? 0 ), array( 'size' => 64 ) ) : '',
		);
	}
}
