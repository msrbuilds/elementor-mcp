# Review Report Template

Use this exact structure when generating the plugin review report.

```markdown
# WordPress Plugin Review Report

**Plugin:** [Plugin Name]
**Version:** [Version]
**Author:** [Author]
**Reviewed:** [Date]
**Review Tool Versions:** PHPCS [version], PHPStan [version], PHPUnit [version]
**Overall Score:** [X/100]
**Verdict:** [✅ Ready for Submission / 🟡 Needs Work / 🔴 Major Rework Required]

---

## Executive Summary

[2-3 paragraph summary covering:
- Overall impression and quality level
- Most critical issues found
- Readiness for WordPress.org repository submission
- Estimated effort to fix all issues]

### Score Breakdown

| Category | Score | Status | Issues Found |
|----------|-------|--------|--------------|
| Security | X/25 | 🔴/🟠/🟡/🟢/✅ | X critical, X high, X medium |
| Coding Standards | X/25 | 🔴/🟠/🟡/🟢/✅ | X errors, X warnings |
| Repository Guidelines | X/20 | 🔴/🟠/🟡/🟢/✅ | X issues |
| Unit Tests | X/15 | 🔴/🟠/🟡/🟢/✅ | X issues |
| Accessibility | X/15 | 🔴/🟠/🟡/🟢/✅ | X issues |

---

## 1. Security Review (X/25)

### 1.1 Input Sanitization
**Status:** [🔴/🟠/🟡/🟢/✅]

[For each finding:]

**[Severity] [Issue Title]**
- **File:** `path/to/file.php` (Line XX)
- **Issue:** [Description]
- **Before (vulnerable):**
  ```php
  // Current code
  ```
- **After (fixed):**
  ```php
  // Fixed code
  ```

### 1.2 Output Escaping
**Status:** [🔴/🟠/🟡/🟢/✅]
[Findings with same format]

### 1.3 SQL Injection Prevention
**Status:** [🔴/🟠/🟡/🟢/✅]
[Findings]

### 1.4 Nonce Verification
**Status:** [🔴/🟠/🟡/🟢/✅]
[Findings]

### 1.5 Capability Checks
**Status:** [🔴/🟠/🟡/🟢/✅]
[Findings]

### 1.6 File Security
**Status:** [🔴/🟠/🟡/🟢/✅]
[Findings]

### 1.7 Data Validation
**Status:** [🔴/🟠/🟡/🟢/✅]
[Findings]

### 1.8 External Requests
**Status:** [🔴/🟠/🟡/🟢/✅]
[Findings]

---

## 2. WordPress Coding Standards (X/25)

### 2.1 Automated PHPCS Results

**Summary:** X errors, X warnings across X files

[Top findings from PHPCS grouped by type]

### 2.2 Manual Findings

#### Naming Conventions
[Findings]

#### Internationalization
[Findings]

#### Enqueuing Assets
[Findings]

#### WordPress API Usage
[Findings]

#### Bundled Libraries Check
[Findings]

---

## 3. Repository Guidelines (X/20)

### 3.1 Plugin Headers
**Status:** [🔴/🟠/🟡/🟢/✅]
[Assessment of each header field]

### 3.2 readme.txt
**Status:** [🔴/🟠/🟡/🟢/✅]
[Assessment of format, content, completeness]

### 3.3 Licensing
**Status:** [🔴/🟠/🟡/🟢/✅]
[License assessment]

### 3.4 Prefixing
**Status:** [🔴/🟠/🟡/🟢/✅]
[Prefix consistency check]

### 3.5 Data & Privacy
**Status:** [🔴/🟠/🟡/🟢/✅]
[External services, data collection, uninstall cleanup]

### 3.6 Admin Experience
**Status:** [🔴/🟠/🟡/🟢/✅]
[Notices, menus, upsells]

---

## 4. Unit Test Coverage (X/15)

### 4.1 Test Existence
**Status:** [🔴/🟠/🟡/🟢/✅]
[Does tests/ directory exist? phpunit.xml?]

### 4.2 Test Quality & Coverage
**Status:** [🔴/🟠/🟡/🟢/✅]
[Assessment of test quality]

### 4.3 Recommended Tests
[If tests are missing or incomplete, provide recommendations:]
- [ ] Test for [critical function 1]
- [ ] Test for [critical function 2]
- [ ] Test for [AJAX handler]
- [ ] Test for [database operations]

[If no tests exist, provide a sample test file]

---

## 5. Accessibility (X/15)

### 5.1 ARIA & Semantic HTML
**Status:** [🔴/🟠/🟡/🟢/✅]
[Findings]

### 5.2 Keyboard Navigation
**Status:** [🔴/🟠/🟡/🟢/✅]
[Findings]

### 5.3 Form Accessibility
**Status:** [🔴/🟠/🟡/🟢/✅]
[Findings]

### 5.4 Screen Reader Support
**Status:** [🔴/🟠/🟡/🟢/✅]
[Findings]

---

## ✅ What's Done Well

[List things the plugin does correctly — acknowledge good practices]

---

## Recommended Fixes (Priority Order)

### 🔴 Critical (Must Fix Before Submission)
1. **[Issue]** — `file.php:XX` — [Brief fix description]

### 🟠 High Priority (Should Fix)
1. **[Issue]** — `file.php:XX` — [Brief fix description]

### 🟡 Medium Priority (Recommended)
1. **[Issue]** — `file.php:XX` — [Brief fix description]

### 🟢 Low Priority (Nice to Have)
1. **[Issue]** — `file.php:XX` — [Brief fix description]

---

## Conclusion

[Final assessment paragraph:
- Is the plugin ready for repository submission?
- What's the estimated effort to address all critical/high issues?
- Any architectural concerns that may require significant refactoring?
- Overall recommendation]

**Verdict:** [✅ Ready / 🟡 Needs Work / 🔴 Major Rework]
```
