import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from './fixtures'
import { createDeferredApiRequests } from './deferredApi'
import type { SiteAppearance } from '../src/features/appearance/types/appearance.types'

test.use({ reducedMotion: 'reduce' })

const appearancePath = '/admin/site-appearance'
const emptyMeta = { current_page: 1, last_page: 1, per_page: 25, total: 0 }

function appearanceFixture(): SiteAppearance {
  return {
    has_unpublished_changes: false,
    header: {
      topbar_left: 'Керамическая плитка и керамогранит для уютного дома',
      topbar_right: 'Помогаем подобрать материалы для ремонта',
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
      patches.push(payload)
      mutations.push(key)
      draft = { ...draft, ...payload, has_unpublished_changes: true }
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

test('appearance retains panel drafts and publishes only saved appearance sections', async ({
  page,
}) => {
  const api = await mockWorkspace(page)
  await page.goto('/content?section=appearance&panel=header')
  const publish = page.getByRole('button', { name: 'Опубликовать оформление' })
  await expect(publish).toBeDisabled()
  await page.getByLabel('Верхняя строка слева').fill('Изменённая шапка')
  await page.getByRole('button', { name: /^Подвал/ }).click()
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
        topbar_right: appearanceFixture().header.topbar_right,
        logo_media_id: null,
        logo_alt: appearanceFixture().header.logo_alt,
        navigation: appearanceFixture().header.navigation,
        topbar_left: 'Изменённая шапка',
      },
    },
  ])
  expect(api.published().header.topbar_left).toBe(
    appearanceFixture().header.topbar_left,
  )
  await expect(publish).toBeDisabled()
  await page.getByRole('button', { name: /^Подвал/ }).click()
  await expect(page.getByLabel('Слоган под логотипом')).toHaveValue(
    'Изменённый подвал',
  )
  await page.getByRole('button', { name: 'Сохранить черновик раздела' }).click()
  await expect(publish).toBeEnabled()
  expect(api.patches[1]).toEqual({
    footer: { ...appearanceFixture().footer, tagline: 'Изменённый подвал' },
  })
  await publish.click()
  await expect(page.getByText('Общее оформление опубликовано.')).toBeVisible()
  expect(api.published().header.topbar_left).toBe('Изменённая шапка')
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
    appearanceFixture().header.topbar_left,
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
    name: 'Вид фильтров',
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
  await expect(
    page.getByRole('button', { name: 'Логотип сайта', exact: true }),
  ).toBeVisible()
  await expect(page.getByLabel('Загрузить файл — Логотип сайта')).toBeVisible()
  await expect(page.getByText('Изображений пока нет.')).toBeVisible()
})

test('appearance panels support keyboard, accessibility and all required widths', async ({
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
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 800 })
    for (const panel of [
      { name: 'Шапка и навигация', key: 'header' },
      { name: 'Подвал', key: 'footer' },
      { name: 'Вид фильтров', key: 'filters' },
    ]) {
      await page.getByRole('button', { name: panel.name, exact: true }).click()
      await expect(
        page.getByRole('region', { name: panel.name, exact: true }),
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
  }
})

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
