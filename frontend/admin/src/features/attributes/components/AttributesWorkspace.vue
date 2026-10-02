<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Plus } from '@lucide/vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import { useAuthStore } from '../../../stores/auth'
import { attributeTypeOptions } from '../../../utils/attributeTypes'
import { useAttributesCatalog } from '../composables/useAttributesCatalog'
import { useAttributeForm } from '../composables/useAttributeForm'
import type { Attribute } from '../types/attribute.types'
import AttributeFormDialog from './AttributeFormDialog.vue'
import AttributesList from './AttributesList.vue'

const auth = useAuthStore()
const { attributes, groups, pagination, error, loading, load, remove, save } =
  useAttributesCatalog()
const editor = useAttributeForm(save, load)
const {
  open,
  title,
  busy,
  error: formError,
  form,
  manuallyEditedSlug,
  show,
  close,
  updateName,
  updateSlug,
  updateType,
  submit,
} = editor
const deleting = ref<Attribute | null>(null)
const deletingBusy = ref(false)
const canManage = computed(() => auth.hasPermission('catalog.manage'))
async function confirmDelete(): Promise<void> {
  if (!deleting.value) return
  deletingBusy.value = true
  try {
    await remove(deleting.value.id)
    deleting.value = null
  } catch (reason) {
    error.value =
      reason instanceof Error
        ? reason.message
        : 'Не удалось удалить характеристику.'
  } finally {
    deletingBusy.value = false
  }
}
onMounted(load)
</script>
<template>
  <AdminWorkspace mode="list" :aria-busy="loading">
    <template #intro>
      <PageHeader class="mb-7" eyebrow="Каталог" title="Характеристики"
        ><template #actions
          ><UiButton v-if="canManage" @click="show()"
            ><Plus :size="18" />Добавить</UiButton
          ></template
        ></PageHeader
      >
    </template>
    <UiNotification v-if="error">{{ error }}</UiNotification>
    <div>
      <UiLoadingState
        v-if="loading"
        class="admin-container"
        label="Загрузка характеристик…"
      /><AttributesList
        v-else
        :attributes="attributes"
        :groups="groups"
        :can-manage="canManage"
        @edit="show"
        @remove="deleting = $event"
      />
    </div>
    <UiPagination
      v-if="pagination"
      class="admin-container"
      :meta="pagination"
      :loading="loading"
      @change="load"
    /><AttributeFormDialog
      :open="open"
      :title="title"
      :busy="busy"
      :error="formError"
      :form="form"
      :groups="groups"
      :types="attributeTypeOptions"
      :manually-edited-slug="manuallyEditedSlug"
      @close="close"
      @submit="submit"
      @update-name="updateName"
      @update-slug="updateSlug"
      @update-type="updateType"
    /><ConfirmDialog
      :open="Boolean(deleting)"
      title="Удалить характеристику?"
      :description="`Характеристика «${deleting?.name ?? ''}» и её варианты будут удалены. Это действие нельзя отменить.`"
      :busy="deletingBusy"
      @close="deleting = null"
      @confirm="confirmDelete"
    />
  </AdminWorkspace>
</template>
