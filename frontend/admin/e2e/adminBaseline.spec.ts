import AxeBuilder from '@axe-core/playwright'
import { expect, test } from './fixtures'
import { mockAdminBaseline } from './adminBaselineApi'
import { createDeferredApiRequests } from './deferredApi'

const stateRoutes = [
  '/products',
  '/categories',
  '/brands',
  '/attribute-groups',
  '/attributes',
  '/employees',
  '/roles',
  '/permissions',
  '/audit-log',
  '/orders',
  '/contacts',
  '/media',
  '/content',
] as const
const stateApiPaths: Record<(typeof stateRoutes)[number], string> = {
  '/products': '/admin/products',
  '/categories': '/admin/categories/tree',
  '/brands': '/admin/brands',
  '/attribute-groups': '/admin/attribute-groups',
  '/attributes': '/admin/attributes',
  '/employees': '/admin/users',
  '/roles': '/admin/roles',
  '/permissions': '/admin/permissions',
  '/audit-log': '/admin/audit-logs',
  '/orders': '/admin/orders',
  '/contacts': '/admin/contact-requests',
  '/media': '/admin/media',
  '/content': '/admin/pages',
}

for (const path of stateRoutes) {
  test(`Admin baseline: ${path} loading state`, async ({ page }) => {
    const deferredRequests = createDeferredApiRequests()
    const request = deferredRequests.defer(stateApiPaths[path])
    await mockAdminBaseline(page, false, 'loading', deferredRequests)
    await page.goto(path, { waitUntil: 'commit' })
    await request.requested
    await expect(
      page.getByRole('status').filter({ hasText: 'Загрузка' }).first(),
    ).toBeVisible()
    await expect(page).toHaveScreenshot(`route-${path.slice(1)}-loading.png`, {
      fullPage: true,
      animations: 'disabled',
    })
    request.release()
  })
}

test('Admin baseline: /products forbidden state', async ({
  page,
  browserIssueGuard,
}) => {
  browserIssueGuard.allowApiError(403, '/api/v1/admin/products')
  await mockAdminBaseline(page, false, 'forbidden')
  await page.goto('/products', { waitUntil: 'networkidle' })
  await expect(page.getByRole('alert')).toContainText(
    'Недостаточно прав для просмотра ресурса.',
  )
  await expect(page).toHaveScreenshot('route-products-forbidden.png', {
    animations: 'disabled',
  })
})

test('Admin baseline: roles destructive confirmation dialog', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.goto('/roles')
  await page.getByRole('button', { name: 'Удалить роль Администратор' }).click()
  const dialog = page.getByRole('dialog', { name: 'Удалить роль?' })
  await expect(dialog).toBeVisible()
  await expect(dialog).toHaveScreenshot('roles-destructive-dialog.png', {
    animations: 'disabled',
  })
})

