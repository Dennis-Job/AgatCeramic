import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, expect, test, vi } from 'vitest'
import { useAppearance } from '../src/features/appearance/composables/useAppearance'
import type { SiteAppearance } from '../src/features/appearance/types/appearance.types'

const transport = vi.hoisted(() => ({
  apiFetch: vi.fn(),
  requestCsrfCookie: vi.fn(),
}))
vi.mock('../src/services/auth', () => transport)

let server: SiteAppearance
let writes: Record<string, unknown>[]
beforeEach(() => {
  writes = []
  server = {
    has_unpublished_changes: false,
    header: {
      topbar_left: '',
      topbar_right: '',
      logo_media_id: null,
      logo_url: null,
      logo_alt: '',
      navigation: [{ label: 'Каталог', to: '/catalog' }],
    },
    footer: {
      tagline: '',
      explore_links: [],
      message: { eyebrow: '', text: '', link_label: '', link_url: '' },
      bottom_left: '© 2026',
      bottom_right: '',
    },
  }
  transport.apiFetch
    .mockReset()
    .mockImplementation(async (_path: string, options?: RequestInit) => {
      if (options?.method === 'PATCH') {
        const payload = JSON.parse(String(options.body))
        writes.push(payload)
        server = { ...server, ...payload, has_unpublished_changes: true }
      }
      return new Response(JSON.stringify({ data: server }))
    })
})

async function editor() {
  let workspace!: ReturnType<typeof useAppearance>
  const wrapper = mount(
    defineComponent({
      setup() {
        workspace = useAppearance()
        return () => h('div')
      },
    }),
  )
  await flushPromises()
  return { workspace, wrapper }
}

test.each(
  [
    [],
    [{ label: 'Каталог', children: [] }],
    [{ label: 'Каталог', children: [{ label: '', to: '' }] }],
    [
      {
        label: 'Каталог',
        children: [{ label: 'Опасная ссылка', to: 'javascript:alert(1)' }],
      },
    ],
    [
      {
        label: 'Каталог',
        children: [{ label: 'HTTP', to: 'http://example.test' }],
      },
    ],
    [
      {
        label: 'Каталог',
        children: [{ label: 'Безопасность', to: '//example.test' }],
      },
    ],
  ].map((navigation) => ({ navigation })),
)(
  'an invalid active navigation draft is retained and is never submitted: $navigation',
  async ({ navigation }) => {
    const { workspace, wrapper } = await editor()
    workspace.content.value!.header.navigation =
      navigation as SiteAppearance['header']['navigation']
    await workspace.save('header')
    expect(writes).toHaveLength(0)
    expect(workspace.error.value).not.toBe('')
    expect(workspace.content.value!.header.navigation).toEqual(navigation)
    wrapper.unmount()
  },
)

test('mixed navigation is sent and reloaded without changing footer links or group shape', async () => {
  const { workspace, wrapper } = await editor()
  const navigation = [
    { label: 'Главная', to: '/' },
    {
      label: 'Каталог',
      children: [
        { label: 'Все коллекции', to: '/catalog' },
        { label: 'Производитель', to: 'https://example.test/collections' },
      ],
    },
  ]
  workspace.content.value!.header.navigation = navigation
  await workspace.save('header')
  expect(writes[0]).toHaveProperty('header.navigation', navigation)
  await workspace.load()
  expect(workspace.content.value!.header.navigation).toEqual(navigation)
  expect(workspace.content.value!.footer.explore_links).toEqual([])
  wrapper.unmount()
})

test('editing a navigation item after saving creates a new dirty draft without changing the saved baseline', async () => {
  const { workspace, wrapper } = await editor()
  workspace.content.value!.header.navigation = [
    { label: 'Каталог', children: [{ label: 'Весь каталог', to: '/catalog' }] },
  ]
  await workspace.save('header')
  expect(workspace.dirty.value).toBe(false)
  workspace.content.value!.header.navigation = [
    { label: 'Каталог', to: '/catalog' },
  ]
  expect(workspace.isDirty('header')).toBe(true)
  await workspace.save('header')
  expect(writes).toHaveLength(2)
  expect(writes[1]).toHaveProperty('header.navigation', [
    { label: 'Каталог', to: '/catalog' },
  ])
  wrapper.unmount()
})

test('menu text starts with the legacy tagline and saves an independent value including an explicit empty string', async () => {
  const { workspace, wrapper } = await editor()
  expect(workspace.content.value!.header.menu_note).toBe(server.footer.tagline)
  workspace.content.value!.header.menu_note =
    'Ежедневно 10:00–21:00\nПоможем с выбором'
  await workspace.save('header')
  expect(writes[0]).toHaveProperty(
    'header.menu_note',
    'Ежедневно 10:00–21:00\nПоможем с выбором',
  )
  await workspace.load()
  expect(workspace.content.value!.header.menu_note).toContain(
    'Поможем с выбором',
  )
  workspace.content.value!.header.menu_note = ''
  await workspace.save('header')
  expect(workspace.content.value!.header.menu_note).toBe('')
  expect(workspace.dirty.value).toBe(false)
  wrapper.unmount()
})

test('oversized menu text stays in the editor and is not submitted', async () => {
  const { workspace, wrapper } = await editor()
  workspace.content.value!.header.menu_note = 'Т'.repeat(1001)
  await workspace.save('header')
  expect(writes).toHaveLength(0)
  expect(workspace.error.value).toContain('1000')
  expect(workspace.content.value!.header.menu_note).toHaveLength(1001)
  wrapper.unmount()
})

test('saving a legacy footer refreshes the derived menu text without a false unsaved header', async () => {
  const { workspace, wrapper } = await editor()
  workspace.content.value!.footer.tagline = 'Обновлённый слоган'
  await workspace.save('footer')
  expect(workspace.content.value!.header.menu_note).toBe('Обновлённый слоган')
  expect(workspace.dirty.value).toBe(false)
  wrapper.unmount()
})
