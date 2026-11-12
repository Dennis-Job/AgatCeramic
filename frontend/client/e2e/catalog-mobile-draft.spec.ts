import { expect, test } from '@playwright/test'

test.beforeEach(async ({ request, page }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=standalone-default')
  await page.setViewportSize({ width: 640, height: 900 })
})

test('plumbing discloses all children and only Apply commits category and filters', async ({
  page,
}, testInfo) => {
  await page.goto('/catalog')
  const original = page.url()
  await page.getByRole('button', { name: 'Фильтры', exact: true }).click()
  const drawer = page.getByRole('dialog', { name: 'Фильтры каталога' })
  const categories = drawer.getByRole('navigation', {
    name: 'Категории каталога',
  })
  const plumbing = categories.getByRole('button', {
    name: 'Сантехника',
    exact: true,
  })
  await expect(plumbing).toHaveAttribute('aria-expanded', 'false')
  await expect(
    categories.getByRole('button', { name: 'Ванны', exact: true }),
  ).toBeHidden()
  await plumbing.click()
  for (const name of [
    'Ванны',
    'Душевые кабины',
    'Раковины',
    'Смесители',
    'Унитазы',
  ])
    await expect(
      categories.getByRole('button', { name, exact: true }),
    ).toBeVisible()
  const textSize = await plumbing.evaluate(
    (el) => getComputedStyle(el).fontSize,
  )
  expect(textSize).toBe(
    await drawer
      .locator('.ui-checkbox')
      .first()
      .evaluate((el) => getComputedStyle(el).fontSize),
  )
  await categories.getByRole('button', { name: 'Ванны', exact: true }).click()
  await expect(drawer).toBeVisible()
  expect(page.url()).toBe(original)
  await expect(drawer.getByLabel('Акрил', { exact: true })).toBeVisible()
  await expect(page.locator('.product-card')).toHaveCount(24)
  await expect(page.locator('.product-card').first()).toContainText('Плитка 01')
  await drawer.getByLabel('Акрил', { exact: true }).check()
  await drawer.getByLabel('В наличии', { exact: true }).check()
  await page.screenshot({
    path: testInfo.outputPath('mobile-draft-plumbing.png'),
  })
  await drawer.getByRole('button', { name: 'Применить', exact: true }).click()
  await expect(drawer).toHaveCount(0)
  await expect(page).toHaveURL(/category=bath/)
  await expect(page).toHaveURL(/in_stock=1/)
  await expect(page.locator('.product-card')).toHaveCount(2)
  await expect(page.locator('.product-card').first()).toContainText('Ванна 01')
})

test('disclosure Escape returns to parent; closing and resetting drafts do not change results', async ({
  page,
}) => {
  await page.goto('/catalog?category=tile&brand=atlas&price_from=120')
  const original = page.url()
  const opener = page.getByRole('button', { name: 'Фильтры', exact: true })
  await opener.click()
  const drawer = page.getByRole('dialog', { name: 'Фильтры каталога' })
  const plumbing = drawer.getByRole('button', {
    name: 'Сантехника',
    exact: true,
  })
  await plumbing.click()
  await drawer.getByRole('button', { name: 'Ванны', exact: true }).focus()
  await page.keyboard.press('Escape')
  await expect(drawer).toBeVisible()
  await expect(plumbing).toHaveAttribute('aria-expanded', 'false')
  await expect(plumbing).toBeFocused()
  await plumbing.click()
  await drawer.getByRole('button', { name: 'Ванны', exact: true }).click()
  await expect(drawer.getByLabel('Акрил', { exact: true })).toBeVisible()
  await expect(drawer.getByText('Цвет', { exact: true })).toHaveCount(0)
  await drawer
    .getByRole('button', { name: 'Сбросить всё', exact: true })
    .click()
  await expect(drawer).toBeVisible()
  expect(page.url()).toBe(original)
  await expect(
    drawer.getByRole('button', { name: 'Все товары', exact: true }),
  ).toHaveAttribute('aria-current', 'true')
  await drawer.getByRole('button', { name: 'Закрыть', exact: true }).click()
  await expect(drawer).toHaveCount(0)
  expect(page.url()).toBe(original)
  await expect(opener).toBeFocused()
})

test('draft metadata errors are retryable without changing the applied page', async ({
  page,
}) => {
  await page.goto('/catalog')
  let fail = true
  await page.route('**/catalog?**', (route) => {
    if (
      new URL(route.request().url()).searchParams.get('category') === 'bath' &&
      fail
    ) {
      fail = false
      return route.fulfill({ status: 503, json: {} })
    }
    return route.continue()
  })
  await page.getByRole('button', { name: 'Фильтры', exact: true }).click()
  const drawer = page.getByRole('dialog', { name: 'Фильтры каталога' })
  await drawer.getByRole('button', { name: 'Сантехника', exact: true }).click()
  await drawer.getByRole('button', { name: 'Ванны', exact: true }).click()
  await expect(drawer.getByRole('alert')).toContainText(
    'Не удалось загрузить фильтры',
  )
  await expect(
    drawer.getByRole('button', { name: 'Применить', exact: true }),
  ).toBeDisabled()
  expect(page.url()).not.toContain('category=')
  await drawer
    .getByRole('button', { name: 'Повторить загрузку', exact: true })
    .click()
  await expect(drawer.getByLabel('Акрил', { exact: true })).toBeVisible()
  await expect(
    drawer.getByRole('button', { name: 'Применить', exact: true }),
  ).toBeEnabled()
})
