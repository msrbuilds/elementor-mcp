# Elementor MCP Plugin - Implementation Plan

## Overview

A WordPress plugin that extends the official WordPress MCP Adapter to expose Elementor data, widgets, structures, and methods as MCP tools. This enables 3rd-party AI tools (Claude, Cursor, etc.) to create and manipulate Elementor page designs programmatically.

## Architecture

```
elementor-mcp/
├── elementor-mcp.php                    # Main plugin file (bootstrap, dependency checks)
├── composer.json                         # Dependencies (wordpress/mcp-adapter)
├── includes/
│   ├── class-plugin.php                 # Singleton plugin orchestrator
│   ├── class-elementor-data.php         # Elementor data access layer
│   ├── class-element-factory.php        # Factory for building element JSON structures
│   ├── class-id-generator.php           # Unique element ID generation
│   ├── abilities/
│   │   ├── class-ability-registrar.php  # Registers all abilities with WP Abilities API
│   │   ├── class-page-abilities.php     # Page/document CRUD tools
│   │   ├── class-layout-abilities.php   # Container/section layout tools
│   │   ├── class-widget-abilities.php   # Widget creation & manipulation tools
│   │   ├── class-template-abilities.php # Template library tools
│   │   ├── class-global-abilities.php   # Global settings/kit tools
│   │   └── class-query-abilities.php    # Read/query tools for discovery
│   ├── schemas/
│   │   ├── class-schema-generator.php   # Auto-generates JSON schemas from widget controls
│   │   ├── widgets/                     # Cached/static widget schemas (optional)
│   │   └── class-control-mapper.php     # Maps Elementor control types to JSON Schema types
│   └── validators/
│       ├── class-element-validator.php  # Validates element structures before saving
│       └── class-settings-validator.php # Validates widget settings against controls
└── tests/                               # PHPUnit tests
```

## Dependencies

- **WordPress >= 6.8**
- **Elementor >= 3.20** (container support)
- **WordPress Abilities API** (bundled in WP 6.9+, or via composer)
- **WordPress MCP Adapter** (via composer `wordpress/mcp-adapter`)

## MCP Server Registration

The plugin registers a dedicated MCP server `elementor-mcp-server` exposed at:
```
/wp-json/mcp/elementor-mcp-server
```

All abilities are registered under the `elementor-mcp/` namespace.

---

## Phase 1: Foundation & Read Tools

### Step 1: Plugin Bootstrap (`elementor-mcp.php`)

- Standard WordPress plugin header
- Dependency checks: Elementor active, MCP Adapter available, Abilities API available
- Autoloader or manual requires
- Singleton initialization on `plugins_loaded`

### Step 2: Core Data Layer (`class-elementor-data.php`)

Helper class wrapping Elementor internals:

```php
class Elementor_Data {
    // Read operations
    public function get_page_data( int $post_id ): array;          // Get _elementor_data
    public function get_page_settings( int $post_id ): array;      // Get _elementor_page_settings
    public function get_document_type( int $post_id ): string;     // page, post, header, etc.

    // Write operations
    public function save_page_data( int $post_id, array $data ): bool;
    public function save_page_settings( int $post_id, array $settings ): bool;

    // Widget/element discovery
    public function get_registered_widgets(): array;               // All available widget types
    public function get_widget_controls( string $widget_type ): array; // Controls for a widget
    public function get_registered_elements(): array;              // Containers, sections, etc.

    // Element operations within a page
    public function find_element_by_id( array $data, string $id ): ?array;
    public function insert_element( array &$data, string $parent_id, array $element, int $position = -1 ): bool;
    public function remove_element( array &$data, string $element_id ): bool;
    public function update_element_settings( array &$data, string $element_id, array $settings ): bool;
}
```

### Step 3: Element Factory (`class-element-factory.php`)

Builds valid Elementor JSON element structures:

