<script setup lang="ts">
import { computed, ref } from 'vue'
import { Plus } from '@lucide/vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import { useAuthStore } from '../../../stores/auth'
import { useProductCatalogOptions } from '../composables/useProductCatalogOptions'
import { useProductEditor } from '../composables/useProductEditor'
import { useProductList } from '../composables/useProductList'
import ProductDownloadMenu from './ProductDownloadMenu.vue'
import ProductEditor from './ProductEditor.vue'
import ProductList from './ProductList.vue'

const auth = useAuthStore()
const canManage = computed(() => auth.hasPermission('catalog.manage'))
const canManageImports = computed(() => auth.hasPermission('imports.manage'))
const catalog = useProductCatalogOptions()
// Keep one mutation lock and one notice across the list and its dialogs.
const saving = ref(false)
const list = useProductList(catalog, saving)
const { error, loading } = list
const editor = useProductEditor({
  catalog,
  canManage,
  error,
  saving,
  refreshList: list.refreshAfterMutation,
  updateThumbnail: list.updateThumbnail,
})
const { opened, open, openPhotos, cloneProduct } = editor
</script>

<template>
  <AdminWorkspace mode="list" :aria-busy="loading">
    <template #intro>
      <PageHeader class="mb-5" title="Товары">
        <template #actions>
          <ProductDownloadMenu v-if="canManageImports" />
          <UiButton v-if="canManage" type="button" @click="open()"
            ><Plus :size="18" aria-hidden="true" />Добавить товар</UiButton
          >
        </template>
      </PageHeader>
    </template>

    <UiNotification v-if="error && !opened">{{ error }}</UiNotification>
    <ProductList
      :state="list"
      :can-manage="canManage"
      @edit="open"
      @photos="openPhotos"
      @clone="cloneProduct"
    />
    <ProductEditor :state="editor" />
  </AdminWorkspace>
</template>
