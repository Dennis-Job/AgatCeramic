<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue'
import {
  onBeforeRouteLeave,
  onBeforeRouteUpdate,
  useRoute,
  useRouter,
} from 'vue-router'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiTabs from '../../../components/ui/UiTabs.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import {
  ArrowUp,
  ArrowDown,
  Trash2,
  ChevronRight,
  FileText,
  Globe,
  Layers,
  Settings,
} from '@lucide/vue'
import { systemPageSlugs } from '../validation/page'
import { slugPattern } from '../../catalog/validation/slug'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import type { ContentPage } from '../types/page.types'
import type { BlockType } from '../types/block.types'
import { blockLabels } from '../validation/blocks'
import { usePageBlocks } from '../composables/usePageBlocks'
import BlockSettings from './BlockSettings.vue'
import HomePageSeoEditor from '../../homepage/components/HomePageSeoEditor.vue'

const props = withDefaults(
  defineProps<{ page: ContentPage; showActions?: boolean }>(),
  { showActions: true },
)
const emit = defineEmits<{
  saved: [page: ContentPage]
  pending: [value: boolean]
  busy: [value: boolean]
}>()
const editor = usePageBlocks(
  () => props.page,
  (page) => emit('saved', page),
)
const route = useRoute()
const router = useRouter()
const adding = ref<BlockType>('text')
const tab = ref('blocks')
const tabsId = useId()
const tabs = [
  { value: 'blocks', label: 'Блоки' },
  { value: 'settings', label: 'Настройки' },
  { value: 'seo', label: 'SEO' },
]
const removing = ref<string | null>(null)
const blockResourceDirty = ref(false)
const blockResourceBusy = ref(false)
const seoResourceDirty = ref(false)
const seoResourceBusy = ref(false)
watch(
  () => blockResourceDirty.value || seoResourceDirty.value,
  (value) => (editor.resourceDirty.value = value),
)
watch(
  () => blockResourceBusy.value || seoResourceBusy.value,
  (value) => (editor.resourceBusy.value = value),
)
const blocks = computed(() => editor.form.value.blocks ?? [])
const selected = computed(
  () =>
    blocks.value.find((block) => block.id === route.query.block) ??
    blocks.value[0],
)
const allowed = computed(() =>
  Object.entries(blockLabels)
    .filter(
      ([type]) =>
        type === 'text' || !blocks.value.some((block) => block.type === type),
    )
    .map(([value, label]) => ({ value, label })),
)
watch(editor.pending, (value) => emit('pending', value), { immediate: true })
watch(
  () => editor.busy.value || editor.resourceBusy.value,
  (value) => emit('busy', value),
  { immediate: true },
)
watch(allowed, (options) => {
  if (!options.some((option) => option.value === adding.value))
    adding.value = (options[0]?.value ?? 'text') as BlockType
})
function select(id: string): void {
  if (editor.resourceDirty.value) return
  void router.replace({ query: { ...route.query, block: id } })
}
function add(): void {
  const id = editor.add(adding.value)
  if (id) select(id)
}
function remove(): void {
  editor.form.value.blocks = blocks.value.filter(
    (block) => block.id !== removing.value,
  )
  removing.value = null
}
function beforeUnload(event: BeforeUnloadEvent): void {
  if (!editor.pending.value) return
  event.preventDefault()
  event.returnValue = ''
}
function confirmLeave(): boolean {
  if (editor.busy.value || editor.resourceBusy.value) return false
  return (
    !editor.pending.value ||
    window.confirm(
      'Есть несохранённые изменения. Покинуть редактор и потерять их?',
    )
  )
}
onBeforeRouteUpdate((to, from) => {
  if (
    to.query.page === from.query.page &&
    to.query.section === from.query.section
  )
    return !editor.resourceDirty.value && !editor.resourceBusy.value
  return confirmLeave()
})
onBeforeRouteLeave(confirmLeave)
onMounted(() => window.addEventListener('beforeunload', beforeUnload))
defineExpose({
  save: editor.save,
  publish: editor.publish,
  dirty: editor.dirty,
  pending: editor.pending,
  busy: computed(() => editor.busy.value || editor.resourceBusy.value),
  resourceDirty: editor.resourceDirty,
})
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', beforeUnload)
  emit('pending', false)
  emit('busy', false)
})
</script>