```php
class Element_Factory {
    public function create_container( array $settings = [], array $children = [] ): array;
    public function create_widget( string $widget_type, array $settings = [] ): array;
    public function create_section( array $settings = [], array $columns = [] ): array;  // legacy
    public function create_column( array $settings = [], array $widgets = [] ): array;    // legacy
}
```

Each returns a properly structured array:
```php
[
    'id' => IdGenerator::generate(),  // 7-char random hex
    'elType' => 'widget',
    'widgetType' => 'heading',
    'isInner' => false,
    'settings' => [ ... ],
    'elements' => [ ... ],
]
```

### Step 4: Schema Generator (`class-schema-generator.php` + `class-control-mapper.php`)

Auto-generates JSON Schema from Elementor's control definitions for each widget. This is critical - it tells AI agents what settings each widget accepts.

**Control Type → JSON Schema Mapping:**

| Elementor Control   | JSON Schema Type                    |
|---------------------|-------------------------------------|
| TEXT, TEXTAREA       | `{ "type": "string" }`             |
| NUMBER               | `{ "type": "number" }`             |
| SLIDER               | `{ "type": "object", "properties": { "size": number, "unit": string } }` |
| SELECT               | `{ "type": "string", "enum": [...options] }` |
| CHOOSE               | `{ "type": "string", "enum": [...options] }` |
| SWITCHER             | `{ "type": "string", "enum": ["yes", ""] }` |
| COLOR                | `{ "type": "string", "description": "hex/rgba color" }` |
| URL                  | `{ "type": "object", "properties": { "url": string, "is_external": bool, "nofollow": bool } }` |
| MEDIA                | `{ "type": "object", "properties": { "url": string, "id": integer } }` |
| ICONS                | `{ "type": "object", "properties": { "value": string, "library": string } }` |
| WYSIWYG             | `{ "type": "string", "description": "HTML content" }` |
| DIMENSIONS           | `{ "type": "object", "properties": { "top": string, "right": string, "bottom": string, "left": string, "unit": string } }` |
| REPEATER             | `{ "type": "array", "items": { ... } }` |
| DATE_TIME            | `{ "type": "string", "format": "date-time" }` |

### Step 5: Query/Discovery Abilities (`class-query-abilities.php`)

**Registered abilities (read-only, safe):**

#### `elementor-mcp/list-widgets`
Returns all registered widget types with their names, titles, icons, categories, and keywords.
```
Input:  { "category": "string (optional)" }
Output: { "widgets": [ { "name", "title", "icon", "categories", "keywords" } ] }
```

#### `elementor-mcp/get-widget-schema`
Returns the full JSON schema for a widget type's settings (all controls mapped).
```
Input:  { "widget_type": "string" }
Output: { "widget_type", "title", "schema": { JSON Schema for all controls } }
```

#### `elementor-mcp/get-page-structure`
Returns the element tree for an Elementor page (containers, widgets, nesting).
```
Input:  { "post_id": "integer" }
Output: { "post_id", "title", "type", "structure": [ nested element tree ] }
```

#### `elementor-mcp/get-element-settings`
Returns the current settings for a specific element on a page.
```
Input:  { "post_id": "integer", "element_id": "string" }
Output: { "element_id", "elType", "widgetType", "settings": { ... } }
```

#### `elementor-mcp/list-pages`
Returns all Elementor-enabled pages/posts.
```
Input:  { "post_type": "string (optional)", "status": "string (optional)" }
Output: { "pages": [ { "post_id", "title", "type", "status", "modified" } ] }
```

#### `elementor-mcp/list-templates`
Returns all saved Elementor templates.
```
Input:  { "template_type": "string (optional)" }
Output: { "templates": [ { "id", "title", "type", "date" } ] }
```

#### `elementor-mcp/get-global-settings`
Returns the active Elementor kit/global settings (colors, fonts, spacing).
```
Input:  {}
Output: { "colors", "typography", "spacing", "breakpoints", ... }
```

---

## Phase 2: Page & Layout Creation Tools

### Step 6: Page Abilities (`class-page-abilities.php`)

