import AxeBuilder from '@axe-core/playwright'
import { expect, test } from './fixtures'
import { mockAdminBaseline } from './adminBaselineApi'

const widths = [320, 640, 768, 1024, 1280, 1440, 1920, 2560]

test('header removes search and keeps its divider inside the common container', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.goto('/products')
  const header = page.getByRole('banner')
  await expect(header.locator('input')).toHaveCount(0)
  await expect(header.getByRole('button', { name: 'Выйти' })).toHaveCount(0)
  for (const width of widths) {
    await page.setViewportSize({ width, height: 900 })
    const geometry = await page
      .locator('.admin-header-inner')
      .evaluate((element) => {
        const style = getComputedStyle(element)
        const line = getComputedStyle(element, '::after')
        const rect = element.getBoundingClientRect()
        return {
          left: rect.left + parseFloat(style.paddingLeft),
          width:
            rect.width -
            parseFloat(style.paddingLeft) -
            parseFloat(style.paddingRight),
          lineHeight: line.height,
          lineWidth: line.width,
        }
      })
    const expectedWidth = Math.min(1280, width - (width < 640 ? 32 : 48))
    expect(geometry.left).toBe((width - expectedWidth) / 2)
    expect(geometry.width).toBe(expectedWidth)
    expect(geometry.lineWidth).toBe(`${expectedWidth}px`)
    expect(geometry.lineHeight).toBe('1px')
    await expect(header).toHaveCSS('border-bottom-width', '0px')
  }
})

test('desktop submenus open on hover, stay hoverable, use group-sized columns and dismiss without stealing input focus', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.setViewportSize({ width: 1920, height: 900 })
  await page.goto('/products')
  const search = page.getByRole('textbox', { name: 'Поиск', exact: true })
  await search.focus()
  const navigation = page.getByRole('navigation', {
    name: 'Основная навигация',
  })
  for (const [name, count] of [
    ['Товары', 3],
    ['Контент', 2],
    ['Управление', 3],
  ] as const) {
    const trigger = navigation.getByRole('button', { name, exact: true })
    await trigger.hover()
    const panel = page.locator('.admin-navigation-panel')
    await expect(panel).toBeVisible()
    await expect(search).toBeFocused()
    expect((await panel.boundingBox())!.width).toBe(
      count * 216 + (count + 1) * 24,
    )
    expect(
      await panel
        .locator('.admin-navigation-groups')
        .evaluate(
          (el) => getComputedStyle(el).gridTemplateColumns.split(' ').length,
        ),
    ).toBe(count)
    const link = panel.getByRole('link').first()
    await link.hover()
    await expect(panel).toBeVisible()
    await page.screenshot({
      path: `.tmp/header-popovers/submenu-${count}-${name}-1920.png`,
    })
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    await page.keyboard.press('Escape')
    await expect(panel).toBeHidden()
    await expect(search).toBeFocused()
    await trigger.hover()
    await expect(panel).toBeVisible()
    await page.mouse.move(5, 850)
    await expect(panel).toBeHidden()
    await expect(search).toBeFocused()
  }
})

test('account and notifications open on hover at every width, stay within the viewport and expose existing actions', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.goto('/products')
  for (const width of widths) {
    await page.setViewportSize({ width, height: 900 })
    await page.mouse.move(0, 800)
    for (const [label, id] of [
      ['Уведомления', '#admin-notifications-panel'],
      ['Меню пользователя', '#admin-user-panel'],
    ] as const) {
      const trigger = page
        .getByRole('banner')
        .getByRole('button', { name: label, exact: true })
      await trigger.hover()
      const panel = page.locator(id)
      await expect(panel).toBeVisible()
      await panel.hover()
      const box = (await panel.boundingBox())!
      expect(box.x).toBeGreaterThanOrEqual(16)
      expect(box.x + box.width).toBeLessThanOrEqual(width - 16)
      expect(box.width).toBe(Math.min(320, width - 32))
      if (label === 'Меню пользователя') {
        await expect(panel).toContainText('Тестовый администратор')
        await expect(panel).toContainText('admin@example.test')
        await expect(
          panel.getByRole('link', { name: 'Мой профиль' }),
        ).toHaveAttribute('href', '/profile')
        await expect(
          panel.getByRole('link', { name: 'Настройки' }),
        ).toHaveAttribute('href', '/settings')
      } else
        await expect(panel.getByRole('status')).toContainText(
          'Новых уведомлений нет',
        )
      await page.screenshot({
        path: `.tmp/header-popovers/${id.slice(1)}-${width}.png`,
      })
      expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
      await page.mouse.move(0, 800)
      await expect(panel).toBeHidden()
    }
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
  }
})

