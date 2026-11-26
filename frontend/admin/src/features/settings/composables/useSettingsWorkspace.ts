import { computed, onMounted, readonly, ref } from 'vue'
import {
  getComplianceApprovals,
  getLegalDocuments,
  getSettings,
} from '../services/settings'
import type { RunSettingsAction, SettingsAction } from '../types/settings.types'
import { useComplianceApprovals } from './useComplianceApprovals'
import { useLegalDocuments } from './useLegalDocuments'
import { useSellerDetails } from './useSellerDetails'

function message(reason: unknown): string {
  return reason instanceof Error
    ? reason.message
    : 'Не удалось выполнить запрос.'
}

export function useSettingsWorkspace() {
  const loading = ref(true)
  const loaded = ref(false)
  const busyAction = ref<SettingsAction | null>(null)
  const error = ref('')
  const success = ref('')

  function clearFeedback(): void {
    error.value = ''
    success.value = ''
  }

  const run: RunSettingsAction = async (action, notice, actionId) => {
    if (busyAction.value) return false
    busyAction.value = actionId
    clearFeedback()
    try {
      await action()
      success.value = notice
      return true
    } catch (reason) {
      error.value = message(reason)
      return false
    } finally {
      busyAction.value = null
    }
  }

  const actionState = readonly(busyAction)
  const { setSettings, ...seller } = useSellerDetails(run)
  const legal = useLegalDocuments({
    busyAction: actionState,
    run,
    clearFeedback,
  })
  const compliance = useComplianceApprovals(run)
  const policyOptions = computed(() =>
    legal.documents.value
      .filter(
        (document) =>
          document.type === 'privacy_policy' && document.published_at,
      )
      .map((document) => ({
        value: String(document.id),
        label: `Политика ПДн · ${document.version}`,
      })),
  )

  function documentVersion(id: number): string {
    return (
      legal.documents.value.find((document) => document.id === id)?.version ??
      `#${id}`
    )
  }

  async function load(): Promise<void> {
    loading.value = true
    loaded.value = false
    error.value = ''
    try {
      const [loadedSettings, loadedDocuments, loadedApprovals] =
        await Promise.all([
          getSettings(),
          getLegalDocuments(),
          getComplianceApprovals(),
        ])
      setSettings(loadedSettings)
      legal.documents.value = loadedDocuments
      compliance.approvals.value = loadedApprovals
      loaded.value = true
    } catch (reason) {
      error.value = message(reason)
    } finally {
      loading.value = false
    }
  }

  onMounted(load)
  return {
    seller,
    legal,
    compliance,
    policyOptions,
    documentVersion,
    loading,
    loaded,
    busyAction: actionState,
    error,
    success,
    load,
  }
}
