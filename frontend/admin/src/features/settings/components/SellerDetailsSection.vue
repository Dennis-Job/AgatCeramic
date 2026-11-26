<script setup lang="ts">
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import { formatPhone } from '../../../utils/phone'
import type { useSellerDetails } from '../composables/useSellerDetails'
import { operatorOptions } from '../settingsOptions'
import type { SettingsAction, SiteSettings } from '../types/settings.types'

const props = defineProps<{
  state: Omit<ReturnType<typeof useSellerDetails>, 'setSettings'>
  busyAction: SettingsAction | null
}>()
const {
  settings,
  phone,
  phoneSelectionRequired,
  legacyPhones,
  updatePhone,
  clearPhone,
  save,
} = props.state

function updateOperator(value: string): void {
  settings.value.operator_type = value as SiteSettings['operator_type']
}
</script>

<template>
  <UiCard aria-labelledby="seller-details-title">
    <template #header>
      <div>
        <h2
          id="seller-details-title"
          class="text-lg font-semibold text-gray-500"
        >
          Реквизиты продавца
        </h2>
        <p class="mt-1 text-sm text-gray-500">
          Контакты для покупателей и сотрудничества публикуются на сайте. Email
          для уведомлений доступен только сотрудникам. Банковские реквизиты
          открываются отдельным переключателем.
        </p>
      </div>
    </template>
    <form class="space-y-5" @submit.prevent="save">
      <div
        class="grid grid-cols-[minmax(0,1fr)] gap-4 md:grid-cols-2 [&>*]:min-w-0"
      >
        <UiField label="Тип оператора"
          ><UiSelect
            :model-value="settings.operator_type ?? ''"
            :options="operatorOptions"
            accessible-name="Тип оператора"
            placeholder="Не выбран"
            clearable
            @update:model-value="updateOperator"
        /></UiField>
        <UiField label="Наименование продавца"
          ><UiInput
            :model-value="settings.seller_name ?? ''"
            maxlength="255"
            @update:model-value="settings.seller_name = $event"
        /></UiField>
        <UiField label="ФИО ИП"
          ><UiInput
            :model-value="settings.entrepreneur_name ?? ''"
            maxlength="255"
            @update:model-value="settings.entrepreneur_name = $event"
        /></UiField>
        <UiField label="ИНН"
          ><UiInput
            :model-value="settings.inn ?? ''"
            inputmode="numeric"
            maxlength="12"
            @update:model-value="settings.inn = $event"
        /></UiField>
        <UiField label="ОГРНИП"
          ><UiInput
            :model-value="settings.ogrnip ?? ''"
            inputmode="numeric"
            maxlength="15"
            @update:model-value="settings.ogrnip = $event"
        /></UiField>
        <UiField
          label="Email для покупателей"
          help="Публичный адрес для покупателей: шапка сайта, контакты и другие разделы."
          ><UiInput
            :model-value="settings.email ?? ''"
            type="email"
            maxlength="255"
            :disabled="busyAction === 'settings'"
            @update:model-value="settings.email = $event"
        /></UiField>
        <div class="space-y-3">
          <UiField
            label="Телефон"
            help="Введите 10 цифр номера без кода страны."
          >
            <UiInput
              :model-value="phone"
              type="tel"
              @update:model-value="updatePhone"
            />
          </UiField>
          <UiAlert v-if="phoneSelectionRequired" tone="warning" live="polite">
            <span>
              Ранее сохранено несколько телефонов. Выберите один телефон
              магазина или введите новый номер перед сохранением.
            </span>
            <span class="mt-2 flex flex-wrap gap-2">
              <UiButton
                v-for="(number, index) in legacyPhones"
                :key="index"
                variant="surface"
                size="sm"
                @click="updatePhone(number)"
              >
                {{ formatPhone(number) }}
              </UiButton>
              <UiButton variant="surface" size="sm" @click="clearPhone"
                >Убрать телефон</UiButton
              >
            </span>
          </UiAlert>
        </div>
        <UiField
          label="Email для уведомлений"
          help="Служебный адрес для уведомлений о новых заявках: обратный звонок, сотрудничество и email. На сайте не публикуется."
          ><UiInput
            :model-value="settings.notification_email ?? ''"
            type="email"
            maxlength="255"
            :disabled="busyAction === 'settings'"
            @update:model-value="settings.notification_email = $event"
        /></UiField>
        <UiField label="Адрес"
          ><UiTextarea
            :model-value="settings.address"
            maxlength="2000"
            @update:model-value="settings.address = $event"
        /></UiField>
        <UiField
          label="Email для сотрудничества"
          help="Публичный адрес для партнёров на странице «Сотрудничество»."
          ><UiInput
            :model-value="settings.partner_email ?? ''"
            type="email"
            maxlength="255"
            :disabled="busyAction === 'settings'"
            @update:model-value="settings.partner_email = $event"
        /></UiField>
      </div>
      <div class="border-t border-gray-100 pt-5">
        <h3 class="mb-4 font-semibold text-gray-500">Банковские реквизиты</h3>
        <div
          class="grid grid-cols-[minmax(0,1fr)] gap-4 md:grid-cols-2 [&>*]:min-w-0"
        >
          <UiField label="Банк"
            ><UiInput
              :model-value="settings.bank_name ?? ''"
              maxlength="255"
              @update:model-value="settings.bank_name = $event"
          /></UiField>
          <UiField label="БИК"
            ><UiInput
              :model-value="settings.bank_bik ?? ''"
              inputmode="numeric"
              maxlength="9"
              @update:model-value="settings.bank_bik = $event"
          /></UiField>
          <UiField label="Расчётный счёт"
            ><UiInput
              :model-value="settings.bank_account ?? ''"
              inputmode="numeric"
              maxlength="20"
              @update:model-value="settings.bank_account = $event"
          /></UiField>
          <UiField label="Корреспондентский счёт"
            ><UiInput
              :model-value="settings.bank_correspondent_account ?? ''"
              inputmode="numeric"
              maxlength="20"
              @update:model-value="
                settings.bank_correspondent_account = $event
              "
          /></UiField>
        </div>
        <UiCheckbox
          v-model:checked="settings.publish_bank_details"
          mode="boolean"
          class="mt-4"
          >Публиковать банковские реквизиты</UiCheckbox
        >
      </div>
      <UiButton
        type="submit"
        :loading="busyAction === 'settings'"
        :disabled="Boolean(busyAction)"
        >Сохранить реквизиты</UiButton
      >
    </form>
  </UiCard>
</template>
