import { expect, test } from '@playwright/test'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
})

test('content introductions have distinct SSR illustrations and a responsive stable layout', async ({
  page,
  request,
}) => {
  for (const slug of [
    'catalog',
    'about',
    'contacts',
    'cooperation',
    'rezka',
    'rospis',
  ]) {
    const html = await (await request.get(`/${slug}`)).text()
    expect(html).toContain(`/images/page-intro/${slug}.webp`)
    await page.goto(`/${slug}`)
    const image = page.locator('.page-intro__image')
    await expect(image).toBeVisible()
    await expect(image).toHaveJSProperty('naturalWidth', 1200)
    for (const width of [320, 640, 768, 1024, 1280]) {
      await page.setViewportSize({ width, height: 900 })
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
      ).toBe(true)
      await expect(page.locator('main h1')).toHaveCount(1)
      const boxes = await page.locator('.page-intro').evaluate((element) => {
        const title = element.querySelector('h1')!.getBoundingClientRect()
        const image = element.querySelector('img')!.getBoundingClientRect()
        return {
          titleRight: title.right,
          titleBottom: title.bottom,
          imageLeft: image.left,
          imageTop: image.top,
        }
      })
      if (width > 760)
        expect(boxes.imageLeft).toBeGreaterThanOrEqual(boxes.titleRight - 1)
      else expect(boxes.imageTop).toBeGreaterThan(boxes.titleBottom)
    }
  }
})

test('custom image appears in SSR and failed custom image restores the standard art', async ({
  page,
  request,
}) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=intro-custom')
  expect(await (await request.get('/catalog')).text()).toContain(
    '/images/page-intro/rospis.webp',
  )
  await page.goto('/catalog')
  await expect(page.locator('.page-intro__image')).toHaveAttribute(
    'alt',
    'Авторская композиция',
  )
  await expect(page.locator('.page-intro__image')).toHaveAttribute(
    'src',
    /rospis.webp$/,
  )
  await request.get('http://127.0.0.1:8015/__scenario?name=intro-broken')
  await page.goto('/catalog')
  await expect(page.locator('.page-intro__image')).toHaveAttribute(
    'src',
    '/images/page-intro/catalog.webp',
  )
  await expect(page.locator('.page-intro__image')).toHaveJSProperty(
    'naturalWidth',
    1200,
  )
  const height = (await page.locator('.page-intro').boundingBox())!.height
  await page.route('**/images/page-intro/*.webp', (route) => route.abort())
  await page.reload()
  await expect(page.locator('.page-intro__image')).toHaveCount(0)
  await expect(page.locator('main h1')).toHaveText('Каталог')
  expect((await page.locator('.page-intro').boundingBox())!.height).toBeCloseTo(
    height,
  )
})

test('an enabled full hero suppresses the extra introduction', async ({
  page,
  request,
}) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=intro-hero')
  await page.goto('/about')
  await expect(page.locator('.page-intro')).toHaveCount(0)
  await expect(page.locator('main h1')).toHaveCount(1)
})

test('saved draft intro maps its own image without changing public content', async ({
  page,
  request,
  context,
}) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=intro-custom')
  await context.addCookies([
    {
      name: 'preview_test_session',
      value: 'employee',
      url: 'http://127.0.0.1:3015',
    },
  ])
  await page.goto('/preview/catalog')
  await expect(page.locator('.page-intro__image')).toHaveAttribute(
    'src',
    /about.webp$/,
  )
  await page.goto('/catalog')
  await expect(page.locator('.page-intro__image')).toHaveAttribute(
    'src',
    /rospis.webp$/,
  )
})
