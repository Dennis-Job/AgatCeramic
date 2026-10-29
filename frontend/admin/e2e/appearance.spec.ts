import AxeBuilder from './approvedAccessibility'
import { expect, test, type Page } from './fixtures'
import { createDeferredApiRequests } from './deferredApi'
import type { SiteAppearance } from '../src/features/appearance/types/appearance.types'

test.use({ reducedMotion: 'reduce' })

test('menu text saves independently, reloads, publishes and can be cleared', async ({
  page,
}, testInfo) => {
  const api = await mockWorkspace(page)
  await page.goto('/content?section=appearance&panel=header')
  const card = page.getByRole('region', { name: 'Боковое меню', exact: true })
  const note = card.getByLabel('Текст под контактами', { exact: true })
  await expect(note).toHaveValue(appearanceFixture().header.menu_note!)
  await expect(card).toContainText(
    'Телефон и email берутся из «Настроек сайта»',
  )
  await note.fill('Ежедневно 10:00–21:00\nПоможем с выбором')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await note.scrollIntoViewIfNeeded()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: testInfo.outputPath(`menu-editor-${width}.png`),
    })
  }
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  await page.getByRole('button', { name: 'Сохранить черновик раздела' }).click()
  await expect(
    page.getByText(
      'Черновик раздела сохранён. Опубликуйте оформление, чтобы обновить сайт.',
    ),
  ).toBeVisible()
  expect(api.patches[0]).toHaveProperty(
    'header.menu_note',
    'Ежедневно 10:00–21:00\nПоможем с выбором',
  )
  expect(api.published().header.menu_note).toBe(
    appearanceFixture().header.menu_note,
  )
  await page.reload()
  await expect(note).toHaveValue('Ежедневно 10:00–21:00\nПоможем с выбором')
  await page.getByRole('button', { name: 'Опубликовать оформление' }).click()
  await expect(page.getByText('Общее оформление опубликовано.')).toBeVisible()
  expect(api.published().header.menu_note).toContain('Поможем с выбором')
  await page.getByRole('button', { name: 'Закрыть уведомление' }).click()
  await note.fill('')
  await page.getByRole('button', { name: 'Сохранить черновик раздела' }).click()
  await expect(
    page.getByText(
      'Черновик раздела сохранён. Опубликуйте оформление, чтобы обновить сайт.',
    ),
  ).toBeVisible()
  await page.reload()
  await expect(note).toHaveValue('')
})

test('footer links are independent, editable and published with social links and bottom URL', async ({
  page,
}, testInfo) => {
  const api = await mockWorkspace(page)
  await page.goto('/content?section=appearance&panel=footer')
  const navigation = page.getByRole('region', {
    name: 'Навигация',
    exact: true,
  })
  const social = page.getByRole('region', {
    name: 'Мы в соцсетях',
    exact: true,
  })
  await navigation.getByRole('button', { name: 'Добавить ссылку' }).click()
  const row = navigation.getByRole('listitem').last()
  await row.getByLabel('Название', { exact: true }).fill('Доставка')
  await row.getByLabel('Ссылка', { exact: true }).fill('/about#delivery')
  await navigation.getByRole('button', { name: 'Поднять ссылку 3' }).click()
  await navigation.getByRole('button', { name: 'Удалить ссылку 1' }).click()
  await social.getByRole('button', { name: 'Добавить ссылку' }).click()
  await social.getByLabel('Название', { exact: true }).fill('Telegram')
  await social.getByLabel('Ссылка', { exact: true }).fill('javascript:alert(1)')
  await page
    .getByLabel('Справа — текст', { exact: true })
    .fill('Политика конфиденциальности')
  await page
    .getByLabel('Справа — ссылка', { exact: true })
    .fill('/about#privacy')
  await page.getByRole('button', { name: 'Сохранить черновик раздела' }).click()
  await expect(page.getByRole('alert')).toContainText('Мы в соцсетях')
  expect(api.patches).toHaveLength(0)
  await expect(social.getByLabel('Название', { exact: true })).toHaveValue(
    'Telegram',
  )
  await page.getByRole('button', { name: 'Закрыть уведомление' }).click()
  await page.mouse.move(0, 0)
  await social
    .getByLabel('Ссылка', { exact: true })
    .fill('https://t.me/agat_test')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await social.scrollIntoViewIfNeeded()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: testInfo.outputPath(`footer-admin-${width}.png`),
    })
  }
  await page.keyboard.press('Escape')
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  await page.getByRole('button', { name: 'Сохранить черновик раздела' }).click()
  await expect(
    page.getByText(
      'Черновик раздела сохранён. Опубликуйте оформление, чтобы обновить сайт.',
    ),
  ).toBeVisible()
  expect(api.patches[0]).toMatchObject({
    footer: {
      navigation_links: [
        { label: 'Доставка', to: '/about#delivery' },
        { label: 'Контакты наших магазинов', to: '/contacts' },
      ],
      social_links: [{ label: 'Telegram', to: 'https://t.me/agat_test' }],
      bottom_right: 'Политика конфиденциальности',
      bottom_right_url: '/about#privacy',
    },
  })
  expect(api.published().footer.social_links).toBeUndefined()
  await page.reload()
  await expect(social.getByLabel('Название', { exact: true })).toHaveValue(
    'Telegram',
  )
  await page.getByRole('button', { name: 'Опубликовать оформление' }).click()
  await expect(page.getByText('Общее оформление опубликовано.')).toBeVisible()
  expect(api.published().footer.bottom_right_url).toBe('/about#privacy')
  expect(api.published().header.navigation).toEqual(
    appearanceFixture().header.navigation,
  )
})

