# Vertical pack tooling

Dev tooling for the **Industry Skill Packs** feature (see
[`docs/INDUSTRY_SKILL_PACKS_PLAN.md`](../../docs/INDUSTRY_SKILL_PACKS_PLAN.md)).
Public, tracked, no dependencies (Node ≥ 18, ESM).

These tools validate the premium vertical files at
`skills/emcp-skills/verticals/*.md` against the shipped content libraries — but
they contain **only slug identifiers and a linter**, no premium content, so they
live in the public repo. The vertical `.md` files themselves are premium content
and are *not* tracked here (`skills/*` is gitignored); they ship via the premium
build and are version-controlled in the premium source of truth.

## Files

| File | What |
|---|---|
| `sync-slugs.mjs` | Regenerates `library-slugs.json` from the EMCP website data dir. |
| `library-slugs.json` | Vendored, content-free snapshot: brand-kit slugs, prompt-category slugs, prompt-slug → category map, template slugs. **Generated — do not hand-edit.** |
| `validate.mjs` | Lints each vertical file's frontmatter + required sections against the snapshot. |

## Usage

```bash
# 1. Refresh the snapshot when the libraries change (point at the website data dir):
node tools/verticals/sync-slugs.mjs "E:/MSR Builds/Products/EMCP/website/src/data"
#    …or set EMCP_DATA_DIR and omit the argument.

# 2. Validate the vertical files (run before assembling the premium build / in pre-commit):
node tools/verticals/validate.mjs
```

## What the validator enforces (§ 8.2)

- `brand_kits[]` — non-empty; each slug is a real **kit** (not a category). **Error** otherwise.
- `prompt_category` — a known category **slug**. **Error** otherwise.
- `prompt_slugs` — `[]` (whole category) or every slug exists. **Error** if a slug exists nowhere;
  **warning** if a slug's home category differs from `prompt_category` (deliberate cross-category pull).
- `template_slugs` — `none` or every slug exists; empty `[]` is an **error** (use `none`).
- The six required body sections (§ 4.2) are present.

Exit `0` = clean (warnings allowed), `1` = at least one error, `2` = setup failure
(missing snapshot or verticals dir).

The run prints a per-vertical resolution summary (`kit=… · prompt=… · template=…`) and a
roll-up of cross-category pulls so an *unexpected* one stands out from the approved cases.
