**Page builder:** Elementor

# Chrome & Shine Auto Spa — premium car wash & detailing landing page

Design and build a **complete, production-quality landing page** for "Chrome & Shine Auto Spa", a premium car wash and auto detailing center. **You own the design.** If web search is available, research 2–3 genuinely well-designed car-wash/auto-detailing sites first and form a point of view; then decide the layout, composition, and section design yourself. Do not fall back to a generic centered-hero + three-identical-cards template — bring editorial composition, asymmetry, scale contrast, and rhythm. **Vary your hero composition to fit this brand specifically — in particular, do not reach for the overused "one large photo with a smaller photo card overlapping its corner" hero unless it genuinely serves this design; choose an opener you would not use for a coffee shop or a law firm.** The sections below define **what the page must communicate, not how it must look.**

## Style guide (constraints, not layout)

**Palette — flat colors only, no gradients:**

| Role | Value |
|---|---|
| Surface, light | `#FFFFFF` (bright white) |
| Surface, dark bands | `#153C8C` (deep cobalt) |
| Ink / headings | `#0D2352` |
| Accent — use sparingly: one word, prices, buttons, icons | `#19B7E0` (cyan) |
| Muted body text | `#5C6B80` |
| Hairlines / dividers | `#E3E9F2` |

**Typography:** Display = **Archivo Black** (fallback: Anton) — heavy, industrial, confident; go bold and big on headlines, uppercase works. Body = **Inter**, 16–18px, relaxed line-height (~1.7–1.8). Short uppercase eyebrows with wide letter-spacing fit the punchy service tone.

**Feel:** fast, gleaming, punchy. Water-droplet and wet-paint photography supply the shine — no effects needed. Bold scale contrast between huge headlines and tight supporting copy; crisp hairline dividers over box shadows; sharp or barely-rounded corners.

**Motion:** energetic but disciplined — quick entrance reveals and image hovers. Use only what the chosen builder provides natively; **no custom JavaScript and no hand-rolled motion CSS.**

## Design signature (make this page distinct — adapt as you see fit)
These are compositional cues, not layout mechanics. Use them to give this car wash its own look rather than a generic template:
- **Hero:** open on a full-width horizontal band of a car mid-wash with water caught in motion, one enormous single-word headline set across it and the actions tucked into a corner — a wide, fast, cinematic strip, never a photo with a smaller photo card overlapping its corner.
- **Wash packages:** present the three tiers as a bold comparison ladder with Premium Detail visibly enlarged as the anchor and prices in the cyan accent — engineered, not a card dump.
- **Big numerals:** run the proof stats (15,000+, 4.9★, 100%) as huge industrial figures on the cobalt band so credibility lands instantly.
- **Shape language:** sharp or barely-rounded corners, crisp hairline dividers, and cyan used only on actions and prices.

## Sections — what each must communicate (design them your way)

1. **Hero** — a gleaming first impression; the brand name, a confident headline, a supporting line about hand wash + detailing + walk-ins welcome, primary + secondary actions (book / call), hours at a glance.
2. **Proof bar** — the four trust stats below, presented as instant credibility.
3. **Services** — the four core services below with their descriptions; each should read as a distinct craft, not four identical boxes.
4. **Wash packages** — the three tiers below with full inclusion lists and prices; make Premium Detail the clear anchor choice.
5. **About** — 10 years of craftsmanship, trained and certified technicians, eco-friendly products, fully insured & bonded, satisfaction guaranteed.
6. **Add-ons** — the three upgrade services below with prices; frame them as easy yeses at booking time.
7. **Unlimited Wash Club** — the membership pitch: wash as often as you want, plans from $39/month; one strong join action.
8. **Testimonials** — the three customer quotes below with names and vehicles.
9. **Book your wash** — a working booking-request form (name, phone, email, vehicle type, service or package, preferred date/time) built with the builder's native form element, with the phone number given equal prominence as an alternative. If the builder has no form element available, use a clearly labeled call/email call-to-action instead of omitting this.
10. **Visit + Footer** — address, hours, phone, email, "open 7 days a week", a map if the builder supports one; then brand + tagline, quick links, contact, copyright.