const appearancePath = '/admin/site-appearance'
const emptyMeta = { current_page: 1, last_page: 1, per_page: 25, total: 0 }

function appearanceFixture(): SiteAppearance {
  return {
    has_unpublished_changes: false,
    header: {
      topbar_left: 'Керамическая плитка и керамогранит для уютного дома',
      topbar_right: 'Помогаем подобрать материалы для ремонта',
      menu_note: 'Материалы для дома и вдохновение для вашего ремонта',
      logo_media_id: null,
      logo_alt: 'Логотип AgatCeramic',
      logo_url: null,
      navigation: [
        { label: 'Каталог керамической плитки', to: '/catalog' },
        { label: 'Контакты наших магазинов', to: '/contacts' },
      ],
    },
    footer: {
      tagline: 'Материалы для дома и вдохновение для вашего ремонта',
      explore_links: [{ label: 'Коллекции керамогранита', to: '/catalog' }],
      message: {
        eyebrow: 'AgatCeramic',
        text: 'Подбираем плитку и керамогранит для небольших квартир и просторных загородных домов.',
        link_label: 'Посмотреть каталог',
        link_url: '/catalog',
      },
      bottom_left: '© 2026 AgatCeramic',
      bottom_right: 'Керамические материалы для вашего дома',
    },
  }
}

async function mockWorkspace(page: Page, permissions = ['content.manage']) {
  let draft = appearanceFixture()
  let published = structuredClone(draft)
  const patches: Record<string, unknown>[] = []
  const mutations: string[] = []
  const requests: string[] = []
  const failures = new Map<string, { status: number; message: string }>()
  const deferred = createDeferredApiRequests()
  const store = {
    id: 7,
    name: 'Магазин керамической плитки и керамогранита',
    address: 'Москва, Тестовая улица, дом 7',
    phone: null,
    is_published: true,
    working_hours: Array.from({ length: 7 }, (_, index) => ({
      weekday: index + 1,
      is_closed: true,
      opens_at: null,
      closes_at: null,
    })),
    created_at: '2026-09-30T10:00:00Z',
    updated_at: '2026-09-30T10:00:00Z',
  }

  await page.route('**/sanctum/csrf-cookie', (route) =>
    route.fulfill({ status: 204 }),
  )
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1', '')
    const method = route.request().method()
    const key = `${method} ${path}`
    requests.push(key)
    await deferred.wait(key)
    const failure = failures.get(key)
    if (failure) {
      failures.delete(key)
      return route.fulfill({
        status: failure.status,
        json: { error: { message: failure.message } },
      })
    }
    if (path === '/admin/auth/me')
      return route.fulfill({
        json: {
          data: {
            id: 1,
            name: 'Редактор контента',
            email: 'editor@example.test',
            status: 'active',
            permissions,
          },
        },
      })
    if (path === appearancePath && method === 'GET')
      return route.fulfill({ json: { data: draft } })
    if (path === appearancePath && method === 'PATCH') {
      const payload = route.request().postDataJSON() as Record<string, unknown>
      patches.push(structuredClone(payload))
      mutations.push(key)
      draft = { ...draft, ...payload, has_unpublished_changes: true }
      draft.footer.bottom_left = appearanceFixture().footer.bottom_left
      return route.fulfill({ json: { data: draft } })
    }
    if (path === `${appearancePath}/publish` && method === 'POST') {
      mutations.push(key)
      draft.has_unpublished_changes = false
      published = structuredClone(draft)
      return route.fulfill({ json: { data: draft } })
    }
    if (path === '/admin/stores' && method === 'GET')
      return route.fulfill({
        json: { data: [store], meta: { ...emptyMeta, total: 1 } },
      })
    if (
      [
        '/admin/pages',
        '/admin/banners',
        '/admin/sliders',
        '/admin/media',
      ].includes(path) &&
      method === 'GET'
    )
      return route.fulfill({ json: { data: [], meta: emptyMeta } })
    return route.fulfill({
      status: 404,
      json: { error: { message: `Unknown fixture endpoint: ${key}` } },
    })
  })
  return {
    patches,
    mutations,
    requests,
    deferred,
    published: () => published,
    failNext: (key: string, status: number, message: string) =>
      failures.set(key, { status, message }),
  }
}

