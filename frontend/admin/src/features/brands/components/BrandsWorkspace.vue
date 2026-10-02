<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Pencil, Plus, Trash2 } from '@lucide/vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiTable from '../../../components/ui/UiTable.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import { countryName } from '../../../constants/countries'
import { useAuthStore } from '../../../stores/auth'
import { useBrandCatalog } from '../composables/useBrandCatalog'
import { useBrandForm } from '../composables/useBrandForm'
import type { Brand } from '../types/brand.types'
import BrandFormDialog from './BrandFormDialog.vue'
const auth = useAuthStore()
const catalog = useBrandCatalog()
const {
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
        ><template #actions
          ><UiButton v-if="canManage" @click="show()"
            ><Plus :size="18" />Добавить бренд</UiButton
          ></template
        ></PageHeader
      >
    </template>
    <UiNotification v-if="error">{{ error }}</UiNotification
    ><UiLoadingState
      v-if="loading"
      class="admin-container"
      label="Загрузка брендов…"
    /><UiTable
      v-else-if="brands.length"
      full-bleed
      label="Список брендов"
      min-width="min-w-[880px]"
      table-class="seller-table"
      sticky-header
      :sticky-edges="canManage"
    >
      <thead>
        <tr>
          <th scope="col">Бренд</th>
          <th scope="col" class="w-64">Страна</th>
          <th scope="col" class="w-32">Статус</th>
          <th v-if="canManage" scope="col" class="w-40">
            <span class="sr-only">Действия</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="brand in brands" :key="brand.id">
          <td>
            <h2 class="font-semibold text-gray-800">{{ brand.name }}</h2>
            <p class="mt-1 text-xs text-gray-500">/{{ brand.slug }}</p>
          </td>
          <td>{{ countryName(brand.country_code ?? '') }}</td>
          <td>
            <UiBadge :tone="brand.is_active ? 'success' : 'neutral'">{{
              brand.is_active ? 'Активен' : 'Скрыт'
            }}</UiBadge>
          </td>
          <td v-if="canManage">
            <div class="flex justify-end gap-1">
              <UiButton
                variant="ghost"
                size="sm"
                :aria-label="`Редактировать бренд ${brand.name}`"
                @click="show(brand)"
                ><Pencil :size="17" /></UiButton
              ><UiButton
                variant="ghost"
                size="sm"
                :aria-label="`Удалить бренд ${brand.name}`"
                @click="deleting = brand"
                ><Trash2 :size="17"
              /></UiButton>
            </div>
          </td>
        </tr></tbody></UiTable
    ><UiEmptyState
      v-else
      class="admin-container"
      label="Брендов пока нет."
    /><UiPagination
      v-if="pagination"
      class="admin-container"
      :meta="pagination"
      :loading="loading"
      @change="load"
    /><BrandFormDialog
      :open="open"
      :title="title"
      :busy="saving"
      :error="formError"
      :form="form"
      @close="close"
      @submit="submit"
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
