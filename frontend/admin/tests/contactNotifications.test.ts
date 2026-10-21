import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { effectScope, nextTick } from 'vue'
import AdminNotifications from '../src/layouts/components/AdminNotifications.vue'
import UiPopover from '../src/components/ui/UiPopover.vue'
import { useAuthStore } from '../src/stores/auth'
import { useContactNotifications } from '../src/features/contacts/composables/useContactNotifications'
import type { Contact } from '../src/features/contacts/types/contact.types'

const api = vi.hoisted(() => ({ getContacts: vi.fn() }))
vi.mock('../src/features/contacts/services/contacts', () => api)
const contact: Contact = {
  id: 42,
  type: 'callback',
  contact: { name: 'Иван', phone: '+79991234567', email: null },
  message: null,
  source: 'site',
  status: 'new',
  assigned_at: null,
  completed_at: null,
  created_at: '2026-10-21T10:00:00Z',
}
const page = (total = 8) => ({
  data: [contact],
  meta: { current_page: 1, last_page: 2, per_page: 5, total },
})
function authorize(id = 1) {
  useAuthStore().user = {
    id,
    name: 'Сотрудник',
    email: 'admin@example.test',
    status: 'active',
    last_login_at: null,
    permissions: ['contacts.view'],
  }
}
beforeEach(() => {
  setActivePinia(createPinia())
  vi.resetAllMocks()
  vi.useFakeTimers()
  api.getContacts.mockResolvedValue(page())
})
afterEach(() => vi.useRealTimers())

test('loads only new callbacks, counts all results, polls and stops on disposal', async () => {
  authorize()
  const scope = effectScope()
  const notifications = scope.run(() => useContactNotifications())!
  await flushPromises()
  expect(api.getContacts).toHaveBeenCalledWith(
    { type: 'callback', status: 'new' },
    { perPage: 5 },
  )
  expect(notifications.total.value).toBe(8)
  expect(notifications.contacts.value).toEqual([contact])
  api.getContacts.mockResolvedValue({ ...page(0), data: [] })
  await vi.advanceTimersByTimeAsync(30_000)
  expect(notifications.contacts.value).toEqual([])
  expect(notifications.total.value).toBe(0)
  scope.stop()
  const calls = api.getContacts.mock.calls.length
  await vi.advanceTimersByTimeAsync(60_000)
  expect(api.getContacts).toHaveBeenCalledTimes(calls)
})

test('does not request without permission and clears private data after permission loss', async () => {
  const scope = effectScope()
  const notifications = scope.run(() => useContactNotifications())!
  await flushPromises()
  expect(api.getContacts).not.toHaveBeenCalled()
  authorize()
  await flushPromises()
  expect(notifications.total.value).toBe(8)
  useAuthStore().user!.permissions = []
  await nextTick()
  expect(notifications.contacts.value).toEqual([])
  expect(notifications.total.value).toBe(0)
  await vi.advanceTimersByTimeAsync(30_000)
  expect(api.getContacts).toHaveBeenCalledTimes(1)
  scope.stop()
})

test.each(['logout', 'different user', 'unmount'])(
  'ignores a late notification response after %s',
  async (change) => {
    let finish!: (result: ReturnType<typeof page>) => void
    api.getContacts.mockReturnValueOnce(
      new Promise((resolve) => {
        finish = resolve
      }),
    )
    authorize()
    const scope = effectScope()
    const notifications = scope.run(() => useContactNotifications())!
    if (change === 'logout') useAuthStore().user = null
    if (change === 'different user') {
      api.getContacts.mockResolvedValue({ ...page(0), data: [] })
      authorize(2)
    }
    if (change === 'unmount') scope.stop()
    await nextTick()
    finish(page())
    await flushPromises()
    expect(notifications.contacts.value).toEqual([])
    expect(notifications.total.value).toBe(0)
    scope.stop()
  },
)

