import { expect, test, type Locator } from '@playwright/test'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
})

test('menu, search and callback share the approved close control and hover', async ({
  page,
}, testInfo) => {
  await page.goto('/about')
  async function checkClose(button: Locator, name: string) {
    await page.mouse.move(0, 0)
    await expect(button).toHaveClass('ui-close-button')
    await expect(button).toHaveCSS('width', '44px')
    await expect(button).toHaveCSS('height', '44px')
    await expect(button).toHaveCSS('border-radius', '50%')
    await expect(button).toHaveCSS('border-width', '1px')
    await expect(button).toHaveCSS('border-color', 'rgb(227, 225, 219)')
    await expect(button).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)')
    await expect(button).toHaveCSS('color', 'rgb(20, 20, 20)')
    await expect(button.locator('svg')).toHaveAttribute('width', '16')
    await expect(button.locator('svg')).toHaveAttribute('stroke-width', '1.6')
    await button.hover()
    await expect(button).toHaveCSS('background-color', 'rgb(20, 20, 20)')
    await expect(button).toHaveCSS('border-color', 'rgb(20, 20, 20)')
    await expect(button).toHaveCSS('color', 'rgb(255, 255, 255)')
    await expect(button.locator('svg')).toHaveCSS(
      'stroke',
      'rgb(255, 255, 255)',
    )
    await expect(button.locator('svg')).toHaveCSS('width', '16px')
    await expect(button.locator('svg')).toHaveCSS('height', '16px')
    await button.evaluate(
      () =>
        new Promise<void>((resolve) => {
          requestAnimationFrame(() => requestAnimationFrame(() => resolve()))
        }),
    )
    await page.screenshot({ path: testInfo.outputPath(`${name}-hover.png`) })
  }
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 707 })
    await page.getByRole('button', { name: 'Открыть меню' }).click()
    await checkClose(
      page.getByRole('button', { name: 'Закрыть меню' }),
      `menu-${width}`,
    )
    await page
      .getByRole('button', { name: 'Поиск по разделам страницы' })
      .click()
    const searchClose = page.getByRole('button', { name: 'Закрыть поиск' })
    await checkClose(searchClose, `search-${width}`)
    await searchClose.click()
    await page
      .getByRole('button', { name: 'заказать звонок', exact: true })
      .click()
    const dialogClose = page
      .getByRole('dialog')
      .getByRole('button', { name: 'Закрыть', exact: true })
    await checkClose(dialogClose, `callback-${width}`)
    await dialogClose.click()
  }
})
