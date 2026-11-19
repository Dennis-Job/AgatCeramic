import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.beforeEach(async ({ request }) => {
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=standalone-attribute-values',
  )
})

test('all characteristics offer actual choices including seam width beyond page one', async ({
  page,
}) => {
  await page.goto('/catalog?category=tile')
  const form = page.getByRole('complementary')
  await expect(
    form.locator('.attribute-filter input:not([type=checkbox])'),
  ).toHaveCount(0)
  const seam = form.getByRole('group', { name: 'Ширина шва · мм', exact: true })
  await expect(seam.getByLabel('2–20', { exact: true })).toBeVisible()
  await expect(
    form
      .getByRole('group', { name: 'Морозостойкость', exact: true })
      .getByLabel('Нет', { exact: true }),
  ).toHaveCount(0)
  await seam.getByLabel('2–20', { exact: true }).check()
  await expect(page.locator('.product-card')).toHaveCount(1)
  await expect(page.locator('.product-card')).toContainText('Плитка 27')
  expect(
    JSON.parse(new URL(page.url()).searchParams.get('attributes')!),
  ).toEqual({ 7: { values: ['2–20'] } })
  await page.reload()
  await expect(seam.getByLabel('2–20', { exact: true })).toBeChecked()
  await page
    .getByRole('button', {
      name: 'Убрать фильтр: Ширина шва: 2–20',
      exact: true,
    })
    .click()
  await expect(page.locator('.product-card')).toHaveCount(24)
})

test('numeric zero, decimals, dates and long text choices serialize exact values and combine', async ({
  page,
}) => {
  await page.goto('/catalog?category=tile')
  const form = page.getByRole('complementary')
  const length = form.getByRole('group', { name: 'Длина · мм', exact: true })
  await length.getByLabel('600', { exact: true }).check()
  await expect(page.locator('.product-card')).toHaveCount(1)
  await length.getByLabel('0', { exact: true }).check()
  await expect(page.locator('.catalog-toolbar')).toContainText('Товаров: 27')
  await form
    .getByRole('group', { name: 'Толщина · мм', exact: true })
    .getByLabel('1.25', { exact: true })
    .check()
  await expect(page.locator('.product-card')).toHaveCount(1)
  await form
    .getByRole('group', { name: 'Дата выпуска', exact: true })
    .getByLabel('2026-12-31', { exact: true })
    .check()
  const description = form
    .getByRole('group', { name: 'Описание', exact: true })
    .getByRole('checkbox')
    .last()
  await expect
    .poll(
      () =>
        JSON.parse(new URL(page.url()).searchParams.get('attributes') || '{}')[
          '6'
        ]?.values,
    )
    .toEqual(['2026-12-31'])
  await description.check()
  await expect
    .poll(
      () =>
        Object.keys(
          JSON.parse(
            new URL(page.url()).searchParams.get('attributes') || '{}',
          ),
        ).length,
    )
    .toBe(4)
  const chosen = JSON.parse(new URL(page.url()).searchParams.get('attributes')!)
  expect(chosen['4']).toEqual({ values: ['600', '0'] })
  expect(chosen['5']).toEqual({ values: ['1.25'] })
  expect(chosen['6']).toEqual({ values: ['2026-12-31'] })
  expect(chosen['8'].values[0].length).toBeGreaterThan(255)
  await expect(page.locator('.product-card')).toContainText('Плитка 03')
})

test('choice-only forms fit five widths while mobile edits remain drafts', async ({
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
    await expect(
      scope.locator('.attribute-filter input:not([type=checkbox])'),
    ).toHaveCount(0)
    await scope
      .locator('.catalog-filter-form')
      .screenshot({ path: testInfo.outputPath(`actual-values-${width}.png`) })
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    if (mobile) {
      await scope.getByLabel('2–20', { exact: true }).check()
      expect(page.url()).not.toContain('attributes=')
      await page.keyboard.press('Escape')
      await expect(scope).toHaveCount(0)
    }
  }
  const violations = (await new AxeBuilder({ page }).analyze()).violations
  expect(
    violations.filter(
      (v) =>
        !(
          v.id === 'color-contrast' &&
          v.nodes.every((n) =>
            n.target.every(
              (t) => typeof t === 'string' && t.endsWith('.image-empty'),
            ),
          )
        ),
    ),
  ).toEqual([])
})