#### `elementor-mcp/create-page`
Creates a new WordPress page with Elementor enabled and optional initial content.
```
Input: {
    "title": "string (required)",
    "status": "draft|publish (default: draft)",
    "post_type": "page|post (default: page)",
    "template": "string (optional, Elementor template slug)",
    "content": "array (optional, element tree)"
}
Output: { "post_id", "title", "edit_url", "preview_url" }
```

#### `elementor-mcp/update-page-settings`
Updates page-level settings (background, padding, custom CSS, etc.).
```
Input: {
    "post_id": "integer (required)",
    "settings": "object (page settings)"
}
Output: { "success": true, "post_id" }
```

#### `elementor-mcp/delete-page-content`
Clears all Elementor content from a page (resets to blank).
```
Input: { "post_id": "integer (required)" }
Output: { "success": true }
```

#### `elementor-mcp/import-template`
Imports a JSON template into a page.
```
Input: {
    "post_id": "integer (required)",
    "template_json": "object (Elementor JSON structure)",
    "position": "integer (optional, insert position)"
}
Output: { "success": true, "elements_count" }
```

#### `elementor-mcp/export-page`
Exports a page's Elementor data as JSON.
```
Input: { "post_id": "integer (required)" }
Output: { "json": { full Elementor JSON structure } }
```

### Step 7: Layout Abilities (`class-layout-abilities.php`)

#### `elementor-mcp/add-container`
Adds a flexbox container to a page (or nested inside another container).
```
Input: {
    "post_id": "integer (required)",
    "parent_id": "string (optional, for nesting - omit for top-level)",
    "position": "integer (optional, -1 = append)",
    "settings": {
        "flex_direction": "row|column (default: column)",
        "flex_wrap": "nowrap|wrap",
        "justify_content": "flex-start|center|flex-end|space-between|space-around|space-evenly",
        "align_items": "flex-start|center|flex-end|stretch",
        "gap": { "size": number, "unit": "px|em|rem|%" },
        "content_width": "boxed|full",
        "boxed_width": { "size": number, "unit": "px|em|rem|%" },
        "min_height": { "size": number, "unit": "px|vh|em" },
        "padding": { "top", "right", "bottom", "left", "unit" },
        "margin": { "top", "right", "bottom", "left", "unit" },
        "background_background": "classic|gradient",
        "background_color": "string (hex/rgba)",
        "background_image": { "url", "id" },
        "border_border": "solid|dashed|dotted|double|groove",
        "border_width": { "top", "right", "bottom", "left", "unit" },
        "border_color": "string",
        "border_radius": { "top", "right", "bottom", "left", "unit" }
    }
}
Output: { "element_id": "string", "post_id" }
```

#### `elementor-mcp/move-element`
Moves an element to a new parent or position.
```
Input: {
    "post_id": "integer",
    "element_id": "string",
    "target_parent_id": "string",
    "position": "integer"
}
Output: { "success": true }
```

#### `elementor-mcp/remove-element`
Removes an element (and all its children) from a page.
```
Input: { "post_id": "integer", "element_id": "string" }
Output: { "success": true }
```

#### `elementor-mcp/duplicate-element`
Duplicates an element (assigns new IDs recursively).
```
Input: { "post_id": "integer", "element_id": "string" }
Output: { "new_element_id": "string" }
```

---

## Phase 3: Widget Creation Tools

### Step 8: Widget Abilities (`class-widget-abilities.php`)

One universal tool plus convenience tools for common widgets.

#### `elementor-mcp/add-widget` (Universal)
Adds any widget to a container on a page.
```
Input: {
    "post_id": "integer (required)",
    "parent_id": "string (required, container element ID)",
    "position": "integer (optional, -1 = append)",
    "widget_type": "string (required, e.g. 'heading', 'button', 'image')",
    "settings": "object (widget-specific settings - use get-widget-schema to discover)"
}
Output: { "element_id": "string", "widget_type": "string" }
```

