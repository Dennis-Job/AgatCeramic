import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from './fixtures'
import { mockCatalogApi } from './catalogApi'
import type { SiteAppearance } from '../src/features/appearance/types/appearance.types'

const allPermissions = [
  'catalog.manage',
  'imports.manage',
  'orders.view',
  'contacts.view',
  'content.manage',
  'media.manage',
  'settings.manage',
  'admin-users.view',
  'roles.view',
  'permissions.view',
  'audit-log.view',
]
const destinations: Record<string, string> = {
  Главная: '/',
  Заказы: '/orders',
  Обращения: '/contacts',
  Настройки: '/settings',
  'Список товаров': '/products',
  'Добавить массово товары': '/products/import',
  'Цены и статусы': '/products/price-status',
  'Объединить товары': '/products/combine',
  Категории: '/categories',
  Бренды: '/brands',
  'Группы характеристик': '/attribute-groups',
  Характеристики: '/attributes',
  Страницы: '/content',
  'Общее оформление': '/content?section=appearance',
  Магазины: '/content?section=stores',
  Медиатека: '/media',
  Сотрудники: '/employees',
  Роли: '/roles',
  Права: '/permissions',
  'Журнал аудита': '/audit-log',
  'UI-kit': '/ui-kit',
}

async function mockNavigation(page: Page, permissions = allPermissions) {
  await mockCatalogApi(page)
  const mutations: string[] = []
  const appearance: SiteAppearance = {
    has_unpublished_changes: false,
    header: {
      topbar_left: 'Керамическая плитка и керамогранит',
      topbar_right: 'Помогаем подобрать материалы',
      logo_media_id: null,
      logo_alt: 'AgatCeramic',
      logo_url: null,
      navigation: [],
    },
    footer: {
      tagline: 'Материалы для дома',
      explore_links: [],
      message: {
        eyebrow: 'AgatCeramic',
        text: 'Подбираем материалы для вашего дома',
        link_label: 'Каталог',
        link_url: '/catalog',
      },
      bottom_left: '© 2026 AgatCeramic',
      bottom_right: 'Керамические материалы',
    },
  }
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1', '')
    if (route.request().method() !== 'GET') mutations.push(path)
    if (path === '/admin/auth/me')
      return route.fulfill({
        json: {
          data: {
            id: 1,
            name: 'Тестовый сотрудник',
            email: 'navigation@example.test',
            status: 'active',
            permissions,
          },
        },
      })
    if (path === '/admin/site-appearance')
      return route.fulfill({ json: { data: appearance } })
    if (['/admin/pages', '/admin/stores'].includes(path))
      return route.fulfill({
        json: {
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
        },
      })
    return route.fallback()
  })
  return { mutations }
}

