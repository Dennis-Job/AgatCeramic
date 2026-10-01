import type { Page } from './fixtures'
import { mockAdminBaseline } from './adminBaselineApi'
import { blankBlockData } from '../src/features/pages/validation/blocks'

const timestamp = '2026-10-01T10:00:00Z'
const longTitle =
  'Керамическая плитка и керамогранит для уютного дома и просторных загородных помещений'
const meta = { current_page: 1, last_page: 1, per_page: 25, total: 1 }
const contentPage = (id: number, slug: string) => ({
  id,
  slug,
  title: slug === 'home' ? 'Главная' : longTitle,
  body: '',
  blocks: [
    {
      id: 'text',
      type: 'text',
      enabled: true,
      data: { ...blankBlockData().text, title: longTitle, body: longTitle },
    },
  ],
  seo: {
    title: longTitle,
    description: longTitle,
    og_title: '',
    og_description: '',
    og_image_url: '',
    og_image_media_id: null,
  },
  is_published: true,
  has_unpublished_changes: false,
  published_at: timestamp,
  created_at: timestamp,
  updated_at: timestamp,
})

export async function mockContentWorkspace(page: Page) {
  await mockAdminBaseline(page, false, 'default', undefined, {
    '/admin/home-page': { data: { page_id: 1 } },
    '/admin/pages': { data: [contentPage(2, 'about')], meta },
    '/admin/pages/1': { data: contentPage(1, 'home') },
    '/admin/pages/2': { data: contentPage(2, 'about') },
    '/admin/site-appearance': {
      data: {
        has_unpublished_changes: false,
        header: {
          topbar_left: longTitle,
          topbar_right: longTitle,
          logo_media_id: null,
          logo_alt: 'Логотип AgatCeramic',
          logo_url: null,
          navigation: [{ label: longTitle, to: '/catalog' }],
        },
        footer: {
          tagline: longTitle,
          explore_links: [{ label: longTitle, to: '/catalog' }],
          message: {
            eyebrow: 'AgatCeramic',
            text: longTitle,
            link_label: 'Каталог',
            link_url: '/catalog',
          },
          bottom_left: '© 2026 AgatCeramic',
          bottom_right: longTitle,
        },
      },
    },
    '/admin/stores': {
      data: [
        {
          id: 7,
          name: longTitle,
          address: 'Москва, Тестовая улица, дом 7',
          phone: null,
          is_published: true,
          working_hours: Array.from({ length: 7 }, (_, index) => ({
            weekday: index + 1,
            is_closed: true,
            opens_at: null,
            closes_at: null,
          })),
          created_at: timestamp,
          updated_at: timestamp,
        },
      ],
      meta,
    },
  })
}
