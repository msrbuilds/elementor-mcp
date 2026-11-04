/** Real native Flatsome frontend and Builder qualification on the owned local demo. */
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const fs = require('node:fs');
const path = require('node:path');
const evidence = process.env.EMCP_FLATSOME_EVIDENCE ? path.resolve(process.env.EMCP_FLATSOME_EVIDENCE) : path.resolve(__dirname, '../docs/flatsome-e2e-20261104');
const manifest = () => JSON.parse(fs.readFileSync(path.join(evidence, 'landing-final.json'), 'utf8'));

test('Flatsome landing page works at five responsive sizes', async ({ page }) => {
    const errors = [];
    const failed = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => { if (response.status() >= 400 && response.url().startsWith('https://elementor-mcp.test/')) failed.push([response.status(), response.url()]); });
    const sizes = [1440, 1024, 768, 390, 320];
    const computed = [];
    for (const width of sizes) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(manifest().url, { waitUntil: 'networkidle' });
        await expect(page.locator('h1')).toHaveCount(1);
        await expect(page.locator('h1')).toHaveText('Room to live.Space to breathe.');
        await expect(page.getByRole('link', { name: 'Explore our approach', exact: true })).toBeVisible();
        await expect(page.getByRole('link', { name: 'View the moodboard', exact: true })).toBeVisible();
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(overflow, `horizontal overflow at ${width}`).toBeLessThanOrEqual(1);
        if (width <= 390) {
            expect(await page.locator('h1').evaluate(el => el.getBoundingClientRect().left)).toBeGreaterThanOrEqual(20);
        }
        const images = page.locator('main img');
        await expect(images).toHaveCount(3);
        for (const image of await images.all()) {
            await image.scrollIntoViewIfNeeded();
            await expect.poll(() => image.evaluate(el => el.complete && el.naturalWidth > 0)).toBeTruthy();
            await expect(image).toHaveAttribute('alt', /\S+/);
        }
        const faq = page.getByRole('button', { name: 'Where does a project begin?', exact: true });
        await faq.scrollIntoViewIfNeeded();
        await faq.focus();
        await faq.press('Enter');
        await expect(faq).toHaveAttribute('aria-expanded', 'true');
        await expect(page.locator('#' + await faq.getAttribute('aria-controls'))).toBeVisible();
        await faq.press('Enter');
        await expect(faq).toHaveAttribute('aria-expanded', 'false');
        for (const link of await page.locator('main a[href^="#"]:not([role="button"])').all()) {
            const href = await link.getAttribute('href');
            expect(await page.evaluate(id => !!document.getElementById(decodeURIComponent(id)), href.slice(1))).toBeTruthy();
        }
        computed.push({ width, h1: await page.locator('h1').evaluate(el => getComputedStyle(el).fontSize) });
        await faq.blur();
        await expect(page.locator('#' + await faq.getAttribute('aria-controls'))).toBeHidden();
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({ path: path.join(evidence, `landing-${width}.png`), fullPage: true });
        const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
        fs.writeFileSync(path.join(evidence, `axe-${width}.json`), JSON.stringify(axe.violations, null, 2));
        expect(axe.violations, `axe at ${width}`).toEqual([]);
    }
    expect(parseFloat(computed[3].h1)).toBeLessThan(parseFloat(computed[0].h1));
    fs.writeFileSync(path.join(evidence, 'browser-results.json'), JSON.stringify({ computed, errors, failed }, null, 2));
    expect(errors).toEqual([]);
    expect(failed).toEqual([]);
});

test('Native Flatsome Builder opens the MCP document without recovery', async ({ browser }) => {
    test.skip(!process.env.EMCP_FLATSOME_COOKIES, 'Private, revocable local browser session required.');
    const auth = JSON.parse(fs.readFileSync(process.env.EMCP_FLATSOME_COOKIES, 'utf8'));
    expect(auth.host).toBe('elementor-mcp.test');
    const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1000 } });
    try {
        await context.addCookies([auth.auth, auth.logged].map(([name, value]) => ({ name, value, domain: auth.host, path: '/', secure: true, httpOnly: true, sameSite: 'Lax' })));
        const page = await context.newPage();
        await page.goto(manifest().editor_url, { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('button', { name: 'Update', exact: true })).toBeVisible({ timeout: 60000 });
        if (await page.getByRole('region', { name: 'Welcome to Flatsome Builder' }).isVisible()) {
            await page.getByRole('region', { name: 'Welcome to Flatsome Builder' }).getByRole('button', { name: 'Dismiss', exact: true }).click();
        }
        await page.screenshot({ path: path.join(evidence, 'builder-initial.png'), fullPage: true });
        fs.writeFileSync(path.join(evidence, 'builder-dom.txt'), await page.locator('body').ariaSnapshot());
        expect(await page.locator('body').innerText()).not.toMatch(/Attempt Block Recovery|unexpected or invalid content|Resolve Block/i);
        const label = page.frameLocator('iframe[name="editor-canvas"]').getByText('01', { exact: true });
        await label.click();
        fs.writeFileSync(path.join(evidence, 'builder-selected-dom.txt'), await page.locator('body').ariaSnapshot());
        await page.screenshot({ path: path.join(evidence, 'builder-selected.png'), fullPage: true });
        const editable = page.getByRole('textbox', { name: 'Content', exact: true });
        await expect(editable).toBeVisible();
        await editable.fill('<p>01 / LISTEN</p>');
        const update = page.getByRole('button', { name: 'Update', exact: true });
        await expect(update).toBeEnabled();
        const saved = page.waitForResponse(response => response.url().includes('/wp/v2/pages/' + manifest().post_id) && response.request().method() === 'POST');
        await update.click();
        expect((await saved).ok()).toBeTruthy();
        await expect(update).toBeDisabled({ timeout: 30000 });
        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(page.frameLocator('iframe[name="editor-canvas"]').getByText('01 / LISTEN', { exact: true })).toBeVisible({ timeout: 60000 });
        expect(await page.frameLocator('iframe[name="editor-canvas"]').locator('body').innerText()).not.toMatch(/Attempt Block Recovery|unexpected or invalid content|Resolve Block/i);
        fs.writeFileSync(path.join(evidence, 'builder-after-save-dom.txt'), await page.locator('body').ariaSnapshot());
        await page.screenshot({ path: path.join(evidence, 'builder-after-save.png'), fullPage: true });
    } finally { await context.close(); }
});

