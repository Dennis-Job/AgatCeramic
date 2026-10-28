import { expect, test } from '@playwright/test'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=submenu')
})

test('burger and desktop navigation are complementary at the breakpoint and expanding the viewport closes the drawer safely', async ({
  page,
}, testInfo) => {
  await page.goto('/about')
  const nav = page.getByRole('navigation', {
    name: 'Основная навигация',
    includeHidden: true,
  })
  for (const width of [320, 640, 768, 1024, 1025, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    const trigger = page.getByRole('button', { name: 'Открыть меню' })
    if (width <= 1024) {
      await expect(trigger).toBeVisible()
      await expect(nav).toBeHidden()
    } else {
      await expect(trigger).toHaveCount(0)
      await expect(nav).toBeVisible()
    }
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page
      .locator('.site-header')
      .screenshot({ path: testInfo.outputPath(`header-${width}.png`) })
  }
  await page.setViewportSize({ width: 768, height: 900 })
  await page.getByRole('button', { name: 'Открыть меню' }).click()
  const menu = page.getByRole('dialog', { name: 'Меню сайта' })
  await expect(menu).toBeFocused()
  await page.setViewportSize({ width: 1280, height: 900 })
  await expect(menu).toHaveCount(0)
  await expect(page.locator('body')).not.toHaveClass(/has-overlay/)
  await expect(page.locator('.site-header .wordmark')).toBeFocused()
})

test('desktop submenu stays immediately below the whole header during motion, sticky scroll and short oversized navigation', async ({
  page,
  request,
}, testInfo) => {
  await page.emulateMedia({ reducedMotion: 'no-preference' })
  await page.setViewportSize({ width: 1280, height: 900 })
  await page.goto('/about')
  const header = page.locator('.site-header')
  const parent = header.getByRole('button', { name: 'Каталог', exact: true })
  const group = page.locator(`#${await parent.getAttribute('aria-controls')}`)
  await parent.click()
  await group.evaluate((node) => {
    for (const animation of node.getAnimations()) {
      animation.pause()
      animation.currentTime = 0
    }
  })
  let headerBox = (await header.boundingBox())!
  expect((await group.boundingBox())!.y).toBeGreaterThanOrEqual(
    headerBox.y + headerBox.height - 1,
  )
  await group.evaluate((node) => {
    for (const animation of node.getAnimations())
      animation.currentTime = Number(animation.effect!.getTiming().duration) / 2
  })
  expect((await group.boundingBox())!.y).toBeGreaterThanOrEqual(
    headerBox.y + headerBox.height - 1,
  )
  await page.screenshot({
    path: testInfo.outputPath('submenu-enter-below-header.png'),
  })
  await group.evaluate((node) => {
    for (const animation of node.getAnimations()) animation.finish()
  })
  expect(
    Math.abs((await group.boundingBox())!.y - headerBox.y - headerBox.height),
  ).toBeLessThan(1)
  await page.evaluate(() => window.scrollTo(0, 200))
  await expect
    .poll(async () => {
      const box = (await header.boundingBox())!
      return Math.abs((await group.boundingBox())!.y - box.y - box.height)
    })
    .toBeLessThan(1)
  await page.screenshot({
    path: testInfo.outputPath('submenu-sticky-below-header.png'),
  })
  await page.keyboard.press('Escape')
  await request.get('http://127.0.0.1:8015/__scenario?name=submenu-max')
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await page.setViewportSize({ width: 1280, height: 360 })
  await page.goto('/about')
  const last = header.getByRole('button', { name: 'Последняя группа' })
  await last.focus()
  await page.keyboard.press('Enter')
  const oversized = page.locator(`#${await last.getAttribute('aria-controls')}`)
  await expect(oversized).toHaveCSS('opacity', '1')
  headerBox = (await header.boundingBox())!
  const bounds = (await oversized.boundingBox())!
  expect(Math.abs(bounds.y - headerBox.y - headerBox.height)).toBeLessThan(1)
  expect(bounds.y + bounds.height).toBeLessThanOrEqual(360)
  await oversized.getByRole('link').last().focus()
  await expect(oversized.getByRole('link').last()).toBeInViewport({ ratio: 1 })
  await page.screenshot({
    path: testInfo.outputPath('submenu-short-below-header.png'),
  })
})
