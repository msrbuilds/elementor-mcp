Publish a local news article as a standalone Elementor page — with Google News-grade structured data and image SEO — on a site whose header and footer already exist as Elementor templates.

This playbook is for hyperlocal news / community sites that publish articles as Pages (not Posts), where every article must be eligible for Google Discover, Top Stories and rich results from day one.

## CRITICAL LAYOUT RULES
- Create the page with `template=elementor_canvas` — the site chrome comes from template shortcodes, not the theme.
- The page is exactly ONE top-level container with THREE children: header shortcode, article HTML widget, footer shortcode.
- The top-level container MUST be full-bleed. Pass ALL of these settings to add-container — Elementor's defaults (boxed width, padding, gaps) show as white strips around the header/footer on Canvas pages:

```json
{
  "content_width": "full",
  "flex_direction": "column",
  "flex_align_items": "stretch",
  "width":    { "unit": "%", "size": 100 },
  "padding":  { "unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": true },
  "gap":      { "unit": "px", "size": 0, "column": "0", "row": "0" },
  "flex_gap": { "unit": "px", "size": 0, "column": "0", "row": "0" }
}
```

## PAGE STRUCTURE
1. `create-page` — title = the headline, `status=publish`, `template=elementor_canvas`.
2. `add-container` with the full-bleed settings above.
3. `add-shortcode` → `[elementor-template id="HEADER_ID"]`
4. `add-html` → the complete article (markup + one `<style>` block + JSON-LD, see below).
5. `add-shortcode` → `[elementor-template id="FOOTER_ID"]`
6. Set the URL slug and SEO meta afterwards via REST (`POST /wp/v2/pages/{id}`).

> **Prerequisite for the REST meta steps:** Yoast's `_yoast_wpseo_*` keys are protected, so a vanilla site silently ignores them over REST — first register them (`register_post_meta(..., ['show_in_rest'=>true])`) or opt them into the `emcp_tools_content_allowed_protected_meta` filter. See #82.

## ARTICLE HTML (inside the single HTML widget)
- Semantic broadsheet structure: `<article>` → lead block (kicker, single `<h1>`, byline row with `<time datetime="YYYY-MM-DD">`, standfirst paragraph, hero `<figure>` with `<figcaption>` crediting the photographer) → body paragraphs → `<h2>` sections → gallery figures → footer block (disclaimer + "send us your story" contact line).
- Exactly one `<h1>`; sections use `<h2>` — never skip levels.
- Hero `<img>`: explicit `width`/`height` attributes + `fetchpriority="high"`. Every other image: `loading="lazy" decoding="async"` + explicit dimensions.
- Alt text on every image, written as a factual description of the photo (it must match the alt text set in the Media Library).
- Attribution discipline for anything unconfirmed: attribute claims to their source and mark them ("A local resident said… This has not been independently verified"). Never state a cause of an incident as fact before officials confirm it.
- Internal links: link earlier related articles in the body with descriptive anchors, and link the news index page. Cross-link back from the older article if this is a follow-up.

### Social embeds (Instagram/X) — lazy-load them
Never load embed scripts at page load; they wreck LCP. Render the plain `<blockquote class="instagram-media">` markup and inject the script only when the embed scrolls near the viewport:

```html
<script>
(function(){
  var box=document.querySelector('.news-embeds');
  if(!box)return;
  var done=false;
  function load(){
    if(done)return;done=true;
    if(window.instgrm&&window.instgrm.Embeds){window.instgrm.Embeds.process();return;}
    var s=document.createElement('script');s.async=true;s.src='https://www.instagram.com/embed.js';document.body.appendChild(s);
  }
  if(!('IntersectionObserver' in window)){load();return;}
  var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){load();io.disconnect();}});},{rootMargin:'600px 0px'});
  io.observe(box);
})();
</script>
```

## IMAGE SEO — DO THIS FOR EVERY ARTICLE
1. From the best original photo, generate three crops at exactly **1200px wide: 16:9 (1200×675), 4:3 (1200×900), 1:1 (1200×1200)** — Google Discover and Top Stories want all three ratios at ≥1200px. Mild upscale from 1080px originals is acceptable.
2. Descriptive, keyword-bearing filenames: `<subject>-<town>-16x9.jpg`, not `IMG_4021.jpg`.
3. Upload via REST (`POST /wp/v2/media`, raw body + `Content-Disposition`), then set `alt_text`, `caption`, and a `slug` that will NOT collide with the page slug (suffix attachment slugs with `-photo` — WordPress otherwise gives the attachment the clean slug and your page gets `-2`).
4. Set the 16:9 crop as the page's featured image: `POST /wp/v2/pages/{id} {"featured_media": <id>}`.
5. **Yoast gotcha:** the featured image does NOT refresh Yoast's og:image (the indexable keeps the first content image). Set it explicitly: postmeta `_yoast_wpseo_opengraph-image` = crop URL and `_yoast_wpseo_opengraph-image-id` = attachment ID. Verify `og:image` on the live page afterwards.

## NEWSARTICLE JSON-LD (in the same HTML widget)
Requirements that rich-results validation actually enforces:
- `headline` ≤ 110 characters.
- `datePublished`/`dateModified` as full ISO-8601 **with timezone** (`2026-07-11T12:45:00+01:00`).
- `image` as an **array of ImageObject** — the three 1200px crops first, then the hero and key gallery shots, each with `url`, `width`, `height`, `caption`.
- `publisher.logo` as an ImageObject **with width and height**.
- `inLanguage`, `isAccessibleForFree: true`, `articleSection`, `mainEntityOfPage`, and `about` as a `Place` naming the location.

## SEO META (via REST after publish)
- SEO title ≤ 60 chars, primary keyword early; meta description 150–160 chars; focus keyword set.
- Slug pattern: `<town>-<subject>` (e.g. `anytown-high-street-fire`), lowercase, no dates.

## KEEP THE SITE FRESH — ROTATION
The homepage "latest news" block and the news index page each show a lead card plus compact rows. When a new article publishes: newest article becomes the lead card (image, meta line, headline, teaser, CTA), the previous lead demotes to the first row, oldest row drops off the homepage (the index keeps all).

## VERIFY BEFORE CALLING IT DONE
Fetch the live URL (not the editor) and confirm: single `<h1>`; canonical URL correct; `og:image` = the 16:9 crop; the JSON-LD parses (`JSON.parse` it); header nav and footer render with no white strips at any edge; all images return 200. Then request indexing for the new URL in Search Console.
