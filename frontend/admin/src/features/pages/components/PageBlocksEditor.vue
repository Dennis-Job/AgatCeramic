<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
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

const props = defineProps<{ page: ContentPage }>()
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
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', beforeUnload)
  emit('pending', false)
  emit('busy', false)
})
</script>

<template>
  <UiCard class="mt-4 min-w-0" aria-labelledby="page-blocks-title">
    <template #header>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2
            id="page-blocks-title"
            class="text-lg font-semibold text-gray-500"
          >
            Блоки страницы
          </h2>
          <p class="mt-1 text-sm text-gray-500">
            Порядок в списке соответствует порядку на сайте. Выключенный блок
            сохранится в черновике.
          </p>
        </div>
        <UiBadge :tone="editor.dirty.value ? 'warning' : 'neutral'">{{
          editor.dirty.value ? 'Не сохранено' : 'Сохранённый черновик'
        }}</UiBadge>
      </div>
    </template>
    <UiNotification v-if="editor.error.value">{{
      editor.error.value
    }}</UiNotification>
    <UiNotification v-if="editor.success.value" tone="success">{{
      editor.success.value
    }}</UiNotification>
    <div class="mt-5 space-y-2" role="group" aria-label="Порядок блоков">
      <div
        v-for="(block, index) in blocks"
        :key="block.id"
        class="flex min-w-0 flex-wrap items-center gap-1 rounded-lg border border-gray-200 p-2"
      >
        <UiButton
          variant="ghost"
          size="sm"
          class="min-w-0 flex-1 break-words"
          :aria-pressed="selected?.id === block.id"
          :disabled="editor.resourceDirty.value || editor.busy.value"
          @click="select(block.id)"
          >{{ index + 1 }}. {{ blockLabels[block.type]
          }}{{ block.enabled ? '' : ' · Выключен' }}</UiButton
        >
        <UiButton
          variant="ghost"
          size="sm"
          :aria-label="`Поднять блок ${index + 1}`"
          :disabled="
            index === 0 || editor.busy.value || editor.resourceDirty.value
          "
          @click="editor.move(block.id, -1)"
          >↑</UiButton
        >
        <UiButton
          variant="ghost"
          size="sm"
          :aria-label="`Опустить блок ${index + 1}`"
          :disabled="
            index === blocks.length - 1 ||
            editor.busy.value ||
            editor.resourceDirty.value
          "
          @click="editor.move(block.id, 1)"
          >↓</UiButton
        >
        <UiButton
          variant="danger-ghost"
          size="sm"
          :aria-label="`Удалить блок ${index + 1}`"
          :disabled="editor.busy.value || editor.resourceDirty.value"
          @click="removing = block.id"
          >Удалить</UiButton
        >
      </div>
    </div>
    <UiEmptyState
      v-if="!blocks.length"
      label="Блоков пока нет. Добавьте первый блок."
    />
    <div class="mt-4 flex min-w-0 flex-wrap items-center gap-2">
      <UiSelect
        v-model="adding"
        class="min-w-0 flex-1"
        :options="allowed"
        accessible-name="Тип нового блока"
        :disabled="
          editor.busy.value || editor.resourceDirty.value || blocks.length >= 40
        "
      />
      <UiButton
        variant="secondary"
        size="sm"
        :disabled="
          editor.busy.value || editor.resourceDirty.value || blocks.length >= 40
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
    <div
      v-if="selected"
      class="mt-6 min-w-0 border-t border-gray-200 pt-5"
      role="region"
      :aria-label="`Настройки: ${blockLabels[selected.type]}`"
    >
      <h3 class="mb-4 font-semibold">
        Настройки: {{ blockLabels[selected.type] }}
      </h3>
      <UiCheckbox
        mode="boolean"
        class="mb-4"
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
    </div>
    <p
      v-if="editor.dirty.value"
      class="mt-5 text-sm text-gray-500"
      role="status"
    >
      Есть несохранённые изменения блоков. Сохраните черновик перед публикацией.
    </p>
    <fieldset
      v-if="page.slug === 'home' && editor.form.value.seo"
      class="mt-6 min-w-0 border-t border-gray-200 pt-5"
      :disabled="editor.busy.value"
    >
      <legend class="sr-only">SEO страницы</legend>
      <HomePageSeoEditor
        :key="page.id"
        v-model="editor.form.value.seo"
        @pending="seoResourceDirty = $event"
        @uploading="seoResourceBusy = $event"
      />
    </fieldset>
    <div class="mt-6 flex flex-wrap gap-2 border-t border-gray-200 pt-4">
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
  </UiCard>
</template>
