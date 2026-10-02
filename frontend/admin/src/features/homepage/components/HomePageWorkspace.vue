<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import PageBlocksEditor from '../../pages/components/PageBlocksEditor.vue'
import { getPage } from '../../pages/services/pages'
import type { ContentPage } from '../../pages/types/page.types'
import { getHomePage } from '../services/homepage'

const emit = defineEmits<{
  saved: [page: ContentPage]
  pending: [value: boolean]
}>()
const page = ref<ContentPage | null>(null)
const loading = ref(false)
const error = ref('')
async function load(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const home = await getHomePage()
    page.value = await getPage(home.page_id)
    emit('saved', page.value)
  } catch (reason) {
    error.value =
      reason instanceof Error
        ? reason.message
        : 'Не удалось загрузить главную страницу.'
  } finally {
    loading.value = false
  }
}
function saved(value: ContentPage): void {
  page.value = value
  emit('saved', value)
}
onMounted(load)
</script>

<template>
  <AdminWorkspace mode="editor">
    <PageHeader
      class="mb-6"
      eyebrow="Управление сайтом"
      title="Главная страница"
      description="Сохраняйте блоки в черновик, затем публикуйте страницу отдельным действием."
    />
    <UiLoadingState v-if="loading" label="Загрузка главной страницы…" />
    <template v-else-if="error && !page">
      <UiNotification>{{ error }}</UiNotification>
      <UiButton class="mt-3" variant="secondary" @click="load"
        >Повторить загрузку</UiButton
      >
    </template>
    <template v-else-if="page">
      <UiNotification v-if="error">{{ error }}</UiNotification>
      <UiBadge :tone="page.is_published ? 'success' : 'neutral'">{{
        page.is_published ? 'Опубликована' : 'Черновик'
      }}</UiBadge>
      <p
        v-if="page.has_unpublished_changes"
        class="mt-2 text-sm text-gray-600"
        role="status"
      >
        В черновике есть неопубликованные изменения.
      </p>
      <PageBlocksEditor
        :page="page"
        @saved="saved"
        @pending="emit('pending', $event)"
      />
    </template>
  </AdminWorkspace>
</template>
