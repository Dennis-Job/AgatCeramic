import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { beforeEach, expect, test, vi } from 'vitest'
import { useSettingsWorkspace } from '../src/features/settings/composables/useSettingsWorkspace'
import * as settingsApi from '../src/features/settings/services/settings'

vi.mock('../src/features/settings/services/settings', () => ({
  getSettings: vi.fn(),
  getLegalDocuments: vi.fn(),
  getComplianceApprovals: vi.fn(),
  saveSettings: vi.fn(),
  createComplianceApproval: vi.fn(),
  createLegalDocument: vi.fn(),
  publishLegalDocument: vi.fn(),
}))

async function workspace(phones: string[]) {
  vi.mocked(settingsApi.getSettings).mockResolvedValue({ phones } as Awaited<
    ReturnType<typeof settingsApi.getSettings>
  >)
  let state!: ReturnType<typeof useSettingsWorkspace>
  mount(
    defineComponent({
      setup() {
        state = useSettingsWorkspace()
        return () => null
      },
    }),
  )
  await flushPromises()
  return state
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(settingsApi.getLegalDocuments).mockResolvedValue([])
  vi.mocked(settingsApi.getComplianceApprovals).mockResolvedValue([])
  vi.mocked(settingsApi.saveSettings).mockImplementation(async (payload) => ({
    ...payload,
    updated_at: null,
  }))
})

test('saves one canonical phone and allows an empty optional phone', async () => {
  const state = await workspace(['8 (495) 120-45-67'])
  await state.save()
  expect(settingsApi.saveSettings).toHaveBeenLastCalledWith({
    phones: ['+74951204567'],
  })
  state.updatePhone('')
  await state.save()
  expect(settingsApi.saveSettings).toHaveBeenLastCalledWith({ phones: [] })
})

test('blocks unrelated save with multiple legacy phones until an explicit choice', async () => {
  const state = await workspace(['+74951204567', '+79991112233'])
  expect(state.phoneSelectionRequired.value).toBe(true)
  await state.save()
  expect(settingsApi.saveSettings).not.toHaveBeenCalled()
  expect(state.error.value).toContain('Выберите один')
  state.updatePhone('+79991112233')
  await state.save()
  expect(settingsApi.saveSettings).toHaveBeenCalledWith({
    phones: ['+79991112233'],
  })
})

test('does not send an incomplete phone even through a direct save', async () => {
  const state = await workspace([])
  state.updatePhone('+7495')
  await state.save()
  expect(settingsApi.saveSettings).not.toHaveBeenCalled()
  expect(state.error.value).toContain('10 цифр')
})

test('abandoning a partial replacement does not discard multiple legacy phones', async () => {
  const state = await workspace(['+74951204567', '+79991112233'])
  state.updatePhone('+7495')
  state.updatePhone('')
  await state.save()
  expect(state.phoneSelectionRequired.value).toBe(true)
  expect(settingsApi.saveSettings).not.toHaveBeenCalled()
})
