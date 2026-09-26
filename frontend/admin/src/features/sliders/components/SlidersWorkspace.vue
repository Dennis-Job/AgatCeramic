<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Pencil, Plus, Trash2 } from '@lucide/vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import { useSliders } from '../composables/useSliders'
import type { Slider } from '../types/slider.types'
import SliderFormDialog from './SliderFormDialog.vue'

const sliders = useSliders()
const deleting = ref<Slider | null>(null)

async function removeSelected(): Promise<void> {
  if (deleting.value && (await sliders.remove(deleting.value)))
    deleting.value = null
}

function confirmDelete(slider: Slider): void {
  sliders.deleteError.value = ''
  deleting.value = slider
}

onMounted(() => sliders.load())
</script>

<template>
  <section class="mx-auto admin-page">
    <PageHeader class="mb-7" eyebrow="Контент" title="Слайдеры">
      <template #actions>
        <UiButton @click="sliders.openEditor()"
          ><Plus :size="18" />Добавить слайдер</UiButton
        >
      </template>
    </PageHeader>
    <UiAlert v-if="sliders.error.value" class="mb-4">{{
      sliders.error.value
    }}</UiAlert>
    <UiButton
      v-if="sliders.error.value"
      class="mb-4"
      variant="secondary"
      @click="sliders.load()"
      >Повторить загрузку</UiButton
    >
    <UiAlert v-if="sliders.success.value" class="mb-4" tone="success">{{
      sliders.success.value
    }}</UiAlert>
    <UiCard
      v-if="!sliders.error.value || sliders.loading.value"
      class="overflow-hidden"
    >
      <UiLoadingState
        v-if="sliders.loading.value"
        label="Загрузка слайдеров…"
      />
      <div v-else-if="sliders.items.value.length" class="divide-y">
        <article
          v-for="slider in sliders.items.value"
          :key="slider.id"
          class="flex flex-wrap items-center gap-3 p-4"
        >
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="break-words font-semibold">{{ slider.name }}</h2>
              <UiBadge :tone="slider.is_published ? 'success' : 'neutral'">{{
                slider.is_published ? 'Опубликован' : 'Черновик'
              }}</UiBadge>
            </div>
            <p class="break-all text-sm text-gray-500">
              {{ slider.slug }} · {{ slider.banners.length }} баннеров
            </p>
          </div>
          <div class="ml-auto flex">
            <UiButton
              variant="ghost"
              size="sm"
              :aria-label="`Редактировать слайдер ${slider.name}`"
              @click="sliders.openEditor(slider)"
              ><Pencil :size="17"
            /></UiButton>
            <UiButton
              variant="danger-ghost"
              size="sm"
              :aria-label="`Удалить слайдер ${slider.name}`"
              @click="confirmDelete(slider)"
              ><Trash2 :size="17"
            /></UiButton>
          </div>
        </article>
      </div>
      <UiEmptyState v-else label="Слайдеров пока нет." />
    </UiCard>
    <UiPagination
      v-if="sliders.pagination.value"
      :meta="sliders.pagination.value"
      :loading="sliders.loading.value"
      @change="sliders.load"
    />
    <SliderFormDialog
      :open="sliders.editorOpen.value"
      :editing="Boolean(sliders.editing.value)"
      :busy="sliders.busy.value"
      :error="sliders.formError.value"
      :form="sliders.form.value"
      :selected-banners="sliders.selectedBanners.value"
      :options="sliders.options.value"
      :options-loading="sliders.optionsLoading.value"
      :options-error="sliders.optionsError.value"
      @close="sliders.closeEditor"
      @submit="sliders.submit"
      @search="sliders.searchBanners"
      @add="sliders.addBanner"
      @move="sliders.moveBanner"
      @remove="sliders.removeBanner"
    />
    <ConfirmDialog
      :open="Boolean(deleting)"
      title="Удалить слайдер?"
      :description="`Слайдер «${deleting?.name ?? ''}» будет удалён. Баннеры сохранятся.`"
      :busy="sliders.busy.value"
      :error="sliders.deleteError.value"
      @close="deleting = null"
      @confirm="removeSelected"
    />
  </section>
</template>
