<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Plus } from '@lucide/vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import { useAuthStore } from '../../../stores/auth'
import { useBrandCatalog } from '../composables/useBrandCatalog'
import { useBrandForm } from '../composables/useBrandForm'
import type { Brand } from '../types/brand.types'
import BrandFormDialog from './BrandFormDialog.vue'
import BrandsList from './BrandsList.vue'
const auth = useAuthStore()
const catalog = useBrandCatalog()
const {
  search,
  brands,
  pagination,
  error,
  loading,
  load,
  loadAfterDeletion,
  removeBrand,
  saveBrand,
} = catalog
const editor = useBrandForm(saveBrand, load)
const {
  open,
  title,
  busy: saving,
  mediaPending,
  mediaUploading,
  error: formError,
  form,
  show,
  close,
  updateName,
  updateSlug,
  submit,
} = editor
const deleting = ref<Brand | null>(null)
const busy = ref(false)
const canManage = computed(() => auth.hasPermission('catalog.manage'))
async function remove() {
  if (!deleting.value) return
  busy.value = true
  try {
    await removeBrand(deleting.value.id)
    deleting.value = null
    await loadAfterDeletion()
  } catch (reason) {
    error.value =
      reason instanceof Error ? reason.message : 'Не удалось удалить бренд.'
  } finally {
    busy.value = false
  }
}
onMounted(load)
</script>
<template>
  <AdminWorkspace mode="list">
    <template #intro>
      <PageHeader class="mb-7" eyebrow="Каталог" title="Бренды"
        ><template #actions>
          <form
            class="w-full sm:w-72"
            role="search"
            aria-label="Поиск брендов"
            @submit.prevent
          >
            <UiInput
              v-model="search"
              searchable
              maxlength="255"
              aria-label="Поиск брендов"
              placeholder="Название или slug бренда"
            />
          </form>
          <UiButton v-if="canManage" class="shrink-0" @click="show()"
            ><Plus :size="18" />Добавить бренд</UiButton
          ></template
        ></PageHeader
      >
    </template>
    <UiNotification v-if="error">{{ error }}</UiNotification>
    <div v-if="error" class="admin-container mb-4">
      <UiButton variant="surface" :disabled="loading" @click="load()"
        >Повторить загрузку</UiButton
      >
    </div>
    <BrandsList
      v-if="!error || loading"
      :brands="brands"
      :search="search"
      :loading="loading"
      :can-manage="canManage"
      @edit="show"
      @remove="deleting = $event"
    /><UiPagination
      v-if="pagination && !error"
      class="admin-container"
      :meta="pagination"
      :loading="loading"
      @change="load"
    /><BrandFormDialog
      :open="open"
      :title="title"
      :busy="saving"
      :media-pending="mediaPending"
      :media-uploading="mediaUploading"
      :error="formError"
      :form="form"
      @close="close"
      @submit="submit"
      @media-pending="mediaPending = $event"
      @media-uploading="mediaUploading = $event"
      @update-name="updateName"
      @update-slug="updateSlug"
    /><ConfirmDialog
      :open="Boolean(deleting)"
      title="Удалить бренд?"
      :description="`Бренд «${deleting?.name ?? ''}» будет удалён.`"
      :busy="busy"
      @close="deleting = null"
      @confirm="remove"
    />
  </AdminWorkspace>
</template>
