<script setup lang="ts">
import { computed, ref } from 'vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import { useAuthStore } from '../../../stores/auth'
import { useSettingsWorkspace } from '../composables/useSettingsWorkspace'
import type {
  ComplianceApproval,
  LegalDocument,
  SiteSettings,
} from '../types/settings.types'

const auth = useAuthStore()
const workspace = useSettingsWorkspace()
const {
  settings,
  phonesText,
  documents,
  approvals,
  policyVersions,
  loading,
  loaded,
  busyAction,
  error,
  success,
  documentForm,
  approvalForm,
  load,
  save,
  addDocument,
  publish,
  addApproval,
} = workspace
const canApprove = computed(() => auth.hasPermission('settings.approve'))
const preview = ref<LegalDocument | null>(null)
const operatorOptions = [
  { value: 'individual_entrepreneur', label: 'Индивидуальный предприниматель' },
  { value: 'legal_entity', label: 'Юридическое лицо' },
]
const documentOptions = [
  { value: 'offer', label: 'Оферта' },
  { value: 'privacy_policy', label: 'Политика ПДн' },
  { value: 'consent', label: 'Текст согласия' },
]
const roleOptions = [
  { value: 'business_owner', label: 'Владелец бизнес-процесса' },
  { value: 'data_protection_officer', label: 'Ответственный за ПДн' },
  { value: 'legal_reviewer', label: 'Юрист / внешний консультант' },
]
const decisionOptions = [
  { value: 'approved', label: 'Согласовано' },
  { value: 'rejected', label: 'Отклонено' },
]
const policyOptions = computed(() =>
  policyVersions.value.map((document) => ({
    value: String(document.id),
    label: `Политика ПДн · ${document.version}`,
  })),
)
function label(
  options: { value: string; label: string }[],
  value: string,
): string {
  return options.find((option) => option.value === value)?.label ?? value
}
function updateOperator(value: string): void {
  settings.value.operator_type = value as SiteSettings['operator_type']
}
function updateDocumentType(value: string): void {
  documentForm.value.type = value as LegalDocument['type']
}
function updateRole(value: string): void {
  approvalForm.value.role = value as ComplianceApproval['role']
}
function updateDecision(value: string): void {
  approvalForm.value.decision = value as ComplianceApproval['decision']
}
function openApprovedDocument(id: number): void {
  preview.value = documents.value.find((document) => document.id === id) ?? null
}
async function confirmPublish(): Promise<void> {
  if (!preview.value) return
  await publish(preview.value.id)
  if (!error.value) preview.value = null
}
</script>

