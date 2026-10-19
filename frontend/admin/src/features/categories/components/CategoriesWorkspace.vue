<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Plus } from '@lucide/vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import CatalogReferenceLayout from '../../../components/shared/CatalogReferenceLayout.vue'
import CategoriesNavigation from './CategoriesNavigation.vue'
import { useCategoryNavigation } from '../composables/useCategoryNavigation'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import { useAuthStore } from '../../../stores/auth'
import { useCategoryAssignments } from '../composables/useCategoryAssignments'
import { useCategoryForm } from '../composables/useCategoryForm'
import { useCategoriesWorkspace } from '../composables/useCategoriesWorkspace'
import CategoriesList from './CategoriesList.vue'
import CategoryAssignmentsDialog from './CategoryAssignmentsDialog.vue'
import CategoryDetailsDialog from './CategoryDetailsDialog.vue'
import CategoryFormDialog from './CategoryFormDialog.vue'

const auth = useAuthStore()
const workspace = useCategoriesWorkspace()
const {
  catalog,
  overview,
  refreshOverview,
  categories,
  error,
  loading,
  deleting,
  deletingBusy,
  deletionError,
  showDelete,
  closeDelete,
  details,
  detailsLoading,
  detailsAttributes,
  detailsGroups,
  parentOptionsFor,
  categoryName,
  load,
  showDetails,
  closeDetails,
  confirmDelete,
} = workspace
const editor = useCategoryForm(catalog.saveCategory, load)
const assignments = useCategoryAssignments()
const {
  open: editorOpen,
  editing,
  busy: editorBusy,
  mediaPending,
  mediaUploading,
  error: editorError,
  form: editorForm,
  title: editorTitle,
  show: showEditor,
  close: closeEditor,
  updateName,
  updateSlug,
  submit: submitEditor,
} = editor
const {
  open: assignmentsOpen,
  busy: assignmentsBusy,
  error: assignmentsError,
  category: assignmentCategory,
  groups: assignmentGroups,
  groupedAttributes,
  ungroupedAttributes,
  selectedGroupIds,
  selectedAttributeIds,
  requiredAttributeIds,
  show: showAssignments,
  close: closeAssignments,
  setGroupIds,
  setAttributeIds,
  submit: submitAssignments,
} = assignments
const canManage = computed(() => auth.hasPermission('catalog.manage'))
const {
  selectedCategoryId,
  search: categorySearch,
  visibleItems,
  categoryOptions,
} = useCategoryNavigation(categories, overview, { loading, error })
const route = useRoute()
const router = useRouter()
watch(
  () => [route.query.category, route.query.search],
  ([category, search]) => {
    selectedCategoryId.value = typeof category === 'string' ? category : ''
    categorySearch.value = typeof search === 'string' ? search : ''
  },
  { immediate: true },
)
watch([selectedCategoryId, categorySearch], ([category, search]) => {
  const query = { ...route.query }
  if (category) query.category = category
  else delete query.category
  if (search) query.search = search
  else delete query.search
  if (
    route.query.category !== query.category ||
    route.query.search !== query.search
  )
    void router.replace({ query })
})
async function saveAssignments(): Promise<void> {
  const id = assignmentCategory.value?.id
  await submitAssignments()
  // Group replacement can succeed even when attribute replacement fails.
  if (id !== undefined) await refreshOverview(id)
}
onMounted(load)
</script>
<template>
  <AdminWorkspace mode="list" :aria-busy="loading">
    <template #intro>
      <PageHeader
        class="mb-7"
        eyebrow="Каталог"
        title="Категории"
        description="Выберите категорию, чтобы посмотреть сведения и назначенные характеристики."
      >
        <template #actions
          ><UiButton
            v-if="canManage"
            :disabled="loading || Boolean(error)"
            @click="showEditor()"
            ><Plus :size="18" />Добавить категорию</UiButton
          ></template
        >
      </PageHeader>
    </template>
    <div v-if="error" class="admin-container space-y-3">
      <UiAlert>{{ error }}</UiAlert>
      <UiButton variant="secondary" @click="load">Повторить загрузку</UiButton>
    </div>
    <UiLoadingState
      v-else-if="loading"
      class="admin-container"
      label="Загрузка категорий…"
    />
    <CatalogReferenceLayout v-else>
      <template #sidebar
        ><CategoriesNavigation
          v-model:selected-category-id="selectedCategoryId"
          v-model:search="categorySearch"
          :items="visibleItems"
          :total="categories.length"
      /></template>
      <template #mobile
        ><label class="block text-sm font-medium text-gray-500"
          >Категория<UiSelect
            v-model="selectedCategoryId"
            :options="categoryOptions"
            accessible-name="Категория"
            searchable
            teleport-menu
            class="mt-1.5 w-full"
            :disabled="!categories.length" /></label
      ></template>
      <section aria-label="Подробная информация о категориях">
        <p class="sr-only" role="status">
          {{
            selectedCategoryId
              ? `Выбрана категория: ${categoryName(Number(selectedCategoryId)) ?? ''}`
              : `Показаны все категории: ${categories.length}`
          }}
        </p>
        <CategoriesList
          v-model:selected-category-id="selectedCategoryId"
          :categories="categories"
          :overview="overview"
          :loading="false"
          :can-manage="canManage"
          :category-name="categoryName"
          @details="showDetails"
          @edit="showEditor"
          @configure="showAssignments"
          @remove="showDelete"
          @retry="refreshOverview"
        />
      </section>
    </CatalogReferenceLayout>
    <CategoryDetailsDialog
      :category="details"
      :loading="detailsLoading"
      :attributes="detailsAttributes"
      :groups="detailsGroups"
      :category-name="categoryName"
      @close="closeDetails"
    /><CategoryAssignmentsDialog
      :open="assignmentsOpen"
      :busy="assignmentsBusy"
      :error="assignmentsError"
      :category="assignmentCategory"
      :groups="assignmentGroups"
      :grouped-attributes="groupedAttributes"
      :ungrouped-attributes="ungroupedAttributes"
      :selected-group-ids="selectedGroupIds"
      :selected-attribute-ids="selectedAttributeIds"
      :required-attribute-ids="requiredAttributeIds"
      @close="closeAssignments"
      @submit="saveAssignments"
      @groups-change="setGroupIds"
      @attributes-change="setAttributeIds"
      @required-change="requiredAttributeIds = $event.map(Number)"
    /><CategoryFormDialog
      :open="editorOpen"
      :title="editorTitle"
      :busy="editorBusy"
      :media-pending="mediaPending"
      :media-uploading="mediaUploading"
      :error="editorError"
      :form="editorForm"
      :parent-options="parentOptionsFor(editing)"
      :update-name="updateName"
      :update-slug="updateSlug"
      @close="closeEditor"
      @submit="submitEditor"
      @media-pending="mediaPending = $event"
      @media-uploading="mediaUploading = $event"
    /><ConfirmDialog
      :open="Boolean(deleting)"
      title="Удалить категорию?"
      :description="`Категория «${deleting?.name ?? ''}» будет удалена. Это действие нельзя отменить.`"
      :busy="deletingBusy"
      :error="deletionError"
      @close="closeDelete"
      @confirm="confirmDelete"
    />
  </AdminWorkspace>
</template>
