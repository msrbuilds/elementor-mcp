**Page builder:** Elementor

# Alex Rivera — web developer portfolio landing page

Design and build a **complete, production-quality portfolio landing page** for "Alex Rivera", a full-stack web developer and UI designer. **You own the design.** If web search is available, research 2–3 genuinely well-designed developer / designer portfolio sites first and form a point of view; then decide the layout, composition, and section design yourself. Do not fall back to a generic centered-hero + three-identical-cards template — this is a dark, code-forward portfolio: sharp and technical, a code aesthetic without the tired clichés, work presented with real editorial rhythm. **Vary your hero composition to fit this brand specifically — in particular, do not reach for the overused "one large photo with a smaller photo card overlapping its corner" hero unless it genuinely serves this design; choose an opener you would not use for a coffee shop or a law firm.** The sections below define **what the page must communicate, not how it must look.**

## Style guide (constraints, not layout)

**Palette — flat colors only, no gradients:**

| Role | Value |
|---|---|
| Surface, base | `#0D1117` (near-black) |
| Surface, raised bands | `#161B22` (raised panel) |
| Ink / headings | `#E6EDF3` (near-white) |
| Accent — use sparingly: eyebrows, labels, links, buttons | `#3FDF8C` (terminal green) |
| Muted body text | `#8B949E` (slate gray) |
| Hairlines / dividers | `#242C36` (subtle border) |

**Typography:** Display = **Inter Tight** (fallback: Inter) — tight, modern sans, bold headlines, lowercase is on-brand. Accents/labels/eyebrows/code in **JetBrains Mono** (fallback: monospace) — the mono is the signature; use it for eyebrows, tech labels, stats, and any code UI. Body = **Inter**, 16–17px, line-height ~1.8. Small uppercase mono eyebrows in terminal green.

**Feel:** sharp, technical, code-aesthetic — crisp 4px radii, subtle 1px borders instead of shadows, generous whitespace on a dark canvas. The green accent is scarce and deliberate; the mono type does the personality. No neon overload, no glow soup.

**Motion:** crisp and restrained — subtle entrance reveals and small hovers; a terminal/code detail may animate if the builder does it natively. Use only what the chosen builder provides natively; **no custom JavaScript and no hand-rolled motion CSS.**

## Design signature (make this page distinct — adapt as you see fit)
These are compositional cues, not layout mechanics. Use them to give this portfolio its own look rather than a generic template:
- **Hero:** a terminal / code-panel lockup — the intro and headline paired with a small, real code-block object-literal about the developer, rendered as a mono panel with a 1px border, lowercase and precise; a code aesthetic without the tired clichés, no glow soup.
- **Projects:** an alternating editorial rhythm where each project block shifts weight and its tech tags read as mono chips — never a uniform three-up grid.
- **Mono signature:** let JetBrains Mono carry the eyebrows, tech labels, and stats so the type does the personality; keep the terminal-green accent scarce.
- **Shape language:** crisp 4px radii, subtle 1px borders instead of shadows, generous whitespace on the dark canvas.

## Sections — what each must communicate (design them your way)

1. **Hero** — instant sense of who and what; a friendly intro line, a headline about building digital experiences people love, a one-line specialty (React, Node.js, creative web), primary "view my work" + secondary "download CV" actions, and social links. A code-block visual (a small object-literal about the developer) is on-brand as a companion to the copy.
2. **Tech stack** — the working toolkit as a clean labeled strip: the eight technologies below.
3. **Projects** — the featured work below, each with its type tag, name, a short outcome-focused description, its tech tags, and a "view project" link; alternate the rhythm so it reads editorial, not grid-monotonous.
4. **Services** — the four ways Alex helps below, each with one plain-language line.
5. **About** — crafting code with creativity (two short bio paragraphs) plus the three stats below.
6. **Testimonials** — three distinct, believable client quotes with names and role/company; write them yourself.
7. **Contact / CTA** — one strong closing moment: have a project in mind? A working contact form (name, email, project type, budget range, message) built with the builder's native form element, with the email address given equal prominence as an alternative, plus social links. If the builder has no form element available, use a clearly labeled email call-to-action instead of omitting this.
8. **Footer** — designed & built by Alex Rivera, and copyright.

## Content facts (use these verbatim)

**Tech stack:** React · Node.js · TypeScript · Figma · Next.js · Tailwind CSS · PostgreSQL · AWS

**Projects:** SaaS Analytics Dashboard — Web App — a real-time analytics platform with custom charting and role-based access · Mobile Fitness App — Mobile UI — a colorful, motivating tracker with clean onboarding · Boutique E-Commerce — Web App — an elegant storefront with a fast, frictionless checkout

**Services:** Frontend Development · Backend & APIs · UI/UX Design · Performance Optimization

**Stats:** 50+ projects completed · 5+ years experience · 30+ happy clients

**Contact:** alex@example.com · available for freelance work and collaborations

## Standards (non-negotiable)

### Accessibility — WCAG 2.1 AA
- Text contrast ≥ 4.5:1 (≥ 3:1 for large text) against its actual background — check text over photos and raised panels.
- Exactly one H1; headings descend logically (no skipped levels for styling reasons).
- Descriptive alt text on every image; meaningful link/button labels (never "click here").
- Comfortable touch targets on buttons and links; readable body size (≥ 16px); every form field has a visible, associated label.

### Images — real photography in every slot
- Source real photos from **Unsplash, Pexels, or Pixabay** (use the available stock-image search/sideload tools if present, otherwise direct source URLs). Never leave an image slot empty; never use placeholder URLs.
- Curate for a cohesive dark-theme grade that matches the palette; project mockups and a portrait that share one crisp, technical mood, not eight random tech shots.

### Icons — one consistent SVG set
- Use inline SVG icons in a single consistent style — Lucide/Feather spec: `viewBox="0 0 24 24"`, `fill="none"`, `stroke="currentColor"`, `stroke-width="1.75"`, round caps/joins. Generate what you need (code brackets, layout-grid, rocket, palette, terminal, globe, github, mail…).
- Do not use the builder's bundled icon font/library, and no emoji as icons.

### Builder-native construction
- Build with the chosen builder's **native elements/widgets/blocks** and its native styling, spacing, and motion features. Inspect the builder's available elements and their options first if tools for that exist.
- Raw HTML/custom code embedded inside a visual builder is a **last resort** for a micro-detail that is genuinely impossible natively — never for whole sections. (If the chosen target IS plain HTML/CSS, write clean semantic HTML with modern CSS instead.)

### Completeness — a half-built page is a failure
- Build and **fully populate one section at a time**: real headline, real copy, every technology labeled, every project with its description and tech tags, every service line, every stat, every quote written, every image placed — then move on. Never scaffold empty containers to "fill later."
- When finished, walk the entire page and fix any empty element, placeholder text, missing image, or contrast failure. Publish as a **draft**.
