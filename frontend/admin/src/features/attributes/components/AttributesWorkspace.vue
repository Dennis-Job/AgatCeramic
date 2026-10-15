<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Folder, Plus, Pencil, Trash2, Info } from '@lucide/vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import { useAuthStore } from '../../../stores/auth'
import { attributeTypeOptions } from '../../../utils/attributeTypes'
import { useAttributesWorkspace } from '../composables/useAttributesWorkspace'
import AttributeFormDialog from './AttributeFormDialog.vue'
import AttributeGroupFormDialog from '../../attribute-groups/components/AttributeGroupFormDialog.vue'
import AttributesList from './AttributesList.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const {
  attributes,
  groups,
  loading,
  loadFailed,
  error,
  notice,
  selectedGroup,
  currentGroup,
  groupSearch,
  search,
  page,
  pagination,
  visibleAttributes,
  visibleGroupItems,
  groupItems,
  pendingIds,
  load,
  attributeEditor,
  groupEditor,
  showAttribute,
  showGroup,
  showGroupFromAttribute,
  toggleDisplay,
  deletingAttribute,
  deletingGroup,
  deletingBusy,
  confirmDelete,
} = useAttributesWorkspace()
const {
  open: attributeOpen,
  title: attributeTitle,
  busy: attributeBusy,
  error: attributeError,
  form: attributeForm,
  manuallyEditedSlug,
  close: closeAttribute,
  updateName,
  updateSlug,
  updateType,
  submit: submitAttribute,
} = attributeEditor
const {
  open: groupOpen,
  title: groupTitle,
  busy: groupBusy,
  error: groupError,
  form: groupForm,
  close: closeGroup,
  updateName: updateGroupName,
  updateSlug: updateGroupSlug,
  submit: submitGroup,
} = groupEditor
const canManage = computed(() => auth.hasPermission('catalog.manage'))
const heading = computed(
  () =>
    currentGroup.value?.name ??
    (selectedGroup.value === 'ungrouped' ? 'Без группы' : 'Все характеристики'),
)
const emptyLabel = computed(() =>
  search.value.trim()
    ? 'Характеристики не найдены. Попробуйте другой запрос.'
    : attributes.value.length
      ? 'В этой группе пока нет характеристик.'
      : 'Характеристик пока нет. Добавьте первую характеристику.',
)
watch(
  () => route.query.group,
  (value) => {
    selectedGroup.value = typeof value === 'string' ? value : 'all'
  },
  { immediate: true },
)
watch(selectedGroup, (value) => {
  const query = { ...route.query }
  if (value === 'all') delete query.group
  else query.group = value
  if (route.query.group !== query.group) void router.replace({ query })
})
onMounted(load)
function closeDelete(): void {
  deletingAttribute.value = null
  deletingGroup.value = null
}
</script>
<template>
  <AdminWorkspace mode="list" :aria-busy="loading">
    <template #intro
      ><PageHeader
        class="mb-7"
        eyebrow="Каталог"
        title="Характеристики"
        description="Управляйте характеристиками и их группами."
        ><template #actions
          ><UiButton
            v-if="canManage"
            :disabled="loading || loadFailed"
            @click="showAttribute()"
            ><Plus :size="18" />Добавить характеристику</UiButton
          ></template
        ></PageHeader
      ></template
    >
    <UiNotification v-if="error">{{ error }}</UiNotification>
    <UiNotification v-if="notice" tone="success">{{ notice }}</UiNotification>
    <UiLoadingState
      v-if="loading"
      class="admin-container"
      label="Загрузка характеристик…"
    />
    <div v-else-if="loadFailed" class="admin-container">
      <UiEmptyState label="Не удалось загрузить характеристики и группы." />
      <UiButton variant="surface" @click="load">Повторить загрузку</UiButton>
    </div>
    <div v-else class="attributes-workspace admin-container">
      <aside class="attribute-groups min-w-0">
        <div class="mb-4 flex items-center justify-between gap-2">
          <h2 class="text-xl font-bold text-gray-500">Группы</h2>
          <UiButton
            v-if="canManage"
            variant="primary-ghost"
            size="sm"
            @click="showGroup()"
            ><Plus :size="17" />Создать</UiButton
          >
        </div>
        <label class="sr-only" for="attribute-group-search">Поиск групп</label
        ><UiInput
          id="attribute-group-search"
          v-model="groupSearch"
          type="search"
          placeholder="Найти группу"
          class="mb-4 w-full"
        />
        <nav
          class="attribute-group-navigation"
          aria-label="Группы характеристик"
        >
          <UiButton
            v-for="item in visibleGroupItems"
            :key="item.value"
            variant="surface"
            class="attribute-group-button"
            :class="{
              'attribute-group-button--selected': selectedGroup === item.value,
            }"
            :aria-current="selectedGroup === item.value ? 'true' : undefined"
            @click="selectedGroup = item.value"
            ><span class="min-w-0 break-words [overflow-wrap:anywhere]">{{
              item.label
            }}</span
            ><span class="attribute-group-count">{{
              item.count
            }}</span></UiButton
          >
        </nav>
        <p
          v-if="groupSearch && visibleGroupItems.length === 2"
          role="status"
          class="mt-3 text-sm text-gray-500"
        >
          Группы не найдены.
        </p>
      </aside>
      <div class="attribute-mobile-group">
        <label class="block text-sm font-medium text-gray-500"
          >Группа<UiSelect
            v-model="selectedGroup"
            class="mt-1.5 w-full"
            accessible-name="Выбор группы характеристик"
            :options="
              groupItems.map((item) => ({
                value: item.value,
                label: `${item.label} (${item.count})`,
              }))
            " /></label
        ><UiButton
          v-if="canManage"
          variant="primary-ghost"
          class="mt-2"
          @click="showGroup()"
          ><Plus :size="17" />Создать группу</UiButton
        >
      </div>
      <section
        class="attribute-content min-w-0"
        aria-labelledby="attribute-content-title"
      >
        <header class="mb-5 flex flex-wrap items-start justify-between gap-4">
          <div class="flex min-w-0 flex-1 items-start gap-4">
            <Folder
              class="mt-1 shrink-0 text-gray-500"
              :size="28"
              aria-hidden="true"
            />
            <div class="min-w-0">
              <h2
                id="attribute-content-title"
                class="break-words text-2xl font-bold text-gray-500 [overflow-wrap:anywhere]"
              >
                {{ heading }}
              </h2>
              <template v-if="currentGroup"
                ><p class="mt-1 break-words text-sm text-gray-500">
                  /{{ currentGroup.slug }}
                </p>
                <p
                  class="mt-2 whitespace-pre-wrap break-words text-sm text-gray-500"
                >
                  {{ currentGroup.description || 'Без описания' }}
                </p>
                <p class="mt-2 text-sm text-gray-500">
                  Порядок: {{ currentGroup.sort_order }}
                </p></template
              >
              <p v-else class="mt-2 text-sm text-gray-500">
                {{
                  selectedGroup === 'ungrouped'
                    ? 'Характеристики без выбранной группы.'
                    : 'Все характеристики каталога, включая характеристики без группы.'
                }}
              </p>
            </div>
          </div>
          <div
            v-if="currentGroup && canManage"
            class="flex max-w-full flex-wrap items-center gap-1"
          >
            <UiButton
              variant="primary-ghost"
              size="sm"
              :aria-label="`Редактировать группу ${currentGroup.name}`"
              @click="showGroup(currentGroup)"
              ><Pencil :size="17" />Редактировать группу</UiButton
            ><UiButton
              variant="danger-ghost"
              size="sm"
              :aria-label="`Удалить группу ${currentGroup.name}`"
              tooltip="Удалить группу"
              @click="deletingGroup = currentGroup"
              ><Trash2 :size="17"
            /></UiButton>
          </div>
        </header>
        <p
          v-if="currentGroup"
          class="attribute-context mb-5 flex items-start gap-2 text-sm text-gray-500"
        >
          <Info
            :size="18"
            class="mt-0.5 shrink-0 text-primary-500"
            aria-hidden="true"
          /><span
            >Новая характеристика будет добавлена в группу «{{
              currentGroup.name
            }}».</span
          >
        </p>
        <label class="sr-only" for="attribute-search">Поиск характеристик</label
        ><UiInput
          id="attribute-search"
          v-model="search"
          type="search"
          placeholder="Поиск по названию или коду"
          class="w-full sm:max-w-sm"
        />
        <p class="mb-3 mt-2 text-xs text-gray-500">
          Переключатели сохраняют изменения сразу.
        </p>
        <AttributesList
          :attributes="visibleAttributes"
          :groups="groups"
          :can-manage="canManage"
          :pending-ids="pendingIds"
          :show-group="selectedGroup === 'all'"
          :empty-label="emptyLabel"
          @edit="showAttribute"
          @remove="deletingAttribute = $event"
          @toggle="toggleDisplay"
        />
        <UiPagination :meta="pagination" @change="page = $event" />
        <p
          class="attribute-help mt-5 flex items-start gap-2 text-xs text-gray-500"
        >
          <Info :size="17" class="shrink-0" aria-hidden="true" /><span
            >Чтобы перенести характеристику, измените поле «Группа» в её форме.
            Применение характеристик настраивается в категориях.</span
          >
        </p>
      </section>
    </div>
    <AttributeFormDialog
      :open="attributeOpen"
      :title="attributeTitle"
      :busy="attributeBusy"
      :error="attributeError"
      :form="attributeForm"
      :groups="groups"
      :types="attributeTypeOptions"
      :manually-edited-slug="manuallyEditedSlug"
      :can-create-group="canManage"
      :suspended="groupOpen"
      @create-group="showGroupFromAttribute"
      @close="closeAttribute"
      @submit="submitAttribute"
      @update-name="updateName"
      @update-slug="updateSlug"
      @update-type="updateType"
    />
    <AttributeGroupFormDialog
      :open="groupOpen"
      :title="groupTitle"
      :busy="groupBusy"
      :error="groupError"
      :form="groupForm"
      @close="closeGroup"
      @submit="submitGroup"
      @update-name="updateGroupName"
      @update-slug="updateGroupSlug"
    />
    <ConfirmDialog
      :open="Boolean(deletingAttribute || deletingGroup)"
      :title="deletingAttribute ? 'Удалить характеристику?' : 'Удалить группу?'"
      :description="
        deletingAttribute
          ? `Характеристика «${deletingAttribute.name}» и её варианты будут удалены. Это действие нельзя отменить.`
          : `Группа «${deletingGroup?.name ?? ''}» будет удалена. Характеристики сохранятся в разделе «Без группы».`
      "
      :busy="deletingBusy"
      @close="closeDelete"
      @confirm="confirmDelete"
    />
  </AdminWorkspace>