for (const width of [320, 640, 768, 1023, 1024, 1280, 1440, 1920, 2560]) {
  test(`navigation exposes every permitted destination without overflow at ${width}px`, async ({
    page,
  }) => {
    await page.setViewportSize({ width, height: 720 })
    await mockNavigation(page)
    await page.goto('/products')
    await expect(page.locator('#admin-sidebar')).toHaveCount(0)
    const panel = page.locator(
      '.admin-navigation-panel, #admin-navigation-panel',
    )
    const expectedGroups = [
      {
        label: 'Товары',
        links: [
          'Список товаров',
          'Добавить массово товары',
          'Цены и статусы',
          'Объединить товары',
          'Категории',
          'Бренды',
          'Группы характеристик',
          'Характеристики',
        ],
      },
      {
        label: 'Контент',
        links: ['Страницы', 'Общее оформление', 'Магазины', 'Медиатека'],
      },
      {
        label: 'Управление',
        links: ['Сотрудники', 'Роли', 'Права', 'Журнал аудита', 'UI-kit'],
      },
    ]
    const compact = width < 1024
    if (compact)
      await page.getByRole('button', { name: 'Открыть меню' }).click()
    const navigation = page.getByRole('navigation', {
      name: 'Основная навигация',
    })
    for (const label of ['Главная', 'Заказы', 'Обращения', 'Настройки']) {
      await expect(
        navigation.getByRole('link', { name: label, exact: true }),
      ).toBeVisible()
      await expect(
        navigation.getByRole('link', { name: label, exact: true }),
      ).toHaveAttribute('href', destinations[label])
    }
    await expect(navigation.locator('a svg')).toHaveCount(0)
    for (const group of expectedGroups) {
      if (!compact)
        await navigation
          .getByRole('button', { name: group.label, exact: true })
          .click()
      await expect(panel).toBeVisible()
      for (const label of group.links) {
        await expect(
          panel.getByRole('link', { name: label, exact: true }),
        ).toBeVisible()
        await expect(
          panel.getByRole('link', { name: label, exact: true }),
        ).toHaveAttribute('href', destinations[label])
      }
      await expect(panel.locator('a svg')).toHaveCount(0)
      const panelBox = await panel.boundingBox()
      expect(panelBox).not.toBeNull()
      expect(panelBox!.x).toBeGreaterThanOrEqual(0)
      expect(panelBox!.x + panelBox!.width).toBeLessThanOrEqual(width)
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
      ).toBe(true)
      if (!compact) await page.keyboard.press('Escape')
    }
    if (compact) {
      const close = page.getByRole('button', { name: 'Закрыть меню' })
      await close.click()
      await expect(panel).toBeHidden()
      await expect(
        page.getByRole('button', { name: 'Открыть меню' }),
      ).toBeFocused()
    }
  })
}

for (const profile of [
  { name: 'no permissions', permissions: [], content: false, media: false },
  {
    name: 'content editor',
    permissions: ['content.manage'],
    content: true,
    media: false,
  },
  {
    name: 'media editor',
    permissions: ['media.manage'],
    content: false,
    media: true,
  },
  {
    name: 'catalog editor',
    permissions: ['catalog.manage'],
    content: false,
    media: false,
  },
]) {
  for (const width of [320, 1280]) {
    test(`${profile.name} sees only permitted navigation at ${width}px`, async ({
      page,
    }) => {
      await page.setViewportSize({ width, height: 720 })
      await mockNavigation(page, profile.permissions)
      await page.goto('/')
      if (width < 1024)
        await page.getByRole('button', { name: 'Открыть меню' }).click()
      const navigation = page.getByRole('navigation', {
        name: 'Основная навигация',
      })
      const panel = page.locator(
        '.admin-navigation-panel, #admin-navigation-panel',
      )
      if (width >= 1024) {
        if (profile.content || profile.media)
          await navigation
            .getByRole('button', { name: 'Контент', exact: true })
            .click()
        else
          await expect(
            navigation.getByRole('button', { name: 'Контент', exact: true }),
          ).toHaveCount(0)
        await expect(
          navigation.getByRole('button', { name: 'Товары', exact: true }),
        ).toHaveCount(profile.permissions.includes('catalog.manage') ? 1 : 0)
      }
      await expect(
        panel.getByRole('link', { name: 'Медиатека', exact: true }),
      ).toHaveCount(profile.media ? 1 : 0)
      for (const label of ['Страницы', 'Общее оформление', 'Магазины'])
        await expect(
          panel.getByRole('link', { name: label, exact: true }),
        ).toHaveCount(profile.content ? 1 : 0)
      for (const label of [
        'Заказы',
        'Обращения',
        'Настройки',
        'Сотрудники',
        'Роли',
        'Права',
        'Журнал аудита',
      ]) {
        await expect(
          navigation.getByRole('link', { name: label, exact: true }),
        ).toHaveCount(0)
        await expect(
          panel.getByRole('link', { name: label, exact: true }),
        ).toHaveCount(0)
      }
      // Media access must not expose the content editor through a grouped menu.
      if (profile.media && !profile.content) {
        await panel.getByRole('link', { name: 'Медиатека' }).click()
        await expect(page).toHaveURL('/media')
        await page.goto('/content')
        await expect(page).toHaveURL('/')
      }
      if (width >= 1024) {
        await navigation
          .getByRole('button', { name: 'Управление', exact: true })
          .click()
        await expect(panel.getByRole('link')).toHaveText(['UI-kit'])
      } else if (!profile.media) {
        await expect(
          panel.getByRole('link', { name: 'Список товаров' }),
        ).toHaveCount(profile.permissions.includes('catalog.manage') ? 1 : 0)
      }
    })
  }
}