test('header menu supports adding, editing, reordering, deleting and publishing links', async ({
  page,
}) => {
  const api = await mockWorkspace(page)
  await page.goto('/content?section=appearance&panel=header')
  const names = page.getByLabel('Название', { exact: true })
  const links = page.getByLabel('Ссылка', { exact: true })
  await names.nth(0).fill('Каталог')
  await links.nth(0).fill('/catalog')
  for (const [label, to] of [
    ['О нас', '/about'],
    ['Главная', '/'],
    ['Сотрудничество', '/cooperation'],
    ['Удаляемый пункт', '/unused'],
  ]) {
    await page.getByRole('button', { name: 'Добавить пункт меню' }).click()
    await names.last().fill(label!)
    await links.last().fill(to!)
  }
  await page
    .getByRole('button', { name: 'Удалить пункт меню 6', exact: true })
    .click()
  await page
    .getByRole('button', { name: 'Поднять пункт меню 4', exact: true })
    .click()
  await page
    .getByRole('button', { name: 'Опустить пункт меню 2', exact: true })
    .click()
  await page.getByRole('button', { name: 'Сохранить черновик раздела' }).click()
  expect(api.published().header.navigation).toHaveLength(2)
  await page.getByRole('button', { name: 'Опубликовать оформление' }).click()
  await expect(page.getByText('Общее оформление опубликовано.')).toBeVisible()
  expect(api.published().header.navigation).toEqual([
    { label: 'Каталог', to: '/catalog' },
    { label: 'Главная', to: '/' },
    { label: 'Контакты наших магазинов', to: '/contacts' },
    { label: 'О нас', to: '/about' },
    { label: 'Сотрудничество', to: '/cooperation' },
  ])
  await page.reload()
  await expect(names).toHaveCount(5)
  await expect(names.nth(1)).toHaveValue('Главная')
})

test('submenu mode reversal preserves local fields and saves, reloads and publishes only active navigation fields', async ({
  page,
}) => {
  const api = await mockWorkspace(page)
  await page.goto('/content?section=appearance&panel=header')
  const first = page.getByRole('listitem', {
    name: 'Пункт меню 1',
    exact: true,
  })
  const toggle = () =>
    page.getByRole('switch', { name: 'Есть подменю у пункта 1', exact: true })
  await first.getByLabel('Ссылка', { exact: true }).fill('/unsaved-catalog')
  await toggle().focus()
  await page.keyboard.press('Space')
  await expect(toggle()).toHaveAttribute('aria-checked', 'true')
  const child = (index: number, parent = 1) =>
    page.getByRole('listitem', {
      name: `Подпункт ${index} пункта меню ${parent}`,
      exact: true,
    })
  await child(1).getByLabel('Название', { exact: true }).fill('Весь каталог')
  await child(1).getByLabel('Ссылка', { exact: true }).fill('/catalog')
  await page
    .getByRole('button', { name: 'Добавить подпункт в пункт меню 1' })
    .click()
  await child(2).getByLabel('Название', { exact: true }).fill('Мозаика')
  await child(2).getByLabel('Ссылка', { exact: true }).fill('/catalog/mosaic')
  await page
    .getByRole('button', { name: 'Поднять подпункт 2 пункта меню 1' })
    .click()
  await toggle().click()
  await expect(first.getByLabel('Ссылка', { exact: true })).toHaveValue(
    '/unsaved-catalog',
  )
  await page.getByRole('button', { name: 'Подвал', exact: true }).click()
  await page.getByRole('button', { name: 'Вид фильтров', exact: true }).click()
  await page.getByRole('button', { name: /^Шапка и навигация/ }).click()
  const save = page.getByRole('button', { name: 'Сохранить черновик раздела' })
  await save.click()
  await expect(save).toBeDisabled()
  expect(api.patches[0]).toHaveProperty('header.navigation', [
    { label: 'Каталог керамической плитки', to: '/unsaved-catalog' },
    { label: 'Контакты наших магазинов', to: '/contacts' },
  ])
  await toggle().click()
  await expect(child(1).getByLabel('Название', { exact: true })).toHaveValue(
    'Мозаика',
  )
  await expect(child(2).getByLabel('Ссылка', { exact: true })).toHaveValue(
    '/catalog',
  )
  await page
    .getByRole('button', { name: 'Опустить пункт меню 1', exact: true })
    .click()
  await save.click()
  await expect(save).toBeDisabled()
  const expected = [
    { label: 'Контакты наших магазинов', to: '/contacts' },
    {
      label: 'Каталог керамической плитки',
      children: [
        { label: 'Мозаика', to: '/catalog/mosaic' },
        { label: 'Весь каталог', to: '/catalog' },
      ],
    },
  ]
  expect(api.patches[1]).toHaveProperty('header.navigation', expected)
  expect(api.published().header.navigation).toEqual(
    appearanceFixture().header.navigation,
  )
  await page.reload()
  await expect(child(1, 2).getByLabel('Название', { exact: true })).toHaveValue(
    'Мозаика',
  )
  await page.getByRole('button', { name: 'Опубликовать оформление' }).click()
  await expect(page.getByText('Общее оформление опубликовано.')).toBeVisible()
  expect(api.published().header.navigation).toEqual(expected)
  expect(api.published().footer.explore_links).toEqual([
    { label: 'Коллекции керамогранита', to: '/catalog' },
  ])
})

