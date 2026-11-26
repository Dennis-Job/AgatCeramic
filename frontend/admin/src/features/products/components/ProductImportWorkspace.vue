<script setup lang="ts">
import {
  nextTick,
  onActivated,
  onDeactivated,
  onMounted,
  reactive,
  ref,
} from 'vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiSegmentedControl from '../../../components/ui/UiSegmentedControl.vue'
import { useProductImport } from '../composables/useProductImport'
import ProductImageImportPanel from './ProductImageImportPanel.vue'
import ProductImportPanel from './ProductImportPanel.vue'

const active = ref(true)
onActivated(() => {
  active.value = true
})
onDeactivated(() => {
  active.value = false
})

const {
  tab,
  importMode,
  categoryId,
  categoriesLoading,
  categoryError,
  categoryOptions,
  successful,
  progress,
  imageProgress,
  statusText,
  productImport,
  imageImport,
  loadCategories,
  downloadTemplate,
  uploadProducts,
  downloadImageErrors,
} = useProductImport(() => {})
const products = reactive(productImport)
const images = reactive(imageImport)

const importTabs = [
  {
    value: 'products',
    label: 'Загрузка товаров',
    panelId: 'import-panel-products',
  },
  {
    value: 'images',
    label: 'Загрузка изображений',
    panelId: 'import-panel-images',
  },
] as const

onMounted(() => {
  void loadCategories()
})

function runAction(action: () => Promise<void>) {
  const trigger = document.activeElement
  const actionTab = tab.value
  void action()
  void nextTick(() => {
    if (
      !active.value ||
      tab.value !== actionTab ||
      !(trigger instanceof HTMLElement)
    )
      return
    if (trigger.isConnected && !trigger.matches(':disabled')) return
    if (
      document.activeElement !== document.body &&
      document.activeElement !== trigger
    )
      return
    document.getElementById(`import-type-${actionTab}`)?.focus()
  })
}
</script>

<template>
  <AdminWorkspace mode="form">
    <PageHeader
      class="mb-7"
      eyebrow="Товары"
      title="Добавить массово товары"
      description="Добавление и массовое редактирование товаров из Excel, загрузка изображений из ZIP."
    />
    <div class="min-w-0">
      <UiSegmentedControl
        v-model="tab"
        id-prefix="import-type"
        name="import-type"
        label="Тип загрузки"
        :options="importTabs"
        fit-content
        hide-label
      />
      <ProductImportPanel
        v-show="tab === 'products'"
        id="import-panel-products"
        v-model:import-mode="importMode"
        v-model:category-id="categoryId"
        role="region"
        aria-labelledby="import-type-products"
        :visible="active && tab === 'products'"
        :category-options="categoryOptions"
        :categories-loading="categoriesLoading"
        :category-error="categoryError"
        :file="products.file"
        :busy="products.busy"
        :uploading="products.uploading"
        :downloading="products.downloading"
        :error="products.error"
        :notice="products.notice"
        :polling-error="products.pollingError"
        :result="products.result"
        :progress="progress"
        :status-text="statusText"
        :successful="successful"
        @select="productImport.selectFile"
        @upload="runAction(uploadProducts)"
        @retry="runAction(() => productImport.poll($event))"
        @retry-categories="runAction(loadCategories)"
        @download-template="runAction(downloadTemplate)"
        @download-errors="runAction(() => downloadTemplate(true))"
      />
      <ProductImageImportPanel
        v-show="tab === 'images'"
        id="import-panel-images"
        role="region"
        aria-labelledby="import-type-images"
        :visible="active && tab === 'images'"
        :file="images.file"
        :busy="images.busy"
        :uploading="images.uploading"
        :downloading="images.downloading"
        :error="images.error"
        :polling-error="images.pollingError"
        :result="images.result"
        :progress="imageProgress"
        @select="imageImport.selectFile"
        @upload="runAction(imageImport.upload)"
        @retry="runAction(() => imageImport.poll($event))"
        @download-errors="runAction(downloadImageErrors)"
      />
    </div>
  </AdminWorkspace>
</template>