for (const path of stateRoutes) {
  for (const state of ['empty', 'error'] as const) {
    test(`Admin baseline: ${path} ${state} state`, async ({
      page,
      browserIssueGuard,
    }) => {
      if (state === 'error')
        browserIssueGuard.allowApiError(500, /^\/api\/v1\/admin\//)
      await mockAdminBaseline(page, false, state)
      await page.goto(path, { waitUntil: 'networkidle' })
      if (state === 'error')
        await expect(page.getByRole('alert')).toBeVisible({ timeout: 10_000 })
      await expect(page).toHaveScreenshot(
        `route-${path.slice(1)}-${state}.png`,
        { fullPage: true, animations: 'disabled' },
      )
    })
  }
}

const authenticatedRoutes = [
  ['/', 'Обзор магазина'],
  ['/profile', 'Мой профиль'],
  ['/products', 'Товары'],
  ['/categories', 'Категории'],
  ['/brands', 'Бренды'],
  ['/attribute-groups', 'Группы характеристик'],
  ['/attributes', 'Характеристики'],
  ['/employees', 'Сотрудники'],
  ['/roles', 'Роли'],
  ['/permissions', 'Права'],
  ['/audit-log', 'Журнал аудита'],
  ['/orders', 'Заказы'],
  ['/contacts', 'Обращения'],
  ['/media', 'Медиатека'],
  ['/content', 'Страницы'],
  ['/settings', 'Настройки сайта'],
] as const

for (const [path, heading] of authenticatedRoutes) {
  test(`Admin baseline: ${path}`, async ({ page }) => {
    await mockAdminBaseline(page)
    await page.goto(path)
    await expect(
      page.getByRole('heading', { level: 1, name: heading }),
    ).toBeVisible()
    await page.waitForLoadState('networkidle')
    const accessibility = await new AxeBuilder({ page }).analyze()
    const serious = accessibility.violations.filter((item) =>
      ['serious', 'critical'].includes(item.impact ?? ''),
    )
    expect(serious).toEqual([])
    await expect(page).toHaveScreenshot(
      `route-${path === '/' ? 'dashboard' : path.slice(1)}.png`,
      { fullPage: true, animations: 'disabled' },
    )
  })
}

test('Admin UI-kit states meet color contrast requirements', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.goto('/ui-kit')
  await expect(
    page.getByRole('heading', { level: 1, name: 'UI-kit' }),
  ).toBeVisible()
  await page.waitForLoadState('networkidle')
  await expect(page.locator('[data-ui-kit-section]')).toHaveCount(12)
  await expect(
    page.getByRole('heading', { level: 2, name: 'Пример auth-формы' }),
  ).toBeVisible()
  await expect(
    page.getByText('--admin-color-primary-500', { exact: true }),
  ).toBeVisible()
  await expect(
    page.getByText('--admin-focus-outline-offset', { exact: true }),
  ).toBeVisible()
  for (const name of [
    'Недоступна',
    'Вторичная недоступна',
    'Опасная недоступна',
    'Прозрачная недоступна',
    'Удаление недоступно',
  ]) {
    await expect(page.getByRole('button', { name, exact: true })).toBeDisabled()
  }
  const disabledCheckbox = page.getByRole('checkbox', {
    name: 'Недоступный выбранный флажок',
  })
  await expect(disabledCheckbox).toBeDisabled()
  await expect(disabledCheckbox).toBeChecked()
  await expect(
    disabledCheckbox.locator('..').locator('span').first(),
  ).toHaveClass(/text-gray-600/)
  const disabledRadio = page.getByRole('radio', {
    name: 'Недоступный',
    exact: true,
  })
  await expect(disabledRadio).toBeDisabled()
  await expect(disabledRadio).not.toBeChecked()
  await expect(disabledRadio.locator('..').locator('span').first()).toHaveClass(
    /text-transparent/,
  )
  const accessibility = await new AxeBuilder({ page }).analyze()
  expect(
    accessibility.violations.filter((item) =>
      ['serious', 'critical'].includes(item.impact ?? ''),
    ),
  ).toEqual([])
})

test('Admin UI-kit exposes interactive select and confirmation states', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.goto('/ui-kit')

  const teleportedSelect = page.getByRole('button', {
    name: 'Select с teleport menu',
    exact: true,
  })
  await teleportedSelect.click()
  const search = page.getByRole('searchbox', {
    name: 'Поиск: Select с teleport menu',
  })
  await expect(search).toBeFocused()
  await search.fill('несуществующий вариант')
  await expect(
    page.getByText('Ничего не найдено', { exact: true }),
  ).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(teleportedSelect).toBeFocused()

  await page.getByRole('button', { name: 'Confirm: error' }).click()
  await expect(
    page.getByRole('alert').filter({
      hasText: 'Не удалось выполнить демонстрационное действие.',
    }),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Отмена' }).click()

  await page.getByRole('button', { name: 'Confirm: busy' }).click()
  await expect(page.getByRole('button', { name: 'Удаление…' })).toBeDisabled()
  await expect(page.getByRole('button', { name: 'Отмена' })).toBeDisabled()
  await expect(page.getByRole('button', { name: 'Отмена' })).toBeEnabled({
    timeout: 2_000,
  })
  await page.getByRole('button', { name: 'Отмена' }).click()
})

