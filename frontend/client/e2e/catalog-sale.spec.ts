import { expect, test } from '@playwright/test'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=standalone-sale')
})

test('sale selection applies to the API, combines with stock and restores through history', async ({
  page,
}) => {
  await page.goto('/catalog?category=tile&sort=price_desc&page=2')
  const form = page.getByRole('complementary', { name: 'Фильтры каталога' })
  const sale = form.getByLabel('Распродажа', { exact: true })
  await expect(page.locator('.product-card')).toHaveCount(3)
  const response = page.waitForResponse((response) => {
    const url = new URL(response.url())
    return (
      url.pathname === '/api/v1/catalog' &&
      url.searchParams.get('is_on_sale') === '1'
    )
  })
  await sale.check()
  await response
  await expect(page).toHaveURL(/is_on_sale=1/)
  await expect(page).not.toHaveURL(/page=/)
  await expect(page.locator('.product-card')).toHaveCount(9)
  await expect(page.locator('.product-card .product-sale-badge')).toHaveCount(9)
  await form.getByLabel('В наличии', { exact: true }).check()
  await expect(page).toHaveURL(/in_stock=1/)
  await expect(page.locator('.product-card')).toHaveCount(5)
  await page
    .getByRole('button', { name: 'Убрать фильтр: Распродажа', exact: true })
    .click()
  await expect(page).not.toHaveURL(/is_on_sale=/)
  await expect(page).toHaveURL(/in_stock=1/)
  await expect(page.locator('.product-card')).toHaveCount(14)
  await expect(sale).not.toBeChecked()
  await page.goBack()
  await expect(sale).toBeChecked()
  await expect(page.locator('.product-card')).toHaveCount(5)
  await page.reload()
  await expect(sale).toBeChecked()
  await page
    .locator('.catalog-applied')
    .getByRole('button', { name: 'Сбросить фильтры', exact: true })
    .click()
  await expect(page).toHaveURL(
    /category=tile&sort=price_desc#catalog-products$/,
  )
  await expect(sale).not.toBeChecked()
  await expect(page.locator('.product-card')).toHaveCount(24)
})

test('mobile sale drafts cancel and reset without applying, then Apply commits', async ({
  page,
}) => {
  await page.setViewportSize({ width: 640, height: 900 })
  await page.goto('/catalog?category=tile')
  const original = page.url()
  const opener = page.getByRole('button', { name: 'Фильтры', exact: true })
  const drawer = page.getByRole('dialog', { name: 'Фильтры каталога' })
  await opener.click()
  await drawer.getByLabel('Распродажа', { exact: true }).check()
  await page.keyboard.press('Escape')
  await expect(drawer).toHaveCount(0)
  expect(page.url()).toBe(original)
  await opener.click()
  await expect(
    drawer.getByLabel('Распродажа', { exact: true }),
  ).not.toBeChecked()
  await drawer.getByLabel('Распродажа', { exact: true }).check()
  await drawer
    .getByRole('button', { name: 'Сбросить фильтры', exact: true })
    .click()
  await expect(
    drawer.getByLabel('Распродажа', { exact: true }),
  ).not.toBeChecked()
  await expect(drawer).toBeVisible()
  expect(page.url()).toBe(original)
  await drawer.getByLabel('Распродажа', { exact: true }).check()
  await drawer.getByRole('button', { name: 'Применить', exact: true }).click()
  await expect(drawer).toHaveCount(0)
  await expect(page).toHaveURL(/is_on_sale=1/)
  await expect(page.locator('.product-card')).toHaveCount(9)
  await expect(opener).toBeFocused()
})

test('sale direct links render filtered SSR and the brand scope retains the selection', async ({
  page,
  request,
}) => {
  const response = await request.get('/catalog?is_on_sale=1')
  const html = await response.text()
  expect(html).toContain('Плитка 01')
  expect(html).not.toContain('Плитка 02')
  await page.goto('/brand/atlas?category=tile&is_on_sale=1')
  const form = page.getByRole('complementary', { name: 'Фильтры каталога' })
  await expect(form.getByLabel('Распродажа', { exact: true })).toBeChecked()
  await expect(page.locator('.product-card')).toHaveCount(9)
  await page.getByRole('link', { name: 'Весь каталог', exact: true }).click()
  await expect(page).toHaveURL(/\/catalog#catalog-products$/)
  await expect(page.locator('.product-card')).toHaveCount(24)
  await page.goto('/catalog?is_on_sale=0')
  await expect(form.getByLabel('Распродажа', { exact: true })).not.toBeChecked()
  await expect(
    page.getByRole('button', {
      name: 'Убрать фильтр: Распродажа',
      exact: true,
    }),
  ).toHaveCount(0)
  await expect(page.locator('.product-card')).toHaveCount(24)
})