#### `elementor-mcp/update-widget`
Updates settings on an existing widget.
```
Input: {
    "post_id": "integer (required)",
    "element_id": "string (required)",
    "settings": "object (partial settings to merge)"
}
Output: { "success": true, "element_id" }
```

#### Convenience Shortcut Tools (common widgets with pre-defined schemas):

#### `elementor-mcp/add-heading`
```
Input: {
    "post_id": integer,
    "parent_id": "string",
    "position": integer,
    "title": "string (required)",
    "header_size": "h1|h2|h3|h4|h5|h6 (default: h2)",
    "size": "default|small|medium|large|xl|xxl",
    "align": "start|center|end|justify",
    "title_color": "string (hex)",
    "link": { "url": "string" }
}
Output: { "element_id" }
```

#### `elementor-mcp/add-text-editor`
```
Input: {
    "post_id": integer,
    "parent_id": "string",
    "position": integer,
    "content": "string (HTML content, required)",
    "align": "start|center|end|justify",
    "text_color": "string (hex)"
}
Output: { "element_id" }
```

#### `elementor-mcp/add-image`
```
Input: {
    "post_id": integer,
    "parent_id": "string",
    "position": integer,
    "image": { "url": "string (required)", "id": integer },
    "image_size": "thumbnail|medium|medium_large|large|full",
    "align": "start|center|end",
    "caption_source": "none|attachment|custom",
    "caption": "string",
    "link_to": "none|file|custom",
    "link": { "url": "string" },
    "width": { "size": number, "unit": "px|%" }
}
Output: { "element_id" }
```

#### `elementor-mcp/add-button`
```
Input: {
    "post_id": integer,
    "parent_id": "string",
    "position": integer,
    "text": "string (required, default: 'Click here')",
    "link": { "url": "string" },
    "size": "xs|sm|md|lg|xl (default: sm)",
    "button_type": "|info|success|warning|danger",
    "align": "start|center|end|stretch",
    "selected_icon": { "value": "string", "library": "string" },
    "icon_align": "row|row-reverse"
}
Output: { "element_id" }
```

#### `elementor-mcp/add-video`
```
Input: {
    "post_id": integer,
    "parent_id": "string",
    "position": integer,
    "video_type": "youtube|vimeo|dailymotion|html5 (default: youtube)",
    "youtube_url": "string",
    "vimeo_url": "string",
    "autoplay": "yes|",
    "mute": "yes|",
    "loop": "yes|",
    "controls": "yes|"
}
Output: { "element_id" }
```

#### `elementor-mcp/add-icon`
```
Input: {
    "post_id": integer,
    "parent_id": "string",
    "selected_icon": { "value": "fas fa-star", "library": "fa-solid" },
    "view": "default|stacked|framed",
    "shape": "circle|square",
    "primary_color": "string",
    "size": { "size": number, "unit": "px" },
    "link": { "url": "string" }
}
Output: { "element_id" }
```

#### `elementor-mcp/add-spacer`
```
Input: {
    "post_id": integer,
    "parent_id": "string",
    "space": { "size": 50, "unit": "px" }
}
Output: { "element_id" }
```

#### `elementor-mcp/add-divider`
```
Input: {
    "post_id": integer,
    "parent_id": "string",
    "style": "solid|dashed|dotted|double",
    "weight": { "size": number, "unit": "px" },
    "color": "string",
    "width": { "size": number, "unit": "%" },
    "gap": { "size": number, "unit": "px" }
}
Output: { "element_id" }
```

#### `elementor-mcp/add-icon-box`
```
Input: {
    "post_id": integer,
    "parent_id": "string",
    "selected_icon": { "value": "string", "library": "string" },
    "title": "string (required)",
    "description": "string",
    "view": "default|stacked|framed",
    "shape": "circle|square",
    "link": { "url": "string" },
    "title_color": "string",
    "primary_color": "string"
}
Output: { "element_id" }
```

### Step 9: Pro Widget Convenience Tools (when Elementor Pro is active)

