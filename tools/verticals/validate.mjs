#!/usr/bin/env node
/**
 * validate.mjs — build-time validator for the Industry Skill Pack vertical files.
 *
 * Parses the frontmatter of every skills/emcp-skills/verticals/<slug>.md and
 * asserts the library combo is real, per docs/INDUSTRY_SKILL_PACKS_PLAN.md § 8.2:
 *
 *   - brand_kits[]   : non-empty; every slug exists in the snapshot        (ERROR)
 *   - prompt_category: one of the known category slugs                     (ERROR)
 *   - prompt_slugs   : [] (= whole category) OR every slug exists          (ERROR)
 *                      a slug whose home category != prompt_category is a  (WARNING)
 *                      deliberate cross-category pull (§ 4.1.1)
 *   - template_slugs : literal `none` OR every slug exists (empty [] = err) (ERROR)
 *   - body           : the six required § 4.2 sections are present         (ERROR)
 *
 * Validates the on-disk vertical files (which are premium content, not tracked
 * in this public repo) against the vendored, content-free library-slugs.json.
 * Run locally / in pre-commit before assembling the premium build.
 *
 * Usage:
 *   node tools/verticals/validate.mjs [path-to-verticals-dir]
 * Exit code: 0 = clean (warnings allowed), 1 = at least one ERROR, 2 = setup failure.
 */

import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { join, dirname, basename } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const PLUGIN_ROOT = join(HERE, '..', '..');

const VERTICALS_DIR =
  process.argv[2] || join(PLUGIN_ROOT, 'skills', 'emcp-skills', 'verticals');
const SNAPSHOT = join(HERE, 'library-slugs.json');

// The six required body sections (§ 4.2), matched case-insensitively by a
// distinctive substring of each heading.
const REQUIRED_SECTIONS = [
  { key: 'Brand voice & tone', test: /brand voice/i },
  { key: 'Target keywords', test: /target keyword/i },
  { key: 'Page set & structure', test: /page set|page structure/i },
  { key: 'Recommended library combo', test: /library combo/i },
  { key: 'Conversion patterns', test: /conversion pattern/i },
  { key: 'Compliance & credibility', test: /complianc/i },
];

function fail(msg) {
  console.error(`✗ ${msg}`);
  process.exit(2);
}

if (!existsSync(SNAPSHOT)) {
  fail(`Missing ${SNAPSHOT}. Run: node tools/verticals/sync-slugs.mjs`);
}
if (!existsSync(VERTICALS_DIR)) {
  fail(`Verticals dir not found: ${VERTICALS_DIR}`);
}

const snap = JSON.parse(readFileSync(SNAPSHOT, 'utf8'));
const brandKitSet = new Set(snap.brand_kits);
const categorySet = new Set(snap.prompt_categories);
const promptToCategory = snap.prompts; // slug -> category slug
const templateSet = new Set(snap.templates);

/**
 * Minimal frontmatter parser — handles the flat scalar + flow-array YAML the
 * vertical contract uses (no nesting). Returns { data, body } or null.
 */