test('submenu validation, server errors and pending save preserve input and disable editing', async ({
  page,
  browserIssueGuard,
}) => {
  const api = await mockWorkspace(page)
  await page.goto('/content?section=appearance&panel=header')
  const toggle = page.getByRole('switch', {
    name: 'Есть подменю у пункта 1',
    exact: true,
  })
  await toggle.click()
  const save = page.getByRole('button', { name: 'Сохранить черновик раздела' })
  await save.click()
  await expect(page.getByRole('alert')).toContainText('подпункт 1')
  expect(api.patches).toHaveLength(0)
  await page
    .getByRole('button', { name: 'Закрыть уведомление' })
    .press('Escape')
  const child = page.getByRole('listitem', {
    name: 'Подпункт 1 пункта меню 1',
    exact: true,
  })
  await expect(
    child.getByRole('button', { name: 'Удалить подпункт 1 пункта меню 1' }),
  ).toBeDisabled()
  await child
    .getByLabel('Название', { exact: true })
    .fill('Сохранённый подпункт')
  await child.getByLabel('Ссылка', { exact: true }).fill('javascript:alert(1)')
  await save.click()
  await expect(page.getByRole('alert')).toContainText('HTTPS')
  expect(api.patches).toHaveLength(0)
  await page
    .getByRole('button', { name: 'Закрыть уведомление' })
    .press('Escape')
  await child.getByLabel('Ссылка', { exact: true }).fill('/catalog')
  api.failNext(`PATCH ${appearancePath}`, 422, 'Исправьте вложенную ссылку.')
  browserIssueGuard.allowApiError(422, '/api/v1/admin/site-appearance')
  await save.click()
  await expect(page.getByRole('alert')).toHaveText(
    'Исправьте вложенную ссылку.',
  )
  await page
    .getByRole('button', { name: 'Закрыть уведомление' })
    .press('Escape')
  await expect(child.getByLabel('Название', { exact: true })).toHaveValue(
    'Сохранённый подпункт',
  )
  const saving = api.deferred.defer(`PATCH ${appearancePath}`)
  await save.click()
  await saving.requested
  await expect(toggle).toBeDisabled()
  await expect(child.getByLabel('Ссылка', { exact: true })).toBeDisabled()
  await expect(
    page.getByRole('button', { name: 'Добавить подпункт в пункт меню 1' }),
  ).toBeDisabled()
  await expect(
    page.getByRole('button', { name: 'Добавить пункт меню', exact: true }),
  ).toBeDisabled()
  saving.release()
  await expect(
    page.getByRole('button', { name: 'Опубликовать оформление' }),
  ).toBeEnabled()
  await expect(toggle).toBeEnabled()
  expect(api.patches[0]).toHaveProperty('header.navigation.0', {
    label: 'Каталог керамической плитки',
    children: [{ label: 'Сохранённый подпункт', to: '/catalog' }],
  })
})

for (const width of [320, 640, 768, 1024, 1280]) {
  test(`submenu editor remains accessible without overflow at ${width}px`, async ({
    page,
  }) => {
    await mockWorkspace(page)
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/content?section=appearance&panel=header')
    await page
      .getByRole('switch', { name: 'Есть подменю у пункта 1', exact: true })
      .click()
    const first = page.getByRole('listitem', {
      name: 'Пункт меню 1',
      exact: true,
    })
    await first
      .getByLabel('Название', { exact: true })
      .first()
      .fill(
        'Коллекции керамической плитки и керамогранита для уютного загородного дома',
      )
    const child = page.getByRole('listitem', {
      name: 'Подпункт 1 пункта меню 1',
      exact: true,
    })
    await child
      .getByLabel('Название', { exact: true })
      .fill('Все коллекции керамогранита российского производства')
    await child
      .getByLabel('Ссылка', { exact: true })
      .fill('/catalog/collections')
    await child.getByLabel('Название', { exact: true }).focus()
    expect(
      await child.getByLabel('Название', { exact: true }).evaluate((input) => {
        const container = input.closest('.admin-control-focus')!
        return (
          getComputedStyle(container).boxShadow !== 'none' ||
          getComputedStyle(container).outlineStyle !== 'none'
        )
      }),
    ).toBe(true)
    expect(
      await page.evaluate(
        () =>
          document.documentElement.scrollWidth >
          document.documentElement.clientWidth,
      ),
    ).toBe(false)
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    await page.evaluate(() => window.scrollTo(0, 0))
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0)
    await page.screenshot({
      path: `../../.tmp/navigation-submenus/admin-${width}.png`,
      fullPage: true,
      animations: 'disabled',
    })
  })
}

