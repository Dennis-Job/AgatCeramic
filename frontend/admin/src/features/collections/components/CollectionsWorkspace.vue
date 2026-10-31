<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Plus } from '@lucide/vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import { useAuthStore } from '../../../stores/auth'
import { useCollectionCatalog } from '../composables/useCollectionCatalog'
import { useCollectionForm } from '../composables/useCollectionForm'
import { getAllBrands } from '../../brands/services/brands'
import type { Brand } from '../../brands/types/brand.types'
import type { ProductCollection } from '../types/collection.types'
import CollectionFormDialog from './CollectionFormDialog.vue'
import CollectionsList from './CollectionsList.vue'
const auth = useAuthStore()
const {
  search,
  brandId,
  activity,
  collections,
  pagination,
  error,
  loading,
  load,
  loadAfterDeletion,
  removeCollection,
  saveCollection,
} = useCollectionCatalog()
const success = ref('')
const {
  open,
  editing,
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
} = useCollectionForm(saveCollection, async () => {
  success.value = 'Коллекция сохранена.'
  await load()
})
const brands = ref<Brand[]>([])
const brandsLoading = ref(false)
const brandsError = ref('')
const deleting = ref<ProductCollection | null>(null)
const busy = ref(false)
const deleteError = ref('')
const canManage = computed(() => auth.hasPermission('catalog.manage'))
const brandOptions = computed(() => [
  { value: '', label: 'Все бренды' },
  ...brands.value.map((item) => ({ value: String(item.id), label: item.name })),
])
async function loadBrands() {
  brandsLoading.value = true
  brandsError.value = ''
  try {
    brands.value = await getAllBrands()
  } catch (reason) {
    brandsError.value =
      reason instanceof Error ? reason.message : 'Не удалось загрузить бренды.'
  } finally {
    brandsLoading.value = false
  }
}
function requestRemove(collection: ProductCollection) {
  deleting.value = collection
  deleteError.value = ''
}
async function remove() {
  if (!deleting.value) return
  busy.value = true
  deleteError.value = ''
  try {
    await removeCollection(deleting.value.id)
    deleting.value = null
    success.value = 'Коллекция удалена.'
    await loadAfterDeletion()
  } catch (reason) {
    deleteError.value =
      reason instanceof Error
        ? reason.message
        : 'Не удалось удалить коллекцию. Если она используется товарами, деактивируйте её.'
  } finally {
    busy.value = false
  }
}
onMounted(() => {
  void load()
  void loadBrands()
})
</script>
<template>
  <AdminWorkspace mode="list">
    <template #intro
      ><PageHeader class="mb-5" eyebrow="Каталог" title="Коллекции"
        ><template #actions
          ><UiButton v-if="canManage" @click="show()"
            ><Plus :size="18" />Добавить коллекцию</UiButton
          ></template
        ></PageHeader
      ></template
    >
    <div class="admin-container mb-5 grid min-w-0 gap-3 sm:grid-cols-3">
      <form
        role="search"
        aria-label="Поиск коллекций"
        @submit.prevent="load(1)"
      >
        <UiInput
          v-model="search"
          searchable
          maxlength="255"
          aria-label="Поиск коллекций"
          placeholder="Название коллекции"
        />
      </form>
      <UiSelect
        v-model="brandId"
        :options="brandOptions"
        accessible-name="Фильтр по бренду"
        searchable
        :disabled="brandsLoading"
      />
      <UiSelect
        v-model="activity"
        :options="[
          { value: '', label: 'Любая активность' },
          { value: '1', label: 'Активные' },
          { value: '0', label: 'Неактивные' },
        ]"
        accessible-name="Фильтр по активности"
      />
    </div>
    <UiNotification v-if="success" tone="success" live="polite">{{
      success
    }}</UiNotification>
    <UiNotification v-if="brandsError"
      >{{ brandsError
      }}<UiButton
        variant="surface"
        :disabled="brandsLoading"
        @click="loadBrands"
        >Повторить загрузку брендов</UiButton
      ></UiNotification
    >
    <UiNotification v-if="error">{{ error }}</UiNotification>
    <div v-if="error" class="admin-container mb-4">
      <UiButton variant="surface" :disabled="loading" @click="load()"
        >Повторить загрузку</UiButton
      >
    </div>
    <CollectionsList
      v-if="!error || loading"
      :collections="collections"
      :loading="loading"
      :filtered="Boolean(search.trim() || brandId || activity)"
      :can-manage="canManage"
      @edit="show"
      @remove="requestRemove"
    />
    <UiPagination
      v-if="pagination && !error"
      class="admin-container"
      :meta="pagination"
      :loading="loading"
      @change="load"
    />
    <CollectionFormDialog
      :open="open"
      :title="title"
      :busy="saving"
      :media-pending="mediaPending"
      :media-uploading="mediaUploading"
      :error="formError"
      :form="form"
      :brands="brands"
      :brand-locked="Boolean(editing?.products_count)"
      :brands-loading="brandsLoading"
      :brands-error="brandsError"
      @retry-brands="loadBrands"
      @close="close"
      @submit="submit"
      @media-pending="mediaPending = $event"
      @media-uploading="mediaUploading = $event"
      @update-name="updateName"
      @update-slug="updateSlug"
    />
    <ConfirmDialog
      :open="Boolean(deleting)"
      title="Удалить коллекцию?"
      :description="
        deleting?.products_count
          ? `Коллекция используется товарами (${deleting.products_count}). Вместо удаления деактивируйте её в редакторе.`
          : `Коллекция «${deleting?.name ?? ''}» будет удалена. Общие медиа сохранятся.`
      "
      :busy="busy"
      :error="deleteError"
      @close="deleting = null"
      @confirm="remove"
    />
  </AdminWorkspace>
</template>
