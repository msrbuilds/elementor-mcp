# Widget Tool Consolidation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Collapse the ~62 per-widget convenience MCP tools into 5 catalog-backed tools (`list-widgets`, `get-widget-schema`, `add-free-widget`, `add-pro-widget`, `update-widget`) without losing any capability, cutting the per-turn tool-list token cost ~10×.

**Architecture:** A curated PHP **widget catalog** (data) becomes the single source of truth for widget metadata + curated params. Five lean tools *serve* the catalog (discover → inspect → act) instead of *being* 62 fat tool schemas. The catalog is harvested mechanically from the existing convenience-tool registrations in `class-widget-abilities.php`, so nothing is re-authored. Clean break at v3.0.0 — the 62 old tool names are removed (no aliases).

**Tech Stack:** WordPress plugin (PHP 8.2+), WordPress Abilities API, bundled MCP Adapter, PHPUnit 9 (BrainMonkey-style stubs via the existing `Ability_Test_Case`).

---

## Background context for the implementing engineer

You have **zero assumed context**. Read these before starting:

- **The spec:** [docs/WIDGET_CONSOLIDATION_PLAN.md](../../WIDGET_CONSOLIDATION_PLAN.md) — the approved design. This plan implements it.
- **The file you're gutting:** [includes/abilities/class-widget-abilities.php](../../../includes/abilities/class-widget-abilities.php) (~2540 lines). It currently registers `add-widget`, `update-widget`, and 62 `register_add_*()` convenience methods. Each convenience method calls `register_convenience_tool($name, $label, $description, $extra_props, $required, $widget_type, $defaults)`.
- **The discovery tools:** [includes/abilities/class-query-abilities.php](../../../includes/abilities/class-query-abilities.php) — `execute_list_widgets()` (line ~168) and `execute_get_widget_schema()` (line ~244). You'll enhance both.
- **How abilities register:** every tool goes through the global `emcp_tools_register_ability( $name, $args )` shim in [includes/class-schema-compat.php](../../../includes/class-schema-compat.php). Always use it — never call `wp_register_ability()` directly.
- **The insert engine:** `EMCP_Tools_Widget_Abilities::execute_add_widget()` (line ~258) is the real insert path: validate widget type → `$this->data->get_page_data()` → `$this->factory->create_widget()` → `$this->data->insert_element()` → `$this->data->save_page_data()`. The new `add-free-widget`/`add-pro-widget` reuse this exact engine.
- **Tests:** PHPUnit config is `phpunit.xml.dist`; run from plugin root. Test base class is [tests/unit/class-ability-test-case.php](../../../tests/unit/class-ability-test-case.php). Existing widget test: [tests/unit/capabilities/WidgetCapabilityTest.php](../../../tests/unit/capabilities/WidgetCapabilityTest.php). In the test env `ELEMENTOR_PRO_VERSION` is **not** defined and `WooCommerce` does not exist — that's how Pro/Woo gating is tested.

**Run the full suite any time with:**
```
vendor/bin/phpunit --configuration phpunit.xml.dist
```
Expected baseline before you start: green (36 tests per the project memory; confirm with a run).

**WP-CLI MCP protocol check** (used in later verification tasks):
```
/c/wp-cli/wp-cli.phar mcp-adapter list --path=f:/laragon/www/msrplugins
```

---

## File Structure

**New files:**
- `includes/widgets/class-widget-catalog.php` — Catalog accessor class `EMCP_Tools_Widget_Catalog`. Public static API: `get()`, `get_widget($type)`, `by_tier($tier)`, `search($query)`, `is_pro($type)`, `tier_of($type)`, `all_types()`. Lazily loads + merges the three data partials, caches in a static.
- `includes/widgets/catalog-free.php` — returns an array of free + core widget definitions (harvested from the free convenience tools).
- `includes/widgets/catalog-pro.php` — returns an array of Elementor Pro widget definitions.
- `includes/widgets/catalog-woo.php` — returns an array of WooCommerce widget definitions.
- `tests/unit/widgets/WidgetCatalogTest.php` — catalog accessor + harvest-integrity tests.
- `tests/unit/widgets/CatalogToolsTest.php` — `add-free-widget`/`add-pro-widget`/enhanced discovery registration + gating + execution tests.

**Modified files:**
- `includes/abilities/class-widget-abilities.php` — remove the 62 `register_add_*()` methods + `register_convenience_tool()` + `slim_convenience_props()` + `execute_convenience_tool()`; add `register_add_free_widget()`, `register_add_pro_widget()`, their executors, and a shared `insert_catalog_widget()` helper; keep `register_update_widget()` + `execute_update_widget()` + `execute_add_widget()` (now private engine) + `check_edit_permission()`.
- `includes/abilities/class-query-abilities.php` — enhance `execute_list_widgets()` (catalog merge, `tier`/`category`/`search` filters, compact output) and `execute_get_widget_schema()` (curated-by-default, `types[]` batch, `full` escape hatch).
- `includes/class-bootstrap.php` — `require_once` the catalog accessor before the ability classes.
- `includes/class-plugin.php` — `get_essential_tool_slugs()`: swap per-widget essentials for the 5 new names.
- `includes/admin/class-admin.php` — `get_tool_catalog()`: replace the `widget_universal` + `widget_core` + `widget_pro` categories with a single 5-tool `widgets` category; bump `DEFAULTS_VERSION` to 5 and add a v5 step that clears orphaned per-widget disabled slugs.
- `emcp-tools.php` — version → `3.0.0`; `EMCP_TOOLS_VERSION` constant → `3.0.0`.
- `CLAUDE.md`, `README.md`, `readme.txt`, `CHANGELOG.md` — tool counts + tool tables + migration note.

**Design boundary:** the catalog is **data**, the accessor is a **thin read API**, the abilities are **thin serve/act wrappers**. None reaches into another's internals — abilities call `EMCP_Tools_Widget_Catalog::get_widget($type)`, never read the partials directly.

---

## The catalog data format (the contract every task depends on)

Each widget is one entry keyed by its Elementor `widget_type`. This is the canonical shape — every task uses these exact key names:

```php
'heading' => array(
    'tier'     => 'free',                 // 'free' | 'pro' | 'woo'
    'title'    => 'Heading',              // human label (from the old tool $label, minus " (Pro)")
    'category' => 'basic',                // grouping for list-widgets filter
    'requires' => null,                   // null | 'elementor-pro' | 'woocommerce'
    'use_case' => 'Section titles and headlines. One h1 per page; h2/h3 for section headers.',
    'keywords' => array( 'title', 'text', 'heading', 'h1', 'h2' ),
    'params'   => array(                  // VERBATIM from the old tool's $extra_props
        'title'       => array( 'type' => 'string', 'description' => 'Heading text.' ),
        'header_size' => array( 'type' => 'string', 'enum' => array( 'h1','h2','h3','h4','h5','h6','div','span','p' ), 'description' => 'HTML tag. Default: h2.' ),
        // …all remaining extra_props, unchanged…
    ),
    'required' => array( 'title' ),       // VERBATIM from the old tool's $required
    'defaults' => array( 'header_size' => 'h2' ), // VERBATIM from the old tool's $defaults
),
```

**Harvest transformation rule (mechanical, no judgement except `use_case`):**

For each `register_add_X()` method in `class-widget-abilities.php`, its `register_convenience_tool()` call has positional args `($name, $label, $description, $extra_props, $required, $widget_type, $defaults=array())`. Map them:

| Catalog key | Source | Transformation |
|---|---|---|
| array key | `$widget_type` (6th arg) | exact string |
| `tier` | which `register()` block the call lives in | free/core block → `'free'`; Pro block (`if ( defined( 'ELEMENTOR_PRO_VERSION' ) )`) → `'pro'`; WooCommerce sub-block → `'woo'` |
| `title` | `$label` (2nd arg) | strip the literal `__( … )` wrapper; drop a trailing `" (Pro)"` |
| `requires` | tier | free→`null`, pro→`'elementor-pro'`, woo→`'woocommerce'` |
| `use_case` | **new prose** | one sentence, derived from `$description` (3rd arg) + widget purpose. The only authored field. |
| `keywords` | `$widget_type` + `title` words | lowercase tokens; include the type, title words, and obvious synonyms |
| `params` | `$extra_props` (4th arg) | **copied verbatim**, `__()` wrappers unwrapped to plain strings |
| `required` | `$required` (5th arg) | copied verbatim |
| `defaults` | `$defaults` (7th arg) | copied verbatim (omit/`array()` if absent) |

The `__()` translation wrappers in `params` descriptions are unwrapped to plain English strings (the catalog is data, not registered i18n strings; tool-level strings that ARE shown to users stay translated in the ability registration, not here).

**The 62 widgets to harvest, by tier** (counted from the `register()` body in `class-widget-abilities.php`, lines ~95–164):

- **Free/core (27):** heading, text-editor, image, button, video, icon, spacer, divider, icon-box (9 core) + accordion, alert, counter, google_maps, icon-list, image-box, image-carousel, progress, social-icons, star-rating, tabs, testimonial, toggle, html, menu-anchor, shortcode, rating, text-path (18 extended).
- **Pro (30):** form, posts-grid (`posts`), countdown, price-table, flip-box, animated-headline, call-to-action, slides, testimonial-carousel, price-list, gallery, share-buttons, table-of-contents, blockquote, lottie, hotspot, nav-menu, loop-grid, loop-carousel, media-carousel, nested-tabs, nested-accordion, portfolio, author-box, login, code-highlight, reviews, off-canvas, progress-tracker, search.
- **Woo (5):** wc-products, wc-add-to-cart, wc-cart, wc-checkout, wc-menu-cart.

> ⚠️ **No silent drops.** The harvest must produce one catalog entry per registered convenience tool. Task 2 includes a guard test that fails if the catalog count ≠ the documented count per tier. If a widget's `register_add_*` method differs from the table above, trust the **code**, not the table, and note the discrepancy in the commit message.

---

## Task 1: Scaffold the catalog accessor (empty partials, real API)

**Files:**
- Create: `includes/widgets/class-widget-catalog.php`
- Create: `includes/widgets/catalog-free.php`
- Create: `includes/widgets/catalog-pro.php`
- Create: `includes/widgets/catalog-woo.php`
- Create: `tests/unit/widgets/WidgetCatalogTest.php`
- Modify: `includes/class-bootstrap.php` (add `require_once` in `load_classes()`)

- [ ] **Step 1: Write the failing test**

Create `tests/unit/widgets/WidgetCatalogTest.php`:

```php
<?php
/**
 * Widget catalog accessor tests.
 * @group widgets
 * @package EMCP_Tools\Tests\Widgets
 */
namespace EMCP_Tools\Tests\Widgets;

require_once dirname(__DIR__) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class WidgetCatalogTest extends Ability_Test_Case {

    /** @test */
    public function test_get_returns_keyed_array(): void {
        $catalog = \EMCP_Tools_Widget_Catalog::get();
        $this->assertIsArray($catalog);
        $this->assertArrayHasKey('heading', $catalog, 'heading must be cataloged');
    }

    /** @test */
    public function test_get_widget_returns_entry_with_required_keys(): void {
        $heading = \EMCP_Tools_Widget_Catalog::get_widget('heading');
        $this->assertIsArray($heading);
        foreach (['tier', 'title', 'category', 'use_case', 'keywords', 'params'] as $key) {
            $this->assertArrayHasKey($key, $heading, "heading entry must have '$key'");
        }
    }

    /** @test */
    public function test_get_widget_unknown_returns_null(): void {
        $this->assertNull(\EMCP_Tools_Widget_Catalog::get_widget('no-such-widget'));
    }

    /** @test */
    public function test_by_tier_filters(): void {
        $free = \EMCP_Tools_Widget_Catalog::by_tier('free');
        $this->assertArrayHasKey('heading', $free);
        $this->assertArrayNotHasKey('form', $free, 'form is Pro, not free');
    }

    /** @test */
    public function test_is_pro(): void {
        $this->assertFalse(\EMCP_Tools_Widget_Catalog::is_pro('heading'));
        $this->assertTrue(\EMCP_Tools_Widget_Catalog::is_pro('form'));
    }

    /** @test */
    public function test_search_matches_use_case_and_keywords(): void {
        $hits = \EMCP_Tools_Widget_Catalog::search('headline');
        $this->assertArrayHasKey('heading', $hits, 'search "headline" should match heading via keywords/use_case');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter WidgetCatalogTest --configuration phpunit.xml.dist`
Expected: FATAL/FAIL — `Class "EMCP_Tools_Widget_Catalog" not found`.

- [ ] **Step 3: Create the accessor class**

Create `includes/widgets/class-widget-catalog.php`:

```php
<?php
/**
 * Widget catalog accessor — single source of truth for curated widget metadata.
 *
 * Merges three data partials (free, pro, woo) into one keyed map and serves
 * read queries (by tier, by search, single lookup). The catalog is plain data;
 * the MCP widget tools (list-widgets, get-widget-schema, add-free-widget,
 * add-pro-widget) serve it instead of carrying 62 fat schemas of their own.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read API over the widget catalog data partials.
 *
 * @since 3.0.0
 */
class EMCP_Tools_Widget_Catalog {

	/**
	 * Merged catalog cache (keyed by widget_type).
	 *
	 * @var array<string,array>|null
	 */
	private static $catalog = null;

	/**
	 * Returns the full merged catalog, keyed by widget_type.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string,array>
	 */
	public static function get(): array {
		if ( null === self::$catalog ) {
			$free = (array) require __DIR__ . '/catalog-free.php';
			$pro  = (array) require __DIR__ . '/catalog-pro.php';
			$woo  = (array) require __DIR__ . '/catalog-woo.php';
			self::$catalog = array_merge( $free, $pro, $woo );
		}
		return self::$catalog;
	}

	/**
	 * Returns a single widget's catalog entry, or null if not cataloged.
	 *
	 * @since 3.0.0
	 *
	 * @param string $type Widget type.
	 * @return array|null
	 */
	public static function get_widget( string $type ) {
		$catalog = self::get();
		return $catalog[ $type ] ?? null;
	}

	/**
	 * Returns all cataloged widget types.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	public static function all_types(): array {
		return array_keys( self::get() );
	}

	/**
	 * Returns the catalog filtered to a single tier ('free' | 'pro' | 'woo').
	 *
	 * @since 3.0.0
	 *
	 * @param string $tier Tier slug.
	 * @return array<string,array>
	 */
	public static function by_tier( string $tier ): array {
		return array_filter(
			self::get(),
			static function ( $entry ) use ( $tier ) {
				return ( $entry['tier'] ?? 'free' ) === $tier;
			}
		);
	}

	/**
	 * Returns the tier of a widget ('free' default if uncataloged).
	 *
	 * @since 3.0.0
	 *
	 * @param string $type Widget type.
	 * @return string
	 */
	public static function tier_of( string $type ): string {
		$entry = self::get_widget( $type );
		return $entry['tier'] ?? 'free';
	}

	/**
	 * Whether a widget is in the Pro or Woo tier (i.e. needs Elementor Pro).
	 *
	 * @since 3.0.0
	 *
	 * @param string $type Widget type.
	 * @return bool
	 */
	public static function is_pro( string $type ): bool {
		$tier = self::tier_of( $type );
		return 'pro' === $tier || 'woo' === $tier;
	}

	/**
	 * Intent search across type, title, use_case, and keywords (case-insensitive
	 * substring). Returns the matching subset of the catalog, keyed by type.
	 *
	 * @since 3.0.0
	 *
	 * @param string $query Search query.
	 * @return array<string,array>
	 */
	public static function search( string $query ): array {
		$query = strtolower( trim( $query ) );
		if ( '' === $query ) {
			return self::get();
		}
		return array_filter(
			self::get(),
			static function ( $entry, $type ) use ( $query ) {
				$haystack = strtolower(
					$type . ' '
					. ( $entry['title'] ?? '' ) . ' '
					. ( $entry['use_case'] ?? '' ) . ' '
					. implode( ' ', (array) ( $entry['keywords'] ?? array() ) )
				);
				return false !== strpos( $haystack, $query );
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Clears the in-memory cache (test seam).
	 *
	 * @since 3.0.0
	 */
	public static function flush_cache(): void {
		self::$catalog = null;
	}
}
```