<template>
  <section class="min-w-0" aria-label="Редактор содержимого страницы">
    <UiTabs
      :id="tabsId"
      v-model="tab"
      label="Редактор страницы"
      :items="
        tabs.map((item) => ({
          ...item,
          disabled: editor.busy.value || editor.resourceBusy.value,
        }))
      "
    />
    <UiNotification v-if="editor.error.value">{{
      editor.error.value
    }}</UiNotification>
    <UiNotification v-if="editor.success.value" tone="success">{{
      editor.success.value
    }}</UiNotification>
    <div
      v-show="tab === 'blocks'"
      :id="`${tabsId}-blocks-panel`"
      role="tabpanel"
      :aria-labelledby="`${tabsId}-blocks`"
      tabindex="0"
      class="min-w-0 space-y-3 pt-5 admin-focus"
    >
      <UiCard aria-labelledby="page-blocks-title">
        <template #header>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h2
              id="page-blocks-title"
              class="flex items-center gap-3 font-semibold text-gray-500"
            >
              <Layers :size="20" class="shrink-0" aria-hidden="true" />Блоки
              страницы
            </h2>
            <UiBadge :tone="editor.dirty.value ? 'warning' : 'neutral'">{{
              editor.dirty.value ? 'Не сохранено' : 'Сохранённый черновик'
            }}</UiBadge>
          </div>
        </template>
        <div
          class="grid gap-1 sm:grid-cols-2"
          role="group"
          aria-label="Порядок блоков"
        >
          <div
            v-for="(block, index) in blocks"
            :key="block.id"
            class="flex min-w-0 flex-wrap items-center gap-1 rounded-lg p-1"
            :class="selected?.id === block.id ? 'bg-primary-50' : 'bg-gray-50'"
          >
            <UiButton
              :variant="
                selected?.id === block.id ? 'primary-ghost' : 'neutral-ghost'
              "
              size="sm"
              class="min-w-0 flex-1 justify-start break-words text-left"
              :aria-pressed="selected?.id === block.id"
              :disabled="editor.resourceDirty.value || editor.busy.value"
              @click="select(block.id)"
              >{{ index + 1 }}. {{ blockLabels[block.type]
              }}{{ block.enabled ? '' : ' · Выключен'
              }}<ChevronRight
                :size="16"
                class="ml-auto shrink-0"
                aria-hidden="true"
            /></UiButton>
            <UiButton
              variant="surface"
              size="sm"
              :aria-label="`Поднять блок ${index + 1}`"
              :disabled="
                index === 0 || editor.busy.value || editor.resourceDirty.value
              "
              @click="editor.move(block.id, -1)"
              ><ArrowUp :size="16" aria-hidden="true"
            /></UiButton>
            <UiButton
              variant="surface"
              size="sm"
              :aria-label="`Опустить блок ${index + 1}`"
              :disabled="
                index === blocks.length - 1 ||
                editor.busy.value ||
                editor.resourceDirty.value
              "
              @click="editor.move(block.id, 1)"
              ><ArrowDown :size="16" aria-hidden="true"
            /></UiButton>
            <UiButton
              variant="danger-ghost"
              size="sm"
              :aria-label="`Удалить блок ${index + 1}`"
              :disabled="editor.busy.value || editor.resourceDirty.value"
              @click="removing = block.id"
              ><Trash2 :size="16" aria-hidden="true"
            /></UiButton>
          </div>
        </div>
        <UiEmptyState
          v-if="!blocks.length"
          label="Блоков пока нет. Добавьте первый блок."
        />
        <div class="mt-4 flex min-w-0 flex-wrap items-center gap-2">
          <UiSelect
            v-model="adding"
            teleport-menu
            class="min-w-0 flex-1"
            :options="allowed"
            accessible-name="Тип нового блока"
            :disabled="
              editor.busy.value ||
              editor.resourceDirty.value ||
              blocks.length >= 40
            "
          />
          <UiButton
            variant="secondary"
            size="sm"
            :disabled="
              editor.busy.value ||
              editor.resourceDirty.value ||
              blocks.length >= 40
            "
            @click="add"
            >Добавить блок</UiButton
          >
        </div>
        <p
          v-if="blocks.length >= 40"
          class="mt-2 text-sm text-gray-500"
          role="status"
        >
          На странице может быть не более 40 блоков.
        </p>
      </UiCard>
      <UiCard
        v-if="selected"
        class="min-w-0"
        :aria-label="`Настройки: ${blockLabels[selected.type]}`"
      >
        <template #header>
          <h3 class="flex items-center gap-3 font-semibold text-gray-500">
            <Settings
              :size="20"
              class="shrink-0"
              aria-hidden="true"
            />Настройки: {{ blockLabels[selected.type] }}
          </h3>
        </template>
        <UiCheckbox
          mode="boolean"
          class="mb-3"
          :checked="selected.enabled"
          :disabled="editor.busy.value || editor.resourceDirty.value"
          @update:checked="selected.enabled = $event"
          >Показывать блок на сайте</UiCheckbox
        >
        <BlockSettings
          :key="selected.id"
          :block="selected"
          :disabled="editor.busy.value"
          @dirty="blockResourceDirty = $event"
          @busy="blockResourceBusy = $event"
        />
      </UiCard>
    </div>
    <div
      v-show="tab === 'settings'"
      :id="`${tabsId}-settings-panel`"
      role="tabpanel"
      :aria-labelledby="`${tabsId}-settings`"
      tabindex="0"
      class="min-w-0 space-y-3 py-3 admin-focus"
    >
      <fieldset :disabled="editor.busy.value" class="min-w-0 space-y-3">
        <legend class="sr-only">Основные настройки страницы</legend>
        <UiCard aria-label="Настройки страницы">
          <template #header>
            <h2 class="flex items-center gap-3 font-semibold text-gray-500">
              <Settings
                :size="20"
                class="shrink-0"
                aria-hidden="true"
              />Настройки страницы
            </h2>
          </template>
          <div class="grid gap-3 sm:grid-cols-2">
            <UiField label="Название страницы" required
              ><UiInput
                v-model="editor.form.value.title"
                required
                maxlength="255"
            /></UiField>
            <UiField
              label="Адрес страницы (slug)"
              required
              :help="
                systemPageSlugs.includes(page.slug)
                  ? 'Адрес системной страницы закреплён.'
                  : 'Латинские строчные буквы, цифры и дефисы.'
              "
              ><UiInput
                v-model="editor.form.value.slug"
                :disabled="systemPageSlugs.includes(page.slug)"
                required
                :pattern="slugPattern"
                maxlength="255"
            /></UiField>
          </div>
        </UiCard>
        <UiCard aria-label="Содержимое страницы">
          <template #header>
            <h2 class="flex items-center gap-3 font-semibold text-gray-500">
              <FileText
                :size="20"
                class="shrink-0"
                aria-hidden="true"
              />Содержимое страницы
            </h2>
          </template>
          <UiField
            label="Текст страницы"
            help="Обычный текст. HTML будет показан как текст."
            ><UiTextarea
              v-model="editor.form.value.body"
              compact
              rows="5"
              maxlength="100000"
          /></UiField>
        </UiCard>
      </fieldset>
      <UiCard
        v-if="$slots['settings-actions']"
        aria-label="Публикация страницы"
      >
        <template #header>
          <h2 class="flex items-center gap-3 font-semibold text-gray-500">
            <Globe :size="20" class="shrink-0" aria-hidden="true" />Публикация
          </h2>
        </template>
        <div class="flex flex-wrap gap-2">
          <slot name="settings-actions" />
        </div>
      </UiCard>
    </div>
    <div
      v-show="tab === 'seo'"
      :id="`${tabsId}-seo-panel`"
      role="tabpanel"
      :aria-labelledby="`${tabsId}-seo`"
      tabindex="0"
      class="min-w-0 py-3 admin-focus"
    >
      <fieldset
        v-if="editor.form.value.seo"
        :disabled="editor.busy.value"
        class="min-w-0 space-y-3"
      >
        <legend class="sr-only">SEO страницы</legend>
        <UiCard aria-label="Индексация страницы">
          <template #header>
            <h2 class="flex items-center gap-3 font-semibold text-gray-500">
              <Globe :size="20" class="shrink-0" aria-hidden="true" />Индексация
            </h2>
          </template>
          <UiCheckbox
            mode="boolean"
            :checked="editor.form.value.is_indexable ?? true"
            :disabled="editor.busy.value || editor.resourceBusy.value"
            @update:checked="editor.form.value.is_indexable = $event"
            >Разрешить индексацию поисковыми системами</UiCheckbox
          >
          <p class="mt-3 text-sm text-gray-500">
            Изменение индексации и SEO-параметров начнёт действовать после
            сохранения черновика и публикации страницы. Отключение индексации
            сохраняет заполненные поля.
          </p>
        </UiCard>
        <HomePageSeoEditor
          :key="page.id"
          v-model="editor.form.value.seo"
          :disabled="editor.busy.value || editor.resourceBusy.value"
          @pending="seoResourceDirty = $event"
          @uploading="seoResourceBusy = $event"
        />
      </fieldset>
    </div>
    <p
      v-if="editor.dirty.value"
      class="mt-5 text-sm text-gray-500"
      role="status"
    >
      Есть несохранённые изменения страницы. Сохраните черновик перед
      публикацией.
    </p>
    <div
      v-if="showActions"
      class="mt-6 flex flex-wrap gap-2 border-t border-gray-200 pt-4"
    >
      <UiButton
        :loading="editor.busy.value"
        :disabled="editor.resourceDirty.value || !editor.dirty.value"
        @click="editor.save"
        >Сохранить черновик блоков</UiButton
      >
      <UiButton
        variant="secondary"
        :disabled="
          editor.pending.value ||
          (page.is_published && !page.has_unpublished_changes)
        "
        @click="editor.publish"
        >Опубликовать блоки страницы</UiButton
      >
    </div>
    <ConfirmDialog
      :open="removing !== null"
      title="Удалить блок?"
      description="Блок будет удалён из локального черновика. Файлы и связанные баннеры сохранятся."
      @close="removing = null"
      @confirm="remove"
    />
  </section>
</template>
