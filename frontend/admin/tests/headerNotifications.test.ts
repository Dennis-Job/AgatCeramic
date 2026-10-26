import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { expect, test, vi } from 'vitest'
import { effectScope, reactive } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import AdminNotifications from '../src/layouts/components/AdminNotifications.vue'
import { useAuthStore } from '../src/stores/auth'
import { useAdminNotifications } from '../src/features/notifications/composables/useAdminNotifications'

vi.mock('../src/features/contacts/services/contacts', () => ({
  getContacts: vi.fn().mockResolvedValue({ data: [], meta: { total: 8 } }),
}))
test('the bell navigates to the notification page with an accessible total and no popup', async () => {
  setActivePinia(createPinia())
  useAuthStore().user = {
    id: 1,
    name: 'Сотрудник',
    email: 'admin@example.test',
    status: 'active',
    last_login_at: null,
    permissions: ['contacts.view'],
  }
  const scope = effectScope()
  const state = scope.run(() => reactive(useAdminNotifications()))!
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/notifications', component: { template: '<div />' } },
    ],
  })
  await router.push('/')
  const wrapper = mount(AdminNotifications, {
    props: { state },
    global: { plugins: [router] },
  })
  await flushPromises()
  const bell = wrapper.get('a[aria-label="Уведомления"]')
  expect(bell.attributes('href')).toBe('/notifications')
  expect(bell.attributes('aria-describedby')).toBe('admin-notifications-count')
  expect(wrapper.text()).toContain('Новых уведомлений: 8')
  await bell.trigger('mouseenter')
  expect(wrapper.find('#admin-notifications-panel').exists()).toBe(false)
  await bell.trigger('click')
  await flushPromises()
  expect(router.currentRoute.value.path).toBe('/notifications')
  wrapper.unmount()
  scope.stop()
})
