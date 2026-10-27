import AxeBuilder from './approvedAccessibility'
import { expect, test, type Page } from './fixtures'
import type { ContentPage } from '../src/features/pages/types/page.types'
import { blankBlockData } from '../src/features/pages/validation/blocks'

async function fixture(
  page: Page,
  permissions = ['content.manage', 'media.manage'],
) {
  const timestamp = '2026-09-30T10:00:00Z'
  let stored: ContentPage = {
    id: 7,
    title: 'О компании — материалы и решения для интерьера',
    slug: 'company',
    body: '',
    blocks: [
      {
        id: 'intro',
        type: 'text',
        enabled: true,
        data: { title: 'История', body: 'Исходный текст' },
      },
    ],
    is_published: true,
    has_unpublished_changes: false,
    published_at: timestamp,
    created_at: timestamp,
    updated_at: timestamp,
  }
  let published = JSON.stringify(stored.blocks)
  let failSave = false
  let failUpload = true
  let mediaReads = 0
  const writes: unknown[] = []
  let releaseUpload: (() => void) | undefined
  const media = {
    id: 5,
    kind: 'image',
    title: 'Керамическая плитка',
    alt: 'Образцы плитки',
    url: '/fixture-media.svg',
    thumbnail_url: null,
    mime_type: 'image/webp',
    size: 1024,
    width: 320,
    height: 180,
    created_at: timestamp,
    updated_at: timestamp,
  }
  await page.route('**/fixture-media.svg', (route) =>
    route.fulfill({
      contentType: 'image/svg+xml',
      body: '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="180"><rect width="320" height="180" fill="#c3b79c"/></svg>',
    }),
  )
  await page.route('**/sanctum/csrf-cookie', (route) =>
    route.fulfill({ status: 204 }),
  )
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1', '')
    const method = route.request().method()
    if (path === '/admin/auth/me')
      return route.fulfill({
        json: {
          data: {
            id: 1,
            name: 'Редактор',
            email: 'editor@example.test',
            status: 'active',
            permissions,
          },
        },
      })
    if (path === '/admin/pages' && method === 'GET')
      return route.fulfill({
        json: {
          data: [stored],
          meta: { current_page: 1, last_page: 2, per_page: 25, total: 26 },
        },
      })
    if (path === '/admin/pages/7' && method === 'GET')
      return route.fulfill({ json: { data: stored } })
    if (path === '/admin/sliders' && method === 'GET')
      return route.fulfill({
        json: {
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
        },
      })
    if (path === '/admin/pages/7' && method === 'PATCH') {
      if (failSave) {
        failSave = false
        return route.fulfill({
          status: 422,
          json: { error: { message: 'Проверьте настройки блока.' } },
        })
      }
      const payload = route.request().postDataJSON()
      writes.push(payload)
      stored = { ...stored, ...payload, has_unpublished_changes: true }
      return route.fulfill({ json: { data: stored } })
    }
    if (path === '/admin/pages/7/publish') {
      published = JSON.stringify(stored.blocks)
      stored = { ...stored, has_unpublished_changes: false }
      return route.fulfill({ json: { data: stored } })
    }
    if (path === '/admin/media' && method === 'GET') {
      mediaReads++
      return route.fulfill({
        json: {
          data: [media],
          meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
        },
      })
    }
    if (path === '/admin/media' && method === 'POST') {
      if (failUpload) {
        failUpload = false
        return route.fulfill({
          status: 422,
          json: {
            error: { message: 'Не удалось загрузить файл. Повторите попытку.' },
          },
        })
      }
      await new Promise<void>((resolve) => {
        releaseUpload = resolve
      })
      return route.fulfill({
        status: 201,
        json: { data: { ...media, id: 9, title: 'Новое фото' } },
      })
    }
    return route.fulfill({
      status: 404,
      json: { error: { message: 'Неизвестный endpoint' } },
    })
  })
  return {
    writes,
    failNextSave: () => {
      failSave = true
    },
    published: () => published,
    stored: () => stored,
    mediaReads: () => mediaReads,
    release: () => releaseUpload?.(),
  }
}