</template>
<style scoped>
.attributes-workspace {
  display: grid;
  min-width: 0;
  border-top: var(--admin-border-width) solid var(--admin-color-gray-200);
}
.attribute-groups {
  display: none;
}
.attribute-mobile-group {
  padding-block: var(--admin-spacing-5);
}
.attribute-content {
  padding-block: var(--admin-spacing-5);
}
.attribute-context {
  border-radius: var(--admin-radius-lg);
  background: var(--admin-color-primary-25);
  padding: var(--admin-spacing-3);
  overflow-wrap: anywhere;
}
.attribute-help {
  border-top: var(--admin-border-width) solid var(--admin-color-gray-200);
  padding-top: var(--admin-spacing-4);
}
.attribute-group-button {
  width: 100%;
  justify-content: space-between;
  text-align: left;
  font-weight: 400;
  gap: var(--admin-spacing-2);
  margin-bottom: var(--admin-spacing-1);
}
.attribute-group-button--selected {
  background: var(--admin-color-primary-50);
  color: var(--admin-color-primary-500);
}
.attribute-group-count {
  flex-shrink: 0;
  min-width: 28px;
  padding: 2px 6px;
  text-align: center;
  border-radius: 999px;
  background: var(--admin-color-primary-25);
}
@media (min-width: 1024px) {
  .attributes-workspace {
    grid-template-columns: 240px minmax(0, 1fr);
  }
  .attribute-groups {
    display: block;
    padding-block: var(--admin-spacing-5);
    padding-right: var(--admin-spacing-5);
    border-right: var(--admin-border-width) solid var(--admin-color-gray-200);
  }
  .attribute-mobile-group {
    display: none;
  }
  .attribute-content {
    padding-left: var(--admin-spacing-6);
  }
}
</style>
