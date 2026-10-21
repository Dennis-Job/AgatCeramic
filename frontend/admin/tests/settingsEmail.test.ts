import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { afterEach, expect, test, vi } from 'vitest'
import SettingsWorkspace from '../src/features/settings/components/SettingsWorkspace.vue'
import * as settingsApi from '../src/features/settings/services/settings'

vi.mock('../src/features/settings/services/settings', () => ({
  getSettings: vi.fn(),
  getLegalDocuments: vi.fn().mockResolvedValue([]),
  getComplianceApprovals: vi.fn().mockResolvedValue([]),
  saveSettings: vi.fn(),
  createComplianceApproval: vi.fn(),
  createLegalDocument: vi.fn(),
  publishLegalDocument: vi.fn(),
}))

afterEach(() => vi.clearAllMocks())

test('edits three independent email recipients without replacing the public customer address', async () => {
  const settings = {
    phones: [],
    email: 'customers@example.test',
    partner_email: 'partners@example.test',
    notification_email: 'staff@example.test',
    updated_at: null,
  }
  vi.mocked(settingsApi.getSettings).mockResolvedValue(
    settings as Awaited<ReturnType<typeof settingsApi.getSettings>>,
  )
  let submitted: object | undefined
  let complete!: () => void
  const saved = new Promise<void>((resolve) => {
    complete = resolve
  })
  vi.mocked(settingsApi.saveSettings).mockImplementation(async (payload) => {
    submitted = payload
    await saved
    return { ...payload, updated_at: null }
  })
  const wrapper = mount(SettingsWorkspace, {
    global: { plugins: [createPinia()] },
  })
  await flushPromises()
  const input = (label: string) =>
    wrapper
      .findAll('label')
      .find((field) => field.text().startsWith(label))
      ?.find('input')
  expect(input('Email для покупателей')?.element.value).toBe(
    'customers@example.test',
  )
  expect(input('Email для сотрудничества')?.element.value).toBe(
    'partners@example.test',
  )
  expect(input('Email для уведомлений')?.element.value).toBe(
    'staff@example.test',
  )
  await input('Email для покупателей')!.setValue('shop@example.test')
  await input('Email для сотрудничества')!.setValue('business@example.test')
  await input('Email для уведомлений')!.setValue('callbacks@example.test')
  await wrapper.find('form').trigger('submit')
  await flushPromises()
  for (const label of [
    'Email для покупателей',
    'Email для сотрудничества',
    'Email для уведомлений',
  ]) {
    expect(input(label)?.attributes('disabled')).toBeDefined()
  }
  complete()
  await flushPromises()
  expect(submitted).toEqual({
    phones: [],
    email: 'shop@example.test',
    partner_email: 'business@example.test',
    notification_email: 'callbacks@example.test',
  })
  wrapper.unmount()
})