test('keyboard disclosures support arrows, Escape, focus return and Tab dismissal; only one header popup is open', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.goto('/products')
  const user = page
    .getByRole('banner')
    .getByRole('button', { name: 'Меню пользователя', exact: true })
  await user.focus()
  await page.keyboard.press('ArrowDown')
  const panel = page.locator('#admin-user-panel')
  await expect(panel.getByRole('link', { name: 'Мой профиль' })).toBeFocused()
  await page.keyboard.press('End')
  await expect(panel.getByRole('button', { name: 'Выйти' })).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(panel).toBeHidden()
  await expect(user).toBeFocused()
  await page.keyboard.press('Enter')
  await expect(panel).toBeVisible()
  const notifications = page
    .getByRole('banner')
    .getByRole('button', { name: 'Уведомления', exact: true })
  await notifications.hover()
  await expect(panel).toBeHidden()
  await expect(page.locator('#admin-notifications-panel')).toBeVisible()
  await notifications.focus()
  await page.keyboard.press('ArrowDown')
  await expect(page.locator('#admin-notifications-panel')).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(notifications).toBeFocused()
  await user.focus()
  await page.keyboard.press('Enter')
  await page.keyboard.press('End')
  await page.keyboard.press('Tab')
  await expect(panel).toBeHidden()
  await expect(
    page.getByRole('link', { name: 'Главная', exact: true }),
  ).toBeFocused()
})

test('reduced permissions produce a single compact submenu column and hide settings without inventing roles', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.route('**/api/v1/admin/auth/me', (route) =>
    route.fulfill({
      json: {
        data: {
          id: 1,
          name: 'Сотрудник с длинным русским именем '.repeat(4),
          email: `${'long-email.'.repeat(10)}@example.test`,
          status: 'active',
          permissions: [],
        },
      },
    }),
  )
  await page.setViewportSize({ width: 1920, height: 900 })
  await page.goto('/')
  await page.getByRole('button', { name: 'Управление', exact: true }).hover()
  expect(
    (await page.locator('.admin-navigation-panel').boundingBox())!.width,
  ).toBe(264)
  await page
    .getByRole('button', { name: 'Меню пользователя', exact: true })
    .hover()
  const panel = page.locator('#admin-user-panel')
  await expect(panel.getByRole('link', { name: 'Настройки' })).toHaveCount(0)
  await expect(panel).not.toContainText('Администратор')
  expect(await panel.evaluate((el) => el.scrollWidth > el.clientWidth)).toBe(
    false,
  )
  await page.screenshot({
    path: '.tmp/header-popovers/user-long-data-1920.png',
  })
})