test('home blocks with nullable image URLs remain editable across repeated switches and reloads', async ({
  page,
}) => {
  const state = await fixture(page)
  const data = blankBlockData()
  state.stored().slug = 'home'
  state.stored().title = 'Главная'
  data.categories.items[0] = {
    id: 'tile',
    name: 'Керамическая плитка',
    short_description: 'Для интерьера',
    description: 'Материалы для стен и пола',
    image_url: null,
    image_media_id: 5,
    image_alt: 'Образцы плитки',
  }
  data.about.image_url = null
  state.stored().blocks = [
    { id: 'promo', type: 'promo', enabled: true, data: data.promo },
    {
      id: 'categories',
      type: 'categories',
      enabled: true,
      data: data.categories,
    },
    { id: 'about', type: 'about', enabled: true, data: data.about },
    { id: 'materials', type: 'materials', enabled: true, data: data.materials },
    { id: 'marquee', type: 'marquee', enabled: true, data: data.marquee },
    { id: 'guide', type: 'guide', enabled: true, data: data.guide },
  ]
  await page.route('**/api/v1/admin/home-page', (route) =>
    route.fulfill({ json: { data: { page_id: 7 } } }),
  )
  // This test verifies the Admin editor; Nuxt rendering has its own preview suite.
  await page.route('http://localhost:3000/preview/home', (route) =>
    route.fulfill({
      contentType: 'text/html',
      body: '<html lang="ru"></html>',
    }),
  )
  await page.route('**/images/company.webp', (route) =>
    route.fulfill({
      contentType: 'image/svg+xml',
      body: '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80"><rect width="80" height="80" fill="#c3b79c"/></svg>',
    }),
  )
  await page.goto('/content?page=home&block=promo')
  const select = async (name: string) =>
    page.getByRole('button', { name, exact: true }).click()
  const save = page.getByRole('button', {
    name: 'Сохранить черновик блоков',
    exact: true,
  })
  const categories = page.getByRole('region', {
    name: 'Настройки: Сетка баннеров',
  })
  const about = page.getByRole('region', { name: 'Настройки: О проекте' })
  await select('2. Сетка баннеров')
  await expect(categories).toBeVisible()
  await expect(save).toBeDisabled()
  await categories.getByLabel('Название', { exact: true }).fill('Новая плитка')
  await select('3. О проекте')
  await about
    .getByRole('textbox', { name: /^Заголовок/ })
    .fill('О нашей компании')
  await about.getByText('Исходный адрес изображения', { exact: true }).click()
  const url = about.getByRole('textbox', {
    name: 'Локальное изображение',
    exact: true,
  })
  await expect(url).toHaveValue('')
  await url.fill('/images/company.webp')
  for (let pass = 0; pass < 2; pass++) {
    for (const name of [
      '4. Меню баннеров',
      '5. Бегущая строка',
      '6. Советы',
      '1. Промо',
      '2. Сетка баннеров',
      '3. О проекте',
    ])
      await select(name)
    await expect(
      about.getByRole('textbox', { name: /^Заголовок/ }),
    ).toHaveValue('О нашей компании')
  }
  await select('2. Сетка баннеров')
  await expect(categories.getByLabel('Название', { exact: true })).toHaveValue(
    'Новая плитка',
  )
  await save.click()
  await expect(save).toBeDisabled()
  expect(state.stored().blocks).toEqual(
    expect.arrayContaining([
      expect.objectContaining({
        type: 'categories',
        data: expect.objectContaining({
          items: [
            expect.objectContaining({ name: 'Новая плитка', image_url: null }),
          ],
        }),
      }),
      expect.objectContaining({
        type: 'about',
        data: expect.objectContaining({
          title: 'О нашей компании',
          image_url: '/images/company.webp',
        }),
      }),
    ]),
  )
  await page.reload()
  await expect(categories.getByLabel('Название', { exact: true })).toHaveValue(
    'Новая плитка',
  )
  await expect(save).toBeDisabled()
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await expect(categories).toBeVisible()
    expect(
      await page.evaluate(
        () =>
          document.documentElement.scrollWidth <=
          document.documentElement.clientWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: test.info().outputPath(`nullable-home-blocks-${width}.png`),
      fullPage: true,
      animations: 'disabled',
    })
  }
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
})

