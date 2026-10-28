import { expect, test, type Locator } from '@playwright/test'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=submenu')
})

async function finishMotion(element: Locator) {
  await element.evaluate((node) => {
    for (const animation of node.getAnimations()) animation.finish()
  })
}

async function halfway(element: Locator) {
  await expect
    .poll(() => element.evaluate((node) => node.getAnimations().length))
    .toBeGreaterThan(0)
  return element.evaluate((node) => {
    for (const animation of node.getAnimations()) {
      animation.pause()
      animation.currentTime = Number(animation.effect!.getTiming().duration) / 2
    }
    return {
      opacity: Number(getComputedStyle(node).opacity),
      height: node.getBoundingClientRect().height,
      display: getComputedStyle(node).display,
    }
  })
}

test('header omits only its disclosure arrow and smoothly enters, leaves and reverses the native popover', async ({
  page,
}, testInfo) => {
  await page.emulateMedia({ reducedMotion: 'no-preference' })
  await page.setViewportSize({ width: 1280, height: 900 })
  await page.goto('/about')
  const nav = page.getByRole('navigation', { name: 'Основная навигация' })
  const parent = nav.getByRole('button', { name: 'Каталог', exact: true })
  await expect(parent.locator('svg')).toHaveCount(0)
  const group = page.locator(`#${await parent.getAttribute('aria-controls')}`)
  await parent.click()
  const entering = await halfway(group)
  expect(entering.opacity).toBeGreaterThan(0)
  expect(entering.opacity).toBeLessThan(1)
  await page.screenshot({ path: testInfo.outputPath('header-enter.png') })
  await finishMotion(group)
  await expect(group).toHaveCSS('opacity', '1')
  await page.keyboard.press('Tab')
  await expect(nav.getByRole('link', { name: 'Весь каталог' })).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(parent).toBeFocused()
  await expect(parent).toHaveAttribute('aria-expanded', 'false')
  await expect(group).toHaveAttribute('inert')
  const leaving = await halfway(group)
  expect(leaving.opacity).toBeGreaterThan(0)
  expect(leaving.opacity).toBeLessThan(1)
  expect(leaving.display).not.toBe('none')
  await page.screenshot({ path: testInfo.outputPath('header-leave.png') })
  await finishMotion(group)
  await expect(group).toBeHidden()

  await parent.click()
  await finishMotion(group)
  await parent.click()
  await halfway(group)
  await parent.click()
  await finishMotion(group)
  await expect(parent).toHaveAttribute('aria-expanded', 'true')
  await expect(group).toBeVisible()
  await expect(group).not.toHaveAttribute('inert')
  await expect(group).toHaveCSS('opacity', '1')
  const beforeSwitch = (await group.boundingBox())!
  await nav.getByRole('button', { name: 'Информация', exact: true }).click()
  const duringSwitch = (await group.boundingBox())!
  expect(Math.abs(duringSwitch.x - beforeSwitch.x)).toBeLessThan(1)
  await page.getByRole('button', { name: 'Открыть поиск' }).click()
  await expect(group).toHaveAttribute('inert')
  await expect(
    page.getByRole('dialog', { name: 'Поиск по разделам страницы' }),
  ).toBeVisible()
})

test('drawer keeps the arrow and expands and collapses actual height without a jump or leaving focusable links', async ({
  page,
}, testInfo) => {
  await page.emulateMedia({ reducedMotion: 'no-preference' })
  await page.setViewportSize({ width: 640, height: 900 })
  await page.goto('/about')
  await page.getByRole('button', { name: 'Открыть меню' }).click()
  const menu = page.getByRole('dialog', { name: 'Меню сайта' })
  await expect(menu).toBeFocused()
  const parent = menu.getByRole('button', { name: 'Каталог', exact: true })
  const next = menu.getByRole('button', { name: 'Информация', exact: true })
  await expect(parent.locator('svg')).toHaveCount(1)
  const group = page.locator(`#${await parent.getAttribute('aria-controls')}`)
  await parent.focus()
  // Finish the existing drawer/item entrance before measuring layout below it.
  await menu.evaluate((node) => {
    for (const animation of node.getAnimations({ subtree: true }))
      animation.finish()
  })
  const collapsedY = (await next.boundingBox())!.y
  await parent.click()
  const entering = await halfway(group)
  expect(entering.opacity).toBeGreaterThan(0)
  expect(entering.opacity).toBeLessThan(1)
  expect(entering.height).toBeGreaterThan(0)
  await page.screenshot({ path: testInfo.outputPath('drawer-enter.png') })
  await finishMotion(group)
  const expandedHeight = (await group.boundingBox())!.height
  expect(entering.height).toBeLessThan(expandedHeight)
  expect((await next.boundingBox())!.y).toBeGreaterThan(collapsedY)
  await menu.getByRole('link', { name: 'Весь каталог' }).focus()
  await page.keyboard.press('Escape')
  await expect(parent).toBeFocused()
  await expect(group).toHaveAttribute('inert')
  const leaving = await halfway(group)
  expect(leaving.height).toBeGreaterThan(0)
  expect(leaving.height).toBeLessThan(expandedHeight)
  await page.screenshot({ path: testInfo.outputPath('drawer-leave.png') })
  await finishMotion(group)
  expect((await group.boundingBox())!.height).toBe(0)
  expect(Math.abs((await next.boundingBox())!.y - collapsedY)).toBeLessThan(1)
  await expect(menu).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(menu).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Открыть меню' })).toBeFocused()
})

test('reduced motion and five widths preserve disclosure controls and never leave an interactive closing group', async ({
  page,
}, testInfo) => {
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await page.goto('/about')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    if (width > 1024) {
      await expect(
        page.getByRole('button', { name: 'Открыть меню' }),
      ).toHaveCount(0)
      continue
    }
    await page.getByRole('button', { name: 'Открыть меню' }).click()
    const menu = page.getByRole('dialog', { name: 'Меню сайта' })
    const parent = menu.getByRole('button', { name: 'Каталог', exact: true })
    await expect(parent.locator('svg')).toHaveCount(1)
    await parent.click()
    const group = page.locator(`#${await parent.getAttribute('aria-controls')}`)
    await expect(menu.getByRole('link', { name: 'Весь каталог' })).toBeVisible()
    await expect(group).toHaveAttribute('aria-hidden', 'false')
    await expect(group).toHaveCSS('opacity', '1')
    await expect
      .poll(async () => (await group.boundingBox())!.height)
      .toBeGreaterThan(0)
    expect(
      await group.evaluate((node) =>
        node
          .getAnimations()
          .every(
            (animation) =>
              Number(animation.effect!.getTiming().duration) <= 0.01,
          ),
      ),
    ).toBe(true)
    await page.screenshot({ path: testInfo.outputPath(`drawer-${width}.png`) })
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.keyboard.press('Escape')
    await expect(group).toHaveAttribute('inert')
    await page.keyboard.press('Escape')
    await expect(menu).toHaveCount(0)
  }
  const nav = page.getByRole('navigation', { name: 'Основная навигация' })
  const parent = nav.getByRole('button', { name: 'Каталог', exact: true })
  await parent.click()
  const group = page.locator(`#${await parent.getAttribute('aria-controls')}`)
  await expect(group).toBeVisible()
  await expect(group).toHaveCSS('opacity', '1')
  expect(
    await group.evaluate((node) =>
      node
        .getAnimations()
        .every(
          (animation) => Number(animation.effect!.getTiming().duration) <= 0.01,
        ),
    ),
  ).toBe(true)
  await page.keyboard.press('Escape')
  await expect(group).toBeHidden()
})