test('Admin UI-kit field disabled contract remains accessible and usable at all supported widths', async ({
  page,
}) => {
  await page.clock.setFixedTime(new Date('2026-09-13T12:00:00Z'))
  await mockAdminBaseline(page)
  await page.goto('/ui-kit')
  await expect(
    page.getByRole('heading', { level: 1, name: 'UI-kit' }),
  ).toBeVisible()
  await page.waitForLoadState('networkidle')

  const enabledDate = page.getByRole('textbox', {
    name: 'Дата публикации',
    exact: true,
  })
  await enabledDate.focus()
  await expect(
    page.getByRole('dialog', { name: 'Дата публикации: выбор даты' }),
  ).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(
    page.getByRole('dialog', { name: 'Дата публикации: выбор даты' }),
  ).toHaveCount(0)
  await expect(enabledDate).toBeFocused()

  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    const disabledFields = page.locator('[data-ui-kit-disabled-fields]')
    await expect(disabledFields).toBeVisible()

    const disabledInput = disabledFields.getByRole('textbox', {
      name: 'Недоступный поиск товара',
    })
    const disabledSelect = disabledFields.getByRole('button', {
      name: 'Недоступная категория',
      exact: true,
    })
    const disabledDate = disabledFields.getByRole('textbox', {
      name: 'Недоступная дата публикации',
    })
    const inputClear = disabledFields.getByRole('button', {
      name: 'Очистить поле',
    })
    const selectClear = disabledFields.getByRole('button', {
      name: 'Очистить выбор: Недоступная категория',
    })
    const dateClear = disabledFields.getByRole('button', {
      name: 'Очистить дату',
    })

    for (const control of [
      disabledInput,
      disabledSelect,
      disabledDate,
      inputClear,
      selectClear,
      dateClear,
    ]) {
      await expect(control).toBeDisabled()
    }

    await inputClear.dispatchEvent('click')
    await selectClear.dispatchEvent('click')
    await dateClear.dispatchEvent('click')
    await expect(disabledInput).toHaveValue('Керамогранит')
    await expect(disabledSelect).toContainText('Расширенный')
    await expect(disabledDate).toHaveValue('13.09.2026')
    await disabledFields.evaluate((element) =>
      element.scrollIntoView({ block: 'center' }),
    )
    await expect
      .soft(disabledFields)
      .toHaveScreenshot(`ui-kit-disabled-fields-${width}.png`, {
        animations: 'disabled',
      })

    const accessibility = await new AxeBuilder({ page })
      .include('[data-ui-kit-disabled-fields]')
      .analyze()
    expect(
      accessibility.violations.filter((item) =>
        ['serious', 'critical'].includes(item.impact ?? ''),
      ),
    ).toEqual([])

    await enabledDate.blur()
    await enabledDate.focus()
    const dateDialog = page.getByRole('dialog', {
      name: 'Дата публикации: выбор даты',
    })
    await expect(dateDialog).toBeVisible()
    await expect
      .soft(dateDialog)
      .toHaveScreenshot(`ui-kit-date-picker-open-${width}.png`, {
        animations: 'disabled',
      })
    const openDateAccessibility = await new AxeBuilder({ page })
      .include('[role="dialog"]')
      .analyze()
    expect(
      openDateAccessibility.violations.filter((item) =>
        ['serious', 'critical'].includes(item.impact ?? ''),
      ),
    ).toEqual([])
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
      ),
    ).toBe(true)
    await page.keyboard.press('Escape')
    await expect(dateDialog).toHaveCount(0)
    await expect(enabledDate).toBeFocused()
  }
})

for (const [path, heading] of [
  ['/login', 'Вход'],
  ['/forgot-password', 'Восстановление пароля'],
  ['/reset-password', 'Задайте новый пароль'],
] as const) {
  test(`Admin baseline: guest ${path}`, async ({ page, browserIssueGuard }) => {
    browserIssueGuard.allowApiError(401, '/api/v1/admin/auth/me')
    await mockAdminBaseline(page, true)
    await page.goto(path)
    await expect(
      page.getByRole('heading', { level: 1, name: heading }),
    ).toBeVisible()
    await page.waitForLoadState('networkidle')
    const accessibility = await new AxeBuilder({ page }).analyze()
    expect(
      accessibility.violations.filter((item) =>
        ['serious', 'critical'].includes(item.impact ?? ''),
      ),
    ).toEqual([])
    await expect(page).toHaveScreenshot(`route-${path.slice(1)}.png`, {
      fullPage: true,
      animations: 'disabled',
    })
  })
}

for (const path of [
  ...authenticatedRoutes.map(([route]) => route),
  '/login',
  '/forgot-password',
  '/reset-password',
  '/ui-kit',
]) {
  test(`Admin baseline: ${path} remains usable at all supported widths`, async ({
    page,
    browserIssueGuard,
  }) => {
    const guest =
      path.startsWith('/login') ||
      path.startsWith('/forgot') ||
      path.startsWith('/reset')
    if (guest) browserIssueGuard.allowApiError(401, '/api/v1/admin/auth/me')
    await mockAdminBaseline(page, guest)
    for (const width of [320, 640, 768, 1024, 1280, 1440, 1920, 2560]) {
      await page.setViewportSize({ width, height: 800 })
      await page.goto(path)
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
      // UIKit intentionally demonstrates a permanent loading state.
      if (path !== '/ui-kit')
        await expect(
          page.getByRole('status').filter({ hasText: 'Загрузка' }),
        ).toHaveCount(0)
      await page.evaluate(async () => {
        await document.fonts.ready
        await new Promise<void>((resolve) =>
          requestAnimationFrame(() => requestAnimationFrame(() => resolve())),
        )
      })
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
        `${path} page overflow at ${width}px`,
      ).toBe(true)
      const accessibility = await new AxeBuilder({ page }).analyze()
      expect(
        accessibility.violations,
        `${path} accessibility at ${width}px`,
      ).toEqual([])
      await page.screenshot({
        path: `.tmp/a062-visual/${path === '/' ? 'dashboard' : path.slice(1)}-${width}.png`,
        animations: 'disabled',
      })
    }
  })
}