#### `elementor-mcp/add-form`
```
Input: {
    "post_id": integer,
    "parent_id": "string",
    "form_name": "string (required)",
    "fields": [
        {
            "field_type": "text|email|textarea|url|tel|select|radio|checkbox|number|date|hidden",
            "field_label": "string",
            "placeholder": "string",
            "required": "yes|",
            "width": "100|80|75|66|50|33|25",
            "field_options": "string (line-separated for select/radio/checkbox)"
        }
    ],
    "button_text": "string (default: Send)",
    "email_to": "string",
    "email_subject": "string"
}
Output: { "element_id" }
```

#### `elementor-mcp/add-posts-grid`
```
Input: {
    "post_id": integer,
    "parent_id": "string",
    "posts_post_type": "post|page|any",
    "posts_per_page": integer,
    "columns": integer,
    "pagination_type": "|numbers|prev_next|numbers_and_prev_next|load_more_on_click"
}
Output: { "element_id" }
```

#### `elementor-mcp/add-countdown`
```
Input: {
    "post_id": integer,
    "parent_id": "string",
    "countdown_type": "due_date|evergreen",
    "due_date": "string (Y-m-d H:i)",
    "show_days": "yes|",
    "show_hours": "yes|",
    "show_minutes": "yes|",
    "show_seconds": "yes|"
}
Output: { "element_id" }
```

#### `elementor-mcp/add-price-table`
```
Input: {
    "post_id": integer,
    "parent_id": "string",
    "heading": "string",
    "sub_heading": "string",
    "currency_symbol": "dollar|euro|pound|yen|custom",
    "price": "string",
    "period": "string (e.g. '/month')",
    "features_list": [ { "item_text": "string", "item_icon": object } ],
    "button_text": "string",
    "button_link": { "url": "string" }
}
Output: { "element_id" }
```

#### `elementor-mcp/add-flip-box`
```
Input: {
    "post_id": integer,
    "parent_id": "string",
    "title_text_a": "string (front title)",
    "description_text_a": "string (front description)",
    "title_text_b": "string (back title)",
    "description_text_b": "string (back description)",
    "graphic_element": "none|image|icon",
    "selected_icon": object,
    "button_text": "string",
    "link": { "url": "string" },
    "flip_effect": "flip|slide|push|zoom-in|zoom-out|fade",
    "flip_direction": "left|right|up|down"
}
Output: { "element_id" }
```

#### `elementor-mcp/add-animated-headline`
```
Input: {
    "post_id": integer,
    "parent_id": "string",
    "headline_style": "highlight|rotate",
    "animation_type": "typing|clip|flip|swirl|blinds|drop-in|wave|slide|slide-down",
    "marker": "circle|curly|underline|double|double_underline|underline_zigzag|diagonal|strikethrough|x",
    "before_text": "string",
    "highlighted_text": "string",
    "rotating_text": "string (line-separated)",
    "after_text": "string",
    "tag": "h1|h2|h3|h4|h5|h6"
}
Output: { "element_id" }
```

---

## Phase 4: Template & Global Tools

### Step 10: Template Abilities (`class-template-abilities.php`)

#### `elementor-mcp/save-as-template`
Saves a page or element as a reusable template.
```
Input: {
    "post_id": "integer (required)",
    "element_id": "string (optional, for partial template)",
    "title": "string (required)",
    "template_type": "page|section|container"
}
Output: { "template_id", "title" }
```

#### `elementor-mcp/apply-template`
Applies a saved template to a page at a given position.
```
Input: {
    "post_id": "integer (required)",
    "template_id": "integer (required)",
    "parent_id": "string (optional)",
    "position": "integer (optional)"
}
Output: { "success": true, "elements_added": integer }
```

### Step 11: Global Abilities (`class-global-abilities.php`)

#### `elementor-mcp/update-global-colors`
Updates site-wide color palette in the Elementor kit.
```
Input: {
    "colors": [ { "id": "string", "title": "string", "color": "string (hex)" } ]
}
Output: { "success": true }
```