test('typed blocks preserve edits, order, state and publication; upload retries in place', async ({
  page,
  browserIssueGuard,
}) => {
  const state = await fixture(page)
  browserIssueGuard.allowApiError(422, '/api/v1/admin/pages/7')
  browserIssueGuard.allowApiError(422, '/api/v1/admin/media')
  await page.goto('/content?page=7')
  await expect(
    page.getByRole('button', { name: 'Следующая страница', exact: true }),
  ).toBeEnabled()
  await page
    .getByRole('textbox', { name: /^Текст блока/ })
    .fill('Изменённая история компании')
  const firstPublished = state.published()
  await expect(
    page.getByRole('button', { name: 'Следующая страница', exact: true }),
  ).toBeDisabled()
  await expect(
    page.getByRole('button', {
      name: 'Опубликовать блоки страницы',
      exact: true,
    }),
  ).toBeDisabled()
  page.once('dialog', (dialog) => dialog.dismiss())
  await page
    .getByRole('link', { name: 'Главная', exact: false })
    .filter({ hasText: '/' })
    .last()
    .click()
  await expect(page).toHaveURL(/page=7/)
  await page
    .getByRole('button', { name: 'Тип нового блока', exact: true })
    .click()
  await page.getByRole('button', { name: 'О проекте', exact: true }).click()
  await page.getByRole('button', { name: 'Добавить блок', exact: true }).click()
  await expect(page).toHaveURL(/block=about-/)
  await page
    .getByRole('textbox', { name: /^Заголовок/ })
    .fill(
      'Керамические материалы для сложных архитектурных решений и современных интерьеров',
    )
  await page.getByLabel('Подпись над заголовком').fill('О компании')
  await page
    .getByRole('textbox', { name: 'Текст', exact: true })
    .fill('История и материалы')
  await page
    .getByLabel('Описание изображения для доступности')
    .fill('Образцы керамики')
  await page.getByLabel('Текст ссылки').fill('Каталог')
  await page
    .getByRole('textbox', { name: 'Ссылка', exact: true })
    .fill('/catalog')
  await page.getByLabel('Загрузить файл — Изображение блока').setInputFiles({
    name: 'tile.webp',
    mimeType: 'image/webp',
    buffer: Buffer.from('synthetic fixture'),
  })
  await expect(
    page.getByRole('button', {
      name: 'Сохранить черновик блоков',
      exact: true,
    }),
  ).toBeDisabled()
  await page
    .getByLabel('Описание загружаемого изображения')
    .fill('Новое изображение керамики')
  await page
    .getByRole('button', { name: 'Загрузить и выбрать', exact: true })
    .click()
  await expect(page.getByRole('alert')).toHaveText(
    'Не удалось загрузить файл. Повторите попытку.',
  )
  await page
    .getByRole('button', { name: 'Загрузить и выбрать', exact: true })
    .click()
  await expect(
    page.getByRole('button', { name: 'Загрузить и выбрать', exact: true }),
  ).toBeDisabled()
  await page
    .getByRole('link', { name: 'Главная', exact: false })
    .filter({ hasText: '/' })
    .last()
    .click()
  await expect(page).toHaveURL(/page=7/)
  state.release()
  await expect(
    page.getByText(
      'Файл загружен и выбран. Сохраните черновик, чтобы закрепить выбор.',
    ),
  ).toBeVisible()
  await expect(page.getByRole('img', { name: 'Образцы плитки' })).toBeVisible()
  await page
    .getByRole('button', { name: 'Поднять блок 2', exact: true })
    .click()
  await page
    .getByRole('checkbox', { name: 'Показывать блок на сайте', exact: true })
    .focus()
  await page
    .getByRole('checkbox', { name: 'Показывать блок на сайте', exact: true })
    .press('Space')
  state.failNextSave()
  await page
    .getByRole('button', { name: 'Сохранить черновик блоков', exact: true })
    .click()
  await expect(page.getByRole('alert')).toHaveText('Проверьте настройки блока.')
  await expect(page.getByRole('textbox', { name: /^Заголовок/ })).toHaveValue(
    'Керамические материалы для сложных архитектурных решений и современных интерьеров',
  )
  await page
    .getByRole('button', { name: 'Сохранить черновик блоков', exact: true })
    .click()
  await expect(
    page.getByText(
      'Черновик блоков сохранён. Для обновления сайта опубликуйте страницу.',
    ),
  ).toBeVisible()
  expect(state.published()).toBe(firstPublished)
  expect(state.stored().blocks?.map((block) => block.type)).toEqual([
    'about',
    'text',
  ])
  expect(state.stored().blocks?.[0]?.enabled).toBe(false)
  expect(state.stored().blocks?.[0]?.data).toMatchObject({
    image_media_id: 9,
    description: 'История и материалы',
  })
  expect(state.stored().blocks?.[1]?.data).toMatchObject({
    body: 'Изменённая история компании',
  })
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    expect(
      await page.evaluate(
        () =>
          document.documentElement.scrollWidth >
          document.documentElement.clientWidth,
      ),
      `overflow ${width}`,
    ).toBe(false)
    await page.screenshot({
      path: test.info().outputPath(`blocks-${width}.png`),
      fullPage: true,
      animations: 'disabled',
    })
  }
  await page
    .getByRole('button', { name: 'Опубликовать блоки страницы', exact: true })
    .click()
  await expect(
    page.getByText('Сохранённый черновик опубликован.', { exact: true }),
  ).toBeVisible()
  expect(state.published()).toBe(JSON.stringify(state.stored().blocks))
  await page
    .getByRole('button', { name: 'Удалить блок 1', exact: true })
    .click()
  await page
    .getByRole('dialog', { name: 'Удалить блок?' })
    .getByRole('button', { name: 'Удалить', exact: true })
    .click()
  await expect(
    page.getByRole('region', { name: 'Настройки: Текст' }),
  ).toBeVisible()
  await expect(page.getByRole('textbox', { name: /^Текст блока/ })).toHaveValue(
    'Изменённая история компании',
  )
})

