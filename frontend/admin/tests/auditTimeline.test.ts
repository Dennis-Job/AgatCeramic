import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import AuditLogWorkspace from '../src/features/audit-log/components/AuditLogWorkspace.vue'
import type { AuditLog } from '../src/features/audit-log/types/auditLog.types'

const api = vi.hoisted(() => ({ getAuditLogs: vi.fn() }))
vi.mock('../src/features/audit-log/services/auditLogs', () => api)
enableAutoUnmount(afterEach)

const log = (overrides: Partial<AuditLog> = {}): AuditLog => ({
  id: 1,
  action: 'product.updated',
  actor: { id: 7, name: 'Анна Смирнова' },
  entity: { type: 'Product', id: 11, name: 'Agat Stone' },
  metadata: { changes: { price: { before: '2400.00', after: '2650.00' } } },
  details: [
    { label: 'Действие', value: 'Изменён товар: SKU 6000011 · Agat Stone' },
    { label: 'SKU', value: '6000011' },
    { label: 'Цена', value: '2400.00 → 2650.00' },
  ],
  occurred_at: '2026-10-16T09:00:00Z',
  ...overrides,
})

beforeEach(() => {
  vi.resetAllMocks()
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date('2026-10-16T12:00:00Z'))
  api.getAuditLogs.mockResolvedValue({
    data: [log()],
    meta: { current_page: 1, last_page: 1, total: 1, per_page: 25 },
  })
})
afterEach(() => vi.useRealTimers())

test('groups events by Moscow date and preserves newest-first chronology without a full-page table', async () => {
  api.getAuditLogs.mockResolvedValue({
    data: [
      log({ id: 3, occurred_at: '2026-10-15T20:30:00Z' }),
      log({ id: 1, occurred_at: '2026-10-16T09:00:00Z' }),
      log({ id: 2, occurred_at: '2026-10-15T21:30:00Z' }),
    ],
    meta: { current_page: 1, last_page: 1, total: 3 },
  })
  const wrapper = mount(AuditLogWorkspace)
  await flushPromises()

  expect(wrapper.find('table').exists()).toBe(false)
  expect(wrapper.findAll('h2').map((heading) => heading.text())).toEqual([
    'Сегодня, 16 октября',
    'Вчера, 15 октября',
  ])
  expect(
    wrapper
      .findAll('article')
      .map((article) => article.attributes('aria-labelledby')),
  ).toEqual([
    'audit-event-1-title',
    'audit-event-2-title',
    'audit-event-3-title',
  ])
  expect(wrapper.text()).toContain('2 события на странице')
  expect(wrapper.text()).toContain('1 событие на странице')
})

test('shows actor, action and SKU once and reveals actual field changes with an accessible disclosure', async () => {
  const wrapper = mount(AuditLogWorkspace)
  await flushPromises()
  const article = wrapper.get('article')
  expect(article.text()).toContain('Анна Смирнова')
  expect(article.text()).toContain('Изменён товар')
  expect(article.text().match(/SKU 6000011/g)).toHaveLength(1)
  expect(article.text()).not.toContain('Было')
  expect(article.text()).not.toContain('product.updated')
  const button = article
    .findAll('button')
    .find((item) => item.text().includes('Показать изменения'))!
  expect(button.exists()).toBe(true)
  expect(button.attributes('aria-expanded')).toBe('false')
  await button.trigger('click')

  expect(button.attributes('aria-expanded')).toBe('true')
  expect(article.get(`#${button.attributes('aria-controls')}`).exists()).toBe(
    true,
  )
  expect(article.text()).toContain('Было')
  expect(article.text()).toContain('Стало')
  expect(article.text()).toContain('2400.00')
  expect(article.text()).toContain('2650.00')
  expect(article.text()).toContain('product.updated')
  await button.trigger('click')
  expect(article.text()).not.toContain('Было')
})

test('keeps old and unknown actions, missing timestamps and employee identity details understandable', async () => {
  api.getAuditLogs.mockResolvedValue({
    data: [
      log({
        action: 'custom.something',
        actor: null,
        occurred_at: 'invalid',
        entity: {
          type: 'User',
          id: 21,
          name: 'Удалённый сотрудник',
          email: 'staff@example.test',
        },
        metadata: null,
        details: [{ label: 'Комментарий', value: 'Текст → со стрелкой' }],
      }),
    ],
    meta: { current_page: 1, last_page: 1, total: 1 },
  })
  const wrapper = mount(AuditLogWorkspace)
  await flushPromises()
  expect(wrapper.text()).toContain('Дата не указана')
  expect(wrapper.text()).toContain('Система')
  expect(wrapper.text()).toContain('Действие в административной панели')
  expect(wrapper.text()).not.toContain('custom.something')
  expect(wrapper.text()).toContain('Удалённый сотрудник')
  const button = wrapper
    .findAll('button')
    .find((item) => item.text().includes('Подробности'))!
  await button.trigger('click')
  expect(wrapper.text()).toContain('custom.something')
  expect(wrapper.text()).toContain('staff@example.test')
  expect(wrapper.text()).toContain('Текст → со стрелкой')
  expect(wrapper.text()).not.toContain('Стало')
})

test('previews useful safe details for events without field changes and bounds long values', async () => {
  api.getAuditLogs.mockResolvedValue({
    data: [
      log({
        id: 10,
        action: 'product.image-uploaded',
        metadata: { values: { is_primary: true } },
        details: [
          { label: 'Главное фото', value: 'Да' },
          { label: 'Файл изображения', value: 'private-file.webp' },
        ],
      }),
      log({
        id: 11,
        action: 'import.completed',
        actor: null,
        entity: null,
        metadata: null,
        details: [
          { label: 'Обработано строк', value: '120' },
          { label: 'Ошибок', value: '0' },
        ],
      }),
      log({
        id: 12,
        action: 'admin-user.created',
        metadata: null,
        details: [
          { label: 'Роли', value: 'Очень длинное название роли '.repeat(30) },
        ],
      }),
    ],
    meta: { current_page: 1, last_page: 1, total: 3 },
  })
  const wrapper = mount(AuditLogWorkspace)
  await flushPromises()
  expect(wrapper.text()).toContain('Главное фото: Да')
  expect(wrapper.text()).toContain('Обработано строк: 120 · Ошибок: 0')
  expect(wrapper.text()).not.toContain('private-file.webp')
  expect(wrapper.text()).not.toContain('Очень длинное название роли')
  const employee = wrapper.get(
    'article[aria-labelledby="audit-event-12-title"]',
  )
  await employee.get('button').trigger('click')
  expect(employee.text()).toContain(
    'Очень длинное название роли '.repeat(30).trim(),
  )
})

test('uses server pagination and preserves loading/error/empty feedback', async () => {
  api.getAuditLogs
    .mockResolvedValueOnce({
      data: [log()],
      meta: { current_page: 1, last_page: 2, total: 26, per_page: 25 },
    })
    .mockResolvedValueOnce({
      data: [log({ id: 26 })],
      meta: { current_page: 2, last_page: 2, total: 26, per_page: 25 },
    })
  const wrapper = mount(AuditLogWorkspace)
  await flushPromises()
  await wrapper.get('button[aria-label="Следующая страница"]').trigger('click')
  await flushPromises()
  expect(api.getAuditLogs).toHaveBeenLastCalledWith(
    expect.objectContaining({ page: 2 }),
  )
  expect(
    wrapper
      .get('button[aria-label="Следующая страница"]')
      .attributes('disabled'),
  ).toBeDefined()
})
