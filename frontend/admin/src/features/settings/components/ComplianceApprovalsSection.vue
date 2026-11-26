<script setup lang="ts">
import { computed } from 'vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import { useAuthStore } from '../../../stores/auth'
import type { useComplianceApprovals } from '../composables/useComplianceApprovals'
import { decisionOptions, optionLabel, roleOptions } from '../settingsOptions'
import type {
  ComplianceApproval,
  SettingsAction,
} from '../types/settings.types'

const props = defineProps<{
  state: ReturnType<typeof useComplianceApprovals>
  busyAction: SettingsAction | null
  policyOptions: { value: string; label: string }[]
  documentVersion: (id: number) => string
}>()

const emit = defineEmits<{ preview: [id: number] }>()
const { approvalForm, approvals, addApproval } = props.state
const auth = useAuthStore()
const canApprove = computed(() => auth.hasPermission('settings.approve'))

function updateRole(value: string): void {
  approvalForm.value.role = value as ComplianceApproval['role']
}
function updateDecision(value: string): void {
  approvalForm.value.decision = value as ComplianceApproval['decision']
}
</script>

<template>
  <UiCard aria-labelledby="approvals-title">
    <template #header>
      <div>
        <h2 id="approvals-title" class="text-lg font-semibold text-gray-500">
          Согласования ADR-014
        </h2>
        <p class="mt-1 text-sm text-gray-500">
          Внутренний журнал решений. Запись не включает production-сбор ПДн и не
          заменяет внешнюю юридическую проверку.
        </p>
      </div>
    </template>
    <form v-if="canApprove" class="space-y-4" @submit.prevent="addApproval">
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
        :disabled="Boolean(busyAction) || !policyOptions.length"
        >Записать решение</UiButton
      >
      <p
        v-if="!policyOptions.length"
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
          <p class="font-medium text-gray-500">
            {{ optionLabel(roleOptions, approval.role) }} ·
            {{ approval.reviewer_name }}
          </p>
          <p class="text-gray-500">
            {{ approval.decided_on }} · запись #{{ approval.id }}
          </p>
          <UiButton
            variant="surface"
            size="sm"
            :aria-label="`Просмотреть политику ПДн, документ ${approval.document_version_id}`"
            @click="emit('preview', approval.document_version_id)"
            >Политика ПДн ·
            {{ documentVersion(approval.document_version_id) }}</UiButton
          >
        </div>
        <UiBadge
          :tone="approval.decision === 'approved' ? 'success' : 'danger'"
          >{{ optionLabel(decisionOptions, approval.decision) }}</UiBadge
        >
      </article>
    </div>
    <UiEmptyState v-else class="mt-5" label="Согласования ещё не записаны." />
  </UiCard>
</template>