test('content-only editor hides media management and preserves selected resource', async ({
  page,
}) => {
  const state = await fixture(page, ['content.manage'])
  state.stored().blocks = [
    {
      id: 'about',
      type: 'about',
      enabled: true,
      data: {
        eyebrow: 'О нас',
        title: 'Компания',
        description: 'Описание',
        image_url: '',
        image_media_id: 5,
        image_alt: 'Керамика',
        link_label: 'Каталог',
        link_url: '/catalog',
      },
    },
  ]
  await page.goto('/content?page=7')
  await expect(
    page
      .getByRole('region', { name: 'Настройки: О проекте' })
      .getByText(
        'Выбор и загрузка доступны сотруднику с правом управления медиа.',
        { exact: false },
      ),
  ).toBeVisible()
  await expect(page.getByRole('img', { name: 'Образцы плитки' })).toBeVisible()
  expect(state.mediaReads()).toBe(1)
  await expect(page.locator('input[type=file]')).toHaveCount(0)
  await expect(
    page.getByRole('button', { name: 'Изображение блока', exact: true }),
  ).toHaveCount(0)
  await page.getByRole('textbox', { name: /^Заголовок/ }).fill('Новое название')
  await page
    .getByRole('button', { name: 'Сохранить черновик блоков', exact: true })
    .click()
  await expect(
    page.getByText(
      'Черновик блоков сохранён. Для обновления сайта опубликуйте страницу.',
    ),
  ).toBeVisible()
  expect(state.stored().blocks?.[0]?.data).toMatchObject({ image_media_id: 5 })
})

