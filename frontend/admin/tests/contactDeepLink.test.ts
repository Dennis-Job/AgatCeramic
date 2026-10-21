import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, expect, test, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { useAuthStore } from '../src/stores/auth'
import ContactsWorkspace from '../src/features/contacts/components/ContactsWorkspace.vue'
import ContactsList from '../src/features/contacts/components/ContactsList.vue'
import { useContactsWorkspace } from '../src/features/contacts/composables/useContactsWorkspace'
import type { Contact } from '../src/features/contacts/types/contact.types'

const api = vi.hoisted(() => ({
  getContacts: vi.fn(),
  getContact: vi.fn(),
  getContactStatuses: vi.fn(),
  getContactAssignees: vi.fn(),
  getContactHistory: vi.fn(),
  getContactComments: vi.fn(),
}))
vi.mock('../src/features/contacts/services/contacts', () => api)
const contact = (id: number): Contact => ({
  id,
  type: 'callback',
  contact: {
    name: `Клиент ${id}`,
    phone: '+79991234567',
    email: 'client@example.test',
  },
  message: null,
  source: 'site',
  status: 'new',
  assigned_at: null,
  completed_at: null,
  created_at: '2026-10-21T10:00:00Z',
})
beforeEach(() => {
  setActivePinia(createPinia())
  vi.resetAllMocks()
  useAuthStore().user = {
    id: 1,
    name: 'Сотрудник',
    email: 'admin@example.test',
    status: 'active',
    last_login_at: null,
    permissions: ['contacts.view'],
  }
  api.getContacts.mockResolvedValue({
    data: [],
    meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
  })
  api.getContactStatuses.mockResolvedValue([])
  api.getContact.mockImplementation(async (id: number) => contact(id))
  api.getContactHistory.mockResolvedValue([])
  api.getContactComments.mockResolvedValue([])
  HTMLElement.prototype.scrollIntoView = vi.fn()
})

test('opens a notification deep link even outside the loaded page and follows query changes', async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/contacts', component: ContactsWorkspace }],
  })
  await router.push('/contacts?contact=42')
  const wrapper = mount(ContactsWorkspace, {
    global: { plugins: [router], stubs: { Teleport: true } },
  })
  await flushPromises()
  expect(wrapper.text()).toContain('Обращение #42')
  expect(wrapper.text()).toContain('client@example.test')
  await router.push('/contacts?contact=43')
  await flushPromises()
  expect(wrapper.text()).toContain('Обращение #43')
  wrapper.findComponent(ContactsList).vm.$emit('select', contact(44))
  await flushPromises()
  expect(router.currentRoute.value.query.contact).toBe('44')
  expect(wrapper.text()).toContain('Обращение #44')
  await router.push('/contacts?contact=43')
  await flushPromises()
  expect(wrapper.text()).toContain('Обращение #43')
  await router.push('/contacts?contact=invalid')
  await flushPromises()
  expect(api.getContact).toHaveBeenCalledTimes(4)
  wrapper.unmount()
})

test('a later selection cannot be overwritten by an earlier detail request', async () => {
  let finish!: (value: Contact) => void
  api.getContact.mockReturnValueOnce(
    new Promise((resolve) => {
      finish = resolve
    }),
  )
  const workspace = useContactsWorkspace()
  const pending = workspace.select(contact(42))
  await workspace.select(contact(43))
  finish(contact(42))
  await pending
  expect(workspace.selected.value?.id).toBe(43)
})