test('shared popover flips above a trigger near the bottom of a short viewport and keeps keyboard dismissal usable', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.setViewportSize({ width: 320, height: 400 })
  await page.goto('/ui-kit')
  const trigger = page.getByRole('button', {
    name: 'Всплывающее окно',
    exact: true,
  })
  await trigger.focus()
  await trigger.evaluate((el) =>
    window.scrollBy(0, el.getBoundingClientRect().bottom - innerHeight + 24),
  )
  const anchor = (await trigger.boundingBox())!
  await page.keyboard.press('ArrowDown')
  const panel = page.getByRole('region', { name: 'Пример всплывающего окна' })
  await expect(panel).toBeFocused()
  const box = (await panel.boundingBox())!
  const header = (await page.getByRole('banner').boundingBox())!
  expect(box.y).toBeGreaterThanOrEqual(header.y + header.height + 8)
  expect(box.y + box.height).toBeLessThanOrEqual(anchor.y - 8)
  expect(box.height).toBeGreaterThan(100)
  expect(box.x).toBeGreaterThanOrEqual(16)
  expect(box.x + box.width).toBeLessThanOrEqual(304)
  await page.screenshot({
    path: '.tmp/header-popovers/ui-popover-flip-320.png',
  })
  await page.keyboard.press('Escape')
  await expect(panel).toBeHidden()
  await expect(trigger).toBeFocused()
})

test('account links navigate and logout reports errors, prevents duplicate requests while busy, and ends the session on retry', async ({
  page,
  browserIssueGuard,
}) => {
  await mockAdminBaseline(page)
  let calls = 0
  let release: (() => void) | undefined
  const pending = new Promise<void>((resolve) => {
    release = resolve
  })
  browserIssueGuard.allowApiError(500, '/api/v1/admin/auth/logout')
  await page.route('**/api/v1/admin/auth/logout', async (route) => {
    calls++
    if (calls === 1)
      return route.fulfill({
        status: 500,
        json: { error: { message: 'Не удалось выйти. Попробуйте ещё раз.' } },
      })
    await pending
    await route.fulfill({ status: 204 })
  })
  await page.goto('/products')
  const user = page.getByRole('button', {
    name: 'Меню пользователя',
    exact: true,
  })
  await user.hover()
  await page.getByRole('link', { name: 'Мой профиль', exact: true }).click()
  await expect(page).toHaveURL('/profile')
  await expect(page.locator('#admin-user-panel')).toBeHidden()
  await user.hover()
  await page
    .getByRole('link', { name: 'Настройки', exact: true })
    .last()
    .click()
  await expect(page).toHaveURL('/settings')
  await user.hover()
  const panel = page.locator('#admin-user-panel')
  const logout = panel.getByRole('button', { name: 'Выйти', exact: true })
  await logout.click()
  await expect(panel.getByRole('alert')).toContainText('Не удалось выйти')
  await expect(page).toHaveURL('/settings')
  await page.screenshot({ path: '.tmp/header-popovers/logout-error.png' })
  await logout.click()
  await expect(logout).toBeDisabled()
  await expect.poll(() => calls).toBe(2)
  await page.screenshot({ path: '.tmp/header-popovers/logout-busy.png' })
  release!()
  await expect(page).toHaveURL('/login')
  await expect(page.getByRole('banner')).toHaveCount(0)
})

test.describe('touch', () => {
  test.use({
    hasTouch: true,
    isMobile: true,
    viewport: { width: 320, height: 800 },
  })
  test('icons use tap, outside tap and compact navigation retain usable touch controls', async ({
    page,
  }) => {
    await mockAdminBaseline(page)
    await page.goto('/products')
    const user = page
      .getByRole('banner')
      .getByRole('button', { name: 'Меню пользователя', exact: true })
    await user.tap()
    await expect(page.locator('#admin-user-panel')).toBeVisible()
    await user.tap()
    await expect(page.locator('#admin-user-panel')).toBeHidden()
    await page.getByRole('button', { name: 'Уведомления', exact: true }).tap()
    await expect(page.locator('#admin-notifications-panel')).toBeVisible()
    await page.touchscreen.tap(4, 600)
    await expect(page.locator('#admin-notifications-panel')).toBeHidden()
    await page.getByRole('button', { name: 'Открыть меню' }).tap()
    await expect(
      page.getByRole('dialog', { name: 'Разделы панели' }),
    ).toBeVisible()
    await page.getByRole('button', { name: 'Закрыть меню' }).tap()
    await expect(
      page.getByRole('button', { name: 'Открыть меню' }),
    ).toBeFocused()
  })
})