for (const type of ['categories', 'materials'] as const)
  test(`banner code edits and reordering preserve the selected upload file (${type})`, async ({
    page,
  }) => {
    const state = await fixture(page)
    const categories = blankBlockData()[type]
    categories.items = [{ ...blankBlockData().categories.items[0]! }]
    categories.items[0]!.id = 'tile'
    categories.items[0]!.name = 'Плитка'
    categories.items.push({
      ...categories.items[0]!,
      id: 'mosaic',
      name: 'Мозаика',
    })
    state.stored().blocks = [
      { id: type, type, enabled: true, data: categories },
    ]
    await page.goto('/content?page=7')
    await page
      .getByLabel('Загрузить файл — Изображение баннера 1')
      .setInputFiles({
        name: 'selected.webp',
        mimeType: 'image/webp',
        buffer: Buffer.from('fixture'),
      })
    const code = page.getByRole('textbox', { name: /^Код баннера/ }).first()
    await code.fill('new-tile')
    await expect(
      page.getByRole('textbox', { name: /^Название файла/ }),
    ).toHaveValue('selected')
    await page
      .getByRole('button', { name: 'Опустить баннер 1', exact: true })
      .click()
    await expect(
      page.getByRole('textbox', { name: /^Название файла/ }),
    ).toHaveValue('selected')
    expect(
      await page
        .getByLabel('Загрузить файл — Изображение баннера 2')
        .evaluate((element: HTMLInputElement) => element.files?.[0]?.name),
    ).toBe('selected.webp')
    await expect(
      page.getByRole('button', {
        name: 'Сохранить черновик блоков',
        exact: true,
      }),
    ).toBeDisabled()
    await page
      .getByRole('button', { name: 'Отменить выбор файла', exact: true })
      .click()
    await expect(
      page.getByRole('button', {
        name: 'Сохранить черновик блоков',
        exact: true,
      }),
    ).toBeEnabled()
  })

