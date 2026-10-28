<script setup lang="ts">
import { reactive, toRef, watch } from 'vue'
import { ArrowDown, ArrowUp, Menu, Plus, Trash2 } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSwitch from '../../../components/ui/UiSwitch.vue'
import type {
  NavigationEditorRow,
  NavigationEditorDraft,
  SiteNavigationItem,
} from '../types/appearance.types'

const model = defineModel<SiteNavigationItem[]>({ required: true })
const props = withDefaults(
  defineProps<{ disabled?: boolean; draft?: NavigationEditorDraft }>(),
  { draft: () => ({ rows: [] }) },
)
const rows = toRef(reactive(props.draft), 'rows')
let nextId = Math.max(-1, ...rows.value.map((row) => row.id)) + 1

function activeItems(): SiteNavigationItem[] {
  return rows.value.map((row) =>
    row.hasChildren
      ? {
          label: row.label,
          children: row.children.map(({ label, to }) => ({ label, to })),
        }
      : { label: row.label, to: row.to },
  )
}

// A server acknowledgement with the same active fields must not erase hidden local input.
watch(
  model,
  (items) => {
    if (JSON.stringify(items) === JSON.stringify(activeItems())) return
    rows.value = items.map((item) => ({
      id: nextId++,
      label: item.label,
      hasChildren: 'children' in item,
      to: 'to' in item ? item.to : '',
      children:
        'children' in item
          ? item.children.map((child) => ({ ...child }))
          : [{ label: '', to: '' }],
    }))
  },
  { deep: true, immediate: true },
)

watch(
  rows,
  () => {
    if (
      !props.disabled &&
      JSON.stringify(model.value) !== JSON.stringify(activeItems())
    )
      model.value = activeItems()
  },
  { deep: true },
)

function move<T>(items: T[], index: number, offset: -1 | 1): void {
  const next = index + offset
  if (props.disabled || next < 0 || next >= items.length) return
  ;[items[index], items[next]] = [items[next]!, items[index]!]
}
function addParent(): void {
  if (props.disabled || rows.value.length >= 16) return
  rows.value.push({
    id: nextId++,
    label: '',
    hasChildren: false,
    to: '',
    children: [{ label: '', to: '' }],
  })
}
function removeParent(index: number): void {
  if (!props.disabled && rows.value.length > 1) rows.value.splice(index, 1)
}
function addChild(row: NavigationEditorRow): void {
  if (!props.disabled && row.children.length < 16)
    row.children.push({ label: '', to: '' })
}
function removeChild(row: NavigationEditorRow, index: number): void {
  if (!props.disabled && row.children.length > 1) row.children.splice(index, 1)
}
function toggle(row: NavigationEditorRow, value: boolean): void {
  if (props.disabled) return
  row.hasChildren = value
  if (value && row.children.length === 0)
    row.children.push({ label: '', to: '' })
}
</script>

