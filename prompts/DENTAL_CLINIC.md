**Page builder:** Elementor

# BrightSmile Dental — family dental clinic landing page

Design and build a **complete, production-quality landing page** for "BrightSmile Dental", a modern family dental clinic. **You own the design.** If web search is available, research 2–3 genuinely well-designed dental clinic sites first and form a point of view; then decide the layout, composition, and section design yourself. Do not fall back to a generic centered-hero + three-identical-cards template — bring editorial composition, asymmetry, scale contrast, and rhythm. **Vary your hero composition to fit this brand specifically — in particular, do not reach for the overused "one large photo with a smaller photo card overlapping its corner" hero unless it genuinely serves this design; choose an opener you would not use for a coffee shop or a law firm.** The sections below define **what the page must communicate, not how it must look.**

## Style guide (constraints, not layout)

**Palette — flat colors only, no gradients:**

| Role | Value |
|---|---|
| Surface, light | `#FFFFFF` (bright white) |
| Surface, dark bands | `#1C2B3A` (navy slate) |
| Ink / headings | `#16283A` |
| Accent — use sparingly: one word, prices, buttons, icons | `#2BB3C0` (aqua sky) |
| Muted body text | `#52657A` |
| Hairlines / dividers | `#E3EAF0` |

**Typography:** Display = **Plus Jakarta Sans** (fallback: Nunito Sans) — rounded-but-professional, friendly without being childish; headlines in sentence or title case, never shouting. Body = **Inter**, 16–18px, relaxed line-height (~1.7–1.8). Small uppercase eyebrows with wide letter-spacing work well.

**Feel:** fresh reassurance — bright, hygienic, friendly-professional. Calm should come from whitespace and clean type, not effects. Hairline dividers over box shadows; soft 10px radii on images and cards.

**Motion:** gentle and restrained — subtle entrance reveals and image hovers. Use only what the chosen builder provides natively; **no custom JavaScript and no hand-rolled motion CSS.**

## Design signature (make this page distinct — adapt as you see fit)
These are compositional cues, not layout mechanics. Use them to give this clinic its own look rather than a generic template:
- **Hero:** lead with a single calm, high-trust photograph (a real, warm dentist-and-patient moment) with the headline and a compact **"Book a visit"** card set alongside it — a composed, uncluttered opener, not stacked or overlapping image cards.
- **Services:** present as a clean, scannable index — a quiet list with hairline dividers or an asymmetric two-column layout — rather than six identical shadowed cards.
- **Reassurance:** turn the trust stats into oversized, friendly numerals on the navy band so the proof reads at a glance.
- **Shape language:** consistent soft radii and generous daylight whitespace; let the aqua accent appear only on actions and a few key numbers.

## Sections — what each must communicate (design them your way)

1. **Hero** — warmth and competence for the whole family; the brand name, a memorable headline, a supporting line, primary + secondary actions, and the fact that new patients and same-day appointments are welcome.
2. **Trust strip** — the four differentiators (years of experience, happy patients, same-day appointments, insurance accepted).
3. **Services** — all 6 services below with short, reassuring descriptions covering routine to emergency care.
4. **About the practice** — why visiting is actually pleasant: modern office, gentle approach, latest technology, family-owned — anchored by the three stats.
5. **Team** — the four dentists below with name, specialty, and a one-line bio each; write the bios yourself.
6. **Pricing & insurance** — the three transparent offers below plus the accepted-insurance line, so cost anxiety is answered head-on.
7. **Testimonials** — three distinct, believable patient quotes with names; write them yourself.
8. **Book a visit** — a working appointment-request form (full name, phone, email, preferred date/time, reason for visit, optional message) built with the builder's native form element, with the phone number given equal prominence as an alternative. If the builder has no form element available, use a clearly labeled call/email call-to-action instead of omitting this.
9. **Visit** — address, hours, phone, email, the 24/7 emergency line, and a map if the builder supports one.
10. **Footer** — brand + tagline, quick links, contact, copyright.

## Content facts (use these verbatim)

**Services:** General Dentistry — checkups, cleanings, fillings · Cosmetic Dentistry — whitening, veneers, bonding · Orthodontics — braces, Invisalign, retainers · Dental Implants — single, bridge, full arch · Pediatric Dentistry — kid-friendly care, sealants · Emergency Dental — same-day pain relief and repair

**Team:** Dr. Sarah Mitchell — General & Cosmetic Dentistry · Dr. James Chen — Orthodontics · Dr. Priya Patel — Pediatric Dentistry · Dr. Michael Torres — Oral Surgery

**Pricing:** New Patient Special — exam, X-rays, cleaning, treatment plan — $99 · Teeth Whitening — professional whitening, custom trays, touch-up kit — $299 · Dental Implant — consultation, implant + crown, follow-up care — from $1,499

**Insurance:** We accept most major insurance plans including Delta Dental, Cigna, Aetna, MetLife, and more.

**Stats:** 15+ years of practice · 10K+ smiles transformed · 98% patient satisfaction

**Visit:** 2205 Maple Ave, Austin, TX · Mon–Fri 8am–6pm, Sat 9am–3pm, Sun closed · Emergency line available 24/7 · (555) 789-0123 · smile@brightsmiledental.com · est. 2011

## Standards (non-negotiable)

### Accessibility — WCAG 2.1 AA
- Text contrast ≥ 4.5:1 (≥ 3:1 for large text) against its actual background — check text over photos and dark bands.
- Exactly one H1; headings descend logically (no skipped levels for styling reasons).
- Descriptive alt text on every image; meaningful link/button labels (never "click here").
- Comfortable touch targets on buttons and links; readable body size (≥ 16px); every form field has a visible, associated label.

### Images — real photography in every slot
- Source real photos from **Unsplash, Pexels, or Pixabay** (use the available stock-image search/sideload tools if present, otherwise direct source URLs). Never leave an image slot empty; never use placeholder URLs.
- Curate for a consistent bright, airy, daylight grade that matches the palette; pick images that share a mood, not six random dental photos.

### Icons — one consistent SVG set
- Use inline SVG icons in a single consistent style — Lucide/Feather spec: `viewBox="0 0 24 24"`, `fill="none"`, `stroke="currentColor"`, `stroke-width="1.75"`, round caps/joins. Generate what you need (tooth, smile, shield-plus, sparkle, calendar, clock, phone, map-pin…).
- Do not use the builder's bundled icon font/library, and no emoji as icons.

### Builder-native construction
- Build with the chosen builder's **native elements/widgets/blocks** and its native styling, spacing, and motion features. Inspect the builder's available elements and their options first if tools for that exist.
- Raw HTML/custom code embedded inside a visual builder is a **last resort** for a micro-detail that is genuinely impossible natively — never for whole sections. (If the chosen target IS plain HTML/CSS, write clean semantic HTML with modern CSS instead.)

### Completeness — a half-built page is a failure
- Build and **fully populate one section at a time**: real headline, real copy, every service described, every dentist named with a bio, every price stated, every quote written, every image placed — then move on. Never scaffold empty containers to "fill later."
- When finished, walk the entire page and fix any empty element, placeholder text, missing image, or contrast failure. Publish as a **draft**.
