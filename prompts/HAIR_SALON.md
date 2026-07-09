**Page builder:** Elementor

# Velvet & Co. — upscale hair salon landing page

Design and build a **complete, production-quality landing page** for "Velvet & Co.", an upscale hair salon and styling studio. **You own the design.** If web search is available, research 2–3 genuinely well-designed salon/beauty-studio sites first and form a point of view; then decide the layout, composition, and section design yourself. Do not fall back to a generic centered-hero + three-identical-cards template — bring editorial composition, asymmetry, scale contrast, and rhythm. **Vary your hero composition to fit this brand specifically — in particular, do not reach for the overused "one large photo with a smaller photo card overlapping its corner" hero unless it genuinely serves this design; choose an opener you would not use for a coffee shop or a law firm.** The sections below define **what the page must communicate, not how it must look.**

## Style guide (constraints, not layout)

**Palette — flat colors only, no gradients:**

| Role | Value |
|---|---|
| Surface, light | `#F7F1EE` (ivory blush) |
| Surface, dark bands | `#171215` (noir) |
| Ink / headings | `#1E181B` |
| Accent — use sparingly: one word, prices, buttons, icons | `#B34A5E` (deep rose) |
| Muted body text | `#8A7A7E` |
| Hairlines / dividers | `#E8DCD6` |

**Typography:** Display = **Libre Caslon Display** (fallback: Cormorant Garamond) — refined, editorial serif with fashion-magazine poise; elegant weight, never heavy. Body = **Inter**, 16–18px, relaxed line-height (~1.7–1.8). Slim uppercase eyebrows with very wide letter-spacing read like a masthead.

**Feel:** chic, glossy, fashion-editorial. Polished portraiture and salon photography carry the glamour; the ivory-blush surface stays quiet so hair is the hero. Refined hairline dividers over box shadows; restrained radii; luxury lives in whitespace and typographic precision.

**Motion:** graceful and understated — soft entrance reveals and image hovers. Use only what the chosen builder provides natively; **no custom JavaScript and no hand-rolled motion CSS.**

## Design signature (make this page distinct — adapt as you see fit)
These are compositional cues, not layout mechanics. Use them to give this hair salon its own look rather than a generic template:
- **Hero:** treat the opener as a stacked editorial masthead — the salon name set huge like a fashion-magazine cover title, a thin rule, the "Where Beauty Meets Artistry" promise beneath, with a single polished portrait held to one side; a cover, not a photo with a smaller photo card on its corner.
- **Services:** compose the six services as a designed price menu — an elegant column with hairline dividers and right-aligned starting prices — rather than a card grid.
- **Masthead rules:** carry slim uppercase eyebrows and hairline rules through every section like a running magazine masthead.
- **Shape language:** restrained radii, quiet ivory-blush whitespace, and deep rose reserved for one word, prices, and actions.

## Sections — what each must communicate (design them your way)

1. **Hero** — instant editorial glamour; the brand name, the "Where Beauty Meets Artistry" promise, a supporting line, primary + secondary actions (book appointment / view services).
2. **Services** — all 6 services below with descriptions and starting prices; treat the service list as a designed price menu, not a card dump.
3. **About** — the salon's story and craft philosophy, anchored by the three proof stats: 12+ years · 25K+ clients · 8 expert stylists.
4. **The team** — the four stylists below with their specialties; each should feel individually bookable.
5. **Portfolio** — a curated gallery of finished work that proves the craft; consistency of grade matters more than quantity.
6. **Retail** — the salon carries professional product lines below; convey take-the-salon-home quality.
7. **Testimonials** — three distinct, believable client quotes with names; write them yourself.
8. **Call to action** — one strong closing moment: your best hair day awaits, book the transformation.
9. **Book an appointment** — a working appointment-request form (name, phone, email, service, preferred stylist, preferred date/time) built with the builder's native form element, with the phone number given equal prominence as an alternative. If the builder has no form element available, use a clearly labeled call/email call-to-action instead of omitting this.
10. **Visit + Footer** — address, hours, phone, email, a map if the builder supports one; then brand + tagline, quick links, contact, copyright.

## Content facts (use these verbatim)

**Services:** Cut & Style — precision cut with consultation, wash, and finish — from $65 · Color & Highlights — full color or dimensional highlights — from $120 · Balayage & Ombré — hand-painted, lived-in color — from $180 · Keratin Treatment — smoothing treatment for frizz-free weeks — from $250 · Bridal & Special Occasion — trials, updos, and day-of styling — from $150 · Blowout & Styling — wash, blowout, and finish — from $45

**Team:** Mia Chen — Color Specialist · James Hart — Creative Director · Sofia Reyes — Bridal Expert · Aisha Patel — Texture & Curls

**Stats:** 12+ years · 25K+ happy clients · 8 expert stylists

**Retail lines:** Oribe · Olaplex · Kérastase

**Tagline:** "Where Beauty Meets Artistry"

**Visit:** 42 Mercer St, New York, NY · Tue–Sat 9AM–7PM, Sun 10AM–4PM, Mon closed · (555) 337-8810 · book@velvetandco.com · est. 2014

## Standards (non-negotiable)

### Accessibility — WCAG 2.1 AA
- Text contrast ≥ 4.5:1 (≥ 3:1 for large text) against its actual background — check text over photos and dark bands.
- Exactly one H1; headings descend logically (no skipped levels for styling reasons).
- Descriptive alt text on every image; meaningful link/button labels (never "click here").
- Comfortable touch targets on buttons and links; readable body size (≥ 16px); every form field has a visible, associated label.

### Images — real photography in every slot
- Source real photos from **Unsplash, Pexels, or Pixabay** (use the available stock-image search/sideload tools if present, otherwise direct source URLs). Never leave an image slot empty; never use placeholder URLs.
- Curate for a consistent glossy, fashion-editorial grade of portraiture and salon interiors that matches the palette; pick images that share a mood, not six random salon photos.

### Icons — one consistent SVG set
- Use inline SVG icons in a single consistent style — Lucide/Feather spec: `viewBox="0 0 24 24"`, `fill="none"`, `stroke="currentColor"`, `stroke-width="1.75"`, round caps/joins. Generate what you need (scissors, sparkle, calendar, clock, crown, heart, star, map-pin…).
- Do not use the builder's bundled icon font/library, and no emoji as icons.

### Builder-native construction
- Build with the chosen builder's **native elements/widgets/blocks** and its native styling, spacing, and motion features. Inspect the builder's available elements and their options first if tools for that exist.
- Raw HTML/custom code embedded inside a visual builder is a **last resort** for a micro-detail that is genuinely impossible natively — never for whole sections. (If the chosen target IS plain HTML/CSS, write clean semantic HTML with modern CSS instead.)

### Completeness — a half-built page is a failure
- Build and **fully populate one section at a time**: real headline, real copy, every service with its starting price, every stylist named, every quote written, every image placed — then move on. Never scaffold empty containers to "fill later."
- When finished, walk the entire page and fix any empty element, placeholder text, missing image, or contrast failure. Publish as a **draft**.