- [ ] **Step 4: Create the three partials with a single seed entry each**

This task only seeds `heading` (free) and `form` (pro) so the accessor tests pass; the full harvest is Task 2. Woo starts empty.

Create `includes/widgets/catalog-free.php`:

```php
<?php
/**
 * Free + core Elementor widget catalog data.
 *
 * Harvested from the convenience-tool registrations in class-widget-abilities.php.
 * Plain data — see EMCP_Tools_Widget_Catalog for the read API.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'heading' => array(
		'tier'     => 'free',
		'title'    => 'Heading',
		'category' => 'basic',
		'requires' => null,
		'use_case' => 'Section titles and headlines. One h1 per page; h2/h3 for section headers. Supports full typography, text stroke, shadow, and blend mode.',
		'keywords' => array( 'title', 'text', 'heading', 'h1', 'h2', 'headline' ),
		'params'   => array(
			'title'       => array( 'type' => 'string', 'description' => 'Heading text.' ),
			'header_size' => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), 'description' => 'HTML tag. Default: h2.' ),
			'align'       => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right', 'justify' ), 'description' => 'Text alignment. Responsive: align_tablet, align_mobile.' ),
			'title_color' => array( 'type' => 'string', 'description' => 'Heading color (hex/rgba).' ),
			'link'        => array( 'type' => 'object', 'description' => 'Link: {url, is_external, nofollow}.' ),
		),
		'required' => array( 'title' ),
		'defaults' => array( 'header_size' => 'h2' ),
	),
);
```

Create `includes/widgets/catalog-pro.php`:

```php
<?php
/**
 * Elementor Pro widget catalog data.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'form' => array(
		'tier'     => 'pro',
		'title'    => 'Form',
		'category' => 'pro',
		'requires' => 'elementor-pro',
		'use_case' => 'Contact and lead-capture forms with configurable fields, submit actions (email, redirect, webhook, integrations), and styling.',
		'keywords' => array( 'form', 'contact', 'lead', 'input', 'submit' ),
		'params'   => array(
			'form_name'   => array( 'type' => 'string', 'description' => 'Form name.' ),
			'button_text' => array( 'type' => 'string', 'description' => 'Submit button text.' ),
		),
		'required' => array( 'form_name' ),
		'defaults' => array( 'button_text' => 'Send', 'submit_actions' => array( 'email' ) ),
	),
);
```

Create `includes/widgets/catalog-woo.php`:

```php
<?php
/**
 * WooCommerce widget catalog data.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array();
```

- [ ] **Step 5: Load the accessor in the bootstrap**

In `includes/class-bootstrap.php`, inside `load_classes()`, add the require **before** the ability classes (right after the validators block, near line 74). Find:

```php
		require_once EMCP_TOOLS_DIR . 'includes/validators/class-settings-validator.php';
```

Add immediately after it:

```php
		// Widget catalog — source of truth for the 5 catalog-backed widget tools.
		require_once EMCP_TOOLS_DIR . 'includes/widgets/class-widget-catalog.php';
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter WidgetCatalogTest --configuration phpunit.xml.dist`
Expected: PASS (6 tests). If `Class not found`, the test bootstrap doesn't autoload plugin classes — add `require_once` of the catalog file at the top of the test file (match how `WidgetCapabilityTest` reaches `EMCP_Tools_Widget_Abilities`; the bootstrap may already `require_once` plugin includes — check `tests/bootstrap.php`).

- [ ] **Step 7: Commit**

```bash
git add includes/widgets/ tests/unit/widgets/WidgetCatalogTest.php includes/class-bootstrap.php
git commit -m "feat(widgets): catalog accessor + seed partials (Task 1)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Harvest all 62 widgets into the catalog partials

**Files:**
- Modify: `includes/widgets/catalog-free.php` (27 entries)
- Modify: `includes/widgets/catalog-pro.php` (30 entries)
- Modify: `includes/widgets/catalog-woo.php` (5 entries)
- Modify: `tests/unit/widgets/WidgetCatalogTest.php` (add count-integrity tests)

This is a **mechanical transcription**. Source of truth: the `register_add_*()` methods in `includes/abilities/class-widget-abilities.php`. Apply the harvest transformation rule from the "catalog data format" section above to every method. Work tier by tier.

- [ ] **Step 1: Write the failing integrity test**

Add these methods to `tests/unit/widgets/WidgetCatalogTest.php`:

```php
    /** @test */
    public function test_free_tier_count(): void {
        $this->assertCount(27, \EMCP_Tools_Widget_Catalog::by_tier('free'),
            'Free tier must catalog exactly 27 widgets (one per free convenience tool).');
    }

    /** @test */
    public function test_pro_tier_count(): void {
        $this->assertCount(30, \EMCP_Tools_Widget_Catalog::by_tier('pro'),
            'Pro tier must catalog exactly 30 widgets.');
    }

    /** @test */
    public function test_woo_tier_count(): void {
        $this->assertCount(5, \EMCP_Tools_Widget_Catalog::by_tier('woo'),
            'Woo tier must catalog exactly 5 widgets.');
    }

    /** @test */
    public function test_every_entry_is_well_formed(): void {
        foreach (\EMCP_Tools_Widget_Catalog::get() as $type => $entry) {
            $this->assertIsString($type);
            $this->assertContains($entry['tier'], ['free', 'pro', 'woo'], "$type has a valid tier");
            $this->assertNotEmpty($entry['title'], "$type has a title");
            $this->assertNotEmpty($entry['use_case'], "$type has a use_case");
            $this->assertIsArray($entry['params'], "$type has params");
            $this->assertIsArray($entry['keywords'], "$type has keywords");
        }
    }
```

> Confirm the three counts against the actual code first: `grep -c 'register_convenience_tool(' includes/abilities/class-widget-abilities.php` gives the total (62); split free/pro/woo by the `register()` blocks. If your count differs from 27/30/5, update BOTH these test numbers and the spec's count table, and say so in the commit. The point is one catalog entry per registered tool — exactly, no drops.

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter WidgetCatalogTest --configuration phpunit.xml.dist`
Expected: FAIL — counts are 1/1/0, not 28/29/5.

- [ ] **Step 3: Harvest the free tier (28 entries)**

For each free `register_add_*` method (lines ~603–1251 in `class-widget-abilities.php`), transcribe into `catalog-free.php` using the format from "catalog data format". Copy `params` from `$extra_props` verbatim (unwrap `__()`), `required` from arg 5, `defaults` from arg 7. Author one `use_case` sentence from the `$description`. Worked example for `button` (from the `register_add_button()` at line ~712):

