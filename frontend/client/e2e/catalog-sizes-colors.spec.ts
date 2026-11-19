import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.beforeEach(async ({ request }) => {
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=standalone-sizes-colors',
  )
})

test('actual sizes include values beyond the first page and combine exactly with color choices', async ({
  page,
}) => {
  await page.goto('/catalog?category=tile')
  const form = page.getByRole('complementary', { name: 'Фильтры каталога' })
  await expect(form.getByLabel('900×900', { exact: true })).toBeVisible()
  await form.getByLabel('900×900', { exact: true }).check()
  await expect(page.locator('.product-card')).toHaveCount(1)
  await expect(page.locator('.product-card')).toContainText('Плитка 27')
  await form.getByLabel('300×3000', { exact: true }).check()
  await expect(page.locator('.product-card')).toHaveCount(2)
  const applied = JSON.parse(
    new URL(page.url()).searchParams.get('attributes')!,
  )
  expect(applied).toEqual({ 7: { values: ['900×900', '300×3000'] } })
  await expect(page.locator('.product-card')).toHaveCount(2)
  await expect(
    page.locator('.product-card').filter({ hasText: 'Плитка 03' }),
  ).toHaveCount(1)
  await expect(
    page.locator('.product-card').filter({ hasText: 'Плитка 27' }),
  ).toHaveCount(1)
  await form.getByLabel('Белый', { exact: true }).check()
  await expect(page.locator('.product-card')).toHaveCount(0)
  await expect(
    page.getByRole('button', {
      name: 'Убрать фильтр: Цвет: Белый',
      exact: true,
    }),
  ).toBeVisible()
  await page
    .getByRole('button', { name: 'Убрать фильтр: Цвет: Белый', exact: true })
    .click()
  await expect(page.locator('.product-card')).toHaveCount(2)
  await page.reload()
  await expect(form.getByLabel('900×900', { exact: true })).toBeChecked()
  await expect(form.getByLabel('300×3000', { exact: true })).toBeChecked()
})

test('size and color controls stay usable across five widths and mobile Apply cancels drafts', async ({
  page,
}, testInfo) => {
  await page.goto('/catalog?category=tile')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    const mobile = width < 1024
    if (mobile)
      await page.getByRole('button', { name: 'Фильтры', exact: true }).click()
    const scope = mobile
      ? page.getByRole('dialog')
      : page.getByRole('complementary')
    const white = scope.getByLabel('Белый', { exact: true })
    await expect(white).toHaveAttribute('title', 'Белый')
    await white.focus()
    await white.press('Space')
    await expect(white).toBeChecked()
    await expect(
      white.locator('..').locator('.ui-color-swatch__color'),
    ).toHaveCSS('background-color', 'rgb(255, 255, 255)')
    if (!mobile) await expect(page.locator('.product-card')).toHaveCount(9)
    await scope.getByLabel('600×300', { exact: true }).check()
    await scope
      .locator('.catalog-filter-form')
      .screenshot({ path: testInfo.outputPath(`size-colors-${width}.png`) })
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    if (mobile) {
      expect(page.url()).not.toContain('attributes=')
      await page.keyboard.press('Escape')
      await expect(scope).toHaveCount(0)
      await page.getByRole('button', { name: 'Фильтры', exact: true }).click()
      await expect(scope.getByLabel('Белый', { exact: true })).not.toBeChecked()
      await scope.getByLabel('Серый', { exact: true }).check()
      await scope
        .getByRole('button', { name: 'Применить', exact: true })
        .click()
      await expect(page).toHaveURL(/attributes=/)
      await expect(
        page.getByRole('button', {
          name: 'Убрать фильтр: Цвет: Серый',
          exact: true,
        }),
      ).toBeVisible()
      await page
        .getByRole('button', {
          name: 'Убрать фильтр: Цвет: Серый',
          exact: true,
        })
        .click()
    } else {
      await expect(page).toHaveURL(/attributes=/)
      await expect(page.locator('.product-card')).toHaveCount(0)
      await white.uncheck()
      await expect(page.locator('.product-card')).toHaveCount(1)
      await scope.getByLabel('600×300', { exact: true }).uncheck()
      await expect(page).not.toHaveURL(/attributes=/)
      await expect(page.locator('.product-card')).toHaveCount(24)
    }
  }
  const violations = (await new AxeBuilder({ page }).analyze()).violations
  expect(
    violations.filter(
      (violation) =>
        !(
          violation.id === 'color-contrast' &&
          violation.nodes.every((node) =>
            node.target.every(
              (target) =>
                typeof target === 'string' && target.endsWith('.image-empty'),
            ),
          )
        ),
    ),
  ).toEqual([])
})

test('legacy text size links and unknown color values remain removable without fabricated colors', async ({
  page,
}) => {
  const attributes = { 7: { text: '300' }, 1: { values: ['legacy-gray'] } }
  await page.goto(
    '/catalog?category=tile&attributes=' +
      encodeURIComponent(JSON.stringify(attributes)),
  )
  const form = page.getByRole('complementary')
  const unknown = form.getByLabel('legacy-gray', { exact: true })
  await expect(unknown).toBeChecked()
  await expect(unknown.locator('..')).not.toHaveClass(/ui-color-swatch/)
  await expect(
    page.getByRole('button', {
      name: 'Убрать фильтр: Размер: 300',
      exact: true,
    }),
  ).toBeVisible()
  await page
    .getByRole('button', {
      name: 'Убрать фильтр: Цвет: legacy-gray',
      exact: true,
    })
    .click()
  await expect(page.locator('.product-card')).toHaveCount(24)
  await form.getByLabel('900×900', { exact: true }).check()
  await expect(page.locator('.product-card')).toHaveCount(1)
  const applied = JSON.parse(
    new URL(page.url()).searchParams.get('attributes')!,
  )
  expect(applied).toEqual({ 7: { values: ['900×900'] } })
})

test('touch color targets retain selection when applying a brand filter', async ({
  browser,
}) => {
  const context = await browser.newContext({
    hasTouch: true,
    viewport: { width: 320, height: 900 },
  })
  const page = await context.newPage()
  await page.goto('http://127.0.0.1:3015/brand/atlas?category=tile')
  await page.getByRole('button', { name: 'Фильтры', exact: true }).click()
  const dialog = page.getByRole('dialog')
  const gray = dialog.getByLabel('Серый', { exact: true })
  const target = await gray.locator('..').boundingBox()
  expect(target!.width).toBeGreaterThanOrEqual(44)
  expect(target!.height).toBeGreaterThanOrEqual(44)
  await gray.check()
  await dialog.getByLabel('300×3000', { exact: true }).check()
  await dialog.getByRole('button', { name: 'Применить', exact: true }).click()
  await expect(page.locator('.product-card')).toHaveCount(1)
  await expect(page).toHaveURL(/\/brand\/atlas\?/)
  await page.getByRole('button', { name: 'Фильтры', exact: true }).click()
  await expect(dialog.getByLabel('Серый', { exact: true })).toBeChecked()
  await expect(dialog.getByLabel('300×3000', { exact: true })).toBeChecked()
  await context.close()
})
