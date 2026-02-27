---
name: wp-plugin-review
description: >
  Comprehensive WordPress plugin review covering security vulnerabilities, WordPress Coding Standards (WPCS) compliance,
  plugin repository guidelines, unit test coverage, and accessibility. Runs automated tools (PHPCS with WPCS, PHPStan, PHPUnit)
  plus deep manual code review. Outputs a detailed Markdown review report with issues, severity ratings, and actionable fixes.
  Use this skill whenever the user asks to "review a plugin", "audit a WordPress plugin", "check plugin security",
  "check plugin for repository submission", "review plugin code", "plugin code review", "check my plugin",
  or any request involving evaluating a WordPress plugin's code quality, security posture, or repository readiness.
  Also trigger when a user uploads a plugin zip or folder and asks for feedback, analysis, or a review.
---

# WordPress Plugin Review Skill

Review WordPress plugins for security, coding standards, repository guidelines, unit tests, and accessibility.
Produces a comprehensive Markdown report with findings, severity levels, and fix recommendations.

## Overview

This skill performs a **two-phase review**:

1. **Automated Analysis** — Install and run PHPCS (with WPCS rules), PHPStan, and PHPUnit
2. **Manual Code Review** — Deep inspection of security patterns, repo compliance, accessibility, and architecture

The final output is a structured Markdown report saved to `/mnt/user-data/outputs/`.

---

## Workflow

### Phase 0: Setup Environment

Run the setup script to install required tools:

```bash
bash scripts/setup_tools.sh
```

This installs PHPCS, WordPress Coding Standards, PHPStan, and PHPUnit if not already present.

### Phase 1: Locate the Plugin

1. Check `/mnt/user-data/uploads/` for uploaded plugin files (zip or folder)
2. If a zip file is found, extract it to `/home/claude/plugin-under-review/`
3. If a folder is found, copy it to `/home/claude/plugin-under-review/`
4. Identify the main plugin file (the one with the `Plugin Name:` header)

### Phase 2: Automated Analysis

Run the following tools and capture output:

#### PHPCS with WordPress Coding Standards
```bash
phpcs --standard=WordPress --extensions=php --report=json \
  /home/claude/plugin-under-review/ > /home/claude/phpcs-report.json 2>&1
```

Also run with security-focused sniffs:
```bash
phpcs --standard=WordPress-Extra --extensions=php \
  /home/claude/plugin-under-review/ 2>&1 | head -200
```

#### PHPStan (Static Analysis)
```bash
phpstan analyse --level=5 --no-progress \
  /home/claude/plugin-under-review/ 2>&1 | head -200
```

#### PHPUnit (if tests exist)
Check for `tests/` directory or `phpunit.xml`. If found:
```bash
cd /home/claude/plugin-under-review && phpunit 2>&1 | head -100
```

If no tests exist, note this as a finding in the report.

### Phase 3: Manual Code Review

Read `references/security-checklist.md` and `references/repo-guidelines-checklist.md` BEFORE starting the manual review.

For each PHP file in the plugin, review against ALL categories:

#### A. Security Review
Consult `references/security-checklist.md` for the full checklist. Key areas:

1. **Input Sanitization** — Every `$_GET`, `$_POST`, `$_REQUEST`, `$_SERVER`, `$_FILES` must be sanitized
2. **Output Escaping** — Every `echo`/`print` of dynamic data must use appropriate `esc_*()` functions
3. **SQL Injection** — All database queries must use `$wpdb->prepare()`
4. **Nonce Verification** — All form submissions and AJAX handlers must verify nonces
5. **Capability Checks** — All privileged actions must check `current_user_can()`
6. **File Operations** — File uploads must validate type/size and use WP filesystem API
7. **CSRF Protection** — State-changing requests must have nonce + referer checks
8. **Data Validation** — All data must be validated before processing
9. **Direct File Access** — All PHP files must prevent direct access (`defined('ABSPATH') || exit`)
10. **Secure API Calls** — External HTTP requests must use `wp_remote_get/post()`

#### B. WordPress Coding Standards
1. **Naming Conventions** — Functions, classes, hooks follow WP naming patterns
2. **File Organization** — Proper directory structure and file naming
3. **Hook Usage** — Correct use of actions and filters
4. **Enqueue Scripts/Styles** — Must use `wp_enqueue_script/style()` with proper deps
5. **Internationalization** — All user-facing strings must use translation functions
6. **PHP Compatibility** — Must work with PHP 7.4+
7. **WordPress API Usage** — Use WP functions instead of raw PHP where available
8. **No Bundled Core Libraries** — Must not include jQuery, PHPMailer, etc.

#### C. Repository Guidelines
Consult `references/repo-guidelines-checklist.md` for full details. Key areas:

1. **readme.txt** — Proper format, required headers, changelog, FAQ
2. **Plugin Headers** — All required headers present and accurate
3. **License** — GPL-2.0-or-later compatible
4. **No Tracking/Phoning Home** — No unauthorized external calls
5. **No Obfuscated Code** — All code must be human-readable
6. **Stable Tag** — Must match the latest tagged version
7. **Tested Up To** — Must reference a current WordPress version

#### D. Unit Test Coverage
1. **Test Existence** — Does a `tests/` directory exist?
2. **Test Quality** — Do tests assert meaningful behavior?
3. **Coverage Areas** — Are critical functions tested?
4. **WP Test Framework** — Uses WP_UnitTestCase or equivalent?
5. **CI/CD Ready** — Is there a phpunit.xml or phpunit.xml.dist?

#### E. Accessibility
1. **ARIA Attributes** — Admin UI elements have proper ARIA labels
2. **Keyboard Navigation** — Interactive elements are keyboard accessible
3. **Color Contrast** — UI meets WCAG 2.1 AA standards
4. **Screen Reader Support** — Dynamic content announces changes
5. **Form Labels** — All inputs have associated labels
6. **Focus Management** — Focus is managed in modals/dialogs
7. **Semantic HTML** — Proper use of headings, landmarks, roles

### Phase 4: Generate Report

Use severity levels:

| Severity | Meaning |
|----------|---------|
| 🔴 CRITICAL | Security vulnerability or blocking issue — must fix |
| 🟠 HIGH | Significant issue — may cause repository rejection |
| 🟡 MEDIUM | Best practice violation — recommended fix |
| 🟢 LOW | Minor improvement — nice to have |
| ✅ PASS | Area passes review |

Use the report template in `references/report-template.md` for structure.

Save the final report to: `/mnt/user-data/outputs/[plugin-name]-review-report.md`

---

## Important Notes

- Always read BOTH reference checklist files before starting manual review
- Provide actual code fixes with before/after snippets, not just descriptions
- Reference specific file names and line numbers for every finding
- If the plugin has no unit tests, provide a sample test file as a bonus deliverable
- Be thorough but fair — acknowledge good practices alongside issues
- The report should be fully actionable: a developer should fix all issues from reading it
- Score each category and provide an overall score out of 100
