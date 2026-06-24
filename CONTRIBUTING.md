# Contributing to MCP Tools for Elementor

Thank you for your interest in contributing to MCP Tools for Elementor! This project bridges AI agents and Elementor page design through the Model Context Protocol, and community contributions are essential to making it better.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How Can I Contribute?](#how-can-i-contribute)
- [Development Setup](#development-setup)
- [Architecture Overview](#architecture-overview)
- [Adding a New MCP Tool](#adding-a-new-mcp-tool)
- [Contributing Prompts](#contributing-prompts)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Submitting Changes](#submitting-changes)
- [Release Checklist](#release-checklist)
- [Reporting Bugs](#reporting-bugs)
- [Suggesting Features](#suggesting-features)

## Code of Conduct

This project follows the [WordPress Community Code of Conduct](https://make.wordpress.org/handbook/community-code-of-conduct/). Please be respectful and constructive in all interactions.

## How Can I Contribute?

There are many ways to contribute, regardless of your experience level:

- **Report bugs** — Found something broken? [Open an issue](https://github.com/msrbuilds/elementor-mcp/issues/new).
- **Suggest features** — Have an idea for a new MCP tool? We'd love to hear it.
- **Improve documentation** — Fix typos, clarify instructions, or add examples.
- **Add new widget tools** — Create convenience shortcuts for Elementor widgets not yet covered.
- **Write tests** — Help improve test coverage with PHPUnit tests.
- **Fix bugs** — Browse [open issues](https://github.com/msrbuilds/elementor-mcp/issues) and submit a fix.
- **Contribute prompts** — Add new landing page blueprints for industries not yet covered. See [Contributing Prompts](#contributing-prompts).
- **Share your experience** — Write about how you use the plugin, share prompts that work well.

## Development Setup

### Prerequisites

- PHP 7.4 or later
- WordPress 6.8+ (local development environment)
- Elementor 3.20+ (free or Pro)
- [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin
- Composer (for dev dependencies)
- WP-CLI (recommended for testing MCP tools)

### Installation

1. Clone the repository into your WordPress plugins directory:

   ```bash
   cd /path/to/wordpress/wp-content/plugins
   git clone https://github.com/msrbuilds/elementor-mcp.git
   cd elementor-mcp
   ```

2. Install development dependencies:

   ```bash
   composer install
   ```

3. Activate the plugin and its dependencies in WordPress.

4. Verify the MCP server is registered:

   ```bash
   wp mcp-adapter list --path=/path/to/wordpress
   ```

### Testing MCP Tools

Use the MCP Inspector to test tools interactively:

```bash
npx @modelcontextprotocol/inspector wp mcp-adapter serve \
  --server=emcp-tools-server --user=admin --path=/path/to/wordpress
```

## Architecture Overview

Understanding the plugin's architecture will help you contribute effectively.

```
emcp-tools/
├── elementor-mcp.php              # Bootstrap: constants, dependency checks, require_once
├── includes/
│   ├── class-plugin.php           # Singleton orchestrator (hooks registration)
│   ├── class-elementor-data.php   # Data access layer (read/write Elementor documents)
│   ├── class-element-factory.php  # Builds valid Elementor JSON structures
│   ├── class-id-generator.php     # 7-char hex unique IDs
│   ├── class-openverse-client.php # Openverse API HTTP client
│   ├── abilities/                 # MCP tools grouped by domain
│   │   ├── class-ability-registrar.php    # Coordinates all ability groups
│   │   ├── class-query-abilities.php      # Read-only discovery tools
│   │   ├── class-page-abilities.php       # Page CRUD
│   │   ├── class-layout-abilities.php     # Container/layout tools
│   │   ├── class-widget-abilities.php     # Widget add/update tools
│   │   ├── class-template-abilities.php   # Template tools
│   │   ├── class-global-abilities.php     # Global settings tools
│   │   ├── class-composite-abilities.php  # build-page composite tool
│   │   ├── class-stock-image-abilities.php # Stock image tools
│   │   ├── class-svg-icon-abilities.php   # SVG icon upload tool
│   │   └── class-custom-code-abilities.php # CSS/JS/snippet tools
│   ├── schemas/                   # JSON Schema generation
│   │   ├── class-schema-generator.php
│   │   └── class-control-mapper.php
│   └── validators/                # Input validation
│       ├── class-element-validator.php
│       └── class-settings-validator.php
```

### Key Concepts

- **Abilities** are MCP tools registered via the WordPress Abilities API (`wp_register_ability()`).
- **Data Layer** wraps Elementor's document system — always use `$this->data` methods, never update `_elementor_data` meta directly.
- **Element Factory** creates valid Elementor JSON element structures with proper IDs.
- **Schema Generator** auto-generates JSON Schema from Elementor's widget controls, so AI agents know what settings each widget accepts.

## Adding a New MCP Tool

This is the most common type of contribution. Here's how to add a new tool:

### 1. Choose the right ability class

Tools are grouped by domain. Add your tool to the appropriate existing class, or create a new one if it doesn't fit.

### 2. Register the ability

In your ability class's `register()` method, call `wp_register_ability()`:

```php
wp_register_ability(
    'emcp-tools/my-new-tool',
    array(
        'title'               => __( 'My New Tool', 'elementor-mcp' ),
        'description'         => __( 'What this tool does.', 'elementor-mcp' ),
        'category'            => 'elementor-mcp',
        'input_schema'        => array(
            'type'       => 'object',
            'properties' => array(
                'post_id' => array(
                    'type'        => 'integer',
                    'description' => __( 'The page/post ID.', 'elementor-mcp' ),
                ),
            ),
            'required' => array( 'post_id' ),
        ),
        'permission_callback' => array( $this, 'check_edit_permission' ),
        'callback'            => array( $this, 'handle_my_new_tool' ),
    )
);
```

### 3. Implement the handler

```php
public function handle_my_new_tool( array $input ) {
    $post_id = absint( $input['post_id'] );

    // Your logic here using $this->data and $this->factory

    return array(
        'success' => true,
        'message' => 'Tool completed successfully.',
    );
}
```

### 4. Register in the registrar

If you created a new ability class, add it to `class-ability-registrar.php`:

```php
$my_class = new Elementor_MCP_My_Abilities( $this->data, $this->factory );
$my_class->register();
$this->ability_names = array_merge( $this->ability_names, $my_class->get_ability_names() );
```

And add the `require_once` in `elementor-mcp.php`.

### 5. Add to admin tools list

Add the tool entry to `get_all_tools()` in `includes/admin/class-admin.php` under the appropriate category.

## Contributing Prompts

The `prompts/` directory contains sample landing page blueprints that users can copy and paste into their AI client to auto-build complete Elementor pages. Contributing new prompts is a great way to help without writing PHP.

### Prompt Structure

Each prompt is a standalone Markdown file (`.md`) that an AI agent can follow from start to finish. A well-structured prompt includes these sections in order:

1. **Title & overview** — What type of page this builds (e.g., "Dental Clinic Landing Page").
2. **Layout rules** — Container-first approach, flexbox direction, responsive widths.
3. **Design system** — Color palette (hex values), typography (font families, sizes, weights), spacing scale.
4. **Image sourcing** — Keywords for `search-images` tool calls, with fallback placeholder descriptions.
5. **SVG icons** — Icon specifications for `upload-svg-icon` (raw SVG markup or common icon names).
6. **Page structure** — Section-by-section breakdown (hero, services, testimonials, CTA, footer, etc.) with specific widget types, text content, and settings.
7. **Entrance animations** — Use Elementor's built-in `_animation` (e.g., `fadeInUp`, `fadeInLeft`, `zoomIn`), `animation_duration` (`slow` or default), and `_animation_delay` (in ms) for staggered effects.
8. **Custom CSS** — Only where Elementor's built-in controls are insufficient (hover states, pseudo-elements). Use the `selector` keyword for element-scoped CSS.
9. **Custom JavaScript** — Scroll-triggered counters, smooth scroll, or other interactivity via `add-custom-js`.
10. **Execution order** — Numbered step-by-step instructions telling the AI which tools to call and in what sequence.
11. **Final checklist** — Verification steps (responsive check, link targets, image alt text, consistent spacing).

### Guidelines

- **Use only MCP tool names** — Reference tools like `create-page`, `add-container`, `add-heading`, `search-images`, etc. Don't use generic instructions like "add a title" — be specific about which widget tool to use.
- **Be explicit with settings** — Include hex colors, font sizes, padding values, and widget-specific settings. The more specific the prompt, the more consistent the output across different AI clients.
- **Prefer built-in animations** — Use Elementor's `_animation` and `_animation_delay` settings over custom CSS animations. This keeps prompts simpler and leverages Elementor's native Motion Effects.
- **Minimize custom CSS** — Only add custom CSS for things Elementor can't do natively (e.g., hover color transitions, gradient text, backdrop filters). Always use the `selector` keyword for scoping.
- **Include realistic content** — Use placeholder text that sounds like a real business, not lorem ipsum. Include realistic pricing, phone numbers, addresses, and business hours.
- **Test your prompt** — Run it through an AI client (Claude Code, Cursor, etc.) connected to a local WordPress + Elementor setup to verify it builds correctly.

### Naming Convention

Prompt files use `UPPER_SNAKE_CASE.md` matching the business type:

```
prompts/
├── LOCAL_BUSINESS.md
├── DENTAL_CLINIC.md
├── HAIR_SALON.md
└── YOUR_NEW_PROMPT.md
```

### Submitting a Prompt

1. Create your `.md` file in the `prompts/` directory.
2. Add an entry to the `$prompt_meta` array in `includes/admin/views/page-prompts.php` with `title`, `industry` tag, and a one-line `description`.
3. Add a row to the Sample Prompts table in `README.md`.
4. Open a PR with the prompt file and the two metadata updates.

### Example Reference

See any existing prompt in `prompts/` (e.g., `LOCAL_BUSINESS.md`) for the expected structure and level of detail.

## Coding Standards

This project follows WordPress coding standards strictly:

- **Naming**: `snake_case` for functions/variables, `Upper_Snake_Case` for classes, `UPPER_SNAKE` for constants.
- **Prefix everything**: All functions, classes, hooks, and options use the `elementor_mcp` or `Elementor_MCP` prefix.
- **Strings**: All user-facing strings must be translatable using `__()`, `esc_html__()`, etc. with the `elementor-mcp` text domain.
- **Security**: Sanitize all input (`sanitize_text_field`, `absint`), escape all output (`esc_html`, `esc_attr`, `esc_url`), check capabilities before privileged operations.
- **No direct meta updates**: Always use `$this->data->save_elementor_data()` which triggers Elementor CSS regeneration and cache busting.

### PHP Compatibility

- Target PHP 7.4+ (no union types, no named arguments, no enums).
- Use type hints for parameters and return types where supported.

## Testing

### Running Tests

```bash
composer install
vendor/bin/phpunit --configuration phpunit.xml.dist
```

### Writing Tests

- Place test files in the `tests/` directory.
- Follow PHPUnit naming conventions: `Test_My_Feature` class in `tests/test-my-feature.php`.
- Mock Elementor and WordPress functions as needed.

## Submitting Changes

### Pull Request Process

1. **Fork** the repository and create a feature branch from `main`:

   ```bash
   git checkout -b feature/my-new-tool
   ```

2. **Make your changes** following the coding standards above.

3. **Test locally** — ensure your tool works via MCP Inspector and doesn't break existing tools.

4. **Run PHP syntax check** on all modified files:

   ```bash
   php -l includes/abilities/class-my-abilities.php
   ```

5. **Commit** with a clear, descriptive message:

   ```bash
   git commit -m "Add my-new-tool ability for doing X"
   ```

6. **Push** and open a Pull Request against `main`.

### PR Guidelines

- Keep PRs focused — one feature or fix per PR.
- Include a clear description of what the tool does and why it's useful.
- Update the tool count in `README.md`, `readme.txt`, and `CLAUDE.md` if adding new tools (see [Release Checklist](#release-checklist)).
- Add a changelog entry in `readme.txt`.
- If adding Pro-only tools, make sure they conditionally register when Elementor Pro is active.

## Release Checklist

Before tagging a new release, verify these items are consistent across all files:

### Version Number

Update the version string in **all** of these locations:

| File | Location |
|---|---|
| `elementor-mcp.php` | Plugin header `Version:` field |
| `elementor-mcp.php` | `ELEMENTOR_MCP_VERSION` constant |
| `readme.txt` | `Stable tag:` header |
| `readme.txt` | New `= x.y.z =` changelog entry |
| `readme.txt` | New `= x.y.z =` upgrade notice entry |
| `README.md` | Version badge in shields.io URL |

> `@since` tags in PHP docblocks should reflect the version a feature was **introduced**, not the current release — don't bump these on patch releases.

### Tool Count

When tools are added or removed, update the count in **all** of these locations:

| File | What to update |
|---|---|
| `README.md` | Features section (`~NN MCP Tools`) |
| `README.md` | MCP Tools badge (`MCP_Tools-~NN-orange`) |
| `readme.txt` | Description paragraph (`~NN MCP`) |
| `CLAUDE.md` | Project Overview paragraph (`~NN MCP tools`) and status line |

### Feature Parity

The feature bullet lists in `README.md` (Features section) and `readme.txt` (Description > Key Features) must cover the same capabilities. When adding a new feature category (e.g., Stock Images, Custom Code), add it to **both** files.

### Quick Verification

```bash
# Check version consistency
grep -n "1\\.x\\.y" elementor-mcp.php readme.txt README.md

# Check tool count consistency
grep -n "~[0-9]* MCP" README.md readme.txt CLAUDE.md
```

## Reporting Bugs

When reporting bugs, please include:

1. **Plugin version** and **Elementor version**.
2. **WordPress version** and **PHP version**.
3. **Steps to reproduce** the issue.
4. **Expected behavior** vs **actual behavior**.
5. **Error logs** if applicable (check `wp-content/debug.log`).
6. **MCP client** you're using (Claude Code, Claude Desktop, Cursor, etc.).

## Suggesting Features

Feature requests are welcome! When suggesting a new MCP tool:

1. **Describe the use case** — what are you trying to build with AI that this tool would enable?
2. **Specify the inputs/outputs** — what parameters should the tool accept and what should it return?
3. **Note if it requires Pro** — does the feature depend on Elementor Pro APIs?
4. **Consider the permission model** — what WordPress capability should be required?

---

Thank you for contributing to MCP Tools for Elementor! Every contribution, no matter how small, helps make AI-powered Elementor design better for everyone.