test('banner grid and menu save, publish and reload independent content', async ({
  page,
}) => {
  const state = await fixture(page)
  const data = blankBlockData()
  const item = {
    id: 'shared-code',
    name: 'Баннер сетки',
    short_description: 'Краткое описание',
    description: 'Подробное описание',
    image_url: null,
    image_media_id: null,
    image_alt: 'Описание изображения',
  }
  data.categories.items = [{ ...item }]
  data.materials.items = [{ ...item, name: 'Баннер меню' }]
  state.stored().title = 'Главная'
  state.stored().slug = 'home'
  state.stored().blocks = [
    {
      id: 'categories',
      type: 'categories',
      enabled: true,
      data: data.categories,
    },
    { id: 'materials', type: 'materials', enabled: true, data: data.materials },
  ]
  await page.route('**/api/v1/admin/home-page', (route) =>
    route.fulfill({ json: { data: { page_id: 7 } } }),
  )
  await page.route('http://localhost:3000/preview/home', (route) =>
    route.fulfill({
      contentType: 'text/html',
      body: '<html lang="ru"></html>',
    }),
  )
  await page.goto('/content?page=home&block=categories')
  const grid = page.getByRole('region', { name: 'Настройки: Сетка баннеров' })
  const menu = page.getByRole('region', { name: 'Настройки: Меню баннеров' })
  const selectGrid = () =>
    page.getByRole('button', { name: '1. Сетка баннеров', exact: true }).click()
  const selectMenu = () =>
    page.getByRole('button', { name: '2. Меню баннеров', exact: true }).click()
  await grid
    .getByRole('textbox', { name: /^Название(?: |$)/ })
    .fill('Изменённая сетка')
  await grid
    .getByRole('textbox', { name: /^Ссылка баннера/ })
    .fill('/catalog?from=grid')
  await selectMenu()
  await expect(
    menu.getByRole('textbox', { name: /^Ссылка баннера/ }),
  ).toHaveCount(1)
  await expect(
    menu.getByRole('textbox', { name: /^Название(?: |$)/ }),
  ).toHaveValue('Баннер меню')
  await menu
    .getByRole('textbox', { name: /^Название(?: |$)/ })
    .fill('Независимое меню')
  await selectGrid()
  await expect(
    grid.getByRole('textbox', { name: /^Название(?: |$)/ }),
  ).toHaveValue('Изменённая сетка')
  const previousPublished = state.published()
  await page
    .getByRole('button', { name: 'Сохранить черновик блоков', exact: true })
    .click()
  await expect(
    page.getByText(
      'Черновик блоков сохранён. Для обновления сайта опубликуйте страницу.',
    ),
  ).toBeVisible()
  expect(state.published()).toBe(previousPublished)
  await page.reload()
  await expect(
    grid.getByRole('textbox', { name: /^Ссылка баннера/ }),
  ).toHaveValue('/catalog?from=grid')
  await expect(
    grid.getByRole('textbox', { name: /^Название(?: |$)/ }),
  ).toHaveValue('Изменённая сетка')
  await selectMenu()
  await expect(
    menu.getByRole('textbox', { name: /^Название(?: |$)/ }),
  ).toHaveValue('Независимое меню')
  await page
    .getByRole('button', { name: 'Опубликовать блоки страницы', exact: true })
    .click()
  await expect.poll(() => state.published()).toContain('Независимое меню')
  expect(state.published()).toContain('Изменённая сетка')
  expect(state.published()).toContain('/catalog?from=grid')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    for (const [name, select, region] of [
      ['grid', selectGrid, grid],
      ['menu', selectMenu, menu],
    ] as const) {
      await select()
      await expect(region).toBeVisible()
      expect(
        await page.evaluate(
          () =>
            document.documentElement.scrollWidth <=
            document.documentElement.clientWidth,
        ),
      ).toBe(true)
      await page.screenshot({
        path: test.info().outputPath(`banner-${name}-${width}.png`),
        fullPage: true,
        animations: 'disabled',
      })
    }
  }
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  // Unlike the grid, a menu may be intentionally empty and stays independent.
  await menu
    .getByRole('button', { name: 'Удалить баннер 1', exact: true })
    .click()
  await expect(menu.getByRole('status')).toContainText('Баннеров пока нет')
  await selectGrid()
  await expect(
    grid.getByRole('textbox', { name: /^Название(?: |$)/ }),
  ).toHaveValue('Изменённая сетка')
})

test('menu notes and links save separately for each banner without editing the legacy shared note', async ({
  page,
}) => {
  const state = await fixture(page)
  const data = blankBlockData()
  const menuData = data.materials
  menuData.note = 'Прежнее общее примечание'
  menuData.items = [
    {
      ...data.categories.items[0]!,
      id: 'first',
      name: 'Первый баннер',
      note: 'Первое примечание',
      link_url: '/catalog/first',
    },
    {
      ...data.categories.items[0]!,
      id: 'second',
      name: 'Второй баннер',
      note: 'Второе примечание',
      link_url: '/catalog/second',
    },
  ]
  state.stored().blocks = [
    { id: 'menu', type: 'materials', enabled: true, data: menuData },
  ]
  await page.goto('/content?page=7&block=menu')
  const menu = page.getByRole('region', { name: 'Настройки: Меню баннеров' })
  const notes = menu.getByRole('textbox', {
    name: 'Примечание под баннером',
    exact: true,
  })
  const links = menu.getByRole('textbox', { name: /^Ссылка баннера/ })
  await expect(notes).toHaveCount(2)
  await expect(notes.nth(0)).toHaveValue('Первое примечание')
  await expect(notes.nth(1)).toHaveValue('Второе примечание')
  await notes.nth(0).fill('Индивидуальное первое примечание')
  await notes.nth(1).fill('')
  await links.nth(0).fill('/catalog?from=menu-first')
  await links.nth(1).fill('https://example.test/menu-second')
  await page
    .getByRole('button', { name: 'Сохранить черновик блоков', exact: true })
    .click()
  await expect(
    page.getByText(
      'Черновик блоков сохранён. Для обновления сайта опубликуйте страницу.',
    ),
  ).toBeVisible()
  await page.reload()
  await expect(notes.nth(0)).toHaveValue('Индивидуальное первое примечание')
  await expect(notes.nth(1)).toHaveValue('')
  await expect(links.nth(0)).toHaveValue('/catalog?from=menu-first')
  await expect(links.nth(1)).toHaveValue('https://example.test/menu-second')
  expect(state.stored().blocks?.[0]?.data).toMatchObject({
    note: 'Прежнее общее примечание',
  })
  await page.screenshot({
    path: test.info().outputPath('menu-individual-fields.png'),
    fullPage: true,
    animations: 'disabled',
  })
})