```php
	'button' => array(
		'tier'     => 'free',
		'title'    => 'Button',
		'category' => 'basic',
		'requires' => null,
		'use_case' => 'Call-to-action buttons with text, link, icon, sizing, and full color/border/typography styling.',
		'keywords' => array( 'button', 'cta', 'link', 'action' ),
		'params'   => array(
			'text'          => array( 'type' => 'string', 'description' => 'Button text.' ),
			'link'          => array( 'type' => 'object', 'description' => 'Link: {url, is_external, nofollow}.' ),
			'size'          => array( 'type' => 'string', 'enum' => array( 'xs', 'sm', 'md', 'lg', 'xl' ), 'description' => 'Button size.' ),
			'align'         => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right', 'justify' ), 'description' => 'Button alignment. Responsive: align_tablet, align_mobile.' ),
			'selected_icon' => array( 'type' => 'object', 'description' => 'Icon object with value and library.' ),
			'button_text_color' => array( 'type' => 'string', 'description' => 'Text color (hex/rgba).' ),
			'background_color'  => array( 'type' => 'string', 'description' => 'Background color (hex/rgba).' ),
			// …copy the REMAINING extra_props from register_add_button() verbatim…
		),
		'required' => array( 'text' ),
		'defaults' => array( 'text' => 'Click here', 'size' => 'sm' ),
	),
```

Transcribe all 28. The full free list: heading (done in Task 1 — **expand its params to the complete set** from `register_add_heading()`), text-editor, image, button, video, icon, spacer, divider, icon-box, accordion, alert, counter, google_maps, icon-list, image-box, image-carousel, progress, social-icons, star-rating, tabs, testimonial, toggle, html, menu-anchor, shortcode, rating, text-path.

- [ ] **Step 4: Harvest the Pro tier (29 entries)**

Same transformation into `catalog-pro.php`, `tier => 'pro'`, `requires => 'elementor-pro'`, `category => 'pro'`. Expand the seed `form` entry to its full param set from `register_add_form()`. Transcribe: form, posts (`posts-grid` tool → type `posts`), countdown, price-table, flip-box, animated-headline, call-to-action, slides, testimonial-carousel, price-list, gallery, share-buttons, table-of-contents, blockquote, lottie, hotspot, nav-menu, loop-grid, loop-carousel, media-carousel, nested-tabs, nested-accordion, portfolio, author-box, login, code-highlight, reviews, off-canvas, progress-tracker, search.

> Use the **`$widget_type`** (6th arg) as the catalog key, not the tool name. E.g. `register_add_posts_grid()` registers tool `add-posts-grid` but `$widget_type` is `posts` → key is `'posts'`.

- [ ] **Step 5: Harvest the Woo tier (5 entries)**

Same into `catalog-woo.php`, `tier => 'woo'`, `requires => 'woocommerce'`, `category => 'woocommerce'`. Transcribe the 5 `register_add_wc_*` methods (lines ~2467–2540): wc-products, wc-add-to-cart, wc-cart, wc-checkout, wc-menu-cart (use each method's `$widget_type` as the key).

- [ ] **Step 6: Run to verify it passes**

Run: `vendor/bin/phpunit --filter WidgetCatalogTest --configuration phpunit.xml.dist`
Expected: PASS (10 tests). Fix any malformed entry the `test_every_entry_is_well_formed` flags.

- [ ] **Step 7: Commit**

```bash
git add includes/widgets/catalog-*.php tests/unit/widgets/WidgetCatalogTest.php
git commit -m "feat(widgets): harvest 62 convenience tools into catalog data (Task 2)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Add `add-free-widget` + `add-pro-widget`, fold `add-widget`

**Files:**
- Modify: `includes/abilities/class-widget-abilities.php`
- Create: `tests/unit/widgets/CatalogToolsTest.php`

The two new tools share one insert path. `add-free-widget` always registers; `add-pro-widget` only when `ELEMENTOR_PRO_VERSION` is defined. Both validate the requested `widget_type` is in the right tier, merge catalog defaults, run the existing `EMCP_Tools_Settings_Validator`, and insert via `execute_add_widget()`.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/widgets/CatalogToolsTest.php`:

```php
<?php
/**
 * Catalog-backed widget tool registration + gating tests.
 * @group widgets
 * @package EMCP_Tools\Tests\Widgets
 */
namespace EMCP_Tools\Tests\Widgets;

require_once dirname(__DIR__) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class CatalogToolsTest extends Ability_Test_Case {
    private \EMCP_Tools_Widget_Abilities $ability;

    protected function setUp(): void {
        parent::setUp();
        $data      = $this->createStub(\EMCP_Tools_Data::class);
        $factory   = $this->make_factory();
        $schema    = $this->createStub(\EMCP_Tools_Schema_Generator::class);
        $validator = $this->createStub(\EMCP_Tools_Settings_Validator::class);
        $this->ability = new \EMCP_Tools_Widget_Abilities($data, $factory, $schema, $validator);
        $this->ability->register();
    }

    /** @test */
    public function test_registers_the_three_catalog_tools(): void {
        $names = $this->ability->get_ability_names();
        $this->assertContains('emcp-tools/add-free-widget', $names);
        $this->assertContains('emcp-tools/update-widget', $names);
    }

    /** @test */
    public function test_does_not_register_old_convenience_tools(): void {
        $names = $this->ability->get_ability_names();
        foreach (['emcp-tools/add-heading', 'emcp-tools/add-button', 'emcp-tools/add-form'] as $gone) {
            $this->assertNotContains($gone, $names, "$gone must be removed in v3.0.0");
        }
    }

    /** @test */
    public function test_add_pro_widget_not_registered_without_pro(): void {
        $this->assertFalse(defined('ELEMENTOR_PRO_VERSION'));
        $this->assertNotContains('emcp-tools/add-pro-widget', $this->ability->get_ability_names());
    }

    /** @test */
    public function test_add_free_widget_rejects_pro_type(): void {
        $this->allow_caps('edit_posts');
        $result = $this->ability->execute_add_free_widget([
            'post_id' => 1, 'parent_id' => 'abc1234', 'widget_type' => 'form', 'settings' => [],
        ]);
        $this->assertWPError($result);
        $this->assertSame('wrong_tier', $result->get_error_code());
    }

    /** @test */
    public function test_add_free_widget_rejects_unknown_type(): void {
        $this->allow_caps('edit_posts');
        $result = $this->ability->execute_add_free_widget([
            'post_id' => 1, 'parent_id' => 'abc1234', 'widget_type' => 'totally-fake', 'settings' => [],
        ]);
        $this->assertWPError($result);
    }
}
```

> Check `Ability_Test_Case` for `assertWPError()`/`make_factory()` helpers; if `assertWPError` doesn't exist, use `$this->assertInstanceOf(\WP_Error::class, $result)`.

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter CatalogToolsTest --configuration phpunit.xml.dist`
Expected: FAIL — `add-free-widget` not registered; `execute_add_free_widget` undefined.

- [ ] **Step 3: Rename `register()` body + add the new registrations**

In `class-widget-abilities.php`, replace the entire `register()` method (lines ~89–167) with:

```php
	public function register(): void {
		// Universal/engine tools.
		$this->register_add_free_widget();
		$this->register_update_widget();

		// Pro insert — only when Elementor Pro is active (natural tier gate).
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			$this->register_add_pro_widget();
		}
	}