test('Native Builder saves the core-family and product fixture without recovery', async ({ browser }) => {
    test.skip(!process.env.EMCP_FLATSOME_COOKIES, 'Private local browser session required.');
    const fixture = JSON.parse(fs.readFileSync(path.join(evidence, 'coverage-fixture.json'), 'utf8'));
    test.skip(fixture.cleaned, 'Disposable fixture already cleaned.');
    const auth = JSON.parse(fs.readFileSync(process.env.EMCP_FLATSOME_COOKIES, 'utf8'));
    const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1000 } });
    try {
        await context.addCookies([auth.auth, auth.logged].map(([name, value]) => ({ name, value, domain: auth.host, path: '/', secure: true, httpOnly: true, sameSite: 'Lax' })));
        const page = await context.newPage();
        page.on('pageerror', error => fs.appendFileSync(path.join(evidence, 'coverage-builder-errors.log'), error.stack + '\n'));
        page.on('console', message => { if (message.type() === 'error') fs.appendFileSync(path.join(evidence, 'coverage-builder-errors.log'), message.text() + '\n'); });
        page.on('response', async response => { if (response.status() >= 400 && response.url().includes('/wp-json/')) fs.appendFileSync(path.join(evidence, 'coverage-builder-http-errors.log'), response.status() + ' ' + response.url().split('?')[0] + ' ' + await response.text() + '\n'); });
        await page.goto(fixture.page.editor_url, { waitUntil: 'domcontentloaded' });
        const frame = page.frameLocator('iframe[name="editor-canvas"]');
        await expect(page.getByRole('button', { name: 'Update', exact: true })).toBeVisible({ timeout: 60000 });
        await page.screenshot({ path: path.join(evidence, 'coverage-builder-loading.png'), fullPage: true });
        fs.writeFileSync(path.join(evidence, 'coverage-builder-frames.json'), JSON.stringify(await page.locator('iframe').evaluateAll(frames => frames.map(f => ({ name: f.name, title: f.title, id: f.id, src: f.src }))), null, 2));
        await expect(frame.locator('body')).toBeVisible({ timeout: 60000 });
        fs.writeFileSync(path.join(evidence, 'coverage-builder-dom.txt'), await frame.locator('body').ariaSnapshot());
        await page.screenshot({ path: path.join(evidence, 'coverage-builder-initial.png'), fullPage: true });
        await expect(frame.getByText('Card body retained.', { exact: true })).toBeVisible({ timeout: 60000 });
        expect(await frame.locator('body').innerText()).not.toMatch(/Attempt Block Recovery|unexpected or invalid content|Resolve Block|encountered an error|cannot be previewed/i);
        await frame.getByText('Card body retained.', { exact: true }).click();
        await page.getByRole('textbox', { name: 'Content', exact: true }).fill('<p>Card body native saved.</p>');
        const saved = page.waitForResponse(response => response.url().includes('/wp/v2/pages/' + fixture.page.post_id) && response.request().method() === 'POST');
        await page.getByRole('button', { name: 'Update', exact: true }).click();
        const response = await saved;
        expect(response.ok(), await response.text()).toBeTruthy();
        await expect(page.getByRole('button', { name: 'Update', exact: true })).toBeDisabled({ timeout: 30000 });
        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(frame.getByText('Card body native saved.', { exact: true })).toBeVisible({ timeout: 60000 });
        await page.screenshot({ path: path.join(evidence, 'coverage-builder-after-save.png'), fullPage: true });
    } finally { await context.close(); }
});

test('Native core-family coverage and real WooCommerce products render and interact', async ({ page }) => {
    const fixture = JSON.parse(fs.readFileSync(path.join(evidence, 'coverage-fixture.json'), 'utf8'));
    test.skip(fixture.cleaned, 'Disposable fixture already cleaned through MCP.');
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(fixture.page.url, { waitUntil: 'networkidle' });
    fs.writeFileSync(path.join(evidence, 'coverage-browser-dom.txt'), await page.locator('body').ariaSnapshot());
    for (const label of ['Native card', 'Native banner', 'Icon box content.', 'First native list item', 'Fictional fixture author', 'Ceramic Vase', 'Framed Print', 'Scented Candle']) {
        await expect(page.getByText(label, { exact: true }).first()).toBeVisible();
    }
    await expect(page.locator('svg.fs-block-icon, .fs-block-icon svg')).not.toHaveCount(0);
    const secondTab = page.getByRole('tab', { name: 'Light', exact: true });
    await page.getByRole('tab', { name: 'Material', exact: true }).focus();
    await page.getByRole('tab', { name: 'Material', exact: true }).press('ArrowRight');
    await expect(secondTab).toHaveAttribute('aria-selected', 'true');
    await expect(page.getByText('Light panel content.', { exact: true })).toBeVisible();
    const next = page.getByRole('button', { name: /next/i });
    await next.click();
    await expect(page.getByText('Second slide content.', { exact: true })).toBeInViewport();
    await page.screenshot({ path: path.join(evidence, 'coverage-browser.png'), fullPage: true });
    expect(errors).toEqual([]);
});
