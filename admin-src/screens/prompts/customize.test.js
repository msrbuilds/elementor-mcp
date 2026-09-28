import { parsePrompt, applyCustomization, CUSTOM_BUILDERS } from './customize';

// A trimmed copy of a real library prompt (automotive/auto-repair).
const PROMPT = `**Page builder:** Elementor

# Ironclad Auto Repair — auto repair & maintenance shop landing page

Design and build a **complete, production-quality landing page** for "Ironclad Auto Repair", a full-service auto repair and maintenance shop.

## Style guide (constraints, not layout)

**Palette — flat colors only, no gradients:**

| Role | Value |
|---|---|
| Surface, light | \`#F4F1EC\` (warm off-white) |
| Surface, dark bands | \`#1B1D21\` (charcoal) |
| Ink / headings | \`#1B1D21\` |
| Accent — use sparingly: one word, prices, buttons, icons | \`#FF6B1A\` (safety orange) |

**Typography:** Display = **Barlow Condensed** (fallback: Oswald) — bold, uppercase, industrial. Body = **Inter**, 16–18px, relaxed line-height (~1.7–1.8).

**Feel:** honest workshop grit; let the orange accent (#ff6b1a) do the work.

## Content facts (use these verbatim)

**Services:** Oil Change & Lube — from $49 · Brake Repair — from $149

**Offer:** Free inspection on your first visit.

**Visit:** 4720 Groveport Rd, Columbus, OH · (555) 345-6789 · service@ironcladautorepair.com

## Standards (non-negotiable)

- Exactly one H1.
`;

describe( 'parsePrompt', () => {
	const p = parsePrompt( PROMPT );

	it( 'finds the builder, the business name and the fonts', () => {
		expect( p.builder ).toBe( 'Elementor' );
		expect( p.name ).toBe( 'Ironclad Auto Repair' );
		expect( p.fonts ).toEqual( [
			{ role: 'Display', font: 'Barlow Condensed' },
			{ role: 'Body', font: 'Inter' },
		] );
	} );

	it( 'lists each colour once, with every role that uses it', () => {
		expect( p.colors ).toEqual( [
			{
				hex: '#F4F1EC',
				roles: [ 'Surface, light' ],
				note: 'warm off-white',
			},
			{
				hex: '#1B1D21',
				roles: [ 'Surface, dark bands', 'Ink / headings' ],
				note: 'charcoal',
			},
			{
				hex: '#FF6B1A',
				roles: [
					'Accent — use sparingly: one word, prices, buttons, icons',
				],
				note: 'safety orange',
			},
		] );
	} );

	it( 'reads the content facts in order', () => {
		expect( p.facts ).toEqual( [
			{
				label: 'Services',
				value: 'Oil Change & Lube — from $49 · Brake Repair — from $149',
			},
			{ label: 'Offer', value: 'Free inspection on your first visit.' },
			{
				label: 'Visit',
				value: '4720 Groveport Rd, Columbus, OH · (555) 345-6789 · service@ironcladautorepair.com',
			},
		] );
	} );

	it( 'returns empty parts for text that is not a library prompt', () => {
		expect( parsePrompt( 'Just build me a page.' ) ).toEqual( {
			builder: '',
			name: '',
			colors: [],
			fonts: [],
			facts: [],
		} );
	} );

	it( 'offers the page builders, Gutenberg and plain HTML', () => {
		expect( CUSTOM_BUILDERS ).toEqual(
			expect.arrayContaining( [
				'Elementor',
				'Gutenberg',
				'Bricks',
				'Plain HTML/CSS',
			] )
		);
	} );
} );

describe( 'applyCustomization', () => {
	const p = parsePrompt( PROMPT );

	it( 'returns the prompt unchanged when nothing changed', () => {
		expect( applyCustomization( PROMPT, p, {} ) ).toBe( PROMPT );
	} );

	it( 'changes the builder line only', () => {
		const out = applyCustomization( PROMPT, p, { builder: 'Bricks' } );
		expect( out.split( '\n' )[ 0 ] ).toBe( '**Page builder:** Bricks' );
		expect( out.replace( 'Bricks', 'Elementor' ) ).toBe( PROMPT );
	} );

	it( 'renames the business everywhere it appears', () => {
		const out = applyCustomization( PROMPT, p, { name: 'Bolt Garage' } );
		expect( out ).toContain( '# Bolt Garage — auto repair' );
		expect( out ).toContain( 'for "Bolt Garage", a full-service' );
		expect( out ).not.toContain( 'Ironclad Auto Repair' );
	} );

	it( 'swaps a colour in the table and in the text, whatever its case', () => {
		const out = applyCustomization( PROMPT, p, {
			colors: { '#ff6b1a': '#2563EB', '#1b1d21': '#1B1D21' },
		} );
		expect( out ).toContain( '| `#2563EB` (safety orange) |' );
		expect( out ).toContain( 'orange accent (#2563EB)' );
		expect( out ).not.toMatch( /#ff6b1a/i );
		expect( out.match( /#1B1D21/g ) ).toHaveLength( 2 );
	} );

	it( 'swaps a font only in the typography line', () => {
		const out = applyCustomization( PROMPT, p, {
			fonts: { Display: 'Oswald', Body: 'Source Sans 3' },
		} );
		expect( out ).toContain( 'Display = **Oswald** (fallback: Oswald)' );
		expect( out ).toContain( 'Body = **Source Sans 3**, 16–18px' );
	} );

	it( 'rewrites a content fact and keeps the others', () => {
		const out = applyCustomization( PROMPT, p, {
			facts: { Offer: '10% off your first service.' },
		} );
		expect( out ).toContain( '**Offer:** 10% off your first service.' );
		expect( out ).toContain( '**Services:** Oil Change & Lube' );
	} );

	it( 'ignores an empty name, an invalid colour and an empty font', () => {
		const out = applyCustomization( PROMPT, p, {
			name: '  ',
			colors: { '#ff6b1a': 'blue' },
			fonts: { Display: '' },
		} );
		expect( out ).toBe( PROMPT );
	} );

	it( 'applies a rename after the facts, so an edited fact is renamed too', () => {
		const out = applyCustomization( PROMPT, p, {
			name: 'Bolt Garage',
			facts: { Offer: 'Ironclad Auto Repair gives you a free check.' },
		} );
		expect( out ).toContain(
			'**Offer:** Bolt Garage gives you a free check.'
		);
	} );
} );
