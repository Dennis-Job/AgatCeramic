<script setup lang="ts">
import { computed, ref } from 'vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import { previewLinks } from '../services/previewLinks'

const props = withDefaults(
  defineProps<{ slug?: string; revision?: number; pending?: boolean }>(),
  { slug: undefined, revision: 0, pending: false },
)
const mobile = ref(false)
const refresh = ref(0)
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
  <UiCard
    class="content-preview-panel min-w-0 self-start p-4 sm:p-6"
    aria-label="Предпросмотр страницы"
  >
    <h2 class="text-lg font-semibold">Предпросмотр</h2>
    <p class="mt-2 text-sm text-gray-600" role="status">
      Сохранённый черновик страницы и общего оформления. Сайт обновляется после
      отдельной публикации.
    </p>
    <UiAlert v-if="pending" class="mt-3" tone="warning" role="status"
      >Несохранённые изменения ещё не вошли в предпросмотр.</UiAlert
    >
    <template v-if="links">
      <div
        class="mt-4 flex flex-wrap gap-2"
        role="group"
        aria-label="Размер предпросмотра"
      >
        <UiButton
          size="sm"
          :variant="mobile ? 'secondary' : 'primary'"
          :aria-pressed="!mobile"
          @click="mobile = false"
          >Компьютер</UiButton
        >
        <UiButton
          size="sm"
          :variant="mobile ? 'primary' : 'secondary'"
          :aria-pressed="mobile"
          @click="mobile = true"
          >Телефон</UiButton
        >
        <UiButton size="sm" variant="secondary" @click="refresh++"
          >Обновить просмотр</UiButton
        >
      </div>
      <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-sm">
        <a
          :href="links.draft"
          target="_blank"
          rel="noopener noreferrer"
          referrerpolicy="no-referrer"
          class="text-primary-700 underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
          >Открыть полноразмерный просмотр</a
        >
        <a
          :href="links.published"
          target="_blank"
          rel="noopener noreferrer"
          referrerpolicy="no-referrer"
          class="text-primary-700 underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
          >Открыть опубликованную страницу</a
        >
      </div>
      <p class="mt-3 text-xs text-gray-500">
        Для просмотра нужна действующая сессия сотрудника с правом управления
        контентом. Если сессия истекла, войдите в админку и обновите просмотр.
      </p>
      <div
        class="preview-viewport mt-4 rounded-lg border border-gray-200 bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
        tabindex="0"
        role="region"
        aria-label="Область просмотра сайта"
      >
        <iframe
          :key="`${slug}:${revision}:${refresh}`"
          :src="links.draft"
          :title="`Сохранённый черновик: ${slug}`"
          referrerpolicy="no-referrer"
          :class="mobile ? 'preview-mobile' : 'preview-desktop'"
        />
      </div>
    </template>
    <UiAlert v-else-if="slug" class="mt-4" role="alert"
      >Не удалось настроить предпросмотр. Проверьте VITE_CLIENT_URL.</UiAlert
    >
    <UiEmptyState v-else label="Выберите страницу для предпросмотра." />
  </UiCard>
</template>

<style scoped>
.preview-viewport {
  max-width: 100%;
  overflow: auto;
}
.preview-viewport iframe {
  display: block;
  height: 720px;
  border: 0;
}
.preview-mobile {
  width: 375px;
  margin-inline: auto;
}
.preview-desktop {
  width: 1280px;
}
</style>