#### `elementor-mcp/update-global-typography`
Updates site-wide typography settings.
```
Input: {
    "typography": [ { "id": "string", "title": "string", "typography_font_family": "string", "typography_font_size": object, "typography_font_weight": "string" } ]
}
Output: { "success": true }
```

---

## Phase 5: Composite / High-Level Tools

### Step 12: Page Builder Composite Tool

#### `elementor-mcp/build-page`
Creates a complete page from a declarative structure in a single call. This is the most powerful tool - it creates the page and all elements at once.

```
Input: {
    "title": "string (required)",
    "status": "draft|publish",
    "post_type": "page|post",
    "page_settings": { ... },
    "structure": [
        {
            "type": "container",
            "settings": { "flex_direction": "row", "gap": { "size": 20, "unit": "px" } },
            "children": [
                {
                    "type": "container",
                    "settings": { "width": { "size": 50, "unit": "%" } },
                    "children": [
                        {
                            "type": "widget",
                            "widget_type": "heading",
                            "settings": { "title": "Hello World", "header_size": "h1" }
                        },
                        {
                            "type": "widget",
                            "widget_type": "text-editor",
                            "settings": { "editor": "<p>Welcome to our site.</p>" }
                        }
                    ]
                },
                {
                    "type": "container",
                    "settings": { "width": { "size": 50, "unit": "%" } },
                    "children": [
                        {
                            "type": "widget",
                            "widget_type": "image",
                            "settings": { "image": { "url": "https://example.com/hero.jpg" } }
                        }
                    ]
                }
            ]
        }
    ]
}
Output: { "post_id", "title", "edit_url", "preview_url", "elements_created": integer }
```

---

## Implementation Order

| Step | Phase | What | Priority |
|------|-------|------|----------|
| 1 | Foundation | Plugin bootstrap + dependency checks | P0 |
| 2 | Foundation | Elementor data access layer | P0 |
| 3 | Foundation | Element factory + ID generator | P0 |
| 4 | Foundation | Schema generator + control mapper | P0 |
| 5 | Read Tools | Query/discovery abilities (7 tools) | P0 |
| 6 | Pages | Page CRUD abilities (5 tools) | P1 |
| 7 | Layout | Container/layout abilities (4 tools) | P1 |
| 8 | Widgets | Universal + core widget tools (10 tools) | P1 |
| 9 | Widgets | Pro widget convenience tools (6 tools) | P2 |
| 10 | Templates | Template save/apply tools (2 tools) | P2 |
| 11 | Globals | Global colors/typography tools (2 tools) | P2 |
| 12 | Composite | Build-page composite tool (1 tool) | P2 |

**Total: ~37 MCP tools**

## Permission Model

All abilities use WordPress capability checks:

| Ability Group | Required Capability |
|---------------|-------------------|
| Read/Query tools | `edit_posts` |
| Page creation | `publish_pages` or `edit_pages` |
| Widget/layout manipulation | `edit_posts` + ownership check |
| Template management | `edit_posts` |
| Global settings | `manage_options` |
| Delete operations | `delete_posts` + ownership check |

## Key Design Decisions

1. **Container-first**: All layout tools use the modern Container element (not legacy Sections/Columns). Legacy support is optional.

2. **Schema-driven validation**: Widget settings are validated against auto-generated schemas from Elementor's control definitions before saving. This prevents invalid data.

3. **Universal + convenience pattern**: The `add-widget` tool works for ANY widget via `get-widget-schema` discovery. Convenience tools (`add-heading`, `add-button`, etc.) provide simpler interfaces for common widgets.

4. **Atomic + composite tools**: Individual tools for granular control, plus `build-page` for creating entire pages in one call. AI agents can choose the appropriate level.

5. **Elementor's native save mechanism**: We use `\Elementor\Plugin::$instance->documents->get()` and its `save()` method rather than raw meta updates, ensuring CSS regeneration and cache busting.

6. **Pro-aware**: Pro widget tools only register when Elementor Pro is active. Core tools work with free Elementor.
