import { expect, test, type Locator } from '@playwright/test'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
})

test('header, menu and footer share a text-only wordmark without decorative symbols', async ({
  page,
  request,
}, testInfo) => {
  const html = await (await request.get('/about')).text()
  expect(html).not.toContain('src="/images/logo.svg"')
  expect(html).not.toContain('<sup>°</sup>')
  await page.goto('/about')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 707 })
    const header = page.locator('.site-header .ui-wordmark')
    const footer = page.locator('.site-footer .ui-wordmark')
    await expect(header).toHaveText('AGATCERAMIC')
    await expect(footer).toHaveText('AGATCERAMIC')
    await page.getByRole('button', { name: 'Открыть меню' }).click()
    const menu = page.getByRole('dialog', { name: 'Меню сайта' })
    for (const wordmark of [header, footer, menu.locator('.ui-wordmark')]) {
      await expect(wordmark).toHaveText('AGATCERAMIC')
      await expect(wordmark.locator('img, svg, sup')).toHaveCount(0)
      await expect(wordmark).toHaveCSS('font-weight', '600')
      await expect(wordmark.locator('.ui-wordmark__light')).toHaveCSS(
        'font-weight',
        '400',
      )
    }
    await page.screenshot({
      path: testInfo.outputPath(`wordmark-menu-${width}.png`),
    })
    await page.keyboard.press('Escape')
    await expect(menu).toHaveCount(0)
    await page
      .locator('.site-header')
      .screenshot({ path: testInfo.outputPath(`wordmark-header-${width}.png`) })
    await page
      .locator('.site-footer')
      .screenshot({ path: testInfo.outputPath(`wordmark-footer-${width}.png`) })
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
  }
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