<template>
  <section class="mx-auto admin-page space-y-6">
    <PageHeader
      eyebrow="Контент"
      title="Настройки сайта"
      description="Публичные реквизиты, юридические документы и внутренние согласования."
    />
    <UiAlert v-if="error" role="alert"
      >{{ error }}
      <UiButton v-if="!loaded" variant="ghost" size="sm" @click="load"
        >Повторить загрузку</UiButton
      ></UiAlert
    >
    <p v-if="success" class="text-sm text-success-700" role="status">
      {{ success }}
    </p>
    <UiLoadingState v-if="loading" label="Загрузка настроек…" />
    <template v-else-if="loaded">
      <UiCard>
        <form class="space-y-5" @submit.prevent="save">
          <div>
            <h2 class="text-lg font-semibold text-gray-800">
              Реквизиты продавца
            </h2>
            <p class="mt-1 text-sm text-gray-500">
              Заполненные контактные поля доступны в публичном API. Банковские
              реквизиты открываются отдельным переключателем.
            </p>
          </div>
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
            <UiField label="Email"
              ><UiInput
                :model-value="settings.email ?? ''"
                type="email"
                maxlength="255"
                @update:model-value="settings.email = $event"
            /></UiField>
            <UiField label="Адрес"
              ><UiTextarea
                :model-value="settings.address"
                maxlength="2000"
                @update:model-value="settings.address = $event"
            /></UiField>
            <UiField
              label="Телефоны"
              help="Один номер на строку, не более пяти."
              ><UiTextarea v-model="phonesText"
            /></UiField>
          </div>
          <div class="border-t border-gray-100 pt-5">
            <h3 class="mb-4 font-semibold text-gray-800">
              Банковские реквизиты
            </h3>
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

      <UiCard>
        <h2 class="text-lg font-semibold text-gray-800">
          Юридические документы
        </h2>
        <p class="mt-1 text-sm text-gray-500">
          Новая версия сохраняется черновиком. Публикация делает текст доступным
          публично.
        </p>
        <form class="mt-5 space-y-4" @submit.prevent="addDocument">
          <div
            class="grid grid-cols-[minmax(0,1fr)] gap-4 md:grid-cols-2 [&>*]:min-w-0"
          >
            <UiField label="Документ" required
              ><UiSelect
                :model-value="documentForm.type"
                :options="documentOptions"
                accessible-name="Тип документа"
                @update:model-value="updateDocumentType"
            /></UiField>
            <UiField label="Версия" required
              ><UiInput
                v-model="documentForm.version"
                required
                maxlength="64"
                placeholder="2026-09-25"
            /></UiField>
          </div>
          <UiField label="Текст документа" required
            ><UiTextarea
              v-model="documentForm.body"
              required
              rows="8"
              maxlength="200000"
          /></UiField>
          <UiButton
            type="submit"
            :loading="busyAction === 'document'"
            :disabled="Boolean(busyAction)"
            >Сохранить версию</UiButton
          >
        </form>
        <div
          v-if="documents.length"
          class="mt-6 divide-y divide-gray-100 border-t border-gray-100"
        >
          <article
            v-for="document in documents"
            :key="document.id"
            class="flex flex-wrap items-center justify-between gap-3 py-4"
          >
            <div class="min-w-0">
              <p class="break-words font-medium text-gray-800">
                {{ label(documentOptions, document.type) }} ·
                {{ document.version }}
              </p>
              <p class="text-xs text-gray-500">
                ID {{ document.id }} ·
                {{
                  document.published_at
                    ? `Опубликован ${new Date(document.published_at).toLocaleDateString('ru-RU')}`
                    : 'Черновик'
                }}
              </p>
            </div>
            <UiBadge :tone="document.published_at ? 'success' : 'neutral'">{{
              document.published_at ? 'Опубликован' : 'Черновик'
            }}</UiBadge>
            <UiButton
              variant="secondary"
              size="sm"
              :disabled="Boolean(busyAction)"
              :aria-label="`Просмотреть ${label(documentOptions, document.type)} версии ${document.version}`"
              @click="preview = document"
              >Просмотреть</UiButton
            >
          </article>
        </div>
        <UiEmptyState v-else class="mt-5" label="Версий документов пока нет." />
      </UiCard>
      <UiDialog
        :open="Boolean(preview)"
        labelledby="legal-preview-title"
        panel-class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-xl"
        :close-disabled="Boolean(busyAction)"
        @close="preview = null"
      >
        <h2
          id="legal-preview-title"
          class="text-lg font-semibold text-gray-800"
        >
          {{ preview ? label(documentOptions, preview.type) : '' }} ·
          {{ preview?.version }}
        </h2>
        <p class="mt-1 text-sm text-gray-500">
          {{
            preview?.published_at
              ? 'Опубликованная версия'
              : 'Черновик. Проверьте текст перед публикацией.'
          }}
        </p>
        <div
          class="mt-4 max-h-[50vh] overflow-y-auto rounded-lg border border-gray-200 bg-gray-25 p-4"
        >
          <pre
            class="whitespace-pre-wrap break-words font-sans text-sm text-gray-700"
            >{{ preview?.body }}</pre>
        </div>
        <div class="mt-5 flex flex-wrap justify-end gap-2">
          <UiButton
            variant="secondary"
            :disabled="Boolean(busyAction)"
            @click="preview = null"
            >Закрыть</UiButton
          >
          <UiButton
            v-if="preview && !preview.published_at"
            :loading="busyAction === `publish:${preview.id}`"
            :disabled="Boolean(busyAction)"
            @click="confirmPublish"
            >Опубликовать эту версию</UiButton
          >
        </div>
      </UiDialog>

      <UiCard>
        <h2 class="text-lg font-semibold text-gray-800">
          Согласования ADR-014
        </h2>
        <p class="mt-1 text-sm text-gray-500">
          Внутренний журнал решений. Запись не включает production-сбор ПДн и не
          заменяет внешнюю юридическую проверку.
        </p>
        <form
          v-if="canApprove"
          class="mt-5 space-y-4"
          @submit.prevent="addApproval"
        >
          <div
            class="grid grid-cols-[minmax(0,1fr)] gap-4 md:grid-cols-2 [&>*]:min-w-0"
          >
            <UiField label="Роль согласующего" required
              ><UiSelect
                :model-value="approvalForm.role"
                :options="roleOptions"
                accessible-name="Роль согласующего"
                @update:model-value="updateRole"
            /></UiField>
            <UiField label="ФИО согласующего" required
              ><UiInput
                v-model="approvalForm.reviewer_name"
                required
                maxlength="255"
            /></UiField>
            <UiField label="Решение" required
              ><UiSelect
                :model-value="approvalForm.decision"
                :options="decisionOptions"
                accessible-name="Решение"
                @update:model-value="updateDecision"
            /></UiField>
            <UiField label="Дата решения" required
              ><UiInput v-model="approvalForm.decided_on" type="date" required
            /></UiField>
            <UiField label="Утверждённая версия политики ПДн" required
              ><UiSelect
                v-model="approvalForm.document_version_id"
                :options="policyOptions"
                accessible-name="Версия политики ПДн"
                placeholder="Выберите опубликованную версию"
            /></UiField>
          </div>
          <UiButton
            type="submit"
            :loading="busyAction === 'approval'"
            :disabled="Boolean(busyAction) || !policyVersions.length"
            >Записать решение</UiButton
          >
          <p
            v-if="!policyVersions.length"
            class="text-sm text-gray-500"
            role="status"
          >
            Сначала опубликуйте версию политики ПДн.
          </p>
        </form>
        <div
          v-if="approvals.length"
          class="mt-6 divide-y divide-gray-100 border-t border-gray-100"
        >
          <article
            v-for="approval in approvals"
            :key="approval.id"
            class="flex flex-wrap items-center justify-between gap-2 py-4 text-sm"
          >
            <div class="min-w-0 break-words">
              <p class="font-medium text-gray-800">
                {{ label(roleOptions, approval.role) }} ·
                {{ approval.reviewer_name }}
              </p>
              <p class="text-gray-500">
                {{ approval.decided_on }} · запись #{{ approval.id }}
              </p>
              <UiButton
                variant="ghost"
                size="sm"
                :aria-label="`Просмотреть политику ПДн, документ ${approval.document_version_id}`"
                @click="openApprovedDocument(approval.document_version_id)"
                >Политика ПДн ·
                {{
                  documents.find(
                    (document) => document.id === approval.document_version_id,
                  )?.version ?? `#${approval.document_version_id}`
                }}</UiButton
              >
            </div>
            <UiBadge
              :tone="approval.decision === 'approved' ? 'success' : 'danger'"
              >{{ label(decisionOptions, approval.decision) }}</UiBadge
            >
          </article>
        </div>
        <UiEmptyState
          v-else
          class="mt-5"
          label="Согласования ещё не записаны."
        />
      </UiCard>
    </template>
  </section>
</template>
