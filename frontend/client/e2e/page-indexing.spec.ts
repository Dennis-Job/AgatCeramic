import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const api = 'http://127.0.0.1:8015'
test.beforeEach(async ({ request }) => {
  await request.get(`${api}/__scenario?name=default`)
})

test('published flags control SSR robots on home, static and generic content routes', async ({
  request,
}) => {
  for (const scenario of ['default', 'indexable', 'noindex']) {
    await request.get(`${api}/__scenario?name=${scenario}`)
    for (const route of ['/', '/about', '/contacts', '/catalog', '/delivery']) {
      const response = await request.get(route)
      expect(response.status()).toBe(200)
      const html = await response.text()
      expect(html).toContain(
        `name="robots" content="${scenario === 'noindex' ? 'noindex,follow' : 'index,follow'}"`,
      )
      expect(html).toContain(
        `rel="canonical" href="https://agatceramic.example${route}"`,
      )
      if (route === '/delivery') {
        expect(html).toContain('Доставка керамической плитки')
        expect(html).toContain('"@type":"WebPage"')
      }
    }
  }
  await request.get(`${api}/__scenario?name=default`)
  const paginated = await (await request.get('/catalog?page=2')).text()
  expect(paginated).toContain('name="robots" content="noindex,follow"')
})

test('home alias redirects and unpublished or failed generic routes fail safely', async ({
  request,
}) => {
  const home = await request.get('/home', { maxRedirects: 0 })
  expect(home.status()).toBe(301)
  expect(home.headers().location).toBe('/')
  expect((await request.get('/draft-only')).status()).toBe(404)
  for (const scenario of ['missing', 'error']) {
    await request.get(`${api}/__scenario?name=${scenario}`)
    for (const route of ['/', '/delivery']) {
      const response = await request.get(route)
      expect(response.status()).toBe(scenario === 'missing' ? 404 : 503)
      expect(await response.text()).toContain('noindex')
    }
  }
  const preview = await request.get('/preview/about')
  expect(await preview.text()).not.toContain('Сохранённый текст черновика')
  expect(preview.headers()['x-robots-tag']).toContain('noindex')
})

test('sitemap paginates published content and products, excludes noindex and uses canonical URLs', async ({
  request,
}) => {
  const response = await request.get('/sitemap.xml')
  expect(response.status()).toBe(200)
  expect(response.headers()['content-type']).toContain('application/xml')
  const xml = await response.text()
  for (const path of [
    '/',
    '/delivery',
    '/legacy-page',
    '/published-address',
    '/products/test',
  ]) {
    expect(xml).toContain(`<loc>https://agatceramic.example${path}</loc>`)
  }
  expect(xml).toContain('<lastmod>2026-10-19T10:00:00Z</lastmod>')
  expect(
    xml.match(/<loc>https:\/\/agatceramic\.example\/products\/test<\/loc>/g),
  ).toHaveLength(1)
  for (const hidden of [
    'hidden-page',
    '/home',
    'draft-only',
    'draft-address',
    '/preview',
    'evil.example',
  ])
    expect(xml).not.toContain(hidden)
  const robots = await (await request.get('/robots.txt')).text()
  expect(robots).toContain('Sitemap: https://agatceramic.example/sitemap.xml')
  expect(robots).not.toContain('Disallow: /hidden-page')
})

test('sitemap returns 503 without partial XML on any upstream failure and permits real empty data', async ({
  request,
}) => {
  for (const scenario of [
    'sitemap-error',
    'sitemap-page-error',
    'sitemap-malformed',
    'sitemap-catalog-error',
    'sitemap-catalog-page-error',
    'sitemap-catalog-malformed',
    'sitemap-catalog-loop',
    'sitemap-catalog-moving-pages',
    'sitemap-catalog-summary-malformed',
  ]) {
    await request.get(`${api}/__scenario?name=${scenario}`)
    const response = await request.get('/sitemap.xml')
    expect(response.status()).toBe(503)
    expect(response.headers()['cache-control']).toContain('no-store')
    expect(await response.text()).not.toContain('<urlset')
  }
  await request.get(`${api}/__scenario?name=sitemap-empty`)
  const response = await request.get('/sitemap.xml')
  expect(response.status()).toBe(200)
  expect(await response.text()).not.toContain('<loc>')
})

test('sitemap validates and deduplicates canonical product slugs across every catalog page', async ({
  request,
}) => {
  await request.get(`${api}/__scenario?name=sitemap-products`)
  const response = await request.get('/sitemap.xml')
  expect(response.status()).toBe(200)
  expect(response.headers()['cache-control']).toContain('no-store')
  const xml = await response.text()
  for (const slug of ['tile-one', 'tile-two']) {
    expect(
      xml.split(`<loc>https://agatceramic.example/products/${slug}</loc>`),
    ).toHaveLength(2)
  }
  for (const invalid of [
    'unsafe',
    'evil.example',
    'UPPER',
    '?page=',
    '?brand=',
    '?collection=',
  ]) {
    expect(xml).not.toContain(invalid)
  }
  expect(xml).toContain('<loc>https://agatceramic.example/delivery</loc>')
})

test('generic page reuses responsive accessible content and hydrates without errors', async ({
  page,
}, testInfo) => {
  const issues: string[] = []
  page.on('pageerror', (error) => issues.push(error.message))
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  await page.goto('/delivery')
  await expect(page.locator('main h1')).toHaveText(
    'Доставка керамической плитки и материалов для ремонта',
  )
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: testInfo.outputPath(`delivery-${width}.png`),
      fullPage: true,
    })
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  }
  expect(issues).toEqual([])
})