test('content navigation marks only the active query section and passes accessibility checks while open', async ({
  page,
}) => {
  await page.setViewportSize({ width: 1280, height: 720 })
  await mockNavigation(page)
  const panel = page.locator('.admin-navigation-panel, #admin-navigation-panel')
  for (const section of [
    { path: '/content', label: 'Страницы' },
    { path: '/content?section=appearance', label: 'Общее оформление' },
    { path: '/content?section=stores', label: 'Магазины' },
    { path: '/content?section=pages', label: 'Страницы' },
  ]) {
    await page.goto(section.path)
    await page
      .getByRole('navigation', { name: 'Основная навигация' })
      .getByRole('button', { name: 'Контент', exact: true })
      .click()
    await expect(panel.locator('a[aria-current="page"]')).toHaveCount(1)
    await expect(
      panel.getByRole('link', { name: section.label, exact: true }),
    ).toHaveAttribute('aria-current', 'page')
    const results = await new AxeBuilder({ page }).analyze()
    expect(
      results.violations.filter((violation) =>
        ['serious', 'critical'].includes(violation.impact ?? ''),
      ),
    ).toEqual([])
  }
})

test('navigation respects cancellation and acceptance of unsaved content edits', async ({
  page,
}) => {
  await page.setViewportSize({ width: 1280, height: 720 })
  const api = await mockNavigation(page)
  await page.goto('/content?section=appearance')
  await page.getByLabel('Верхняя строка слева').fill('Несохранённая шапка')
  const content = page
    .getByRole('navigation', { name: 'Основная навигация' })
    .getByRole('button', { name: 'Контент', exact: true })
  const panel = page.locator('.admin-navigation-panel, #admin-navigation-panel')
  await content.click()
  page.once('dialog', (dialog) => dialog.dismiss())
  await panel.getByRole('link', { name: 'Магазины', exact: true }).click()
  await expect(page).toHaveURL('/content?section=appearance')
  await expect(page.getByLabel('Верхняя строка слева')).toHaveValue(
    'Несохранённая шапка',
  )
  if (!(await panel.isVisible())) await content.click()
  await expect(
    panel.getByRole('link', { name: 'Общее оформление' }),
  ).toHaveAttribute('aria-current', 'page')
  await expect(
    panel.getByRole('link', { name: 'Магазины', exact: true }),
  ).not.toHaveAttribute('aria-current', 'page')
  page.once('dialog', (dialog) => dialog.accept())
  await panel.getByRole('link', { name: 'Магазины', exact: true }).click()
  await expect(page).toHaveURL('/content?section=stores')
  await expect(panel).toBeHidden()
  expect(api.mutations).toEqual([])
})

test('compact navigation passes axe and ignores a drag from its panel onto the backdrop', async ({
  page,
}) => {
  await page.setViewportSize({ width: 320, height: 720 })
  await mockNavigation(page)
  await page.goto('/products')
  await page.getByRole('button', { name: 'Открыть меню' }).click()
  const panel = page.locator('.admin-navigation-panel, #admin-navigation-panel')
  await expect(panel).toBeVisible()
  const results = await new AxeBuilder({ page }).analyze()
  expect(
    results.violations.filter((violation) =>
      ['serious', 'critical'].includes(violation.impact ?? ''),
    ),
  ).toEqual([])
  const box = await panel.boundingBox()
  await page.mouse.move(box!.x + 20, box!.y + 20)
  await page.mouse.down()
  await page.mouse.move(315, 112)
  await page.mouse.up()
  await expect(panel).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(page.getByRole('button', { name: 'Открыть меню' })).toBeFocused()
})
