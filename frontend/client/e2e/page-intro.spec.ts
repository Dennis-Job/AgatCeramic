import { expect, test } from '@playwright/test'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
})

test('content introductions have distinct SSR illustrations and a responsive stable layout', async ({
  page,
  request,
}, testInfo) => {
  for (const slug of [
    'catalog',
    'about',
    'contacts',
    'cooperation',
    'rezka',
    'rospis',
    'delivery',
    'payment',
    'designers',
  ]) {
    const html = await (await request.get(`/${slug}`)).text()
    expect(html).toContain(`/images/page-intro/${slug}-wide.webp`)
    await page.goto(`/${slug}`)
    const image = page.locator('.page-intro__image')
    await expect(image).toBeVisible()
    await expect(image).toHaveJSProperty('naturalWidth', 2400)
    await expect(image).toHaveJSProperty('naturalHeight', 800)
    for (const width of [320, 640, 768, 1024, 1280, 1920, 2560, 3840]) {
      await page.setViewportSize({ width, height: 900 })
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
      ).toBe(true)
      await expect(page.locator('main h1')).toHaveCount(1)
      const boxes = await page.locator('.page-intro').evaluate((element) => {
        const hero = element.getBoundingClientRect()
        const title = element.querySelector('h1')!.getBoundingClientRect()
        const imageElement = element.querySelector('img')!
        const image = imageElement.getBoundingClientRect()
        return {
          fullWidth: Math.abs(hero.width - document.body.clientWidth) < 1,
          coversHero:
            Math.abs(image.left - hero.left) < 1 &&
            Math.abs(image.top - hero.top) < 1 &&
            Math.abs(image.width - hero.width) < 1 &&
            Math.abs(image.height - hero.height) < 1,
          titleOverImage:
            title.top >= image.top &&
            title.bottom <= image.bottom &&
            title.left >= image.left &&
            title.right <= image.right,
          fit: getComputedStyle(imageElement).objectFit,
        }
      })
      expect(boxes).toEqual({
        fullWidth: true,
        coversHero: true,
        titleOverImage: true,
        fit: 'cover',
      })
      if (slug === 'catalog' && [320, 1280, 3840].includes(width))
        await page.screenshot({
          path: testInfo.outputPath(`intro-fullwidth-${width}.png`),
        })
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
    '/images/page-intro/catalog-wide.webp',
  )
  await expect(page.locator('.page-intro__image')).toHaveJSProperty(
    'naturalWidth',
    2400,
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