test('appearance retains panel drafts and publishes only saved appearance sections', async ({
  page,
}) => {
  const api = await mockWorkspace(page)
  await page.goto('/content?section=appearance&panel=header')
  await expect(page.getByLabel('Верхняя строка справа')).toHaveCount(0)
  await expect(
    page.getByRole('region', { name: 'Верхняя строка', exact: true }),
  ).toContainText('Настроек сайта')
  const publish = page.getByRole('button', { name: 'Опубликовать оформление' })
  await expect(publish).toBeDisabled()
  await page.getByLabel('Верхняя строка слева').fill('Изменённая шапка')
  await page.getByRole('button', { name: /^Подвал/ }).click()
  await expect(page.getByLabel('Слева', { exact: true })).toHaveCount(0)
  const bottom = page.getByRole('region', {
    name: 'Нижняя строка',
    exact: true,
  })
  await expect(bottom).toContainText('© 2026 AgatCeramic')
  await expect(bottom).toContainText(
    'Год обновляется автоматически по часовому поясу сайта.',
  )
  await expect(page).toHaveURL(/panel=footer/)
  await page.getByLabel('Слоган под логотипом').fill('Изменённый подвал')
  await page.getByRole('button', { name: /^Шапка и навигация/ }).click()
  await expect(page.getByLabel('Верхняя строка слева')).toHaveValue(
    'Изменённая шапка',
  )
  await page.getByRole('button', { name: 'Сохранить черновик раздела' }).click()
  await expect(
    page.getByRole('status').filter({ hasText: 'Черновик раздела сохранён.' }),
  ).toBeVisible()
  expect(api.patches).toEqual([
    {
      header: {
        topbar_left: appearanceFixture().header.topbar_left,
        menu_note: appearanceFixture().header.menu_note,
        logo_media_id: null,
        logo_alt: appearanceFixture().header.logo_alt,
        navigation: appearanceFixture().header.navigation,
        topbar_right: 'Изменённая шапка',
      },
    },
  ])
  expect(api.published().header.topbar_right).toBe(
    appearanceFixture().header.topbar_right,
  )
  await expect(publish).toBeDisabled()
  await page.getByRole('button', { name: /^Подвал/ }).click()
  await expect(page.getByLabel('Слоган под логотипом')).toHaveValue(
    'Изменённый подвал',
  )
  await page.getByRole('button', { name: 'Сохранить черновик раздела' }).click()
  await expect(publish).toBeEnabled()
  expect(api.patches[1]).toMatchObject({
    footer: { tagline: 'Изменённый подвал' },
  })
  expect(api.patches[1]?.footer).not.toHaveProperty('bottom_left')
  await publish.click()
  await expect(page.getByText('Общее оформление опубликовано.')).toBeVisible()
  expect(api.published().header.topbar_right).toBe('Изменённая шапка')
  expect(api.published().footer.tagline).toBe('Изменённый подвал')
  expect(api.mutations).toEqual([
    `PATCH ${appearancePath}`,
    `PATCH ${appearancePath}`,
    `POST ${appearancePath}/publish`,
  ])
  await page.reload()
  await expect(page.getByLabel('Слоган под логотипом')).toHaveValue(
    'Изменённый подвал',
  )
  await expect(publish).toBeDisabled()
})

for (const [panel, label, value] of [
  ['header', 'Верхняя строка слева', 'Закреплённая шапка'],
  ['footer', 'Слоган под логотипом', 'Закреплённый подвал'],
] as const) {
  test(`appearance ${panel} floating actions save and publish with keyboard focus and busy states`, async ({
    page,
  }) => {
    const api = await mockWorkspace(page)
    await page.goto(`/content?section=appearance&panel=${panel}`)
    await page.getByLabel(label).fill(value)
    const save = page.getByRole('button', {
      name: 'Сохранить черновик раздела',
    })
    const publish = page.getByRole('button', {
      name: 'Опубликовать оформление',
    })
    await save.focus()
    await page.evaluate(() => window.scrollTo(0, 600))
    await expect(page.locator('.admin-sticky-actions--floating')).toBeVisible()
    await expect(save).toBeFocused()
    await page.evaluate(() => window.scrollTo(0, 0))
    await expect(
      page
        .locator('.admin-editor-toolbar')
        .getByRole('button', { name: 'Сохранить черновик раздела' }),
    ).toBeVisible()
    await expect(save).toBeFocused()
    await page.evaluate(() => window.scrollTo(0, 600))
    const saving = api.deferred.defer(`PATCH ${appearancePath}`)
    await save.press('Enter')
    await saving.requested
    await expect(save).toBeDisabled()
    await expect(publish).toBeDisabled()
    saving.release()
    await expect(publish).toBeEnabled()
    await publish.click()
    await expect(publish).toBeDisabled()
    expect(JSON.stringify(api.published()[panel])).toContain(value)
  })
}

test('appearance confirms leaving dirty edits while panel changes remain safe', async ({
  page,
}) => {
  const api = await mockWorkspace(page)
  await page.goto('/content?section=appearance')
  await page.getByLabel('Верхняя строка слева').fill('Несохранённая шапка')
  page.once('dialog', (dialog) => dialog.dismiss())
  await page.getByRole('button', { name: 'Страницы', exact: true }).click()
  await expect(page).toHaveURL(/section=appearance/)
  await expect(page.getByLabel('Верхняя строка слева')).toHaveValue(
    'Несохранённая шапка',
  )
  page.once('dialog', (dialog) => dialog.accept())
  await page.getByRole('button', { name: 'Магазины', exact: true }).click()
  await expect(page).toHaveURL(/section=stores/)
  await expect(
    page.getByRole('heading', { name: 'Магазины', exact: true }),
  ).toBeVisible()
  expect(api.mutations).toEqual([])
  await page
    .getByRole('button', { name: 'Общее оформление', exact: true })
    .click()
  await expect(page.getByLabel('Верхняя строка слева')).toHaveValue(
    appearanceFixture().header.topbar_right,
  )
})

