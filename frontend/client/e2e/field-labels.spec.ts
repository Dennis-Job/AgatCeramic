import { expect, test, type Locator } from '@playwright/test'

async function expectReferenceLabels(scope: Locator) {
  const labels = scope.locator('.ui-text-field > label, .ui-textarea > label')
  expect(await labels.count()).toBeGreaterThan(0)
  for (const label of await labels.all()) {
    await expect(label).toHaveCSS('font-size', '10px')
    await expect(label).toHaveCSS('font-weight', '500')
    await expect(label).toHaveCSS('letter-spacing', '2px')
    await expect(label).toHaveCSS('text-transform', 'uppercase')
    await expect(label).toHaveCSS('color', 'rgb(110, 107, 102)')
    const control = scope.locator(`[id="${await label.getAttribute('for')}"]`)
    await expect(control).toHaveCount(1)
  }
}

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
})

test('text and textarea labels use the reference style in cooperation', async ({
  page,
}) => {
  await page.goto('/cooperation')
  const form = page.getByRole('form', { name: 'Предложить сотрудничество' })
  await expect(form.getByLabel('Ваше предложение')).toBeVisible()
  await expectReferenceLabels(form)
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    expect(
      await page.evaluate(
        () =>
          document.documentElement.scrollWidth <=
          document.documentElement.clientWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: `../../.tmp/field-labels-cooperation-${width}.png`,
      fullPage: true,
    })
  }
})

test('callback field labels use the same reference style', async ({ page }) => {
  await page.goto('/about')
  await page
    .getByRole('button', { name: /^заказать звонок$/i })
    .first()
    .click()
  const dialog = page.getByRole('dialog', {
    name: 'Заказать звонок',
    exact: true,
  })
  await expect(dialog).toBeVisible()
  await expectReferenceLabels(dialog)
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await page.screenshot({
      path: `../../.tmp/field-labels-callback-${width}.png`,
      fullPage: true,
    })
  }
})