test('shows failures as errors and supports retry', async () => {
  authorize()
  api.getContacts.mockRejectedValueOnce(new Error('Сервер недоступен'))
  const scope = effectScope()
  const notifications = scope.run(() => useContactNotifications())!
  await flushPromises()
  expect(notifications.error.value).toBe('Сервер недоступен')
  expect(notifications.loading.value).toBe(false)
  await notifications.refresh()
  expect(notifications.error.value).toBe('')
  expect(notifications.total.value).toBe(8)
  scope.stop()
})

test('renders real notification links, formatted phone and an accessible total', async () => {
  authorize()
  const wrapper = mount(AdminNotifications, {
    props: { open: true },
    global: {
      stubs: {
        RouterLink: {
          props: ['to'],
          template:
            '<a :href="typeof to === \'string\' ? to : `/contacts?contact=${to.query.contact}`"><slot /></a>',
        },
      },
    },
  })
  await flushPromises()
  expect(
    wrapper
      .get(
        'button[aria-label="Уведомления"][aria-describedby="admin-notifications-count"]',
      )
      .exists(),
  ).toBe(true)
  expect(wrapper.get('a[href="/contacts?contact=42"]').text()).toContain(
    '+7 (999) 123-45-67',
  )
  expect(wrapper.text()).toContain('до смены статуса')
  await wrapper.get('a').trigger('click')
  expect(wrapper.emitted('update:open')?.[0]).toEqual([false])
  wrapper.unmount()
})

test('a background refresh keeps the focused notification link mounted', async () => {
  authorize()
  const wrapper = mount(AdminNotifications, {
    attachTo: document.body,
    props: { open: true },
    global: {
      stubs: {
        RouterLink: {
          props: ['to'],
          template: '<a href="/contacts"><slot /></a>',
        },
      },
    },
  })
  await flushPromises()
  const link = wrapper.findAll('a')[0]!.element as HTMLAnchorElement
  link.focus()
  let finish!: (value: ReturnType<typeof page>) => void
  api.getContacts.mockReturnValueOnce(
    new Promise((resolve) => {
      finish = resolve
    }),
  )
  await vi.advanceTimersByTimeAsync(30_000)
  expect(link.isConnected).toBe(true)
  expect(document.activeElement).toBe(link)
  finish(page())
  await flushPromises()
  expect(document.activeElement).toBe(link)
  wrapper.unmount()
})

test('retry retains focus inside the stable panel while loading and after success', async () => {
  authorize()
  api.getContacts.mockRejectedValueOnce(new Error('Недоступно'))
  const wrapper = mount(AdminNotifications, {
    attachTo: document.body,
    props: { open: true },
    global: {
      stubs: { RouterLink: { template: '<a href="/contacts"><slot /></a>' } },
    },
  })
  await flushPromises()
  const retry = wrapper
    .findAll('button')
    .find((button) => button.text() === 'Повторить')!
  const element = retry.element
  element.focus()
  let finish!: (value: ReturnType<typeof page>) => void
  api.getContacts.mockReturnValueOnce(
    new Promise((resolve) => {
      finish = resolve
    }),
  )
  await retry.trigger('click')
  expect(document.activeElement).toBe(
    wrapper.get('#admin-notifications-panel').element,
  )
  finish({ ...page(0), data: [] })
  await flushPromises()
  expect(document.activeElement).toBe(
    wrapper.get('#admin-notifications-panel').element,
  )
  wrapper.unmount()
})

test('repeated hover-open emissions do not refetch an already open panel', async () => {
  authorize()
  const wrapper = mount(AdminNotifications, {
    props: {
      open: false,
      'onUpdate:open': (value: boolean) => {
        void wrapper.setProps({ open: value })
      },
    },
    global: {
      stubs: { RouterLink: { template: '<a href="/contacts"><slot /></a>' } },
    },
  })
  await flushPromises()
  wrapper.findComponent(UiPopover).vm.$emit('update:open', true)
  await flushPromises()
  const calls = api.getContacts.mock.calls.length
  wrapper.findComponent(UiPopover).vm.$emit('update:open', true)
  await flushPromises()
  expect(api.getContacts).toHaveBeenCalledTimes(calls)
  wrapper.unmount()
})
