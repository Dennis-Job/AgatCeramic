<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  Monitor,
  Smartphone,
  RefreshCw,
  Maximize2,
  ExternalLink,
} from '@lucide/vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import { previewLinks } from '../services/previewLinks'

const props = withDefaults(
  defineProps<{ slug?: string; revision?: number; pending?: boolean }>(),
  { slug: undefined, revision: 0, pending: false },
)
const mobile = ref(false)
const refresh = ref(0)
const viewport = ref<HTMLElement | null>(null)
const width = ref(1280)
const height = ref(720)
const nativeWidth = computed(() => (mobile.value ? 375 : 1280))
const scale = computed(() => Math.min(1, width.value / nativeWidth.value))
watch(
  viewport,
  (element, _, onCleanup) => {
    if (!element || typeof ResizeObserver === 'undefined') return
    const observer = new ResizeObserver(([entry]) => {
      if (!entry) return
      width.value = Math.max(1, entry.contentRect.width)
      height.value = Math.max(1, entry.contentRect.height)
    })
    observer.observe(element)
    onCleanup(() => observer.disconnect())
  },
  { flush: 'post' },
)
const links = computed(() => {
  if (!props.slug) return null
  try {
    return previewLinks(props.slug)
  } catch {
    return null
  }
})
</script>

<template>
  <section
    class="content-preview-panel min-w-0"
    aria-labelledby="draft-preview-title"
  >
    <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <div class="flex min-w-0 flex-wrap items-center gap-2">
        <h2 id="draft-preview-title" class="font-semibold">Предпросмотр</h2>
        <span class="text-xs text-gray-500" role="status"
          >Сохранённый черновик</span
        >
      </div>
      <div
        v-if="links"
        class="flex flex-wrap gap-1"
        role="group"
        aria-label="Размер предпросмотра"
      >
        <UiButton
          size="sm"
          :variant="mobile ? 'neutral-ghost' : 'primary-ghost'"
          :aria-pressed="!mobile"
          tooltip="Компьютер"
          @click="mobile = false"
          ><Monitor :size="18" aria-hidden="true" /><span class="sr-only"
            >Компьютер</span
          ></UiButton
        >
        <UiButton
          size="sm"
          :variant="mobile ? 'primary-ghost' : 'neutral-ghost'"
          :aria-pressed="mobile"
          tooltip="Телефон"
          @click="mobile = true"
          ><Smartphone :size="18" aria-hidden="true" /><span class="sr-only"
            >Телефон</span
          ></UiButton
        >
        <UiButton
          size="sm"
          variant="neutral-ghost"
          tooltip="Обновить просмотр"
          @click="refresh++"
          ><RefreshCw :size="18" aria-hidden="true" /><span class="sr-only"
            >Обновить просмотр</span
          ></UiButton
        >
        <a
          :href="links.draft"
          target="_blank"
          rel="noopener noreferrer"
          referrerpolicy="no-referrer"
          class="preview-link admin-focus"
          title="Открыть полноразмерный просмотр"
          ><Maximize2 :size="18" aria-hidden="true" /><span class="sr-only"
            >Открыть полноразмерный просмотр</span
          ></a
        >
      </div>
    </header>
    <UiAlert v-if="pending" class="mb-3" tone="info" role="status"
      >Несохранённые изменения ещё не вошли в предпросмотр.</UiAlert
    >
    <template v-if="links">
      <div
        ref="viewport"
        class="preview-viewport rounded-lg border border-gray-200 bg-gray-50 admin-focus"
        tabindex="0"
        role="region"
        aria-label="Область просмотра сайта"
      >
        <div
          class="preview-canvas"
          :style="{ width: `${nativeWidth * scale}px` }"
        >
          <iframe
            :key="`${slug}:${revision}:${refresh}`"
            :src="links.draft"
            :title="`Сохранённый черновик: ${slug}`"
            referrerpolicy="no-referrer"
            :class="mobile ? 'preview-mobile' : 'preview-desktop'"
            :style="{
              height: `${height / scale}px`,
              transform: `scale(${scale})`,
            }"
          />
        </div>
      </div>
      <footer
        class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-500"
      >
        <p>Предпросмотр обновляется после сохранения черновика.</p>
        <a
          :href="links.published"
          target="_blank"
          rel="noopener noreferrer"
          referrerpolicy="no-referrer"
          class="inline-flex items-center gap-1 text-primary-500 admin-focus"
          ><ExternalLink :size="14" aria-hidden="true" />Открыть опубликованную
          страницу</a
        >
        <details class="w-full">
          <summary class="cursor-pointer admin-focus">
            Доступ к предпросмотру
          </summary>
          <p class="mt-2">
            Для просмотра нужна действующая сессия сотрудника с правом
            управления контентом. Если сессия истекла, войдите в админку и
            обновите просмотр. Сайт обновляется после отдельной публикации.
          </p>
        </details>
      </footer>
    </template>
    <UiNotification v-else-if="slug"
      >Не удалось настроить предпросмотр. Проверьте
      VITE_CLIENT_URL.</UiNotification
    >
    <UiEmptyState v-else label="Выберите страницу для предпросмотра." />
  </section>
</template>

<style scoped>
.preview-viewport {
  max-width: 100%;
  height: 720px;
  overflow: auto;
}
.preview-canvas {
  position: relative;
  height: 100%;
  margin-inline: auto;
  overflow: hidden;
}
.preview-viewport iframe {
  display: block;
  border: 0;
  max-width: none;
  transform-origin: top left;
}
.preview-mobile {
  width: 375px;
}
.preview-desktop {
  width: 1280px;
}
.preview-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: var(--admin-control-height-sm);
  min-height: var(--admin-control-height-sm);
  border-radius: var(--admin-radius-lg);
}
.preview-link:hover {
  background: var(--admin-color-gray-100);
}
</style>
