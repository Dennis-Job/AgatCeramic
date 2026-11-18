import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.beforeEach(async ({ request }) => {
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=standalone-brand-menu',
  )
})

const menuName = 'Бренды по алфавиту'

test('SSR includes selected brand links and only populated alphabet groups', async ({
  page,
  request,
}) => {
  const response = await request.get('/catalog?category=tile&page=2')
  const html = await response.text()
  expect(html).toContain('/brand/41zero42')
  expect(html).toContain('/brand/atlas-ru')
  await page.goto('/catalog?category=tile&page=2')
  const menu = page.getByRole('navigation', { name: menuName })
  await expect(menu.getByRole('button')).toHaveText(['0–9', 'A', 'E', 'РУС'])
  await menu.getByRole('button', { name: '0–9', exact: true }).click()
  await expect(menu.getByRole('link')).toHaveText(['9Cento', '41zero42'])
  await expect(menu.locator('a[aria-current="page"]')).toHaveCount(0)
  await expect(menu.getByRole('link').first()).toHaveAttribute(
    'aria-current',
    'false',
  )
  await menu.getByRole('button', { name: 'РУС', exact: true }).click()
  await expect(menu.getByRole('link')).toHaveText(['Атлас', 'Ёлка', 'Ясень'])
  await menu.getByRole('button', { name: 'E', exact: true }).click()
  await expect(menu.getByRole('link')).toHaveText(['Équipe'])
})

test('hover reaches panel, click after hover keeps it open and brand links reset prior filters', async ({
  page,
}) => {
  await page.goto(
    '/catalog?category=tile&collection=Stone&price_from=120&page=2',
  )
  const menu = page.getByRole('navigation', { name: menuName })
  const letter = menu.getByRole('button', { name: 'A', exact: true })
  await letter.hover()
  await expect(letter).toHaveAttribute('aria-expanded', 'true')
  await letter.click()
  await expect(letter).toHaveAttribute('aria-expanded', 'true')
  const atlas = menu.getByRole('link', { name: '«Atlas»', exact: true })
  await atlas.hover()
  await expect(atlas).toBeVisible()
  await atlas.click()
  await expect(page).toHaveURL(/\/brand\/atlas$/)
  await expect(menu.getByRole('button', { expanded: true })).toHaveCount(0)
  await expect(menu.getByRole('button', { name: 'A', exact: true })).toHaveCSS(
    'text-decoration-line',
    'none',
  )
  await menu.getByRole('button', { name: 'A', exact: true }).click()
  await expect(
    menu.getByRole('link', { name: '«Atlas»', exact: true }),
  ).toHaveAttribute('aria-current', 'true')
})

test('keyboard opens groups, Escape restores focus and outside interaction closes', async ({
  page,
}) => {
  await page.goto('/catalog')
  const menu = page.getByRole('navigation', { name: menuName })
  const letter = menu.getByRole('button', { name: '0–9', exact: true })
  await letter.focus()
  await page.keyboard.press('Enter')
  await expect(letter).toBeFocused()
  await page.keyboard.press('Tab')
  await expect(menu.getByRole('link', { name: '9Cento' })).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(letter).toBeFocused()
  await expect(menu.getByRole('link')).toHaveCount(0)
  await page.keyboard.press('Space')
  await page.getByRole('heading', { level: 1 }).click()
  await expect(letter).toHaveAttribute('aria-expanded', 'false')
  await letter.focus()
  await page.keyboard.press('Enter')
  await page.getByRole('button', { name: 'Открыть поиск' }).focus()
  await expect(letter).toHaveAttribute('aria-expanded', 'false')
})

test('brand menu is desktop-only and panels close when resizing to a small screen', async ({
  page,
}, testInfo) => {
  const errors: string[] = []
  page.on('pageerror', (error) => errors.push(error.message))
  for (const width of [320, 640, 704, 768, 1024, 1025, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/catalog')
    const menu = page.getByRole('navigation', { name: menuName })
    if (width <= 1024) {
      await expect(page.locator('.catalog-brand-navigation')).toBeHidden()
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
      ).toBe(true)
      await page.screenshot({
        path: testInfo.outputPath(`brand-menu-${width}.png`),
      })
      continue
    }
    const letter = menu.getByRole('button', { name: 'A', exact: true })
    await letter.click()
    const panel = page.locator(`#${await letter.getAttribute('aria-controls')}`)
    await expect(panel).toBeVisible()
    const bounds = await page.locator('.catalog-brand-navigation').boundingBox()
    const popup = await panel.boundingBox()
    expect(Math.abs(popup!.x - bounds!.x)).toBeLessThan(2)
    expect(Math.abs(popup!.width - bounds!.width)).toBeLessThan(2)
    const columns = await panel
      .locator('ul')
      .evaluate(
        (el) => getComputedStyle(el).gridTemplateColumns.split(' ').length,
      )
    expect(columns).toBe(4)
    expect(popup!.y + popup!.height).toBeLessThanOrEqual(901)
    await menu
      .getByRole('link', { name: 'Atelier 31', exact: true })
      .scrollIntoViewIfNeeded()
    await expect(
      menu.getByRole('link', { name: 'Atelier 31', exact: true }),
    ).toBeInViewport()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: testInfo.outputPath(`brand-menu-${width}.png`),
    })
    await menu.getByRole('link', { name: 'Atelier 31', exact: true }).focus()
    await page.mouse.move(0, 0)
    await expect(letter).toHaveAttribute('aria-expanded', 'true')
    await page.setViewportSize({ width: 1024, height: 900 })
    await expect(page.locator('.catalog-brand-navigation')).toBeHidden()
    await expect(
      page.locator('.catalog-brand-navigation :popover-open'),
    ).toHaveCount(0)
    await page.setViewportSize({ width, height: 900 })
    await expect(menu).toBeVisible()
    await expect(letter).toHaveAttribute('aria-expanded', 'false')
  }
  expect(errors).toEqual([])
})

