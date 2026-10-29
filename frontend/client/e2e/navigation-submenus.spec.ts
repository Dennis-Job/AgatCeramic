import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=submenu')
})

test('query-address children identify the current group and only the matching child', async ({
  page,
  request,
}) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=submenu-max')
  await page.setViewportSize({ width: 1280, height: 900 })
  await page.goto('/catalog?section=16')
  const nav = page.getByRole('navigation', { name: 'Основная навигация' })
  const parent = nav.getByRole('button', { name: 'Последняя группа' })
  await expect(parent).toHaveClass(/is-current/)
  await parent.click()
  const group = page.locator(`#${await parent.getAttribute('aria-controls')}`)
  await expect(group.getByRole('link').last()).toHaveAttribute(
    'aria-current',
    'page',
  )
  await expect(group.getByRole('link').first()).not.toHaveAttribute(
    'aria-current',
  )
})

test('desktop parent only expands; children stay in SSR, navigate and mark the active section', async ({
  page,
  request,
}, testInfo) => {
  await page.setViewportSize({ width: 1280, height: 900 })
  const html = await (await request.get('/about')).text()
  expect(html).toContain('Весь каталог')
  const issues: string[] = []
  page.on('pageerror', (error) => issues.push(error.message))
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  await page.goto('/about')
  await expect(
    page.getByRole('contentinfo').getByRole('link', { name: 'Весь каталог' }),
  ).toHaveAttribute('href', '/catalog')
  const nav = page.getByRole('navigation', { name: 'Основная навигация' })
  const parent = nav.getByRole('button', { name: 'Каталог', exact: true })
  await expect(parent).toBeVisible({ timeout: 2000 })
  await expect(parent).toHaveAttribute('aria-expanded', 'false')
  await expect(parent).toHaveClass(/is-current/)
  await expect(nav.getByRole('link', { name: 'Весь каталог' })).toBeHidden()
  await parent.click()
  await expect(page).toHaveURL(/\/about$/)
  await expect(parent).toHaveAttribute('aria-expanded', 'true')
  const child = nav.getByRole('link', { name: 'О материалах' })
  await expect(child).toHaveAttribute('aria-current', 'page')
  await expect(nav.getByRole('link', { name: 'Весь каталог' })).toBeVisible()
  await page.screenshot({ path: testInfo.outputPath('desktop-open-1280.png') })
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  await page.keyboard.press('Tab')
  await expect(nav.getByRole('link', { name: 'Весь каталог' })).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(parent).toHaveAttribute('aria-expanded', 'false')
  await expect(parent).toBeFocused()
  await page.keyboard.press('Space')
  await nav.getByRole('link', { name: 'Магазины' }).click()
  await expect(page).toHaveURL(/\/contacts$/)
  await expect(parent).toHaveAttribute('aria-expanded', 'false')
  expect(issues).toEqual([])
})

test('desktop groups close each other, close outside and on search, and expose children to search without duplicates', async ({
  page,
}) => {
  await page.setViewportSize({ width: 1280, height: 900 })
  await page.goto('/about')
  const nav = page.getByRole('navigation', { name: 'Основная навигация' })
  const catalog = nav.getByRole('button', { name: 'Каталог', exact: true })
  const info = nav.getByRole('button', { name: 'Информация', exact: true })
  await catalog.click()
  await info.click()
  await expect(catalog).toHaveAttribute('aria-expanded', 'false')
  await expect(info).toHaveAttribute('aria-expanded', 'true')
  await page.getByRole('heading', { level: 1 }).click()
  await expect(info).toHaveAttribute('aria-expanded', 'false')
  await catalog.click()
  await page.getByRole('button', { name: 'Открыть поиск' }).click()
  await expect(catalog).toHaveAttribute('aria-expanded', 'false')
  const search = page.getByRole('dialog', {
    name: 'Поиск по разделам страницы',
  })
  await search.getByRole('searchbox').fill('каталог')
  await expect(search.getByRole('link')).toHaveText([
    /Весь каталог/,
    /О материалах/,
    /Магазины/,
  ])
  await search.getByRole('searchbox').fill('информация')
  await expect(search.getByRole('link')).toHaveText([
    /Контакты/,
    /Сотрудничество/,
  ])
  await search.getByRole('searchbox').fill('магазины')
  await expect(search.getByRole('link')).toHaveText([/Магазины/])
  await search.getByRole('searchbox').fill('контакты')
  await expect(search.getByRole('link')).toHaveCount(1)
  await search.getByRole('link').click()
  await expect(page).toHaveURL(/\/contacts$/)
  await expect(search).toHaveCount(0)
})

test('drawer groups work at all widths; Escape closes disclosure before modal and preserves focus and scroll lock', async ({
  page,
}, testInfo) => {
  await page.goto('/about')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    const trigger = page.getByRole('button', { name: 'Открыть меню' })
    if (width > 1024) {
      await expect(trigger).toHaveCount(0)
      continue
    }
    await trigger.click()
    const dialog = page.getByRole('dialog', { name: 'Меню сайта' })
    const parent = dialog.getByRole('button', { name: 'Каталог', exact: true })
    await expect(parent).toHaveAttribute('aria-expanded', 'false')
    await parent.focus()
    await page.keyboard.press('Enter')
    await expect(parent).toHaveAttribute('aria-expanded', 'true')
    await expect(
      dialog.getByRole('link', { name: 'Весь каталог' }),
    ).toBeVisible()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: testInfo.outputPath(`drawer-open-${width}.png`),
    })
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    await page.keyboard.press('Tab')
    await expect(
      dialog.getByRole('link', { name: 'Весь каталог' }),
    ).toBeFocused()
    await page.keyboard.press('Escape')
    await expect(dialog).toBeVisible()
    await expect(parent).toBeFocused()
    await expect(parent).toHaveAttribute('aria-expanded', 'false')
    await expect(page.locator('body')).toHaveClass(/has-overlay/)
    await page.keyboard.press('Escape')
    await expect(dialog).toHaveCount(0)
    await expect(trigger).toBeFocused()
  }
})