```

- [ ] **Step 4: Add the two registrations + shared insert helper**

Add these methods to the class (e.g. right after `register()`). Keep `execute_add_widget()` and `check_edit_permission()` as-is — they're the engine.

```php
	/**
	 * Shared input schema for the catalog insert tools.
	 *
	 * @return array
	 */
	private function catalog_insert_input_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'post_id'     => array( 'type' => 'integer', 'description' => __( 'The post/page ID.', 'emcp-tools' ) ),
				'parent_id'   => array( 'type' => 'string', 'description' => __( 'Parent container element ID.', 'emcp-tools' ) ),
				'position'    => array( 'type' => 'integer', 'description' => __( 'Insert position. -1 = append.', 'emcp-tools' ) ),
				'widget_type' => array( 'type' => 'string', 'description' => __( 'Widget type from list-widgets. Use get-widget-schema for its curated params.', 'emcp-tools' ) ),
				'settings'    => array( 'type' => 'object', 'description' => __( 'Widget settings (see get-widget-schema). Any valid Elementor control passes through.', 'emcp-tools' ) ),
			),
			'required'   => array( 'post_id', 'parent_id', 'widget_type' ),
		);
	}

	private function register_add_free_widget(): void {
		$this->ability_names[] = 'emcp-tools/add-free-widget';
		emcp_tools_register_ability(
			'emcp-tools/add-free-widget',
			array(
				'label'               => __( 'Add Widget', 'emcp-tools' ),
				'description'         => __( 'Adds any free/core Elementor widget to a container. Discover types with list-widgets and their settings with get-widget-schema. Catalog defaults are merged automatically.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_add_free_widget' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => $this->catalog_insert_input_schema(),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'element_id'  => array( 'type' => 'string' ),
						'widget_type' => array( 'type' => 'string' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_add_pro_widget(): void {
		$this->ability_names[] = 'emcp-tools/add-pro-widget';
		emcp_tools_register_ability(
			'emcp-tools/add-pro-widget',
			array(
				'label'               => __( 'Add Pro Widget', 'emcp-tools' ),
				'description'         => __( 'Adds an Elementor Pro (or WooCommerce) widget to a container. Discover types with list-widgets (tier:pro) and settings with get-widget-schema. Only available when Elementor Pro is active.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_add_pro_widget' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => $this->catalog_insert_input_schema(),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'element_id'  => array( 'type' => 'string' ),
						'widget_type' => array( 'type' => 'string' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public function execute_add_free_widget( $input ) {
		return $this->insert_catalog_widget( $input, 'free' );
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public function execute_add_pro_widget( $input ) {
		return $this->insert_catalog_widget( $input, 'pro' );
	}

	/**
	 * Validates the requested widget against the catalog tier, merges catalog
	 * defaults, then delegates to the shared insert engine.
	 *
	 * @param array  $input         Tool input.
	 * @param string $expected_tier 'free' = free only; 'pro' = pro OR woo.
	 * @return array|\WP_Error
	 */
	private function insert_catalog_widget( $input, string $expected_tier ) {
		$widget_type = sanitize_text_field( $input['widget_type'] ?? '' );
		if ( '' === $widget_type ) {
			return new \WP_Error( 'missing_params', __( 'widget_type is required.', 'emcp-tools' ) );
		}

		$entry    = EMCP_Tools_Widget_Catalog::get_widget( $widget_type );
		$is_pro   = EMCP_Tools_Widget_Catalog::is_pro( $widget_type );

		// Tier gate. 'free' tool: reject Pro/Woo types. 'pro' tool: reject free types.
		if ( 'free' === $expected_tier && $is_pro ) {
			return new \WP_Error( 'wrong_tier', __( 'That is a Pro widget — use add-pro-widget.', 'emcp-tools' ) );
		}
		if ( 'pro' === $expected_tier && null !== $entry && ! $is_pro ) {
			return new \WP_Error( 'wrong_tier', __( 'That is a free widget — use add-free-widget.', 'emcp-tools' ) );
		}

		// Merge catalog defaults under the caller's settings (caller wins).
		$settings = is_array( $input['settings'] ?? null ) ? $input['settings'] : array();
		if ( null !== $entry && ! empty( $entry['defaults'] ) ) {
			$settings = array_merge( $entry['defaults'], $settings );
		}

		return $this->execute_add_widget(
			array(
				'post_id'     => $input['post_id'] ?? 0,
				'parent_id'   => $input['parent_id'] ?? '',
				'position'    => $input['position'] ?? -1,
				'widget_type' => $widget_type,
				'settings'    => $settings,
			)
		);
	}
```

- [ ] **Step 5: Delete the old convenience + universal registrations**

Delete from `class-widget-abilities.php`:
- All 62 `register_add_*()` convenience methods (heading…wc-menu-cart).
- The `register_convenience_tool()`, `slim_convenience_props()`, and `execute_convenience_tool()` helpers.
- `register_add_widget()` — the old universal add tool is folded into `add-free-widget`.

**Keep** `execute_add_widget()` — it is now the private insert engine that `insert_catalog_widget()` delegates to (its `widget_type` validation against the live Elementor registry and `insert_element`/`save_page_data` calls are unchanged).

After deletion the class must contain exactly these methods: `get_ability_names()`, `register()`, `check_edit_permission()`, `catalog_insert_input_schema()`, `register_add_free_widget()`, `register_add_pro_widget()`, `execute_add_free_widget()`, `execute_add_pro_widget()`, `insert_catalog_widget()`, `execute_add_widget()`, `register_update_widget()`, `execute_update_widget()`. Confirm with:

```
grep -n 'function ' includes/abilities/class-widget-abilities.php
```

- [ ] **Step 6: Run the catalog tool tests**

Run: `vendor/bin/phpunit --filter CatalogToolsTest --configuration phpunit.xml.dist`
Expected: PASS (5 tests).

- [ ] **Step 7: Commit**

```bash
git add includes/abilities/class-widget-abilities.php tests/unit/widgets/CatalogToolsTest.php
git commit -m "feat(widgets): add-free-widget + add-pro-widget; remove 62 convenience tools (Task 3)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Enhance `list-widgets` (catalog merge + filters + compact)

**Files:**
- Modify: `includes/abilities/class-query-abilities.php`
- Create test: add to `tests/unit/widgets/CatalogToolsTest.php`

`list-widgets` gains `tier`, `category`, `search` inputs and returns a compact catalog-backed index (type, title, tier, category, one-line use_case, param_names), still listing non-cataloged registered widgets (marked, no curated guidance).

- [ ] **Step 1: Write the failing test**

Add to `tests/unit/widgets/CatalogToolsTest.php`:

```php
    /** @test */
    public function test_list_widgets_compact_from_catalog(): void {
        $query = new \EMCP_Tools_Query_Abilities(
            $this->createStub(\EMCP_Tools_Data::class),
            $this->createStub(\EMCP_Tools_Schema_Generator::class)
        );
        $out = $query->execute_list_widgets(['tier' => 'free', 'search' => 'headline']);
        $this->assertArrayHasKey('widgets', $out);
        $types = array_column($out['widgets'], 'type');
        $this->assertContains('heading', $types);
        $this->assertNotContains('form', $types, 'tier:free must exclude Pro widgets');
        // Compact shape: use_case present, param_names present, no full params blob.
        $heading = $out['widgets'][array_search('heading', $types, true)];
        $this->assertArrayHasKey('use_case', $heading);
        $this->assertArrayHasKey('param_names', $heading);
    }
```

> `execute_list_widgets()` currently calls `$this->data->get_registered_widgets()`. The catalog path must NOT depend on a live Elementor registry, so the catalog branch runs purely from `EMCP_Tools_Widget_Catalog`. The stub `EMCP_Tools_Data` returns nothing for `get_registered_widgets()` — that's fine; the catalog supplies the rows.

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter CatalogToolsTest --configuration phpunit.xml.dist`
Expected: FAIL — `execute_list_widgets` doesn't accept `tier`/`search`, output rows lack `use_case`/`param_names`.

- [ ] **Step 3: Rewrite `execute_list_widgets()` + its registration**

Replace `execute_list_widgets()` (lines ~168–190) with a catalog-first implementation:

```php
	public function execute_list_widgets( $input = null ): array {
		$tier     = isset( $input['tier'] ) ? sanitize_key( $input['tier'] ) : 'all';
		$category = isset( $input['category'] ) ? sanitize_text_field( $input['category'] ) : '';
		$search   = isset( $input['search'] ) ? sanitize_text_field( $input['search'] ) : '';

		// Catalog rows (curated, compact).
		$catalog = $search
			? EMCP_Tools_Widget_Catalog::search( $search )
			: EMCP_Tools_Widget_Catalog::get();

		$rows = array();
		foreach ( $catalog as $type => $entry ) {
			$entry_tier = $entry['tier'] ?? 'free';
			if ( 'all' !== $tier && $entry_tier !== $tier ) {
				continue;
			}
			if ( '' !== $category && ( $entry['category'] ?? '' ) !== $category ) {
				continue;
			}
			$rows[] = array(
				'type'        => $type,
				'title'       => $entry['title'] ?? $type,
				'tier'        => $entry_tier,
				'category'    => $entry['category'] ?? '',
				'use_case'    => $entry['use_case'] ?? '',
				'param_names' => array_keys( $entry['params'] ?? array() ),
				'requires'    => $entry['requires'] ?? null,
			);
		}

		return array( 'widgets' => $rows );
	}
```

Update the registration's `input_schema` (in `register_list_widgets()`, lines ~115–123) to add the new params:

```php
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'tier'     => array( 'type' => 'string', 'enum' => array( 'all', 'free', 'pro', 'woo' ), 'description' => __( 'Filter by tier. Default: all.', 'emcp-tools' ) ),
						'category' => array( 'type' => 'string', 'description' => __( 'Filter by widget category.', 'emcp-tools' ) ),
						'search'   => array( 'type' => 'string', 'description' => __( 'Match by intent across title, use-case, and keywords (e.g. "pricing table").', 'emcp-tools' ) ),
					),
				),
```

And update its `output_schema` items to the compact shape (`type`, `title`, `tier`, `category`, `use_case`, `param_names`, `requires`) and the description to mention discover→inspect→act. Also add `use EMCP_Tools_Widget_Catalog` is unnecessary (same global namespace); reference the class directly.

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit --filter CatalogToolsTest --configuration phpunit.xml.dist`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/abilities/class-query-abilities.php tests/unit/widgets/CatalogToolsTest.php
git commit -m "feat(widgets): catalog-backed list-widgets with tier/category/search (Task 4)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: Enhance `get-widget-schema` (curated default, batch, full escape hatch)

**Files:**
- Modify: `includes/abilities/class-query-abilities.php`
- Create test: add to `tests/unit/widgets/CatalogToolsTest.php`

`get-widget-schema` returns the **curated** catalog params by default; accepts `types[]` for batch; `full:true` falls back to the existing auto-generated control schema.

- [ ] **Step 1: Write the failing test**

Add to `tests/unit/widgets/CatalogToolsTest.php`:

```php
    /** @test */
    public function test_get_widget_schema_curated_default(): void {
        $query = new \EMCP_Tools_Query_Abilities(
            $this->createStub(\EMCP_Tools_Data::class),
            $this->createStub(\EMCP_Tools_Schema_Generator::class)
        );
        $out = $query->execute_get_widget_schema(['widget_type' => 'heading']);
        $this->assertArrayHasKey('widget_type', $out);
        $this->assertSame('heading', $out['widget_type']);
        $this->assertArrayHasKey('params', $out, 'curated mode returns catalog params');
        $this->assertArrayHasKey('title', $out['params']);
    }

    /** @test */
    public function test_get_widget_schema_batch(): void {
        $query = new \EMCP_Tools_Query_Abilities(
            $this->createStub(\EMCP_Tools_Data::class),
            $this->createStub(\EMCP_Tools_Schema_Generator::class)
        );
        $out = $query->execute_get_widget_schema(['types' => ['heading', 'button']]);
        $this->assertArrayHasKey('widgets', $out);
        $returned = array_column($out['widgets'], 'widget_type');
        $this->assertContains('heading', $returned);
        $this->assertContains('button', $returned);
    }
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter CatalogToolsTest --configuration phpunit.xml.dist`
Expected: FAIL — no `params` key (current impl returns `schema` from the generator and requires a live Elementor widget), no batch support.

- [ ] **Step 3: Rewrite `execute_get_widget_schema()` + registration**

Replace `execute_get_widget_schema()` (lines ~244–274). The curated path is catalog-only (no live Elementor dependency); `full:true` uses the generator (which needs a live widget, so it stays guarded).

```php
	public function execute_get_widget_schema( $input ) {
		$full  = ! empty( $input['full'] );
		$types = array();

		if ( ! empty( $input['types'] ) && is_array( $input['types'] ) ) {
			$types = array_map( 'sanitize_text_field', $input['types'] );
		} elseif ( ! empty( $input['widget_type'] ) ) {
			$types = array( sanitize_text_field( $input['widget_type'] ) );
		}

		if ( empty( $types ) ) {
			return new \WP_Error( 'missing_widget_type', __( 'Provide widget_type or types[].', 'emcp-tools' ) );
		}

		$build = function ( $type ) use ( $full ) {
			$entry = EMCP_Tools_Widget_Catalog::get_widget( $type );

			if ( $full ) {
				// Escape hatch: full auto-generated control schema (needs live widget).
				$schema = $this->schema_generator->generate( $type );
				return array(
					'widget_type' => $type,
					'tier'        => EMCP_Tools_Widget_Catalog::tier_of( $type ),
					'use_case'    => $entry['use_case'] ?? '',
					'schema'      => is_wp_error( $schema ) ? array() : $schema,
				);
			}

			if ( null === $entry ) {
				return array( 'widget_type' => $type, 'error' => __( 'Not in the curated catalog. Retry with full:true for the raw control schema.', 'emcp-tools' ) );
			}

			return array(
				'widget_type' => $type,
				'tier'        => $entry['tier'] ?? 'free',
				'use_case'    => $entry['use_case'] ?? '',
				'params'      => $entry['params'] ?? array(),
				'required'    => $entry['required'] ?? array(),
				'defaults'    => $entry['defaults'] ?? array(),
			);
		};

		// Batch shape.
		if ( count( $types ) > 1 || ( ! empty( $input['types'] ) && is_array( $input['types'] ) ) ) {
			$widgets = array();
			foreach ( $types as $t ) {
				$widgets[] = $build( $t );
			}
			return array( 'widgets' => $widgets );
		}

		return $build( $types[0] );
	}
```

Update the registration's `input_schema` (lines ~206–215) to:

```php
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'widget_type' => array( 'type' => 'string', 'description' => __( 'A single widget type, e.g. "heading".', 'emcp-tools' ) ),
						'types'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => __( 'Batch: several widget types in one call.', 'emcp-tools' ) ),
						'full'        => array( 'type' => 'boolean', 'description' => __( 'Return the full auto-generated control schema instead of the curated params. Default: false.', 'emcp-tools' ) ),
					),
				),
```

Remove `required` (neither field is unconditionally required now). Update `output_schema` to allow both `{params,…}` and `{widgets:[…]}` shapes (use a permissive `'type' => 'object'` with the documented properties).

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit --filter CatalogToolsTest --configuration phpunit.xml.dist`
Expected: PASS.

- [ ] **Step 5: Run the full suite (catch regressions in existing query/widget tests)**

Run: `vendor/bin/phpunit --configuration phpunit.xml.dist`
Expected: PASS for catalog/widget groups. **`WidgetCapabilityTest` and `QueryCapabilityTest` will now FAIL** where they assert old tool names / old `get_widget_schema` shape — that's expected; Task 6 fixes them. Note which fail and move on.

- [ ] **Step 6: Commit**

```bash
git add includes/abilities/class-query-abilities.php tests/unit/widgets/CatalogToolsTest.php
git commit -m "feat(widgets): curated/batch/full get-widget-schema (Task 5)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: Update existing tests for the new tool surface

**Files:**
- Modify: `tests/unit/capabilities/WidgetCapabilityTest.php`
- Modify: `tests/unit/capabilities/QueryCapabilityTest.php`
- Modify: `tests/unit/input/WidgetInputTest.php` (if it asserts convenience-tool input schemas)

- [ ] **Step 1: Run the suite to enumerate the failures**

Run: `vendor/bin/phpunit --configuration phpunit.xml.dist`
Expected: FAILs in `WidgetCapabilityTest` (asserts `add-form` etc. registered/gated), possibly `QueryCapabilityTest`, `WidgetInputTest`. Record the exact failing assertions.

- [ ] **Step 2: Update `WidgetCapabilityTest`**

Replace the Pro-gating test (lines ~52+) that checks the 62 old names. The new contract: without Pro, `add-pro-widget` is absent; `add-free-widget` + `update-widget` present. Example replacement:

```php
    /** @test @group t0 */
    public function test_pro_insert_tool_not_registered_without_pro(): void {
        $this->assertFalse(defined('ELEMENTOR_PRO_VERSION'));
        $names = $this->ability->get_ability_names();
        $this->assertNotContains('emcp-tools/add-pro-widget', $names);
        $this->assertContains('emcp-tools/add-free-widget', $names);
        $this->assertContains('emcp-tools/update-widget', $names);
    }
```

Delete assertions referencing removed tool names (`add-heading`, `add-form`, the WC tools, etc.). Keep the `check_edit_permission()` tests unchanged (that method is unchanged).

- [ ] **Step 3: Update `QueryCapabilityTest` + `WidgetInputTest`**

Fix any assertion about `get-widget-schema` requiring `widget_type` or returning a `schema` key by default — it now returns `params` by default and accepts `types[]`/`full`. Fix `list-widgets` assertions to expect the compact catalog shape. If `WidgetInputTest` validated convenience-tool input schemas that no longer exist, replace those cases with `add-free-widget`/`add-pro-widget` input-schema checks (post_id/parent_id/widget_type required).

- [ ] **Step 4: Run the full suite to green**

Run: `vendor/bin/phpunit --configuration phpunit.xml.dist`
Expected: PASS (all groups).

- [ ] **Step 5: Commit**

```bash
git add tests/
git commit -m "test(widgets): update capability/query/input tests for 5-tool surface (Task 6)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: Migrate essentials list, admin Tools UI, defaults seeder

**Files:**
- Modify: `includes/class-plugin.php`
- Modify: `includes/admin/class-admin.php`

- [ ] **Step 1: Update `get_essential_tool_slugs()`**

In `includes/class-plugin.php`, in `get_essential_tool_slugs()` (lines ~170–247), replace the universal + per-widget-shortcut entries with the catalog tools. Remove these slugs: `add-widget`, `update-widget` (keep update-widget? **yes keep** — it stays), `add-heading`, `add-text-editor`, `add-image`, `add-button`, `add-icon`, `add-spacer`, `add-divider`, `add-icon-box`, `add-html`. Replace the "Universal widget add/update (2)" + "Most-used core widget shortcuts (9)" blocks with:

```php
			// Widget tools — catalog-backed (4).
			'emcp-tools/list-widgets',     // also in Query block above — array_intersect dedupes
			'emcp-tools/get-widget-schema',
			'emcp-tools/add-free-widget',
			'emcp-tools/add-pro-widget',
			'emcp-tools/update-widget',
```

(`list-widgets`/`get-widget-schema` already appear in the Query block — leave both; `filter_disabled_tools()` uses `array_intersect` against registered names so duplicates are harmless. To be tidy, don't re-add them and just add the three new write/insert slugs.)

- [ ] **Step 2: Replace the admin Tools categories**

In `includes/admin/class-admin.php`, in `get_tool_catalog()`, replace the three categories `widget_universal`, `widget_core`, and `widget_pro` (lines ~1141–1435) with a single category:

```php
			'widgets'          => array(
				'label' => __( 'Widgets', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-widgets'      => array(
						'label'       => __( 'List Widgets', 'emcp-tools' ),
						'description' => __( 'Catalog-backed widget discovery with tier/category/search filters.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-widget-schema' => array(
						'label'       => __( 'Get Widget Schema', 'emcp-tools' ),
						'description' => __( 'Curated params for a widget (or batch); full:true for the raw control schema.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/add-free-widget'   => array(
						'label'       => __( 'Add Widget', 'emcp-tools' ),
						'description' => __( 'Adds any free/core Elementor widget by type.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-pro-widget'    => array(
						'label'       => __( 'Add Pro Widget', 'emcp-tools' ),
						'description' => __( 'Adds an Elementor Pro / WooCommerce widget by type. Registers only with Elementor Pro active.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro' ),
					),
					'emcp-tools/update-widget'     => array(
						'label'       => __( 'Update Widget', 'emcp-tools' ),
						'description' => __( 'Updates settings on an existing widget (partial merge).', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
```

> `list-widgets` and `get-widget-schema` also appear in the `query` category. Having a slug in two display categories double-renders a toggle. To avoid that, REMOVE them from the `query` category's tools array (they're conceptually widget tools now) — OR keep them only in `query` and list just the 3 write tools here. **Decision: keep `list-widgets`/`get-widget-schema` in the `query` category (where users expect discovery tools) and put only `add-free-widget`, `add-pro-widget`, `update-widget` in the new `widgets` category.** Adjust the array above to those three.

- [ ] **Step 3: Bump `DEFAULTS_VERSION` and clear orphaned slugs**

In `class-admin.php`, change `const DEFAULTS_VERSION = 4;` to `5`. In `maybe_apply_default_disabled_tools()`, after the v4 block, add:

```php
		// v5 — Widget consolidation (3.0.0). The 62 per-widget Pro slugs that were
		// seeded disabled in v1 no longer exist; clear them so they don't linger in
		// the stored option. add-pro-widget is a single tool, left ENABLED (it only
		// registers with Elementor Pro anyway).
		if ( $applied < 5 ) {
			$dead_prefixes = array( 'emcp-tools/add-' );
			$dead_exact    = self::removed_widget_tool_slugs(); // see below
			$existing      = array_values( array_diff( $existing, $dead_exact ) );
		}
```

Add a helper listing the 62 removed slugs (so only those are cleared, not unrelated `add-*` tools):

```php
	/**
	 * The 62 per-widget convenience tool slugs removed in 3.0.0 (widget
	 * consolidation). Used by the v5 defaults step to clear orphaned disabled
	 * entries from the stored option.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	public static function removed_widget_tool_slugs(): array {
		return array(
			'emcp-tools/add-widget',
			'emcp-tools/add-heading', 'emcp-tools/add-text-editor', 'emcp-tools/add-image',
			'emcp-tools/add-button', 'emcp-tools/add-video', 'emcp-tools/add-icon',
			'emcp-tools/add-spacer', 'emcp-tools/add-divider', 'emcp-tools/add-icon-box',
			'emcp-tools/add-accordion', 'emcp-tools/add-alert', 'emcp-tools/add-counter',
			'emcp-tools/add-google-maps', 'emcp-tools/add-icon-list', 'emcp-tools/add-image-box',
			'emcp-tools/add-image-carousel', 'emcp-tools/add-progress', 'emcp-tools/add-social-icons',
			'emcp-tools/add-star-rating', 'emcp-tools/add-tabs', 'emcp-tools/add-testimonial',
			'emcp-tools/add-toggle', 'emcp-tools/add-html', 'emcp-tools/add-menu-anchor',
			'emcp-tools/add-shortcode', 'emcp-tools/add-rating', 'emcp-tools/add-text-path',
			'emcp-tools/add-form', 'emcp-tools/add-posts-grid', 'emcp-tools/add-countdown',
			'emcp-tools/add-price-table', 'emcp-tools/add-flip-box', 'emcp-tools/add-animated-headline',
			'emcp-tools/add-call-to-action', 'emcp-tools/add-slides', 'emcp-tools/add-testimonial-carousel',
			'emcp-tools/add-price-list', 'emcp-tools/add-gallery', 'emcp-tools/add-share-buttons',
			'emcp-tools/add-table-of-contents', 'emcp-tools/add-blockquote', 'emcp-tools/add-lottie',
			'emcp-tools/add-hotspot', 'emcp-tools/add-nav-menu', 'emcp-tools/add-loop-grid',
			'emcp-tools/add-loop-carousel', 'emcp-tools/add-media-carousel', 'emcp-tools/add-nested-tabs',
			'emcp-tools/add-nested-accordion', 'emcp-tools/add-portfolio', 'emcp-tools/add-author-box',
			'emcp-tools/add-login', 'emcp-tools/add-code-highlight', 'emcp-tools/add-reviews',
			'emcp-tools/add-off-canvas', 'emcp-tools/add-progress-tracker', 'emcp-tools/add-search',
			'emcp-tools/add-wc-products', 'emcp-tools/add-wc-add-to-cart', 'emcp-tools/add-wc-cart',
			'emcp-tools/add-wc-checkout', 'emcp-tools/add-wc-menu-cart',
		);
	}
```

> Drop the `$dead_prefixes` line (unused — the exact list is safer; `add-free-widget`/`add-pro-widget` must NOT be cleared). Keep only `$dead_exact` filtering.

- [ ] **Step 4: Verify admin counts don't fatal**

Run: `vendor/bin/phpunit --configuration phpunit.xml.dist`
Expected: PASS. The `F019F020AdminTest` cross-checks catalog slugs against the registry under WP_DEBUG; ensure the 5 widget slugs it now references are all registered (they are).

- [ ] **Step 5: Commit**

```bash
git add includes/class-plugin.php includes/admin/class-admin.php
git commit -m "feat(widgets): essentials list, admin Tools category, v5 defaults cleanup (Task 7)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Version bump + docs + CHANGELOG

**Files:**
- Modify: `emcp-tools.php`
- Modify: `CLAUDE.md`, `README.md`, `readme.txt`, `CHANGELOG.md`

- [ ] **Step 1: Bump version to 3.0.0**

In `emcp-tools.php`: header `Version: 3.0.0` and `define( 'EMCP_TOOLS_VERSION', '3.0.0' );`.

- [ ] **Step 2: Update tool counts + tables in CLAUDE.md**

In `CLAUDE.md`: update the "Tool counts by configuration" block and the widget tool tables. Free Elementor drops by ~26 (28 free convenience tools → 0, +2 new) → recompute from the new surface. Replace the big widget table with the 5-tool table from the spec. Note the v3.0.0 clean break.

- [ ] **Step 3: Update README.md / readme.txt**

Update tool counts and any per-widget tool listings to the catalog model. README version badge is dynamic — no manual bump.

- [ ] **Step 4: Add CHANGELOG entry**

Prepend to `CHANGELOG.md`:

```markdown
## 3.0.0

### Changed (BREAKING)
- **Widget tools consolidated.** The 62 per-widget convenience tools (`add-heading`, `add-button`, `add-form`, …) are removed and replaced by 5 catalog-backed tools: `list-widgets` (now with `tier`/`category`/`search`), `get-widget-schema` (curated by default, `types[]` batch, `full:true` escape hatch), `add-free-widget`, `add-pro-widget`, and `update-widget`. **No capability is lost** — every widget and every curated parameter is still reachable via discover → inspect → act. AI scripts hardcoding old tool names must switch to `add-free-widget`/`add-pro-widget` with a `widget_type`.
- Per-turn widget tool-list context cut ~10× (~18–20k → ~2k tokens), freeing the context window and removing the need for Low-tools mode on most clients.

### Migration
- Old per-widget disabled-tool toggles are cleared automatically (defaults seeder v5).
- Existing pages/templates are unaffected — this changes tools, not `_elementor_data`.
```

- [ ] **Step 5: Commit**

```bash
git add emcp-tools.php CLAUDE.md README.md readme.txt CHANGELOG.md
git commit -m "docs: v3.0.0 widget consolidation — counts, tables, CHANGELOG (Task 8)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: End-to-end MCP protocol verification

**Files:** none (verification only)

- [ ] **Step 1: Confirm the server lists exactly the new widget tools**

Run:
```
/c/wp-cli/wp-cli.phar mcp-adapter list --path=f:/laragon/www/msrplugins
```
Then drive `tools/list` (the project previously used `wp mcp-adapter serve` for this):
```
/c/wp-cli/wp-cli.phar mcp-adapter serve --server=emcp-tools-server --user=admin --path=f:/laragon/www/msrplugins
```
Expected: `add-free-widget`, `update-widget`, `list-widgets`, `get-widget-schema` present; `add-pro-widget` present only if Elementor Pro active; NONE of the 62 old names present.

- [ ] **Step 2: Round-trip a real free insert**

Via the MCP session (or a PHPUnit functional test mirroring `tests/unit/functional/LayoutFunctionalTest.php`): create a page, `add-container`, then `add-free-widget` with `widget_type: heading, settings:{title:"Hi"}`. Confirm `get-page-structure` shows the heading persisted.
Expected: element persists; `element_id` returned matches the tree.

- [ ] **Step 3: Confirm tier gating end-to-end**

`add-free-widget` with `widget_type: form` → `wrong_tier` error. Without Elementor Pro, `add-pro-widget` is not in `tools/list`.

- [ ] **Step 4: Token check**

Compare serialized `tools/list` size before (git stash / prior tag) vs after. Expected: widget portion drops from ~18–20k to ~2k.

- [ ] **Step 5: Final full suite**

Run: `vendor/bin/phpunit --configuration phpunit.xml.dist`
Expected: PASS, all groups.

- [ ] **Step 6: Commit any verification fixes, then finish the branch**

Use `superpowers:finishing-a-development-branch` to decide merge/PR.

---

## Self-Review notes (for the implementer)

- **Spec coverage:** all five spec tools (Task 3–5), catalog (Task 1–2), token optimizations (compact list + curated schema + batch, Tasks 4–5), migration (Task 7), version/docs (Task 8), verification incl. real MCP protocol + token check (Task 9) — covered.
- **Clean break (Decision A):** no alias tasks; Task 7 clears orphaned slugs instead.
- **Naming consistency:** catalog keys are widget *types* (`posts`, not `posts-grid`); tool names are `add-free-widget`/`add-pro-widget`; accessor methods `get/get_widget/by_tier/tier_of/is_pro/search/all_types/flush_cache` — used identically across tasks.
- **Known judgement calls flagged inline:** the 28/29/5 counts (verify against code in Task 2), and the double-render of `list-widgets`/`get-widget-schema` (resolved: keep in `query` category only, Task 7 Step 2).
