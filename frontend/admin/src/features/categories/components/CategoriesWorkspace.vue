<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Plus } from '@lucide/vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
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
  confirmDelete,
} = workspace
const editor = useCategoryForm(catalog.saveCategory, load)
const assignments = useCategoryAssignments()
const {
  open: editorOpen,
  editing,
  busy: editorBusy,
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
const selectedCategoryId = ref('')
const categoryOptions = computed(() => [
  { label: 'Все категории', value: '' },
  ...categories.value.map((category) => ({
    label:
      category.parent_id === null
        ? category.name
        : `${categoryName(category.parent_id) ?? 'Без родителя'} / ${category.name}`,
    value: String(category.id),
  })),
])
watch(categories, (items) => {
  if (
    selectedCategoryId.value &&
    !items.some((category) => String(category.id) === selectedCategoryId.value)
  ) {
    selectedCategoryId.value = ''
  }
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
      <PageHeader class="mb-7" eyebrow="Каталог" title="Категории"
        ><template #actions
          ><UiSelect
            v-model="selectedCategoryId"
            :options="categoryOptions"
            accessible-name="Категория"
            searchable
            teleport-menu
            class="w-full sm:w-72"
            :disabled="loading || !categories.length"
          /><UiButton v-if="canManage" @click="showEditor()"
            ><Plus :size="18" />Добавить категорию</UiButton
          ></template
        ></PageHeader
      >
    </template>
    <div v-if="error" class="admin-container space-y-3">
      <UiAlert>{{ error }}</UiAlert>
      <UiButton variant="secondary" @click="load">Повторить загрузку</UiButton>
    </div>
    <CategoriesList
      v-else
      v-model:selected-category-id="selectedCategoryId"
      :categories="categories"
      :overview="overview"
      :loading="loading"
      :can-manage="canManage"
      :category-name="categoryName"
      @details="showDetails"
      @edit="showEditor"
      @configure="showAssignments"
      @remove="showDelete"
      @retry="refreshOverview"
    /><CategoryDetailsDialog
      :category="details"
      :loading="detailsLoading"
      :attributes="detailsAttributes"
      :groups="detailsGroups"
      :category-name="categoryName"
      @close="details = null"
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
      :error="editorError"
      :form="editorForm"
      :parent-options="parentOptionsFor(editing)"
      :update-name="updateName"
      :update-slug="updateSlug"
      @close="closeEditor"
      @submit="submitEditor"
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
