<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { Plus } from '@lucide/vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
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
const { catalog, categories, error, loading, deleting, deletingBusy, details, detailsLoading, detailsAttributes, detailsGroups, parentOptionsFor, categoryName, load, showDetails, confirmDelete } = workspace
const editor = useCategoryForm(catalog.saveCategory, load)
const assignments = useCategoryAssignments()
const { open: editorOpen, editing, busy: editorBusy, error: editorError, form: editorForm, title: editorTitle, show: showEditor, close: closeEditor, updateName, updateSlug, submit: submitEditor } = editor
const { open: assignmentsOpen, busy: assignmentsBusy, error: assignmentsError, category: assignmentCategory, groups: assignmentGroups, groupedAttributes, ungroupedAttributes, selectedGroupIds, selectedAttributeIds, requiredAttributeIds, show: showAssignments, close: closeAssignments, setGroupIds, setAttributeIds, submit: submitAssignments } = assignments
const canManage = computed(() => auth.hasPermission('catalog.manage'))
onMounted(load)
</script>
<template><section class="mx-auto admin-page" :aria-busy="loading"><PageHeader class="mb-7" eyebrow="Каталог" title="Категории"><template #actions><UiButton v-if="canManage" @click="showEditor()"><Plus :size="18" />Добавить категорию</UiButton></template></PageHeader><UiAlert v-if="error" class="mb-4">{{ error }}</UiAlert><CategoriesList :categories="categories" :loading="loading" :can-manage="canManage" :category-name="categoryName" @details="showDetails" @edit="showEditor" @configure="showAssignments" @remove="deleting = $event" /><CategoryDetailsDialog :category="details" :loading="detailsLoading" :attributes="detailsAttributes" :groups="detailsGroups" :category-name="categoryName" @close="details = null" /><CategoryAssignmentsDialog :open="assignmentsOpen" :busy="assignmentsBusy" :error="assignmentsError" :category="assignmentCategory" :groups="assignmentGroups" :grouped-attributes="groupedAttributes" :ungrouped-attributes="ungroupedAttributes" :selected-group-ids="selectedGroupIds" :selected-attribute-ids="selectedAttributeIds" :required-attribute-ids="requiredAttributeIds" @close="closeAssignments" @submit="submitAssignments" @groups-change="setGroupIds" @attributes-change="setAttributeIds" @required-change="requiredAttributeIds = $event.map(Number)" /><CategoryFormDialog :open="editorOpen" :title="editorTitle" :busy="editorBusy" :error="editorError" :form="editorForm" :parent-options="parentOptionsFor(editing)" :update-name="updateName" :update-slug="updateSlug" @close="closeEditor" @submit="submitEditor" /><ConfirmDialog :open="Boolean(deleting)" title="Удалить категорию?" :description="`Категория «${deleting?.name ?? ''}» будет удалена. Это действие нельзя отменить.`" :busy="deletingBusy" @close="deleting = null" @confirm="confirmDelete" /></section></template>