test('homepage block URL restores selection and preserves canonical block edits', async ({
  page,
}) => {
  const state = await fixture(page)
  state.stored().slug = 'home'
  state.stored().title = 'Главная'
  const data = blankBlockData()
  state.stored().blocks = [
    {
      id: 'promo',
      type: 'promo',
      enabled: true,
      data: { ...data.promo, title: 'Исходное промо' },
    },
    ...state.stored().blocks!,
  ]
  const home = {
    page_id: 7,
    is_published: true,
    has_unpublished_changes: false,
    published_at: null,
    hero_slider_id: null,
    hero_slides: [],
    header: {
      topbar_left: '',
      topbar_right: '',
      logo_media_id: null,
      logo_alt: '',
      logo_url: null,
      navigation: [],
    },
    footer: {
      tagline: '',
      explore_links: [],
      message: { eyebrow: '', text: '', link_label: '', link_url: '' },
      bottom_left: '',
      bottom_right: '',
    },
    marquee: data.marquee,
    categories: data.categories,
    materials: data.materials,
    about: data.about,
    guide: data.guide,
    seo: {
      title: 'Главная',
      description: '',
      og_title: '',
      og_description: '',
      og_image_url: '',
      og_image_media_id: null,
    },
  }
  await page.route('**/api/v1/admin/home-page', (route) => {
    if (route.request().method() === 'PATCH') {
      const payload = route.request().postDataJSON()
      const block = state
        .stored()
        .blocks!.find((item) => item.type === 'promo')!
      if (block.type === 'promo') block.data = payload.promo
    }
    const promo = state
      .stored()
      .blocks!.find((block) => block.type === 'promo')!.data
    return route.fulfill({ json: { data: { ...home, promo } } })
  })
  await page.goto('/content?page=home&editor=blocks&block=promo')
  await expect(
    page.getByRole('region', { name: 'Настройки: Промо' }),
  ).toBeVisible()
  await page
    .getByRole('textbox', { name: /^Заголовок/ })
    .fill('Редактированное промо')
  let dialogs = 0
  page.on('dialog', (dialog) => {
    dialogs++
    void dialog.dismiss()
  })
  await page.getByRole('button', { name: '2. Текст', exact: true }).click()
  await page.getByRole('button', { name: '1. Промо', exact: true }).click()
  expect(dialogs).toBe(0)
  await expect(page.getByRole('textbox', { name: /^Заголовок/ })).toHaveValue(
    'Редактированное промо',
  )
  await page
    .getByRole('button', { name: 'Сохранить черновик блоков', exact: true })
    .click()
  await expect(
    page.getByText(
      'Черновик блоков сохранён. Для обновления сайта опубликуйте страницу.',
    ),
  ).toBeVisible()
  await page.reload()
  await expect(
    page.getByRole('region', { name: 'Настройки: Промо' }),
  ).toBeVisible()
  await expect(page.getByRole('textbox', { name: /^Заголовок/ })).toHaveValue(
    'Редактированное промо',
  )
  await expect(
    page.getByRole('button', { name: 'Блоки и порядок', exact: true }),
  ).toHaveCount(0)
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  await page.screenshot({
    path: test.info().outputPath('home-blocks.png'),
    fullPage: true,
    animations: 'disabled',
  })
})

