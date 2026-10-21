import { expect, test, type Locator } from '@playwright/test'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
})

test('search opens as a compact full-width strip below the sticky header', async ({
  page,
}, testInfo) => {
  await page.goto('/about')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 707 })
    await page.evaluate(() => window.scrollTo(0, 250))
    const trigger = page.getByRole('button', { name: 'Открыть поиск' })
    await trigger.click()
    const search = page.locator('#site-search')
    await expect(search).toBeFocused()
    await expect(search).not.toHaveClass(/ui-search-enter/)
    await expect(search).toHaveCSS('opacity', '1')
    await expect(page.locator('.backdrop')).toHaveCSS('opacity', '1')
    await expect(search.getByRole('searchbox')).not.toBeFocused()
    await expect(search.getByRole('navigation')).toHaveCount(0)
    await expect(page.locator('.topbar')).toHaveAttribute('inert', '')
    const header = (await page.locator('.site-header').boundingBox())!
    const strip = (await search.boundingBox())!
    expect(Math.abs(strip.y - header.y - header.height)).toBeLessThan(1)
    expect(strip.x).toBe(0)
    expect(strip.width).toBe(
      await page.evaluate(() => document.body.clientWidth),
    )
    expect(strip.height).toBeLessThanOrEqual(70)
    const input = search.getByRole('searchbox')
    await expect(input).toHaveAttribute(
      'placeholder',
      'Поиск по названию, коллекции, артикулу...',
    )
    await expect(input).toHaveCSS('border-bottom-width', '0px')
    await expect(input).toHaveCSS('font-size', '15px')
    await expect(input).toHaveCSS('font-weight', '400')
    await expect(
      search.getByRole('button', { name: 'Закрыть поиск' }),
    ).toHaveCount(0)
    await expect(
      search.getByRole('button', { name: 'Очистить поиск' }),
    ).toHaveCount(0)
    await page.screenshot({
      animations: 'disabled',
      path: testInfo.outputPath(`search-strip-${width}.png`),
    })
    await page.keyboard.press('Tab')
    await expect(search.getByRole('searchbox')).toBeFocused()
    await search.getByRole('searchbox').fill('Главная')
    const clear = search.getByRole('button', { name: 'Очистить поиск' })
    await expect(clear).toHaveCSS('width', '44px')
    await expect(clear.locator('svg')).toHaveAttribute('stroke-width', '1.2')
    expect(await clear.evaluate((node) => getComputedStyle(node).color)).toBe(
      await search
        .locator('.search-panel__icon')
        .evaluate((node) => getComputedStyle(node).color),
    )
    await clear.hover()
    await expect(clear).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)')
    await expect(search.getByRole('link', { name: 'Главная' })).toBeVisible()
    await page.screenshot({
      animations: 'disabled',
      path: testInfo.outputPath(`search-results-${width}.png`),
    })
    await search.getByRole('searchbox').fill('несуществующий раздел')
    await expect(search.getByRole('status')).toBeVisible()
    await clear.click()
    await expect(input).toHaveValue('')
    await expect(input).toBeFocused()
    await expect(search).toBeVisible()
    await expect(search.getByRole('navigation')).toHaveCount(0)
    await page.locator('.backdrop').click({ position: { x: 5, y: 400 } })
    await expect(search).toHaveCount(0)
    await expect(trigger).toBeFocused()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
  }
  await page.getByRole('button', { name: 'Открыть поиск' }).click()
  await page.setViewportSize({ width: 320, height: 420 })
  await expect
    .poll(async () => {
      const header = (await page.locator('.site-header').boundingBox())!
      const search = (await page.locator('#site-search').boundingBox())!
      return Math.abs(search.y - header.y - header.height)
    })
    .toBeLessThan(1)
  await page.keyboard.press('Escape')
  await expect(page.locator('#site-search')).toHaveCount(0)
})

test('header actions follow the reference order, fit narrow screens and return search focus to its trigger', async ({
  page,
}, testInfo) => {
  await page.goto('/about')
  await page.evaluate(() => document.fonts.ready)
  const actions = page.locator('.site-header__actions')
  const search = actions.getByRole('button', { name: 'Открыть поиск' })
  const cart = actions.getByRole('button', { name: 'Корзина', exact: true })
  await expect(actions.getByRole('button')).toHaveCount(3)
  expect(
    await actions
      .getByRole('button')
      .evaluateAll((buttons) =>
        buttons.map((button) => button.getAttribute('aria-label')),
      ),
  ).toEqual(['Открыть поиск', 'Корзина', 'Открыть меню'])
  await expect(cart).toBeDisabled()
  await expect(cart).toHaveAttribute('title', 'Корзина будет доступна позже')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 707 })
    await page.evaluate(
      () =>
        new Promise<void>((resolve) =>
          requestAnimationFrame(() => requestAnimationFrame(() => resolve())),
        ),
    )
    for (const button of await actions.getByRole('button').all()) {
      await expect(button).toBeVisible()
      await expect(button).toHaveCSS('width', '44px')
      await expect(button).toHaveCSS('height', '44px')
    }
    const brandRect = (await page
      .locator('.site-header .ui-wordmark')
      .boundingBox())!
    const actionsRect = (await actions.boundingBox())!
    expect(brandRect.x + brandRect.width).toBeLessThanOrEqual(actionsRect.x)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await search.hover()
    await expect(search).toHaveCSS('background-color', 'rgb(233, 230, 224)')
    await cart.hover()
    await expect(cart).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)')
    await page.mouse.move(0, 0)
    await page
      .locator('.site-header')
      .screenshot({ path: testInfo.outputPath(`header-actions-${width}.png`) })
    await search.click()
    const dialog = page.getByRole('dialog', {
      name: 'Поиск по разделам страницы',
    })
    await expect(dialog).toBeFocused()
    await expect(dialog.getByRole('searchbox')).not.toBeFocused()
    await dialog.getByRole('searchbox').fill('Главная')
    await expect(dialog.getByRole('link', { name: 'Главная' })).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(dialog).toHaveCount(0)
    await expect(search).toBeFocused()
  }
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

test('menu and callback share the approved close control and hover', async ({
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
    await page.keyboard.press('Escape')
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
