import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=standalone-default')
})

test('parents contain their categories and links retain catalog filtering', async ({
  page,
}) => {
  await page.goto(
    '/catalog?category=tile&price_from=100&page=2&collection=Stone',
  )
  const nav = page.getByRole('navigation', { name: 'Категории каталога' })
  const parent = nav.getByRole('button', { name: 'Сантехника', exact: true })
  await expect(parent).toHaveAttribute('aria-expanded', 'false')
  await expect(
    nav.getByRole('link', { name: 'Ванны', exact: true }),
  ).toBeHidden()
  await parent.click()
  await expect(parent).toHaveAttribute('aria-expanded', 'true')
  for (const name of [
    'Ванны',
    'Душевые кабины',
    'Раковины',
    'Смесители',
    'Унитазы',
  ])
    await expect(nav.getByRole('link', { name, exact: true })).toBeVisible()
  await nav.getByRole('link', { name: 'Ванны', exact: true }).click()
  await expect(page).toHaveURL(/category=bath/)
  expect(new URL(page.url()).searchParams.get('price_from')).toBe('100')
  expect(page.url()).not.toContain('page=')
  expect(page.url()).not.toContain('collection=')
  await expect(parent).toHaveAttribute('aria-expanded', 'false')
  await parent.click()
  await nav
    .getByRole('link', { name: 'Все товары категории', exact: true })
    .click()
  await expect(page).toHaveURL(/category=plumbing/)
})

test('dropdown keyboard, dismissal and one open group', async ({ page }) => {
  await page.goto('/catalog')
  const nav = page.getByRole('navigation', { name: 'Категории каталога' })
  const parent = nav.getByRole('button', { name: 'Сантехника', exact: true })
  await parent.focus()
  await page.keyboard.press('Enter')
  await expect(parent).toBeFocused()
  await page.keyboard.press('Tab')
  await expect(
    nav.getByRole('link', { name: 'Все товары категории', exact: true }),
  ).toBeFocused()
  await page.keyboard.press('Tab')
  await expect(
    nav.getByRole('link', { name: 'Ванны', exact: true }),
  ).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(parent).toBeFocused()
  await expect(parent).toHaveAttribute('aria-expanded', 'false')
  await page.keyboard.press('Space')
  await parent.click()
  await expect(parent).toHaveAttribute('aria-expanded', 'false')
  await parent.click()
  await nav.getByRole('button', { name: 'Материалы', exact: true }).click()
  await expect(parent).toHaveAttribute('aria-expanded', 'false')
  await expect(nav.locator(':popover-open')).toHaveCount(1)
  await page
    .getByRole('heading', { name: 'Каталог материалов', exact: true })
    .click()
  await expect(nav.locator(':popover-open')).toHaveCount(0)
  await parent.click()
  await page.getByLabel('Сортировка').focus()
  await expect(parent).toHaveAttribute('aria-expanded', 'false')
})

test('dynamic nested categories remain accessible with short sticky viewport and resize', async ({
  page,
  request,
}, testInfo) => {
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=standalone-category-dropdowns',
  )
  const html = await (await request.get('/catalog')).text()
  expect(html).toContain('category=corner-baths')
  expect(html).toContain('category=new-category-29')
  const issues: string[] = []
  page.on('pageerror', (error) => issues.push(error.message))
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  await page.setViewportSize({ width: 1280, height: 650 })
  await page.goto('/catalog?category=corner-baths')
  const nav = page.getByRole('navigation', { name: 'Категории каталога' })
  const parent = nav.getByRole('button', { name: 'Сантехника', exact: true })
  // Use Playwright's settled element scroll after initial router restoration.
  await page.locator('.product-card').first().scrollIntoViewIfNeeded()
  await expect(page.locator('.catalog-category-navigation')).toHaveClass(
    /catalog-category-navigation--pinned/,
  )
  await parent.click()
  const panel = nav.locator(':popover-open')
  const child = panel.getByRole('link', { name: 'Угловые ванны', exact: true })
  await expect(child).toHaveAttribute('aria-current', 'page')
  expect(await panel.evaluate((el) => el.scrollHeight > el.clientHeight)).toBe(
    true,
  )
  const last = panel.getByRole('link', {
    name: 'Новая категория 29',
    exact: true,
  })
  await last.focus()
  await expect(last).toBeInViewport({ ratio: 1 })
  const bounds = (await panel.boundingBox())!
  expect(bounds.y).toBeGreaterThanOrEqual(
    (await page.locator('.site-header').boundingBox())!.height - 1,
  )
  expect(bounds.y + bounds.height).toBeLessThanOrEqual(650 - 11)
  expect(await panel.evaluate((el) => el.scrollWidth <= el.clientWidth)).toBe(
    true,
  )
  await child.focus()
  await page.screenshot({
    path: testInfo.outputPath('nested-sticky-dropdown.png'),
  })
  const violations = (
    await new AxeBuilder({ page }).include('.catalog-categories').analyze()
  ).violations
  expect(violations).toEqual([])
  await page.setViewportSize({ width: 768, height: 900 })
  await expect(page.locator(':popover-open')).toHaveCount(0)
  await page.setViewportSize({ width: 1280, height: 900 })
  await parent.click()
  await nav
    .getByRole('link', { name: 'Новая категория 29', exact: true })
    .click()
  await expect(page).toHaveURL(/category=new-category-29/)
  expect(issues).toEqual([])
})

test('dropdown layout at desktop breakpoints and mobile tree retain draft behavior', async ({
  page,
}, testInfo) => {
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/catalog')
    if (width >= 1024) {
      const nav = page.getByRole('navigation', { name: 'Категории каталога' })
      await nav.getByRole('button', { name: 'Сантехника', exact: true }).click()
      await expect(
        nav.getByRole('link', { name: 'Унитазы', exact: true }),
      ).toBeInViewport()
    } else {
      await page.getByRole('button', { name: 'Фильтры', exact: true }).click()
      const drawer = page.getByRole('dialog', { name: 'Фильтры каталога' })
      await drawer
        .getByRole('button', { name: 'Сантехника', exact: true })
        .click()
      await drawer.getByRole('button', { name: 'Ванны', exact: true }).click()
      expect(page.url()).not.toContain('category=bath')
      await page.screenshot({
        path: testInfo.outputPath(`categories-${width}.png`),
      })
      await drawer
        .getByRole('button', { name: 'Применить', exact: true })
        .click()
      await expect(page).toHaveURL(/category=bath/)
    }
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    if (width >= 1024)
      await page.screenshot({
        path: testInfo.outputPath(`categories-${width}.png`),
      })
  }
})

test('normal motion closes inert links immediately and can reopen', async ({
  page,
}) => {
  await page.emulateMedia({ reducedMotion: 'no-preference' })
  await page.goto('/catalog')
  const nav = page.getByRole('navigation', { name: 'Категории каталога' })
  const parent = nav.getByRole('button', { name: 'Сантехника', exact: true })
  await parent.click()
  const panel = nav.locator('.ui-navigation-dropdown__panel').last()
  await expect(panel).toHaveCSS('opacity', '1')
  await page.keyboard.press('Escape')
  await expect(panel).toHaveAttribute('inert', '')
  await expect(panel).toHaveAttribute('aria-hidden', 'true')
  await parent.click()
  await expect(panel).toHaveCSS('opacity', '1')
  await expect(
    nav.getByRole('link', { name: 'Ванны', exact: true }),
  ).toBeVisible()
})
