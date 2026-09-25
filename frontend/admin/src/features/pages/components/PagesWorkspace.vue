<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { FileText, Pencil, Plus, Trash2 } from '@lucide/vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import { usePages } from '../composables/usePages'
import type { ContentPage } from '../types/page.types'
import PageFormDialog from './PageFormDialog.vue'

const pages = usePages()
const deleting = ref<ContentPage | null>(null)

async function removeSelected(): Promise<void> {
  if (deleting.value && (await pages.remove(deleting.value)))
    deleting.value = null
}

function confirmDelete(page: ContentPage): void {
  pages.deleteError.value = ''
  deleting.value = page
}

onMounted(() => pages.load())
</script>

<template>
  <section class="mx-auto admin-page">
    <PageHeader class="mb-7" eyebrow="Страницы" title="Контент">
      <template #actions
        ><UiButton @click="pages.openEditor()"
          ><Plus :size="18" />Добавить страницу</UiButton
        ></template
      >
    </PageHeader>
    <UiAlert v-if="pages.error.value" class="mb-4">{{
      pages.error.value
    }}</UiAlert>
    <UiButton
      v-if="pages.error.value"
      class="mb-4"
      variant="secondary"
      @click="pages.load()"
      >Повторить загрузку</UiButton
    >
    <UiAlert v-if="pages.success.value" class="mb-4" tone="success">{{
      pages.success.value
    }}</UiAlert>
    <UiCard
      v-if="!pages.error.value || pages.loading.value"
      class="overflow-hidden"
    >
      <UiLoadingState v-if="pages.loading.value" label="Загрузка страниц…" />
      <div v-else-if="pages.items.value.length" class="divide-y">
        <article
          v-for="page in pages.items.value"
          :key="page.id"
          class="flex flex-wrap items-start gap-3 p-4 sm:flex-nowrap sm:items-center"
        >
          <FileText class="shrink-0 text-primary-600" aria-hidden="true" />
          <div class="min-w-0 flex-[1_1_calc(100%-52px)] sm:flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="break-words font-semibold">{{ page.title }}</h2>
              <UiBadge :tone="page.is_published ? 'success' : 'neutral'">{{
                page.is_published ? 'Опубликована' : 'Черновик'
              }}</UiBadge>
            </div>
            <p class="break-all text-sm text-gray-500">/{{ page.slug }}</p>
          </div>
          <div class="ml-[36px] flex sm:ml-0">
            <UiButton
              variant="ghost"
              size="sm"
              :aria-label="`Редактировать страницу ${page.title}`"
              @click="pages.openEditor(page)"
              ><Pencil :size="17"
            /></UiButton>
            <UiButton
              variant="danger-ghost"
              size="sm"
              :aria-label="`Удалить страницу ${page.title}`"
              @click="confirmDelete(page)"
              ><Trash2 :size="17"
            /></UiButton>
          </div>
        </article>
      </div>
      <UiEmptyState v-else label="Страниц пока нет." />
    </UiCard>
    <UiPagination
      v-if="pages.pagination.value"
      :meta="pages.pagination.value"
      :loading="pages.loading.value"
      @change="pages.load"
    />
    <PageFormDialog
      :open="pages.editorOpen.value"
      :editing="Boolean(pages.editing.value)"
      :busy="pages.busy.value"
      :error="pages.formError.value"
      :form="pages.form.value"
      @close="pages.closeEditor"
      @submit="pages.submit"
    />
    <ConfirmDialog
      :open="Boolean(deleting)"
      title="Удалить страницу?"
      :description="`Страница «${deleting?.title ?? ''}» будет удалена, а публичная ссылка перестанет работать.`"
      :busy="pages.busy.value"
      :error="pages.deleteError.value"
      @close="deleting = null"
      @confirm="removeSelected"
    />
  </section>
</template>
