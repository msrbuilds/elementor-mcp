# Security Policy

## Reporting a Vulnerability

Please **do not** open a public GitHub issue for security vulnerabilities.

Report security issues privately using GitHub's built-in advisory mechanism:

**[Security → Advisories → New draft advisory](../../security/advisories/new)**

Include:
- A description of the vulnerability
- The file and line number where it occurs
- Steps to reproduce or a proof-of-concept
- Your assessment of impact and severity

We aim to acknowledge reports within **3 business days** and provide a fix or mitigation plan within **30 days** for confirmed vulnerabilities.

Responsible disclosure is appreciated. We will credit researchers in the release notes unless you prefer to remain anonymous.

## Scope

This plugin requires WordPress Application Password authentication for all tool calls. There are no unauthenticated attack surfaces. Vulnerabilities affecting only users with `manage_options` (Administrator role) are considered lower severity than those affecting `edit_posts` (Editor/Author role).

## Supported Versions

Security fixes are applied to the latest release only.
