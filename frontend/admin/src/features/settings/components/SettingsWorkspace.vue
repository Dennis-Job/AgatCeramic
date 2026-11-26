<script setup lang="ts">
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import { useSettingsWorkspace } from '../composables/useSettingsWorkspace'
import ComplianceApprovalsSection from './ComplianceApprovalsSection.vue'
import LegalDocumentsSection from './LegalDocumentsSection.vue'
import SellerDetailsSection from './SellerDetailsSection.vue'

const {
  seller,
  legal,
  compliance,
  policyOptions,
  documentVersion,
  loading,
  loaded,
  busyAction,
  error,
  success,
  load,
} = useSettingsWorkspace()
</script>

<template>
  <AdminWorkspace mode="form" class="space-y-6">
    <PageHeader
      eyebrow="Контент"
      title="Настройки сайта"
      description="Контакты, реквизиты, юридические документы и внутренние согласования."
    />
    <UiNotification v-if="error"
      >{{ error }}
      <UiButton v-if="!loaded" variant="surface" size="sm" @click="load"
        >Повторить загрузку</UiButton
      ></UiNotification
    >
    <UiNotification v-if="success" tone="success">{{ success }}</UiNotification>
    <UiLoadingState v-if="loading" label="Загрузка настроек…" />
    <template v-else-if="loaded">
      <SellerDetailsSection :state="seller" :busy-action="busyAction" />
      <LegalDocumentsSection :state="legal" :busy-action="busyAction" />
      <ComplianceApprovalsSection
        :state="compliance"
        :busy-action="busyAction"
        :policy-options="policyOptions"
        :document-version="documentVersion"
        @preview="legal.openPreview"
      />
    </template>
  </AdminWorkspace>
</template>