test('home block and SEO files retain independent pending guards until both are discarded', async ({
  page,
}) => {
  const state = await fixture(page)
  const data = blankBlockData()
  state.stored().slug = 'home'
  state.stored().title = 'Главная'
  state.stored().blocks = [
    { id: 'about', type: 'about', enabled: true, data: data.about },
  ]
  state.stored().seo = {
    title: 'Главная',
    description: '',
    og_title: '',
    og_description: '',
    og_image_url: '',
    og_image_media_id: null,
  }
  await page.route('**/api/v1/admin/home-page', (route) =>
    route.fulfill({ json: { data: { page_id: 7 } } }),
  )
  await page.goto('/content?page=home&block=about')
  const block = page.getByRole('region', { name: 'Настройки: О проекте' })
  const seo = page.getByRole('group', { name: 'SEO страницы' })
  const selectTab = async (name: string) =>
    page.getByRole('tab', { name, exact: true }).click()
  await block
    .getByRole('textbox', { name: /^Заголовок/ })
    .fill('Новый заголовок блока')
  const save = page.getByRole('button', {
    name: 'Сохранить черновик блоков',
    exact: true,
  })
  await expect(save).toBeEnabled()
  const file = {
    name: 'preview.png',
    mimeType: 'image/png',
    buffer: Buffer.from('selected-file'),
  }
  for (const first of ['seo', 'block']) {
    await selectTab('Блоки')
    await block
      .getByLabel('Загрузить файл — Изображение блока')
      .setInputFiles(file)
    await selectTab('SEO')
    await seo
      .getByLabel('Загрузить файл — Изображение Open Graph')
      .setInputFiles(file)
    await expect(save).toBeDisabled()
    await selectTab(first === 'seo' ? 'SEO' : 'Блоки')
    await (first === 'seo' ? seo : block)
      .getByRole('button', { name: 'Отменить выбор файла', exact: true })
      .click()
    await expect(save).toBeDisabled()
    await selectTab(first === 'seo' ? 'Блоки' : 'SEO')
    await expect(
      (first === 'seo' ? block : seo).getByRole('button', {
        name: 'Отменить выбор файла',
        exact: true,
      }),
    ).toBeVisible()
    await expect(
      page.getByText('Несохранённые изменения ещё не вошли в предпросмотр.'),
    ).toBeVisible()
    await selectTab(first === 'seo' ? 'Блоки' : 'SEO')
    await (first === 'seo' ? block : seo)
      .getByRole('button', { name: 'Отменить выбор файла', exact: true })
      .click()
    await expect(save).toBeEnabled()
  }
  await page.screenshot({
    path: test.info().outputPath('home-independent-files.png'),
    fullPage: true,
  })
})

test('inspector tabs preserve block edits and page settings in one saved draft', async ({
  page,
}) => {
  const state = await fixture(page)
  await page.goto('/content?page=7')
  await page
    .getByRole('textbox', { name: /^Текст блока/ })
    .fill('Несохранённый текст блока')
  await page.getByRole('tab', { name: 'Настройки', exact: true }).click()
  await page
    .getByRole('textbox', { name: /^Название страницы/ })
    .fill('Новое название страницы')
  await page.getByRole('tab', { name: 'Блоки', exact: true }).click()
  await expect(page.getByRole('textbox', { name: /^Текст блока/ })).toHaveValue(
    'Несохранённый текст блока',
  )
  await page
    .getByRole('tab', { name: 'Блоки', exact: true })
    .press('ArrowRight')
  await expect(
    page.getByRole('tab', { name: 'Настройки', exact: true }),
  ).toBeFocused()
  await expect(
    page.getByRole('textbox', { name: /^Название страницы/ }),
  ).toHaveValue('Новое название страницы')
  await page
    .getByRole('button', { name: 'Сохранить черновик блоков', exact: true })
    .click()
  await expect.poll(() => state.stored().title).toBe('Новое название страницы')
  expect(state.stored().blocks?.[0]?.data).toMatchObject({
    body: 'Несохранённый текст блока',
  })
  expect(state.published()).not.toBe(JSON.stringify(state.stored().blocks))
})
