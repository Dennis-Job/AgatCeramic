import AxeBuilder from '@axe-core/playwright'
import { expect, test } from './fixtures'
import type { Banner } from '../src/features/banners/types/banner.types'
import type { Slider } from '../src/features/sliders/types/slider.types'

test('page editor reuses and edits sliders and banners in context with guarded dialogs', async ({
  page,
  browserIssueGuard,
}) => {
  const timestamp = '2026-09-30T10:00:00Z'
  let banner: Banner = {
    id: 10,
    title: 'Общий керамический баннер',
    eyebrow: 'AgatCeramic',
    description: 'Используется на нескольких страницах.',
    image_url: '/fixture-banner.svg',
    legacy_image_url: '/fixture-banner.svg',
    image_media_id: null,
    image_alt: 'Керамическая плитка',
    link_label: '',
    link_url: '',
    is_published: true,
    created_at: timestamp,
    updated_at: timestamp,
  }
  const secondBanner = { ...banner, id: 11, title: 'Мозаика' }
  let slider: Slider = {
    id: 7,
    name: 'Общий слайдер',
    slug: 'shared',
    is_published: true,
    banners: [banner, secondBanner],
    created_at: timestamp,
    updated_at: timestamp,
  }
  const content = {
    id: 7,
    title: 'Материалы',
    slug: 'materials',
    body: '',
    blocks: [
      { id: 'hero', type: 'hero', enabled: true, data: { slider_id: 7 } },
    ],
    seo: {
      title: 'Материалы',
      description: '',
      og_title: 'Материалы',
      og_description: '',
      og_image_url: null,
      og_image_media_id: null,
    },
    is_published: true,
    has_unpublished_changes: false,
    published_at: timestamp,
    created_at: timestamp,
    updated_at: timestamp,
  }
  let failBanner = true
  let mediaRequests = 0
  let pageWrites = 0
  const sliderWrites: number[][] = []
  browserIssueGuard.allowApiError(422, '/api/v1/admin/banners/10')
  await page.route('**/fixture-banner.svg', (route) =>
    route.fulfill({
      contentType: 'image/svg+xml',
      body: '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="180"><rect width="320" height="180" fill="#d4c7ac"/></svg>',
    }),
  )
  await page.route('**/sanctum/csrf-cookie', (route) =>
    route.fulfill({ status: 204 }),
  )
  await page.route('**/api/v1/**', (route) => {
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
            permissions: ['content.manage'],
          },
        },
      })
    if (path.startsWith('/admin/media')) {
      mediaRequests++
      return route.fulfill({
        status: 403,
        json: { error: { message: 'Нет прав' } },
      })
    }
    if (path === '/admin/pages' && method === 'GET')
      return route.fulfill({
        json: {
          data: [content],
          meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
        },
      })
    if (path === '/admin/pages/7' && method === 'GET')
      return route.fulfill({ json: { data: content } })
    if (path.startsWith('/admin/pages') && method !== 'GET') {
      pageWrites++
      return route.fulfill({ json: { data: content } })
    }
    if (path === '/admin/sliders' && method === 'GET')
      return route.fulfill({
        json: {
          data: [slider],
          meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
        },
      })
    if (path === '/admin/sliders/banner-options')
      return route.fulfill({ json: { data: [banner, secondBanner] } })
    if (path === '/admin/sliders' && method === 'POST') {
      slider = {
        ...slider,
        ...route.request().postDataJSON(),
        id: 8,
        banners: [],
      }
      return route.fulfill({ status: 201, json: { data: slider } })
    }
    if (
      ['/admin/sliders/7', '/admin/sliders/8'].includes(path) &&
      method === 'PATCH'
    ) {
      const payload = route.request().postDataJSON()
      sliderWrites.push(payload.banner_ids)
      slider = {
        ...slider,
        ...payload,
        banners: payload.banner_ids.map((id: number) =>
          id === banner.id ? banner : secondBanner,
        ),
      }
      return route.fulfill({ json: { data: slider } })
    }
    if (path === '/admin/banners/10' && method === 'PATCH') {
      if (failBanner) {
        failBanner = false
        return route.fulfill({
          status: 422,
          json: { error: { message: 'Повторите сохранение баннера.' } },
        })
      }
      banner = { ...banner, ...route.request().postDataJSON() }
      return route.fulfill({ json: { data: banner } })
    }
    if (path === '/admin/banners' && method === 'POST') {
      banner = { ...banner, ...route.request().postDataJSON(), id: 12 }
      return route.fulfill({ status: 201, json: { data: banner } })
    }
    return route.fulfill({
      status: 404,
      json: { error: { message: `Unknown endpoint ${path}` } },
    })
  })
  await page.goto('/content?page=7')
  await expect(
    page.getByText('Общий слайдер', { exact: true }).last(),
  ).toBeVisible()
  await expect(
    page.getByText(/Сохранение опубликованного ресурса сразу меняет/),
  ).toBeVisible()
  await page
    .getByRole('button', {
      name: 'Редактировать баннер Общий керамический баннер',
    })
    .click()
  const dialog = page.getByRole('dialog', { name: 'Редактировать баннер' })
  await expect(
    dialog.getByRole('button', { name: 'Изображение баннера', exact: true }),
  ).toHaveCount(0)
  await expect(dialog.locator('input[type=file]')).toHaveCount(0)
  await expect(
    dialog.getByText(/Выбор и загрузка доступны сотруднику/),
  ).toBeVisible()
  await dialog.getByLabel(/^Заголовок/).fill('Несохранённый заголовок')
  await dialog.getByRole('button', { name: 'Закрыть окно баннера' }).click()
  const discard = page.getByRole('dialog', {
    name: 'Отменить изменения ресурса?',
  })
  await expect(discard).toBeVisible()
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  await page.keyboard.press('Tab')
  await expect(
    discard.getByRole('button', { name: 'Отменить изменения', exact: true }),
  ).toBeFocused()
  await page.keyboard.press('Tab')
  await expect(
    discard.getByRole('button', { name: 'Отмена', exact: true }),
  ).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(discard).not.toBeVisible()
  await expect(dialog.getByLabel(/^Заголовок/)).toHaveValue(
    'Несохранённый заголовок',
  )
  await dialog.getByLabel(/^Заголовок/).fill('Обновлённый общий баннер')
  await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(dialog.getByRole('alert')).toHaveText(
    'Повторите сохранение баннера.',
  )
  await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(dialog).not.toBeVisible()
  await expect(
    page.getByText('1. Обновлённый общий баннер', { exact: true }),
  ).toBeVisible()
  await page
    .getByRole('button', { name: 'Настроить слайдер и порядок баннеров' })
    .click()
  const sliderDialog = page.getByRole('dialog', {
    name: 'Редактировать слайдер',
  })
  await sliderDialog
    .getByRole('button', { name: 'Поднять баннер Мозаика' })
    .click()
  await expect(
    sliderDialog.getByText('1. Мозаика', { exact: true }),
  ).toBeVisible()
  await sliderDialog
    .getByRole('button', { name: 'Сохранить', exact: true })
    .click()
  await expect(sliderDialog).not.toBeVisible()
  expect(sliderWrites).toEqual([[11, 10]])
  expect(mediaRequests).toBe(0)
  expect(pageWrites).toBe(0)
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    expect(
      await page.evaluate(
        () =>
          document.documentElement.scrollWidth >
          document.documentElement.clientWidth,
      ),
      `horizontal overflow at ${width}px`,
    ).toBe(false)
    await page.screenshot({
      path: test.info().outputPath(`slider-block-${width}.png`),
      fullPage: true,
      animations: 'disabled',
    })
  }
  await page
    .getByRole('button', { name: 'Создать слайдер', exact: true })
    .click()
  const creating = page.getByRole('dialog', { name: 'Новый слайдер' })
  await creating.getByLabel(/^Название/).fill('Слайдер этой страницы')
  await creating.getByLabel('Код (slug)').fill('materials-new')
  await creating.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(creating).not.toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Слайдер блока', exact: true }),
  ).toContainText('Слайдер этой страницы')
  await page
    .getByRole('button', { name: 'Создать баннер для слайдера' })
    .click()
  const creatingBanner = page.getByRole('dialog', { name: 'Новый баннер' })
  await creatingBanner
    .getByLabel(/^Заголовок/)
    .fill('Новый баннер для материалов')
  await creatingBanner
    .getByRole('button', { name: 'Сохранить', exact: true })
    .click()
  await expect(sliderDialog).toBeVisible()
  await expect(
    sliderDialog.getByText(/^1\. Новый баннер для материалов/),
  ).toBeVisible()
  expect(sliderWrites).toHaveLength(1)
  await sliderDialog
    .getByRole('button', { name: 'Сохранить', exact: true })
    .click()
  await expect(sliderDialog).not.toBeVisible()
  expect(sliderWrites).toEqual([[11, 10], [12]])
  expect(pageWrites).toBe(0)
  expect(mediaRequests).toBe(0)
  await expect(
    page.getByRole('button', {
      name: 'Сохранить черновик блоков',
      exact: true,
    }),
  ).toBeEnabled()
})