## Content facts (use these verbatim)

**Proof stats:** 15,000+ cars washed monthly · 4.9★ Google rating · 100% hand wash · eco-friendly products

**Services:** Exterior Hand Wash — premium hand wash with pH-balanced foam, wheel cleaning, and towel dry · Full Detail — complete interior and exterior detail including clay bar, polish, and wax · Ceramic Coating — long-lasting nano-ceramic protection for ultimate paint defense · Interior Deep Clean — steam cleaning, leather conditioning, carpet extraction, odor removal

**Packages:** Express Wash — $25 — exterior hand wash, wheel clean, tire shine, windows, air freshener · Premium Detail — $89 — full exterior wash, interior vacuum & wipe, dashboard treatment, tire dressing, scent treatment · Ultimate Spa — $179 — everything in Premium plus clay bar, paint correction, ceramic spray sealant, leather conditioning, engine bay clean

**Add-ons:** Headlight Restoration — $45 — crystal-clear headlights for better visibility and appearance · Paint Protection Film — from $299 — invisible shield against rock chips, scratches, and UV damage · Odor Elimination — $35 — ozone treatment to completely neutralize stubborn odors

**Membership:** Unlimited Wash Club — plans starting at $39/month

**Testimonials:** "My Tesla has never looked this good. The ceramic coating is absolutely worth it." — Mark R., Tesla Model 3 · "I've tried every car wash in town. Chrome & Shine is hands down the best." — Sarah K., BMW X5 · "The interior deep clean saved my seats after a road trip with two kids and a dog!" — James T., Honda Odyssey

**Visit:** 2280 Coastal Hwy, San Diego, CA · Mon–Sat 7AM–7PM, Sun 8AM–5PM · open 7 days a week · (555) 789-2345 · wash@chromeandshine.com · est. 2016

## Standards (non-negotiable)

### Accessibility — WCAG 2.1 AA
- Text contrast ≥ 4.5:1 (≥ 3:1 for large text) against its actual background — check text over photos and dark bands.
- Exactly one H1; headings descend logically (no skipped levels for styling reasons).
- Descriptive alt text on every image; meaningful link/button labels (never "click here").
- Comfortable touch targets on buttons and links; readable body size (≥ 16px); every form field has a visible, associated label.

### Images — real photography in every slot
- Source real photos from **Unsplash, Pexels, or Pixabay** (use the available stock-image search/sideload tools if present, otherwise direct source URLs). Never leave an image slot empty; never use placeholder URLs.
- Curate for a consistent crisp, high-gloss grade of water droplets and wet paint that matches the palette; pick images that share a mood, not six random car photos.

### Icons — one consistent SVG set
- Use inline SVG icons in a single consistent style — Lucide/Feather spec: `viewBox="0 0 24 24"`, `fill="none"`, `stroke="currentColor"`, `stroke-width="1.75"`, round caps/joins. Generate what you need (droplet, car, sparkle, shield, spray, clock, star, check, map-pin…).
- Do not use the builder's bundled icon font/library, and no emoji as icons.

### Builder-native construction
- Build with the chosen builder's **native elements/widgets/blocks** and its native styling, spacing, and motion features. Inspect the builder's available elements and their options first if tools for that exist.
- Raw HTML/custom code embedded inside a visual builder is a **last resort** for a micro-detail that is genuinely impossible natively — never for whole sections. (If the chosen target IS plain HTML/CSS, write clean semantic HTML with modern CSS instead.)

### Completeness — a half-built page is a failure
- Build and **fully populate one section at a time**: real headline, real copy, every package with its full inclusion list and price, every add-on and stat placed, every quote written, every image placed — then move on. Never scaffold empty containers to "fill later."
- When finished, walk the entire page and fix any empty element, placeholder text, missing image, or contrast failure. Publish as a **draft**.
