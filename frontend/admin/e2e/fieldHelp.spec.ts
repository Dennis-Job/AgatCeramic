import AxeBuilder from './approvedAccessibility'
import { expect, test } from './fixtures'
import { mockAdminBaseline } from './adminBaselineApi'

const hintName = 'Подсказка: Email для уведомлений'
const help =
  'Служебный адрес для уведомлений о новых заявках: обратный звонок, сотрудничество и email. На сайте не публикуется.'

test('field hints stay beside labels and remain hoverable and accessible at every width', async ({
  page,
}, testInfo) => {
  await mockAdminBaseline(page)
  await page.goto('/settings')
  await expect(
    page.getByRole('heading', { name: 'Настройки сайта' }),
  ).toBeVisible()
  const hint = page.getByRole('button', { name: hintName, exact: true })
  const input = page.getByRole('textbox', {
    name: 'Email для уведомлений',
    exact: true,
  })
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await page.mouse.move(1, 1)
    await input.focus()
    await expect(page.getByRole('tooltip')).toHaveCount(0)
    await expect(page.getByText(help, { exact: true })).toHaveCount(0)
    await hint.scrollIntoViewIfNeeded()
    await hint.hover()
    const tooltip = page.getByRole('tooltip')
    await expect(tooltip).toHaveText(help)
    const box = (await tooltip.boundingBox())!
    expect(box.x).toBeGreaterThanOrEqual(0)
    expect(box.x + box.width).toBeLessThanOrEqual(width)
    expect(box.y).toBeGreaterThanOrEqual(0)
    expect(box.y + box.height).toBeLessThanOrEqual(900)
    const title = page
      .locator('.ui-field')
      .filter({ has: hint })
      .locator('.ui-field-title')
    const titleBox = (await title.boundingBox())!
    const iconBox = (await hint.boundingBox())!
    expect(iconBox.x).toBeGreaterThanOrEqual(titleBox.x + titleBox.width)
    await tooltip.hover()
    await expect(tooltip).toBeVisible()
    expect(
      await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(width)
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    await page.screenshot({
      path: testInfo.outputPath(`field-help-${width}.png`),
      fullPage: false,
      animations: 'disabled',
    })
    await page.keyboard.press('Escape')
    await expect(tooltip).toHaveCount(0)
  }
  await hint.focus()
  await expect(page.getByRole('tooltip')).toHaveText(help)
  await page.keyboard.press('Escape')
  await expect(hint).toBeFocused()
  await expect(page.getByRole('tooltip')).toHaveCount(0)
  await page.keyboard.press('Tab')
  await expect(
    page.getByRole('textbox', { name: 'Адрес', exact: true }),
  ).toBeFocused()
})

test('touch can toggle a field hint without activating or submitting its field', async ({
  browser,
}) => {
  const context = await browser.newContext({
    hasTouch: true,
    viewport: { width: 390, height: 844 },
  })
  const page = await context.newPage()
  await mockAdminBaseline(page)
  await page.goto('/settings')
  const hint = page.getByRole('button', { name: hintName, exact: true })
  const input = page.getByRole('textbox', {
    name: 'Email для уведомлений',
    exact: true,
  })
  await hint.tap()
  await expect(page.getByRole('tooltip')).toHaveText(help)
  await expect(input).not.toBeFocused()
  await hint.tap()
  await expect(page.getByRole('tooltip')).toHaveCount(0)
  await hint.tap()
  await expect(page.getByRole('tooltip')).toBeVisible()
  await input.tap()
  await expect(page.getByRole('tooltip')).toHaveCount(0)
  await context.close()
})

test('field help is available inside a dialog and the first Escape only dismisses the hint', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.goto('/content')
  await page.getByRole('button', { name: 'Добавить страницу' }).click()
  const dialog = page.getByRole('dialog', { name: 'Новая страница' })
  const hint = dialog.getByRole('button', {
    name: 'Подсказка: Адрес страницы (slug)',
    exact: true,
  })
  await hint.focus()
  await expect(dialog.getByRole('tooltip')).toContainText('Латинские')
  await page.keyboard.press('Escape')
  await expect(page.getByRole('tooltip')).toHaveCount(0)
  await expect(dialog).toBeVisible()
  await expect(hint).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(dialog).toHaveCount(0)
})
