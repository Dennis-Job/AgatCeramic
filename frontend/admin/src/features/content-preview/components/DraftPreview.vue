<script setup lang="ts">
import { computed, ref } from 'vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
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
    class="content-preview-panel min-w-0 self-start"
    aria-labelledby="draft-preview-title"
  >
    <template #header>
      <h2 id="draft-preview-title" class="text-lg font-semibold text-gray-500">
        Предпросмотр
      </h2>
    </template>
    <p class="mt-2 text-sm text-gray-500" role="status">
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
          class="text-primary-500 underline admin-focus"
          >Открыть полноразмерный просмотр</a
        >
        <a
          :href="links.published"
          target="_blank"
          rel="noopener noreferrer"
          referrerpolicy="no-referrer"
          class="text-primary-500 underline admin-focus"
          >Открыть опубликованную страницу</a
        >
      </div>
      <p class="mt-3 text-xs text-gray-500">
        Для просмотра нужна действующая сессия сотрудника с правом управления
        контентом. Если сессия истекла, войдите в админку и обновите просмотр.
      </p>
      <div
        class="preview-viewport mt-4 rounded-lg border border-gray-200 bg-gray-50 admin-focus"
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
    <UiNotification v-else-if="slug"
      >Не удалось настроить предпросмотр. Проверьте
      VITE_CLIENT_URL.</UiNotification
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