test('appearance loading, retry and save errors preserve editable drafts', async ({
  page,
  browserIssueGuard,
}) => {
  const api = await mockWorkspace(page)
  const loading = api.deferred.defer(`GET ${appearancePath}`)
  api.failNext(`GET ${appearancePath}`, 503, 'Оформление временно недоступно.')
  browserIssueGuard.allowApiError(503, '/api/v1/admin/site-appearance')
  await page.goto('/content?section=appearance')
  await loading.requested
  await expect(
    page.getByRole('status').filter({ hasText: 'Загрузка общего оформления' }),
  ).toBeVisible()
  loading.release()
  await expect(page.getByRole('alert')).toHaveText(
    'Оформление временно недоступно.',
  )
  await page
    .getByRole('button', { name: 'Закрыть уведомление' })
    .press('Escape')
  await expect(
    page.getByRole('button', { name: 'Повторить загрузку' }),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Повторить загрузку' }).click()
  await page.getByLabel('Верхняя строка слева').fill('Повторное сохранение')
  api.failNext(
    `PATCH ${appearancePath}`,
    422,
    'Исправьте текст верхней строки.',
  )
  browserIssueGuard.allowApiError(422, '/api/v1/admin/site-appearance')
  await page.getByRole('button', { name: 'Сохранить черновик раздела' }).click()
  await expect(page.getByRole('alert')).toHaveText(
    'Исправьте текст верхней строки.',
  )
  await expect(page.getByLabel('Верхняя строка слева')).toHaveValue(
    'Повторное сохранение',
  )
  await page.getByRole('button', { name: 'Сохранить черновик раздела' }).click()
  await expect(
    page.getByRole('button', { name: 'Опубликовать оформление' }),
  ).toBeEnabled()
})

test('appearance publish failure preserves saved draft and permits retry', async ({
  page,
  browserIssueGuard,
}) => {
  const api = await mockWorkspace(page)
  await page.goto('/content?section=appearance&panel=footer')
  await page.getByLabel('Слоган под логотипом').fill('Сохранённый слоган')
  await page.getByRole('button', { name: 'Сохранить черновик раздела' }).click()
  const publish = page.getByRole('button', { name: 'Опубликовать оформление' })
  await expect(publish).toBeEnabled()
  api.failNext(
    `POST ${appearancePath}/publish`,
    503,
    'Публикация временно недоступна.',
  )
  browserIssueGuard.allowApiError(503, '/api/v1/admin/site-appearance/publish')
  const pending = api.deferred.defer(`POST ${appearancePath}/publish`)
  await publish.click()
  await pending.requested
  await expect(publish).toBeDisabled()
  await expect(page.getByLabel('Слоган под логотипом')).toBeDisabled()
  pending.release()
  await expect(page.getByRole('alert')).toHaveText(
    'Публикация временно недоступна.',
  )
  await expect(page.getByLabel('Слоган под логотипом')).toHaveValue(
    'Сохранённый слоган',
  )
  expect(api.published().footer.tagline).toBe(
    appearanceFixture().footer.tagline,
  )
  await publish.click()
  await expect(page.getByText('Общее оформление опубликовано.')).toBeVisible()
  expect(api.published().footer.tagline).toBe('Сохранённый слоган')
})

test('appearance load retry remains available after the error notification times out', async ({
  page,
  browserIssueGuard,
}) => {
  const api = await mockWorkspace(page)
  await page.clock.install()
  api.failNext(`GET ${appearancePath}`, 503, 'Оформление временно недоступно.')
  browserIssueGuard.allowApiError(503, '/api/v1/admin/site-appearance')
  await page.goto('/content?section=appearance')
  await expect(page.getByRole('alert')).toHaveText(
    'Оформление временно недоступно.',
  )
  await page.clock.fastForward(5100)
  await expect(page.getByRole('alert')).toHaveCount(0)
  const retry = page.getByRole('button', { name: 'Повторить загрузку' })
  await expect(retry).toBeVisible()
  await retry.click()
  await expect(
    page.getByRole('switch', { name: 'Есть подменю у пункта 1', exact: true }),
  ).toBeVisible()
})

test('appearance media controls require media.manage and filters stay informational', async ({
  page,
}) => {
  const api = await mockWorkspace(page)
  await page.goto('/content?section=appearance')
  await expect(
    page.getByText(
      'Выбор и загрузка доступны сотруднику с правом управления медиа.',
      { exact: false },
    ),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Логотип сайта', exact: true }),
  ).toHaveCount(0)
  await expect(page.locator('input[type="file"]')).toHaveCount(0)
  await expect(
    page.getByRole('button', { name: 'Загрузить и выбрать' }),
  ).toHaveCount(0)
  await page.getByRole('button', { name: 'Вид фильтров' }).click()
  const filters = page.getByRole('region', {
    name: 'Представление фильтров',
    exact: true,
  })
  await expect(filters.getByRole('status')).toContainText('пока не реализованы')
  await expect(filters.getByRole('textbox')).toHaveCount(0)
  await expect(
    page.getByRole('button', { name: 'Сохранить черновик раздела' }),
  ).toHaveCount(0)
  expect(
    api.requests.filter((request) => request.includes('/admin/media')),
  ).toEqual([])
  expect(api.mutations).toEqual([])
})

test('appearance exposes media selection and upload for a media manager', async ({
  page,
}) => {
  await mockWorkspace(page, ['content.manage', 'media.manage'])
  await page.goto('/content?section=appearance')
  for (const name of ['Верхняя строка', 'Логотип', 'Навигация']) {
    await expect(page.getByRole('region', { name, exact: true })).toBeVisible()
  }
  await expect(
    page.getByRole('button', { name: 'Выберите или перетащите логотип' }),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Выбрать из загруженных' }).click()
  await expect(
    page.getByRole('button', { name: 'Логотип сайта', exact: true }),
  ).toBeVisible()
  await expect(page.getByText('Изображений пока нет.')).toBeVisible()
})

test('logo selection stays usable and accessible in narrow screens and the desktop inspector', async ({
  page,
}) => {
  await mockWorkspace(page, ['content.manage', 'media.manage'])
  await page.goto('/content?section=appearance&panel=header')
  await page.getByLabel('Загрузить файл — Логотип сайта').setInputFiles({
    name: 'logo.webp',
    mimeType: 'image/webp',
    buffer: Buffer.from('fixture'),
  })
  for (const width of [320, 640, 768, 1024, 1280, 1440]) {
    await page.setViewportSize({ width, height: 1024 })
    await expect(page.getByLabel('Название файла')).toHaveValue('logo')
    await expect(
      page.getByRole('button', { name: 'Выберите или перетащите логотип' }),
    ).toBeVisible()
    expect(
      await page.evaluate(
        () =>
          document.documentElement.scrollWidth >
          document.documentElement.clientWidth,
      ),
      `logo overflow at ${width}px`,
    ).toBe(false)
    expect(
      (await new AxeBuilder({ page }).analyze()).violations,
      `logo axe at ${width}px`,
    ).toEqual([])
    await page.screenshot({
      path: test.info().outputPath(`logo-${width}.png`),
      fullPage: true,
      animations: 'disabled',
    })
  }
  await page.getByRole('button', { name: 'Отменить выбор файла' }).click()
  await expect(
    page.getByRole('button', { name: 'Выберите или перетащите логотип' }),
  ).toBeFocused()
  await expect(
    page.getByRole('button', { name: 'Подвал', exact: true }),
  ).toBeEnabled()
})

for (const width of [320, 640, 768, 1024, 1280]) {
  test(`appearance panels support keyboard, accessibility and all required widths at ${width}px`, async ({
    page,
  }) => {
    await mockWorkspace(page)
    await page.goto('/content?section=appearance&panel=header')
    const contentNavigation = page.getByRole('navigation', {
      name: 'Разделы контента',
    })
    await expect(contentNavigation.getByRole('button')).toHaveText([
      'Страницы',
      'Общее оформление',
      'Магазины',
    ])
    await expect(
      contentNavigation.getByRole('button', { name: /Баннеры|Слайдеры/ }),
    ).toHaveCount(0)
    const footer = page.getByRole('button', { name: 'Подвал', exact: true })
    await footer.focus()
    await page.keyboard.press('Enter')
    await expect(page.getByLabel('Слоган под логотипом')).toBeVisible()
    await expect(footer).toBeFocused()
    expect(
      await footer.evaluate((element) => {
        const style = getComputedStyle(element)
        return style.outlineStyle !== 'none' || style.boxShadow !== 'none'
      }),
    ).toBe(true)

    await page.setViewportSize({ width, height: 800 })
    for (const panel of [
      { name: 'Шапка и навигация', key: 'header' },
      { name: 'Подвал', key: 'footer' },
      { name: 'Вид фильтров', key: 'filters' },
    ]) {
      await page.getByRole('button', { name: panel.name, exact: true }).click()
      await expect(
        page.getByRole('region', {
          name: panel.key === 'filters' ? 'Представление фильтров' : panel.name,
          exact: true,
        }),
      ).toBeVisible()
      await page
        .getByRole('navigation', { name: 'Разделы общего оформления' })
        .evaluate(async (element) => {
          await Promise.all(
            element
              .getAnimations({ subtree: true })
              .map((animation) => animation.finished),
          )
        })
      expect(
        await page.evaluate(
          () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
        ),
        `${panel.key} overflow at ${width}px`,
      ).toBe(false)
      expect(
        (await new AxeBuilder({ page }).analyze()).violations,
        `${panel.key} axe at ${width}px`,
      ).toEqual([])
      await page.screenshot({
        path: test.info().outputPath(`appearance-${panel.key}-${width}.png`),
        fullPage: true,
        animations: 'disabled',
      })
    }
  })
}

test('pending and in-flight logo uploads survive browser history panel changes', async ({
  page,
  browserIssueGuard,
}) => {
  const api = await mockWorkspace(page, ['content.manage', 'media.manage'])
  await page.goto('/content?section=appearance&panel=header')
  await page.getByRole('button', { name: 'Подвал', exact: true }).click()
  await expect(page).toHaveURL(/panel=footer/)
  await page
    .getByRole('button', { name: 'Шапка и навигация', exact: true })
    .click()
  await expect(page).toHaveURL(/panel=header/)
  await page.getByLabel('Загрузить файл — Логотип сайта').setInputFiles({
    name: 'logo.webp',
    mimeType: 'image/webp',
    buffer: Buffer.from('fixture'),
  })
  await expect(
    page.getByRole('button', { name: 'Подвал', exact: true }),
  ).toBeDisabled()
  await page.goBack()
  await expect(page).toHaveURL(/panel=header/)
  await expect(page.getByLabel('Название файла')).toHaveValue('logo')
  await page
    .getByLabel('Описание загружаемого изображения')
    .fill('Логотип магазина')
  const upload = api.deferred.defer('POST /admin/media')
  api.failNext('POST /admin/media', 422, 'Повторите загрузку файла.')
  browserIssueGuard.allowApiError(422, '/api/v1/admin/media')
  await page.getByRole('button', { name: 'Загрузить и выбрать' }).click()
  await upload.requested
  await page.goBack()
  await expect(page).toHaveURL(/panel=header/)
  await expect(
    page.getByRole('button', { name: 'Загрузить и выбрать' }),
  ).toBeDisabled()
  upload.release()
  await expect(page.getByRole('alert')).toHaveText('Повторите загрузку файла.')
  await expect(page.getByLabel('Название файла')).toHaveValue('logo')
  await page.getByRole('button', { name: 'Отменить выбор файла' }).click()
  await expect(
    page.getByRole('button', { name: 'Подвал', exact: true }),
  ).toBeEnabled()
})

test('store editor protects dirty edits on Escape, close and navigation', async ({
  page,
}) => {
  const api = await mockWorkspace(page)
  await page.goto('/content?section=stores')
  await page.getByRole('button', { name: 'Добавить магазин' }).click()
  const dialog = page.getByRole('dialog', { name: 'Новый магазин' })
  await dialog.getByLabel('Название').fill('Несохранённый магазин')
  page.once('dialog', (confirmation) => confirmation.dismiss())
  await page.keyboard.press('Escape')
  await expect(dialog).toBeVisible()
  await expect(dialog.getByLabel('Название')).toHaveValue(
    'Несохранённый магазин',
  )
  page.once('dialog', (confirmation) => confirmation.dismiss())
  // The dialog traps pointers; a router link activation models navigation from outside the form.
  await page
    .getByRole('button', { name: 'Общее оформление', exact: true })
    .evaluate((element: HTMLButtonElement) => element.click())
  await expect(page).toHaveURL(/section=stores/)
  await expect(dialog).toBeVisible()
  page.once('dialog', (confirmation) => confirmation.accept())
  await dialog.getByRole('button', { name: 'Закрыть окно магазина' }).click()
  await expect(dialog).toHaveCount(0)
  await expect(
    page.getByRole('button', { name: 'Добавить магазин' }),
  ).toBeFocused()
  await page.getByRole('button', { name: 'Добавить магазин' }).click()
  await expect(dialog.getByLabel('Название')).toHaveValue('')
  await dialog.getByLabel('Название').fill('Другой несохранённый магазин')
  page.once('dialog', (confirmation) => confirmation.accept())
  await page
    .getByRole('button', { name: 'Общее оформление', exact: true })
    .evaluate((element: HTMLButtonElement) => element.click())
  await expect(page).toHaveURL(/section=appearance/)
  await expect(page.getByLabel('Верхняя строка слева')).toBeVisible()
  expect(api.mutations).toEqual([])
})

test('store working hours protect dirty edits and restore original hours after discard', async ({
  page,
}) => {
  await mockWorkspace(page)
  await page.goto('/content?section=stores')
  const openHours = page.getByRole('button', {
    name: 'Часы работы магазина Магазин керамической плитки и керамогранита',
  })
  await openHours.click()
  const dialog = page.getByRole('dialog', { name: 'Часы работы', exact: true })
  await dialog.getByRole('checkbox', { name: 'Понедельник — выходной' }).focus()
  await page.keyboard.press('Space')
  await dialog.getByLabel('Понедельник — открытие').fill('10:30')
  page.once('dialog', (confirmation) => confirmation.dismiss())
  await dialog.getByRole('button', { name: 'Отмена', exact: true }).click()
  await expect(dialog.getByLabel('Понедельник — открытие')).toHaveValue('10:30')
  page.once('dialog', (confirmation) => confirmation.accept())
  await page.keyboard.press('Escape')
  await expect(dialog).toHaveCount(0)
  await expect(openHours).toBeFocused()
  await openHours.click()
  await expect(
    dialog.getByRole('checkbox', { name: 'Понедельник — выходной' }),
  ).toBeChecked()
  await expect(dialog.getByLabel('Понедельник — открытие')).toHaveCount(0)
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
})
