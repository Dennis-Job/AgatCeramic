import { ref } from 'vue'
import { normalizePhoneInput } from '../../../utils/phone'
import { saveSettings } from '../services/settings'
import type { RunSettingsAction, SiteSettings } from '../types/settings.types'

const emptySettings = (): SiteSettings => ({
  operator_type: null,
  seller_name: null,
  entrepreneur_name: null,
  inn: null,
  ogrnip: null,
  address: null,
  phones: [],
  email: null,
  partner_email: null,
  notification_email: null,
  bank_name: null,
  bank_bik: null,
  bank_account: null,
  bank_correspondent_account: null,
  publish_bank_details: false,
  updated_at: null,
})

export function useSellerDetails(run: RunSettingsAction) {
  const settings = ref<SiteSettings>(emptySettings())
  const phone = ref('')
  const phoneSelectionRequired = ref(false)
  const legacyPhones = ref<string[]>([])

  function setSettings(value: SiteSettings): void {
    settings.value = value
    setLoadedPhones(value.phones)
  }

  function setLoadedPhones(phones: string[]): void {
    legacyPhones.value = [...phones]
    phoneSelectionRequired.value = phones.length > 1
    phone.value = phoneSelectionRequired.value ? '' : (phones[0] ?? '')
  }

  function updatePhone(value: string): void {
    phone.value = value
    if (/^\+7\d{10}$/.test(normalizePhoneInput(value) ?? '')) {
      phoneSelectionRequired.value = false
    }
  }

  function clearPhone(): void {
    phone.value = ''
    phoneSelectionRequired.value = false
  }

  function save() {
    return run(
      async () => {
        if (phoneSelectionRequired.value) {
          throw new Error(
            'Выберите один телефон магазина или введите новый номер перед сохранением.',
          )
        }
        const normalizedPhone = normalizePhoneInput(phone.value)
        if (
          normalizedPhone === null ||
          (normalizedPhone && !/^\+7\d{10}$/.test(normalizedPhone))
        ) {
          throw new Error('Введите 10 цифр номера телефона после +7.')
        }
        const payload = { ...settings.value }
        delete (payload as Partial<SiteSettings>).updated_at
        setSettings(
          await saveSettings({
            ...payload,
            phones: normalizedPhone ? [normalizedPhone] : [],
          }),
        )
      },
      'Реквизиты сохранены.',
      'settings',
    )
  }

  return {
    setSettings,
    settings,
    phone,
    phoneSelectionRequired,
    legacyPhones,
    updatePhone,
    clearPhone,
    save,
  }
}
