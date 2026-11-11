import { expect, test } from '@playwright/test'

test.beforeEach(async ({ request }) => {
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=catalog-card-standard',
  )
})

test('catalog categories follow the header on scroll and resize, and return to their original position', async ({
  page,
}, testInfo) => {
  const issues: string[] = []
  page.on('pageerror', (error) => issues.push(error.message))
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  await page.goto('/catalog')
  await page.evaluate(() => document.fonts.ready)
  const categories = page.getByRole('navigation', {
    name: 'Категории каталога',
  })
  const header = page.locator('.site-header')
  await expect(categories).toHaveCSS('position', 'sticky')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    // The responsive header and font metrics settle on the next rendered frame.
    await page.evaluate(
      () =>
        new Promise<void>((resolve) =>
          requestAnimationFrame(() => requestAnimationFrame(() => resolve())),
        ),
    )
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }))
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0)
    const initial = (await categories.boundingBox())!
    await expect(categories).toHaveCSS(
      'border-bottom-color',
      'rgba(0, 0, 0, 0)',
    )
    expect(initial.y).toBeGreaterThan((await header.boundingBox())!.height)
    await page.evaluate(() => {
      const menu = document.querySelector('[aria-label="Категории каталога"]')!
      window.scrollTo({
        top: menu.getBoundingClientRect().top + window.scrollY + 100,
        behavior: 'instant',
      })
    })
    await expect
      .poll(async () => {
        const menu = (await categories.boundingBox())!
        const head = (await header.boundingBox())!
        return Math.abs(menu.y - (head.y + head.height))
      })
      .toBeLessThan(2)
    await expect(categories).toHaveCSS(
      'border-bottom-color',
      'rgb(227, 225, 219)',
    )
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({ path: testInfo.outputPath(`sticky-${width}.png`) })
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }))
    await expect
      .poll(async () =>
        Math.abs((await categories.boundingBox())!.y - initial.y),
      )
      .toBeLessThan(2)
    await expect(categories).toHaveCSS(
      'border-bottom-color',
      'rgba(0, 0, 0, 0)',
    )
  }
  await page.setViewportSize({ width: 320, height: 260 })
  await categories.getByRole('link').last().focus()
  await page.keyboard.press('Tab')
  const productLink = page.locator('#catalog-products .product-card a').first()
  await expect(productLink).toBeFocused()
  await expect
    .poll(async () => {
      const menu = (await categories.boundingBox())!
      return (await productLink.boundingBox())!.y - menu.y - menu.height
    })
    .toBeGreaterThanOrEqual(7)
  await page.screenshot({
    path: testInfo.outputPath('sticky-keyboard-short.png'),
  })
  await page.evaluate(() => {
    const section = document.querySelector('#catalog-products')!
    window.scrollTo({
      top: section.getBoundingClientRect().bottom + window.scrollY,
      behavior: 'instant',
    })
  })
  await expect
    .poll(async () => {
      const menu = (await categories.boundingBox())!
      const section = (await page.locator('#catalog-products').boundingBox())!
      return menu.y + menu.height - section.y - section.height
    })
    .toBeLessThanOrEqual(1)
  await page.setViewportSize({ width: 1280, height: 900 })
  await categories.getByRole('link').first().click()
  await expect(page).toHaveURL(/category=porcelain/)
  await expect(categories).toHaveCSS('position', 'sticky')
  await page.goto('/about')
  await expect(page.locator('.categories--sticky')).toHaveCount(0)
  expect(issues).toEqual([])
})
