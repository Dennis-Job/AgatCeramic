import { expect, test } from './fixtures'
import AxeBuilder from '@axe-core/playwright'

test('content manager edits the homepage and reaches banner management', async ({
  page,
}) => {
  const content = {
    page_id: 1,
    is_published: true,
    has_unpublished_changes: false,
    published_at: '2026-09-25T10:00:00Z',
    hero_slider_id: 7,
    hero_slides: [
      {
        id: 10,
        eyebrow: 'Керамогранит',
        title: 'Первый слайд',
        description: '',
        image_url: '/images/home/hero-porcelain.webp',
        image_alt: 'Керамогранит',
        link_label: 'Каталог',
        link_url: '/#catalog',
      },
    ],
    header: {
      topbar_left: 'Слева',
      topbar_right: 'Справа',
      logo_media_id: null,
      logo_alt: 'AgatCeramic',
      logo_url: null,
      navigation: [{ label: 'Главная', to: '/#home' }],
    },
    footer: {
      tagline: 'Теглайн',
      explore_links: [],
      message: {
        eyebrow: 'AgatCeramic',
        text: 'Текст',
        link_label: 'Каталог',
        link_url: '/#catalog',
      },
      bottom_left: '© 2026',
      bottom_right: 'AgatCeramic',
    },
    marquee: { topics: ['Плитка'] },
    categories: {
      eyebrow: 'Каталог',
      title: 'Материалы',
      description: 'Описание',
      items: [
        {
          id: 'tile',
          name: 'Плитка',
          short_description: 'Кратко',
          description: 'Подробнее',
          image_url: '/images/home/category-tile.webp',
          image_media_id: null,
          image_alt: 'Плитка',
        },
      ],
    },
    materials: {
      eyebrow: 'Материалы',
      title: 'Фактуры',
      description: 'Описание',
      note: 'Примечание',
    },
    promo: {
      eyebrow: 'AgatCeramic',
      title: 'Старый заголовок',
      description: 'Описание',
      link_label: 'Каталог',
      link_url: '/#catalog',
    },
    about: {
      eyebrow: 'О нас',
      title: 'Проект',
      description: 'Описание',
      image_url: '/images/home/materials.webp',
      image_media_id: null,
      image_alt: 'Образцы',
      link_label: 'Материалы',
      link_url: '/#materials',
    },
    guide: {
      eyebrow: 'Советы',
      title: 'Выбор',
      items: [{ title: 'Формат', description: 'Описание' }],
    },
    seo: {
      title: 'AgatCeramic',
      description: 'Описание',
      og_title: 'AgatCeramic',
      og_description: 'Описание',
      og_image_url: '/images/home/hero-porcelain.webp',
      og_image_media_id: null,
    },
  }
  let patchBody: Record<string, unknown> | null = null
  let publishedPromo = content.promo.title

  await page.route('**/sanctum/csrf-cookie', (route) =>
    route.fulfill({ status: 204 }),
  )
  await page.route('**/api/v1/**', (route) => {
    const url = new URL(route.request().url())
    const path = url.pathname.replace('/api/v1', '')
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
    if (path === '/admin/home-page' && route.request().method() === 'GET')
      return route.fulfill({ json: { data: content } })
    if (path === '/admin/pages' && route.request().method() === 'GET')
      return route.fulfill({
        json: {
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
        },
      })
    if (path === '/admin/home-page' && route.request().method() === 'PATCH') {
      patchBody = route.request().postDataJSON() as Record<string, unknown>
      Object.assign(content, patchBody)
      content.has_unpublished_changes = true
      return route.fulfill({ json: { data: content } })
    }
    if (
      path === '/admin/home-page/publish' &&
      route.request().method() === 'POST'
    ) {
      publishedPromo = content.promo.title
      content.has_unpublished_changes = false
      return route.fulfill({ json: { data: content } })
    }
    if (path === '/admin/sliders')
      return route.fulfill({
        json: {
          data: [
            {
              id: 7,
              name: 'Главная',
              slug: 'home-hero',
              is_published: true,
              banners: [],
            },
          ],
          meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
        },
      })
    if (path === '/admin/banners')
      return route.fulfill({
        json: {
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
        },
      })
    return route.fulfill({
      status: 404,
      json: { error: { message: 'Unknown endpoint' } },
    })
  })

  await page.goto('/home-page')
  await expect(page).toHaveURL(/\/content\?section=pages&page=home/)
  await expect(
    page.getByRole('link', { name: 'Главная сайта', exact: true }),
  ).toHaveCount(0)
  await expect(
    page.getByRole('heading', { name: 'Главная страница' }),
  ).toBeVisible()
  await expect(page.getByText('Первый слайд')).toBeVisible()
  await page.getByRole('button', { name: 'Бегущая строка' }).click()
  await page.getByLabel('Тема 1: Плитка').fill('Керамогранит')
  page.once('dialog', (dialog) => dialog.dismiss())
  await page.getByRole('button', { name: 'Магазины', exact: true }).click()
  await expect(page).toHaveURL(/\/content\?section=pages&page=home/)
  await page.getByRole('button', { name: 'Промо' }).click()
  await page.getByLabel('Заголовок').fill('Новый заголовок')
  await expect(
    page.getByRole('button', { name: 'Опубликовать черновик' }),
  ).toBeDisabled()
  await page.getByRole('button', { name: 'Сохранить черновик раздела' }).click()
  await expect(
    page.getByText(
      'Черновик раздела сохранён. Для обновления сайта опубликуйте страницу.',
    ),
  ).toBeVisible()
  expect(publishedPromo).toBe('Старый заголовок')
  await expect(
    page.getByRole('button', { name: 'Опубликовать черновик' }),
  ).toBeDisabled()
  expect(patchBody).toEqual({
    promo: {
      eyebrow: 'AgatCeramic',
      title: 'Новый заголовок',
      description: 'Описание',
      link_label: 'Каталог',
      link_url: '/#catalog',
    },
  })
  await page.getByRole('button', { name: /Бегущая строка/ }).click()
  await expect(page.getByLabel('Тема 1: Керамогранит')).toHaveValue(
    'Керамогранит',
  )
  await page.reload()
  await page.getByRole('button', { name: 'Промо' }).click()
  await expect(page.getByLabel('Заголовок')).toHaveValue('Новый заголовок')
  await page.getByRole('button', { name: 'Опубликовать черновик' }).click()
  await expect(
    page.getByText('Сохранённый черновик главной страницы опубликован.'),
  ).toBeVisible()
  expect(publishedPromo).toBe('Новый заголовок')
  await page.screenshot({
    path: test.info().outputPath('home-published.png'),
    fullPage: true,
    animations: 'disabled',
  })
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 800 })
    expect(
      await page.evaluate(
        () =>
          document.documentElement.scrollWidth >
          document.documentElement.clientWidth,
      ),
      `homepage overflow at ${width}px`,
    ).toBe(false)
    await page.screenshot({
      path: test.info().outputPath(`home-published-${width}.png`),
      fullPage: true,
      animations: 'disabled',
    })
  }
  await page.getByRole('button', { name: 'Главный слайдер' }).click()
  await page.getByRole('link', { name: 'Управлять баннерами' }).click()
  await expect(page).toHaveURL(/\/content\?section=banners/)
  await expect(page.getByRole('heading', { name: 'Баннеры' })).toBeVisible()
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 800 })
    const overflow = await page.evaluate(
      () =>
        document.documentElement.scrollWidth >
        document.documentElement.clientWidth,
    )
    expect(overflow, `horizontal overflow at ${width}px`).toBe(false)
  }
})