function parseFrontmatter(raw) {
  const m = raw.match(/^---\r?\n([\s\S]*?)\r?\n---\r?\n?([\s\S]*)$/);
  if (!m) return null;
  const data = {};
  for (const line of m[1].split(/\r?\n/)) {
    if (!line.trim() || /^\s*#/.test(line)) continue;
    const idx = line.indexOf(':');
    if (idx === -1) continue;
    const key = line.slice(0, idx).trim();
    let val = line.slice(idx + 1).trim();
    // Strip trailing inline comment (only when clearly a comment, after a value).
    val = val.replace(/\s+#.*$/, '').trim();
    if (val.startsWith('[') && val.endsWith(']')) {
      const inner = val.slice(1, -1).trim();
      data[key] =
        inner === ''
          ? []
          : inner.split(',').map((s) => s.trim().replace(/^['"]|['"]$/g, '')).filter(Boolean);
    } else {
      data[key] = val.replace(/^['"]|['"]$/g, '');
    }
  }
  return { data, body: m[2] };
}

const files = readdirSync(VERTICALS_DIR)
  .filter((f) => f.endsWith('.md') && f.toLowerCase() !== 'readme.md')
  .sort();

if (files.length === 0) {
  console.log(`No vertical files found in ${VERTICALS_DIR} — nothing to validate.`);
  process.exit(0);
}

let errorCount = 0;
let warnCount = 0;
const crossCategory = []; // roll-up

for (const file of files) {
  const slug = basename(file, '.md');
  const raw = readFileSync(join(VERTICALS_DIR, file), 'utf8');
  const errors = [];
  const warns = [];

  const parsed = parseFrontmatter(raw);
  if (!parsed) {
    errors.push('no parseable YAML frontmatter');
    report(file, slug, null, errors, warns);
    errorCount += errors.length;
    continue;
  }
  const { data, body } = parsed;

  // brand_kits
  const kits = Array.isArray(data.brand_kits) ? data.brand_kits : [];
  if (kits.length === 0) {
    errors.push('brand_kits is empty (need at least the primary kit)');
  }
  for (const k of kits) {
    if (!brandKitSet.has(k)) errors.push(`brand_kit "${k}" not in library`);
  }

  // prompt_category (slug form)
  const cat = data.prompt_category;
  if (!cat) {
    errors.push('prompt_category missing');
  } else if (!categorySet.has(cat)) {
    errors.push(
      `prompt_category "${cat}" is not a known category slug (expected one of: ${snap.prompt_categories.join(', ')})`,
    );
  }

  // prompt_slugs — [] means "use whole category"; otherwise each must exist,
  // and a different home category is a cross-category WARNING (not an error).
  const promptSlugs = Array.isArray(data.prompt_slugs) ? data.prompt_slugs : [];
  for (const ps of promptSlugs) {
    const home = promptToCategory[ps];
    if (!home) {
      errors.push(`prompt_slug "${ps}" exists in no category`);
    } else if (cat && home !== cat) {
      warns.push(`prompt_slug "${ps}" is cross-category (lives in "${home}", not "${cat}")`);
      crossCategory.push(`${slug}: ${ps} (${home})`);
    }
  }

  // template_slugs — literal "none" or a non-empty array of real slugs.
  const tpl = data.template_slugs;
  if (tpl === undefined || tpl === '') {
    errors.push('template_slugs missing (use `none` or a list)');
  } else if (Array.isArray(tpl)) {
    if (tpl.length === 0) {
      errors.push('template_slugs is an empty array — use the literal `none` instead');
    }
    for (const t of tpl) {
      if (!templateSet.has(t)) errors.push(`template_slug "${t}" not in library`);
    }
  } else if (tpl !== 'none') {
    errors.push(`template_slugs must be \`none\` or a list, got "${tpl}"`);
  }

  // Required body sections.
  for (const sec of REQUIRED_SECTIONS) {
    const present = body
      .split(/\r?\n/)
      .some((l) => /^#{1,6}\s/.test(l) && sec.test.test(l));
    if (!present) errors.push(`missing required section: "${sec.key}"`);
  }

  report(file, slug, data, errors, warns);
  errorCount += errors.length;
  warnCount += warns.length;
}

function report(file, slug, data, errors, warns) {
  const status = errors.length ? '✗' : warns.length ? '!' : '✓';
  // Resolution summary so a reviewer sees the actual combo at a glance.
  let combo = '';
  if (data) {
    const kit = (data.brand_kits && data.brand_kits[0]) || '—';
    const prompt =
      Array.isArray(data.prompt_slugs) && data.prompt_slugs.length
        ? data.prompt_slugs.join(', ')
        : `(whole ${data.prompt_category || '?'} category)`;
    const tpl = Array.isArray(data.template_slugs)
      ? data.template_slugs.join(', ')
      : data.template_slugs || '—';
    combo = `kit=${kit} · prompt=${prompt} · template=${tpl}`;
  }
  console.log(`${status} ${file}${combo ? `  [${combo}]` : ''}`);
  for (const w of warns) console.log(`    ! ${w}`);
  for (const e of errors) console.log(`    ✗ ${e}`);
}

console.log('');
console.log(
  `Checked ${files.length} vertical(s): ${errorCount} error(s), ${warnCount} warning(s).`,
);
if (crossCategory.length) {
  console.log(`Cross-category pulls (expected — verify each is intentional):`);
  for (const c of crossCategory) console.log(`  · ${c}`);
}

process.exit(errorCount > 0 ? 1 : 0);