<template>
  <UiCard aria-labelledby="appearance-navigation-title">
    <template #header>
      <h3
        id="appearance-navigation-title"
        class="flex items-center gap-3 font-semibold text-gray-500"
      >
        <Menu :size="20" class="shrink-0" aria-hidden="true" />Навигация
      </h3>
    </template>
    <p class="mb-4 text-xs text-gray-500">
      На компьютере — между логотипом и иконками, на узких экранах — в боковом
      меню. Пункт с подменю только раскрывает вложенные ссылки. До 16 пунктов и
      16 подпунктов в каждом.
    </p>
    <ol class="divide-y divide-gray-100">
      <li
        v-for="(row, index) in rows"
        :key="row.id"
        class="min-w-0 space-y-3 py-4 first:pt-0"
        :aria-label="`Пункт меню ${index + 1}`"
      >
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span class="text-xs text-gray-500">Пункт {{ index + 1 }}</span>
          <div class="flex gap-1">
            <UiButton
              variant="surface"
              size="sm"
              :disabled="disabled || index === 0"
              :aria-label="`Поднять пункт меню ${index + 1}`"
              @click="move(rows, index, -1)"
              ><ArrowUp :size="16" aria-hidden="true"
            /></UiButton>
            <UiButton
              variant="surface"
              size="sm"
              :disabled="disabled || index === rows.length - 1"
              :aria-label="`Опустить пункт меню ${index + 1}`"
              @click="move(rows, index, 1)"
              ><ArrowDown :size="16" aria-hidden="true"
            /></UiButton>
            <UiButton
              variant="danger-ghost"
              size="sm"
              :disabled="disabled || rows.length === 1"
              :aria-label="`Удалить пункт меню ${index + 1}`"
              @click="removeParent(index)"
              ><Trash2 :size="16" aria-hidden="true"
            /></UiButton>
          </div>
        </div>
        <div class="grid min-w-0 gap-3 sm:grid-cols-2">
          <UiField label="Название" required
            ><UiInput
              v-model="row.label"
              aria-label="Название"
              :disabled="disabled"
              maxlength="255"
              required
          /></UiField>
          <UiField
            v-if="!row.hasChildren"
            label="Ссылка"
            help="Внутренний путь /... или HTTPS-адрес."
            required
            ><UiInput
              v-model="row.to"
              aria-label="Ссылка"
              :disabled="disabled"
              maxlength="2048"
              required
          /></UiField>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <UiSwitch
            :checked="row.hasChildren"
            :accessible-name="`Есть подменю у пункта ${index + 1}`"
            :disabled="disabled"
            @update:checked="toggle(row, $event)"
          />
          <span class="text-sm text-gray-500">Есть подменю</span>
        </div>
        <p class="text-xs text-gray-500">
          Сохраняется только выбранный режим. Скрытые значения сохраняются до
          выхода из редактора.
        </p>
        <div
          v-if="row.hasChildren"
          class="min-w-0 border-l border-gray-200 pl-3"
        >
          <ol
            class="divide-y divide-gray-100"
            :aria-label="`Подменю пункта ${index + 1}`"
          >
            <li
              v-for="(child, childIndex) in row.children"
              :key="childIndex"
              class="min-w-0 space-y-2 py-3 first:pt-0"
              :aria-label="`Подпункт ${childIndex + 1} пункта меню ${index + 1}`"
            >
              <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-xs text-gray-500"
                  >Подпункт {{ childIndex + 1 }}</span
                >
                <div class="flex gap-1">
                  <UiButton
                    variant="surface"
                    size="sm"
                    :disabled="disabled || childIndex === 0"
                    :aria-label="`Поднять подпункт ${childIndex + 1} пункта меню ${index + 1}`"
                    @click="move(row.children, childIndex, -1)"
                    ><ArrowUp :size="16" aria-hidden="true"
                  /></UiButton>
                  <UiButton
                    variant="surface"
                    size="sm"
                    :disabled="
                      disabled || childIndex === row.children.length - 1
                    "
                    :aria-label="`Опустить подпункт ${childIndex + 1} пункта меню ${index + 1}`"
                    @click="move(row.children, childIndex, 1)"
                    ><ArrowDown :size="16" aria-hidden="true"
                  /></UiButton>
                  <UiButton
                    variant="danger-ghost"
                    size="sm"
                    :disabled="disabled || row.children.length === 1"
                    :aria-label="`Удалить подпункт ${childIndex + 1} пункта меню ${index + 1}`"
                    @click="removeChild(row, childIndex)"
                    ><Trash2 :size="16" aria-hidden="true"
                  /></UiButton>
                </div>
              </div>
              <div class="grid min-w-0 gap-3 sm:grid-cols-2">
                <UiField label="Название" required
                  ><UiInput
                    v-model="child.label"
                    aria-label="Название"
                    :disabled="disabled"
                    maxlength="255"
                    required
                /></UiField>
                <UiField
                  label="Ссылка"
                  help="Внутренний путь /... или HTTPS-адрес."
                  required
                  ><UiInput
                    v-model="child.to"
                    aria-label="Ссылка"
                    :disabled="disabled"
                    maxlength="2048"
                    required
                /></UiField>
              </div>
            </li>
          </ol>
          <UiButton
            variant="primary-ghost"
            size="sm"
            class="mt-3"
            :disabled="disabled || row.children.length >= 16"
            :aria-label="`Добавить подпункт в пункт меню ${index + 1}`"
            @click="addChild(row)"
            ><Plus :size="16" aria-hidden="true" />Добавить подпункт</UiButton
          >
        </div>
      </li>
    </ol>
    <UiButton
      variant="primary-ghost"
      size="sm"
      class="mt-3"
      aria-label="Добавить пункт меню"
      :disabled="disabled || rows.length >= 16"
      @click="addParent"
      ><Plus :size="16" aria-hidden="true" />Добавить пункт меню</UiButton
    >
  </UiCard>
</template>