test('touch opens and closes a letter and empty special groups disappear after data changes', async ({
  browser,
  request,
}) => {
  const context = await browser.newContext({
    viewport: { width: 1280, height: 900 },
    hasTouch: true,
    isMobile: true,
  })
  const page = await context.newPage()
  await page.goto('/catalog')
  const menu = page.getByRole('navigation', { name: menuName })
  const letter = menu.getByRole('button', { name: 'РУС', exact: true })
  await letter.tap()
  await expect(menu.getByRole('link', { name: 'Ёлка' })).toBeVisible()
  await letter.tap()
  await expect(letter).toHaveAttribute('aria-expanded', 'false')
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=standalone-brand-menu-latin',
  )
  await page.reload()
  await expect(menu.getByRole('button')).toHaveText(['B'])
  await request.get('http://127.0.0.1:8015/__scenario?name=standalone-default')
  await page.reload()
  await expect(menu).toHaveCount(0)
  await context.close()
})

test('normal motion panel is accessible and immediately leaves the tab order when closed', async ({
  page,
}) => {
  await page.emulateMedia({ reducedMotion: 'no-preference' })
  await page.goto('/catalog')
  const menu = page.getByRole('navigation', { name: menuName })
  await menu.getByRole('button', { name: 'A', exact: true }).click()
  const violations = await new AxeBuilder({ page })
    .include('.catalog-brand-navigation')
    .analyze()
  expect(violations.violations).toEqual([])
  const panel = menu.locator('[data-brand-panel="A"]')
  const before = await panel.evaluate((el) => ({
    left: getComputedStyle(el).left,
    width: getComputedStyle(el).width,
  }))
  await page.keyboard.press('Escape')
  await expect(menu.getByRole('link')).toHaveCount(0)
  expect(
    await panel.evaluate((el) => ({
      left: getComputedStyle(el).left,
      width: getComputedStyle(el).width,
    })),
  ).toEqual(before)
  await menu.getByRole('button', { name: 'E', exact: true }).click()
  await expect(menu.getByRole('link')).toHaveText(['Équipe'])
})

test('short viewport permits keyboard reach to the final brand and refresh removes the old group', async ({
  page,
  request,
}) => {
  await page.setViewportSize({ width: 1280, height: 400 })
  await page.goto('/catalog')
  const menu = page.getByRole('navigation', { name: menuName })
  await menu.scrollIntoViewIfNeeded()
  const letter = menu.getByRole('button', { name: 'A', exact: true })
  await letter.focus()
  await page.keyboard.press('Enter')
  await expect(letter).toHaveAttribute('aria-expanded', 'true')
  for (let i = 0; i < 33; i++) await page.keyboard.press('Tab')
  const last = menu.getByRole('link', { name: '«Atlas»', exact: true })
  await expect(last).toBeFocused()
  await expect(last).toBeInViewport()
  await page.keyboard.press('Escape')
  await expect(letter).toBeFocused()
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=standalone-brand-menu-latin',
  )
  await page
    .getByRole('navigation', { name: 'Категории каталога' })
    .getByRole('button', { name: 'Материалы', exact: true })
    .click()
  await page
    .getByRole('navigation', { name: 'Категории каталога' })
    .getByRole('link', { name: 'Плитка', exact: true })
    .click()
  await expect(menu.getByRole('button')).toHaveText(['B'])
  await expect(
    page.locator('.catalog-brand-navigation [popover]:popover-open'),
  ).toHaveCount(0)
  await menu.getByRole('button', { name: 'B', exact: true }).click()
  await expect(menu.getByRole('link', { name: 'Beta' })).toBeVisible()
})
