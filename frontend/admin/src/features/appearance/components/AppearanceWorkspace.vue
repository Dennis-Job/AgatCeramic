<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  onBeforeRouteLeave,
  onBeforeRouteUpdate,
  useRoute,
  useRouter,
} from 'vue-router'
import AdminEditorLayout from '../../../components/shared/AdminEditorLayout.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import { useAppearance } from '../composables/useAppearance'
import type { AppearancePanel } from '../types/appearance.types'
import SiteChromeEditor from './SiteChromeEditor.vue'
import DraftPreview from '../../content-preview/components/DraftPreview.vue'

const workspace = useAppearance()
const route = useRoute()
const router = useRouter()
const mediaPending = ref(false)
const uploading = ref(false)
const previewRevision = ref(0)
watch(
  () => workspace.content.value,
  () => previewRevision.value++,
)
const panels: { key: AppearancePanel; label: string }[] = [
  { key: 'header', label: 'Шапка и навигация' },
  { key: 'footer', label: 'Подвал' },
  { key: 'filters', label: 'Вид фильтров' },
]
const panel = computed<AppearancePanel>(() =>
  route.query.panel === 'footer' || route.query.panel === 'filters'
    ? route.query.panel
    : 'header',
)
const pending = computed(() => workspace.dirty.value || mediaPending.value)
const busy = computed(() => workspace.busy.value || uploading.value)
function selectPanel(value: AppearancePanel): void {
  if (busy.value || mediaPending.value) return
  void router.push({ query: { ...route.query, panel: value } })
}
function confirmLeave(): boolean {
  return (
    !busy.value &&
    (!pending.value ||
      window.confirm(
        'Есть несохранённые изменения оформления. Покинуть редактор и потерять их?',
      ))
  )
}
onBeforeRouteLeave(confirmLeave)
onBeforeRouteUpdate((to, from) => {
  if (to.query.section !== from.query.section) return confirmLeave()
  if (to.query.panel !== from.query.panel && (busy.value || mediaPending.value))
    return false
  return true
})
function beforeUnload(event: BeforeUnloadEvent): void {
  if (!pending.value && !busy.value) return
  event.preventDefault()
  event.returnValue = ''
}
onMounted(() => window.addEventListener('beforeunload', beforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))
</script>

<template>
  <section class="min-w-0">
    <AdminEditorLayout>
      <template #toolbar-navigation
        ><slot name="toolbar-navigation"
      /></template>
      <template #toolbar>
        <div class="flex min-w-0 flex-wrap items-center gap-3">
          <h1 class="text-xl font-semibold">Общее оформление</h1>
          <UiBadge
            v-if="workspace.content.value"
            :tone="
              workspace.content.value.has_unpublished_changes
                ? 'neutral'
                : 'success'
            "
            >{{
              workspace.content.value.has_unpublished_changes
                ? 'Есть неопубликованные изменения'
                : 'Оформление опубликовано'
            }}</UiBadge
          >
        </div>
        <div v-if="workspace.content.value" class="flex flex-wrap gap-2">
          <UiButton
            v-if="panel !== 'filters'"
            variant="secondary"
            aria-label="Сохранить черновик раздела"
            :loading="workspace.saving.value"
            :disabled="busy || mediaPending || !workspace.isDirty(panel)"
            @click="workspace.save(panel)"
            >Сохранить черновик</UiButton
          >
          <UiButton
            :loading="workspace.publishing.value"
            :disabled="
              busy ||
              pending ||
              !workspace.content.value.has_unpublished_changes
            "
            @click="workspace.publish()"
            >Опубликовать оформление</UiButton
          >
        </div>
      </template>
      <template #navigation>
        <nav aria-label="Разделы общего оформления" class="space-y-2">
          <h2 class="mb-4 font-semibold">Элементы сайта</h2>
          <UiButton
            v-for="item in panels"
            :key="item.key"
            class="w-full justify-start text-left"
            :variant="panel === item.key ? 'primary-ghost' : 'neutral-ghost'"
            :class="panel === item.key && 'bg-primary-50'"
            :aria-pressed="panel === item.key"
            :disabled="busy || mediaPending"
            @click="selectPanel(item.key)"
            >{{ item.label
            }}<span
              v-if="item.key !== 'filters' && workspace.isDirty(item.key)"
              class="ml-auto"
              aria-label="Есть несохранённые изменения"
              >•</span
            ></UiButton
          >
        </nav>
      </template>
      <template #editor>
        <UiNotification v-if="workspace.error.value">{{
          workspace.error.value
        }}</UiNotification>
        <UiNotification v-if="workspace.success.value" tone="success">{{
          workspace.success.value
        }}</UiNotification>
        <UiButton
          v-if="workspace.error.value && !workspace.content.value"
          class="mb-4"
          variant="secondary"
          :disabled="busy"
          @click="workspace.load()"
          >Повторить загрузку</UiButton
        >
        <UiLoadingState
          v-if="workspace.loading.value"
          label="Загрузка общего оформления…"
        />
        <div v-if="workspace.content.value" class="min-w-0">
          <section
            v-if="panel === 'filters'"
            class="min-w-0"
            role="region"
            aria-labelledby="appearance-filters-title"
          >
            <h2 id="appearance-filters-title" class="mb-4 font-semibold">
              Представление фильтров
            </h2>
            <p class="text-sm text-gray-500" role="status">
              Фильтры клиентского каталога пока не реализованы. Настройки их
              внешнего вида появятся вместе с фильтрами каталога.
            </p>
            <p class="mt-3 text-sm text-gray-500">
              Здесь будут параметры представления. Состав фильтров,
              характеристики и значения товаров управляются в каталоге.
            </p>
          </section>
          <section
            v-else
            class="min-w-0"
            role="region"
            aria-labelledby="appearance-editor-title"
          >
            <h2 id="appearance-editor-title" class="mb-4 font-semibold">
              {{ panel === 'header' ? 'Шапка и навигация' : 'Подвал' }}
            </h2>
            <fieldset class="min-w-0" :disabled="busy">
              <legend class="sr-only">
                {{ panel === 'header' ? 'Шапка и навигация' : 'Подвал' }}
              </legend>
              <SiteChromeEditor
                :content="workspace.content.value"
                :section="panel"
                :disabled="busy"
                @pending="mediaPending = $event"
                @uploading="uploading = $event"
              />
            </fieldset>
          </section>
          <p
            class="mt-5 border-t border-gray-200 pt-4 text-sm text-gray-500"
            role="status"
          >
            {{
              pending
                ? 'Есть несохранённые изменения. Перед публикацией сохраните каждый изменённый раздел.'
                : 'Сохранение меняет только черновик. Публикация оформления обновляет общие элементы всех страниц сайта.'
            }}
          </p>
        </div>
      </template>
      <template #preview>
        <DraftPreview
          v-if="workspace.content.value"
          slug="home"
          :revision="previewRevision"
          :pending="pending"
        />
      </template>
    </AdminEditorLayout>
  </section>
</template>
