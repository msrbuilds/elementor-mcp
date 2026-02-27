# WordPress.org Detailed Plugin Guidelines (Condensed)

Source: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/

## 18 Official Guidelines

1. **GPL Compatible License** — All code, data, images must use GPLv2 or later (or GPL-compatible). Third-party libraries must also comply.
2. **Developer Responsibility** — Developers are responsible for all files, licensing, and third-party API terms of use.
3. **Stable Version Available** — The directory-hosted version must always be the current, distributable version.
4. **Human-Readable Code** — No obfuscation (p,a,c,k,e,r, uglify mangle, unclear naming). Source code must be publicly accessible.
5. **No Trialware** — No locked features, time-limited trials, or sandbox-only API access. Use add-on plugins for premium code.
6. **SaaS Permitted** — Plugins can interface with paid external services if documented in readme. Services must provide real functionality.
7. **No Tracking Without Consent** — No contacting external servers without explicit opt-in. Privacy policy required. Exception: SaaS services where activation = consent.
8. **No External Executable Code** — No serving updates from non-WP.org servers, no third-party CDNs for JS/CSS (fonts excepted), no iframes for admin pages.
9. **No Illegal/Dishonest Activity** — No SEO manipulation, fake reviews, sockpuppeting, presenting others' work as your own, crypto-mining, etc.
10. **No Embedded Links/Credits Without Permission** — "Powered by" links must be optional and default to hidden. Credits cannot be required for functionality.
11. **No Dashboard Hijacking** — Upgrade prompts/notices must be limited, contextual, and dismissible. Avoid dashboard advertising.
12. **No Readme Spam** — No keyword stuffing, no competitor tags, max 5 tags. Affiliate links must be disclosed.
13. **Use WordPress Default Libraries** — Must use WP-bundled libraries (jQuery, PHPMailer, etc.), not custom copies.
14. **Avoid Frequent SVN Commits** — SVN is for releases, not development. Descriptive commit messages required.
15. **Increment Version Numbers** — Version must increment for each release. trunk readme.txt must reflect current version.
16. **Complete Plugin at Submission** — Fully functional plugin zip required. No name reservations.
17. **Respect Trademarks** — Cannot use others' trademarks as initial slug term without proof of ownership.
18. **WordPress.org Reserves Maintenance Rights** — They may update guidelines, remove plugins, or modify code for public safety.

## Developer Expectations

- Plugin data/reviews may not be restored after removal.
- Repeat violations = all plugins removed + developer ban.
- Contact info must be current; auto-replies not permitted.
- Security is ultimately the developer's responsibility.