test('last group in oversized header escapes scroll clipping and remains usable in a short viewport', async ({
  page,
  request,
}, testInfo) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=submenu-max')
  await page.setViewportSize({ width: 1280, height: 360 })
  await page.goto('/about')
  const nav = page.getByRole('navigation', { name: 'Основная навигация' })
  const parent = nav.getByRole('button', { name: 'Последняя группа' })
  await parent.focus()
  await page.keyboard.press('Enter')
  const group = page.locator(`#${await parent.getAttribute('aria-controls')}`)
  await expect(group).toBeVisible()
  const bounds = (await group.boundingBox())!
  expect(bounds.x).toBeGreaterThanOrEqual(0)
  expect(bounds.x + bounds.width).toBeLessThanOrEqual(1280)
  expect(bounds.y).toBeGreaterThanOrEqual(0)
  expect(bounds.y + bounds.height).toBeLessThanOrEqual(360)
  expect(
    (await page.locator('.site-header').boundingBox())!.height,
  ).toBeLessThan(180)
  await group.getByRole('link').last().focus()
  await expect(group.getByRole('link').last()).toBeInViewport({ ratio: 1 })
  await page.screenshot({
    path: testInfo.outputPath('max-menu-short-viewport.png'),
  })
  await page.keyboard.press('Escape')
  await expect(parent).toBeFocused()
  await page.setViewportSize({ width: 768, height: 900 })
  await expect(group).toBeHidden()
})

test('unsafe submenu links and empty filtered groups never render; authenticated saved preview uses shared navigation', async ({
  page,
  context,
  request,
}, testInfo) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=submenu-unsafe')
  await page.setViewportSize({ width: 1280, height: 900 })
  await page.goto('/about')
  await page.getByRole('button', { name: 'Безопасный раздел' }).click()
  await expect(page.getByText('Опасный подпункт')).toHaveCount(0)
  await expect(page.getByText('С credentials')).toHaveCount(0)
  await expect(page.getByText('С обратным слешем')).toHaveCount(0)
  await expect(page.getByText('Пустая после фильтрации')).toHaveCount(0)
  await request.get('http://127.0.0.1:8015/__scenario?name=submenu')
  await context.addCookies([
    {
      name: 'preview_test_session',
      value: 'employee',
      url: 'http://127.0.0.1:8015',
      sameSite: 'Lax',
    },
  ])
  await page.goto('/preview/about')
  await expect(page.getByText('Черновая шапка')).toBeVisible()
  const nav = page.getByRole('navigation', { name: 'Навигация предпросмотра' })
  await nav.getByRole('button', { name: 'Каталог', exact: true }).click()
  await expect(nav.getByRole('link', { name: 'О материалах' })).toHaveAttribute(
    'aria-current',
    'page',
  )
  await expect(nav.locator('[popover]:popover-open')).toHaveCSS('opacity', '1')
  await page.screenshot({
    path: testInfo.outputPath('preview-submenu-1280.png'),
  })
})

test('normal motion drawer navigates through children and closed groups stay outside the focus cycle', async ({
  page,
}, testInfo) => {
  await page.emulateMedia({ reducedMotion: 'no-preference' })
  await page.setViewportSize({ width: 640, height: 900 })
  await page.goto('/about')
  await page.getByRole('button', { name: 'Открыть меню' }).click()
  const dialog = page.getByRole('dialog', { name: 'Меню сайта' })
  const close = dialog.getByRole('button', { name: 'Закрыть меню' })
  await close.focus()
  await page.keyboard.press('Shift+Tab')
  await expect(
    dialog.getByRole('link', { name: 'shop@example.test' }),
  ).toBeFocused()
  await page.keyboard.press('Tab')
  await expect(close).toBeFocused()
  const catalog = dialog.getByRole('button', { name: 'Каталог', exact: true })
  const info = dialog.getByRole('button', { name: 'Информация', exact: true })
  await catalog.click()
  await info.click()
  await expect(catalog).toHaveAttribute('aria-expanded', 'false')
  await expect(info).toHaveAttribute('aria-expanded', 'true')
  await dialog.getByRole('link', { name: 'Сотрудничество' }).click()
  await expect(page).toHaveURL(/\/cooperation$/)
  await expect(dialog).toHaveCount(0)
  await expect(page.locator('body')).not.toHaveClass(/has-overlay/)
  await page.setViewportSize({ width: 1280, height: 900 })
  const nav = page.getByRole('navigation', {
    name: 'Основная навигация',
    includeHidden: true,
  })
  const parent = nav.getByRole('button', {
    name: 'Информация',
    exact: true,
    includeHidden: true,
  })
  await parent.click()
  await expect(nav.getByRole('link', { name: 'Сотрудничество' })).toBeVisible()
  await page.setViewportSize({ width: 768, height: 900 })
  await expect(parent).toHaveAttribute('aria-expanded', 'false')
  await page.setViewportSize({ width: 1280, height: 900 })
  await parent.click()
  await page.screenshot({
    path: testInfo.outputPath('normal-motion-desktop.png'),
  })
})
