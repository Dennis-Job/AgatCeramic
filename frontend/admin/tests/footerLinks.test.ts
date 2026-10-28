import { mount } from '@vue/test-utils'
import { reactive } from 'vue'
import { expect, test, vi } from 'vitest'
import SiteChromeEditor from '../src/features/appearance/components/SiteChromeEditor.vue'
import { getAppearance } from '../src/features/appearance/services/appearance'
import { footerError } from '../src/features/appearance/validation/footer'
import type { SiteAppearance } from '../src/features/appearance/types/appearance.types'

const transport = vi.hoisted(() => ({
  apiFetch: vi.fn(),
  requestCsrfCookie: vi.fn(),
}))
vi.mock('../src/services/auth', () => transport)

function appearance(): SiteAppearance {
  return {
    has_unpublished_changes: false,
    header: {
      topbar_left: '',
      topbar_right: '',
      logo_media_id: null,
      logo_url: null,
      logo_alt: '',
      navigation: [
        {
          label: 'Каталог',
          children: [{ label: 'Весь каталог', to: '/catalog' }],
        },
        { label: 'Дубликат', to: '/catalog' },
        { label: 'Контакты', to: '/contacts' },
      ],
    },
    footer: {
      tagline: '',
      explore_links: [],
      message: { eyebrow: '', text: '', link_label: '', link_url: '' },
      bottom_left: '© 2026 AgatCeramic',
      bottom_right: 'Плитка',
    },
  }
}

test('legacy appearance exposes the current footer navigation without overriding an explicit empty list', async () => {
  const legacy = appearance()
  transport.apiFetch.mockResolvedValueOnce(
    new Response(JSON.stringify({ data: legacy })),
  )
  const result = await getAppearance()
  expect(result.footer).toMatchObject({
    navigation_links: [
      { label: 'Весь каталог', to: '/catalog' },
      { label: 'Контакты', to: '/contacts' },
    ],
    social_links: [],
    bottom_right_url: '',
  })
  transport.apiFetch.mockResolvedValueOnce(
    new Response(
      JSON.stringify({
        data: { ...legacy, footer: { ...legacy.footer, navigation_links: [] } },
      }),
    ),
  )
  expect((await getAppearance()).footer.navigation_links).toEqual([])
})

test('footer lists can be added, edited, reordered and removed independently of the header', async () => {
  const content = reactive(appearance())
  const wrapper = mount(SiteChromeEditor, {
    props: { content, section: 'footer' },
  })
  const card = (title: string) =>
    wrapper
      .findAll('section')
      .find((section) => section.find('h3').text() === title)!
  const navigation = card('Навигация')
  expect(navigation).toBeDefined()
  await navigation
    .findAll('button')
    .find((button) => button.text().includes('Добавить ссылку'))!
    .trigger('click')
  await navigation.findAll('input')[0]!.setValue('Доставка')
  await navigation.findAll('input')[1]!.setValue('/delivery')
  await navigation
    .findAll('button')
    .find((button) => button.text().includes('Добавить ссылку'))!
    .trigger('click')
  await navigation.findAll('input')[2]!.setValue('Контакты')
  await navigation.findAll('input')[3]!.setValue('/contacts')
  await navigation.find('[aria-label="Поднять ссылку 2"]').trigger('click')
  expect(content.footer.navigation_links).toEqual([
    { label: 'Контакты', to: '/contacts' },
    { label: 'Доставка', to: '/delivery' },
  ])
  await navigation.find('[aria-label="Удалить ссылку 1"]').trigger('click')
  expect(content.footer.navigation_links).toEqual([
    { label: 'Доставка', to: '/delivery' },
  ])
  expect(content.header.navigation).toEqual(appearance().header.navigation)
  const social = card('Мы в соцсетях')
  await social
    .findAll('button')
    .find((button) => button.text().includes('Добавить ссылку'))!
    .trigger('click')
  await social.findAll('input')[0]!.setValue('Telegram')
  await social.findAll('input')[1]!.setValue('https://t.me/agat_test')
  expect(content.footer.social_links).toEqual([
    { label: 'Telegram', to: 'https://t.me/agat_test' },
  ])
  const bottom = card('Нижняя строка')
  const url = bottom.findAll('input')[1]!
  await url.setValue('/privacy')
  expect(content.footer.bottom_right_url).toBe('/privacy')
  expect(content.footer.bottom_right).toBe('Плитка')
  await wrapper.setProps({ disabled: true })
  expect(
    social.find('[aria-label="Удалить ссылку 1"]').attributes('disabled'),
  ).toBeDefined()
  expect(
    social
      .findAll('input')
      .every((input) => input.attributes('disabled') !== undefined),
  ).toBe(true)
  wrapper.unmount()
})

test('footer validation accepts empty columns and rejects incomplete, unsafe and excessive links', () => {
  const footer = appearance().footer
  expect(footerError(footer)).toBe('')
  footer.social_links = [{ label: 'Telegram', to: '' }]
  expect(footerError(footer)).toContain('Мы в соцсетях')
  for (const to of [
    'javascript:alert(1)',
    '//example.test',
    '/\\example.test',
    'https://user:pass@example.test',
    '/path with spaces',
  ]) {
    footer.social_links = [{ label: 'Telegram', to }]
    expect(footerError(footer)).not.toBe('')
  }
  footer.social_links = Array.from({ length: 13 }, () => ({
    label: 'Telegram',
    to: 'https://t.me/agat_test',
  }))
  expect(footerError(footer)).toContain('до 12')
  footer.social_links = []
  footer.bottom_right_url = '/privacy'
  expect(footerError(footer)).toBe('')
  footer.bottom_right = ''
  expect(footerError(footer)).toContain('название')
})
