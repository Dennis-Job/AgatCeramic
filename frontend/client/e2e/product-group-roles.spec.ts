import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test('identical product names and axes remain identifiable by SKU', async ({
  page,
  request,
}, testInfo) => {
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=product-duplicate-names',
  )
  await page.goto('/products/product-1')
  const choices = page.locator('.variant-options a')
  await expect(choices.nth(0)).toContainText('Артикул TEST-1')
  await expect(choices.nth(1)).toContainText('Артикул TEST-2')
  for (const width of [320, 768, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page
      .locator('.variants')
      .screenshot({ path: testInfo.outputPath(`duplicate-sku-${width}.png`) })
  }
  await choices.nth(1).click()
  await expect(page).toHaveURL(/\/products\/product-2$/)
})

test('equal axes keep separate product links with distinguishable names', async ({
  page,
  request,
}, testInfo) => {
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=product-duplicate-axes',
  )
  const response = await request.get('/products/product-1')
  const html = await response.text()
  expect(html).toContain('Серый · Керамогранит Светлый')
  await page.goto('/products/product-1')
  const choices = page.locator('.variant-options a')
  await expect(choices).toHaveText([
    'Серый · Керамогранит с длинным русским названием 1',
    'Серый · Керамогранит Светлый',
  ])
  await expect(choices.nth(0)).toHaveAttribute('href', '/products/product-1')
  await expect(choices.nth(1)).toHaveAttribute('href', '/products/product-2')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page
      .locator('.variants')
      .screenshot({
        path: testInfo.outputPath(`duplicate-variants-${width}.png`),
      })
  }
  expect(
    (await new AxeBuilder({ page }).include('.variants').analyze()).violations,
  ).toEqual([])
  await choices.nth(1).click()
  await expect(page).toHaveURL(/\/products\/product-2$/)
  await expect(page.getByRole('heading', { level: 1 })).toHaveText(
    'Керамогранит Светлый',
  )
  await expect(
    page.locator('.variant-options a[aria-current="page"]'),
  ).toHaveAttribute('href', '/products/product-2')
})
