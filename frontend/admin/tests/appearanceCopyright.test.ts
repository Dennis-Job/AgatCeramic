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

let responseYear: number
let server: SiteAppearance
let writes: Record<string, Record<string, unknown>>[]
beforeEach(() => {
  responseYear = 2026
  writes = []
  server = {
    has_unpublished_changes: false,
    header: {
      topbar_left: '',
      topbar_right: 'Москва',
      logo_media_id: null,
      logo_url: null,
      logo_alt: 'AgatCeramic',
      navigation: [{ label: 'Каталог', to: '/catalog' }],
    },
    footer: {
      tagline: 'Слоган',
      explore_links: [],
      message: {
        eyebrow: 'AgatCeramic',
        text: 'Текст',
        link_label: 'Каталог',
        link_url: '/catalog',
      },
      bottom_left: '© 2026 AgatCeramic',
      bottom_right: 'Плитка',
    },
  }
  transport.apiFetch
    .mockReset()
    .mockImplementation(async (_path: string, options?: RequestInit) => {
      if (options?.method === 'PATCH') {
        const payload = JSON.parse(String(options.body)) as Record<
          string,
          Record<string, unknown>
        >
        writes.push(payload)
        for (const section of ['header', 'footer'] as const) {
          if (payload[section]) Object.assign(server[section], payload[section])
        }
        server.has_unpublished_changes = true
      }
      server.footer.bottom_left = `© ${responseYear} AgatCeramic`
      return new Response(JSON.stringify({ data: server }), { status: 200 })
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
  return { wrapper, workspace }
}

test('a server year change refreshes the read-only copyright without creating an unsaved footer', async () => {
  const { wrapper, workspace } = await editor()
  responseYear = 2027
  workspace.content.value!.header.topbar_right = 'Новая шапка'
  await workspace.save('header')
  expect(workspace.content.value!.footer.bottom_left).toBe('© 2027 AgatCeramic')
  expect(workspace.isDirty('footer')).toBe(false)
  expect(workspace.dirty.value).toBe(false)
  wrapper.unmount()
})

test('refreshing copyright preserves actual unsaved footer edits', async () => {
  const { wrapper, workspace } = await editor()
  workspace.content.value!.footer.tagline = 'Локальный черновик'
  workspace.content.value!.header.topbar_right = 'Новая шапка'
  responseYear = 2027
  await workspace.save('header')
  expect(workspace.content.value!.footer.tagline).toBe('Локальный черновик')
  expect(workspace.isDirty('footer')).toBe(true)
  expect(workspace.content.value!.footer.bottom_left).toBe('© 2027 AgatCeramic')
  wrapper.unmount()
})

test('saving editable footer fields omits the server-generated copyright', async () => {
  const { wrapper, workspace } = await editor()
  workspace.content.value!.footer.bottom_right = 'Новая подпись'
  await workspace.save('footer')
  expect(writes[0]?.footer).not.toHaveProperty('bottom_left')
  expect(writes[0]?.footer).toHaveProperty('bottom_right', 'Новая подпись')
  expect(workspace.dirty.value).toBe(false)
  wrapper.unmount()
})
