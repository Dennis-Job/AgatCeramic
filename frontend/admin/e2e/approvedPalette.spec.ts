import { expect, test } from './fixtures'
import { mockAdminBaseline } from './adminBaselineApi'
import { assertApprovedAdminColors } from './approvedAccessibility'

for (const width of [320, 1280]) {
  test(`approved macOS palette renders identical tokens and component colors at ${width}px`, async ({
    page,
  }) => {
    await page.setViewportSize({ width, height: 900 })
    await mockAdminBaseline(page)
    await page.goto('/ui-kit')
    await assertApprovedAdminColors(page)
    const feedback = page.locator('[data-ui-kit-section="feedback"]')
    for (const [name, color, background] of [
      ['Нейтральный', 'rgb(102, 112, 133)', 'rgb(242, 244, 247)'],
      ['Основной', 'rgb(0, 91, 255)', 'rgb(235, 244, 255)'],
      ['Дополнительный', 'rgb(11, 165, 236)', 'rgb(240, 249, 255)'],
      ['Успешно', 'rgb(18, 183, 106)', 'rgb(236, 253, 243)'],
      ['Внимание', 'rgb(247, 144, 9)', 'rgb(255, 250, 235)'],
      ['Ошибка', 'rgb(240, 68, 56)', 'rgb(254, 243, 242)'],
    ] as const) {
      const badge = feedback.getByText(name, { exact: true })
      await expect(badge).toHaveCSS('color', color)
      await expect(badge).toHaveCSS('background-color', background)
    }
    const buttons = page.locator('[data-ui-kit-section="buttons"]')
    for (const [name, color, background, hoverColor, hoverBackground] of [
      [
        'Основная',
        'rgb(255, 255, 255)',
        'rgb(0, 91, 255)',
        'rgb(255, 255, 255)',
        'rgb(0, 80, 224)',
      ],
      [
        'Вторичная',
        'rgb(102, 112, 133)',
        'rgb(249, 250, 251)',
        'rgb(102, 112, 133)',
        'rgb(242, 244, 247)',
      ],
      [
        'На белой поверхности',
        'rgb(102, 112, 133)',
        'rgb(255, 255, 255)',
        'rgb(0, 91, 255)',
        'rgb(235, 244, 255)',
      ],
      [
        'Опасное действие',
        'rgb(255, 255, 255)',
        'rgb(240, 68, 56)',
        'rgb(255, 255, 255)',
        'rgb(217, 45, 32)',
      ],
      [
        'Нейтральная прозрачная',
        'rgb(102, 112, 133)',
        'rgba(0, 0, 0, 0)',
        'rgb(102, 112, 133)',
        'rgb(242, 244, 247)',
      ],
      [
        'Синяя прозрачная',
        'rgb(0, 91, 255)',
        'rgba(0, 0, 0, 0)',
        'rgb(0, 91, 255)',
        'rgb(235, 244, 255)',
      ],
      [
        'Удалить',
        'rgb(240, 68, 56)',
        'rgba(0, 0, 0, 0)',
        'rgb(240, 68, 56)',
        'rgb(254, 243, 242)',
      ],
    ] as const) {
      const button = buttons
        .getByRole('button', { name, exact: true })
        .filter({ hasText: name })
      await page.mouse.move(0, 0)
      await expect(button).toHaveCSS('color', color)
      await expect(button).toHaveCSS('background-color', background)
      await button.hover()
      await expect(button).toHaveCSS('color', hoverColor)
      await expect(button).toHaveCSS('background-color', hoverBackground)
    }
    for (const name of ['Недоступна', 'Опасная недоступна']) {
      const button = buttons.getByRole('button', { name, exact: true })
      await expect(button).toBeDisabled()
      await expect(button).toHaveCSS('background-color', 'rgb(152, 162, 179)')
    }
    const primary = buttons.getByRole('button', {
      name: 'Основная',
      exact: true,
    })
    await page.keyboard.press('Tab')
    await primary.focus()
    await expect(primary).toHaveCSS('outline-color', 'rgb(100, 125, 209)')
    await expect(primary).toHaveCSS('outline-width', '1px')
    await expect(primary).toHaveCSS(
      'box-shadow',
      /rgba\(100, 125, 209, 0\.14\)/,
    )
    for (const section of [
      'inventory',
      'buttons',
      'feedback',
      'collection-states',
    ]) {
      await page.locator(`[data-ui-kit-section="${section}"]`).screenshot({
        path: test.info().outputPath(`approved-${section}-${width}.png`),
        animations: 'disabled',
      })
    }
  })
}

test('approved palette guard rejects a changed token even before snapshot comparison', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.goto('/ui-kit')
  await assertApprovedAdminColors(page)
  await page.addStyleTag({
    content: ':root { --admin-color-primary-500: #0050e0; }',
  })
  await expect(assertApprovedAdminColors(page)).rejects.toThrow(
    'Owner-approved macOS color tokens',
  )
})
