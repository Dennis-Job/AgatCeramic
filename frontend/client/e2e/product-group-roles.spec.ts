import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test('each variant link changes one characteristic and keeps the others', async ({
  page,
  request,
}, testInfo) => {
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=product-sized-variants',
  )
  await page.goto('/products/product-1')
  const colors = page.getByRole('group', { name: 'Цвет', exact: true })
  const sizes = page.getByRole('group', { name: 'Размер', exact: true })
  const light = colors.getByRole('link', { name: 'Цвет: Светлый', exact: true })
  const size300 = sizes.getByRole('link', {
    name: 'Размер: 300×300 мм',
    exact: true,
  })
  const size600 = sizes.getByRole('link', {
    name: 'Размер: 600×300 мм',
    exact: true,
  })
  await expect(colors.locator('a img')).toHaveCount(2)
  await expect(sizes.locator('a img')).toHaveCount(0)
  await expect(size300).toHaveCSS('background-color', 'rgb(20, 20, 20)')
  await expect(size300).toHaveCSS('color', 'rgb(255, 255, 255)')
  await expect(size600).toHaveCSS('background-color', 'rgb(250, 250, 248)')
  await expect(light).toHaveAttribute('href', '/products/product-2')
  await expect(size600).toHaveAttribute('href', '/products/product-4')
  await light.press('Enter')
  await expect(page).toHaveURL(/\/products\/product-2$/)
  await expect(size300).toHaveAttribute('aria-current', 'page')
  await expect(size600).toHaveCount(0)
  await page.goBack()
  await size600.click()
  await expect(page).toHaveURL(/\/products\/product-4$/)
  await expect(size600).toHaveCSS('background-color', 'rgb(20, 20, 20)')
  await expect(colors.getByRole('link')).toHaveCount(1)
  await expect(light).toHaveCount(0)
  await expect(size300).toHaveAttribute('href', '/products/product-1')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.locator('.variants').screenshot({
      path: testInfo.outputPath(`compatible-variants-${width}.png`),
    })
  }
  expect(
    (await new AxeBuilder({ page }).include('.variants').analyze()).violations,
  ).toEqual([])
  await size300.click()
  await expect(light).toBeVisible()
})

test('identical product names and axes remain identifiable by SKU', async ({
  page,
  request,
}, testInfo) => {
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=product-duplicate-names',
  )
  await page.goto('/products/product-1')
  const choices = page
    .getByRole('group', { name: 'Варианты товара' })
    .getByRole('link')
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
  const choices = page
    .getByRole('group', { name: 'Варианты товара' })
    .getByRole('link')
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
    await page.locator('.variants').screenshot({
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
    choices.and(page.locator('[aria-current="page"]')),
  ).toHaveAttribute('href', '/products/product-2')
})
