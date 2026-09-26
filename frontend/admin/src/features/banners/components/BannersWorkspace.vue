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
import { useBanners } from '../composables/useBanners'
import type { Banner } from '../types/banner.types'
import BannerFormDialog from './BannerFormDialog.vue'
import BannerImagePreview from './BannerImagePreview.vue'

const banners = useBanners()
const deleting = ref<Banner | null>(null)

async function removeSelected(): Promise<void> {
  if (deleting.value && (await banners.remove(deleting.value)))
    deleting.value = null
}

function confirmDelete(banner: Banner): void {
  banners.deleteError.value = ''
  deleting.value = banner
}

onMounted(() => banners.load())
</script>

<template>
  <section class="mx-auto admin-page">
    <PageHeader class="mb-7" eyebrow="Контент" title="Баннеры">
      <template #actions
        ><UiButton @click="banners.openEditor()"
          ><Plus :size="18" />Добавить баннер</UiButton
        ></template
      >
    </PageHeader>
    <UiAlert v-if="banners.error.value" class="mb-4">{{
      banners.error.value
    }}</UiAlert>
    <UiButton
      v-if="banners.error.value"
      class="mb-4"
      variant="secondary"
      @click="banners.load()"
      >Повторить загрузку</UiButton
    >
    <UiAlert v-if="banners.success.value" class="mb-4" tone="success">{{
      banners.success.value
    }}</UiAlert>
    <UiCard
      v-if="!banners.error.value || banners.loading.value"
      class="overflow-hidden"
    >
      <UiLoadingState v-if="banners.loading.value" label="Загрузка баннеров…" />
      <div v-else-if="banners.items.value.length" class="divide-y">
        <article
          v-for="banner in banners.items.value"
          :key="banner.id"
          class="flex flex-wrap items-start gap-3 p-4 sm:flex-nowrap sm:items-center"
        >
          <BannerImagePreview
            :url="banner.image_url"
            :alt="`Баннер «${banner.title}»`"
            class="w-20 shrink-0 sm:w-28"
          />
          <div class="min-w-0 flex-[1_1_calc(100%-112px)] sm:flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="break-words font-semibold">{{ banner.title }}</h2>
              <UiBadge :tone="banner.is_published ? 'success' : 'neutral'">{{
                banner.is_published ? 'Опубликована' : 'Черновик'
              }}</UiBadge>
            </div>
            <p
              v-if="banner.description"
              class="break-words text-sm text-gray-500"
            >
              {{ banner.description }}
            </p>
          </div>
          <div class="ml-auto flex sm:ml-0">
            <UiButton
              variant="ghost"
              size="sm"
              :aria-label="`Редактировать баннер ${banner.title}`"
              @click="banners.openEditor(banner)"
              ><Pencil :size="17"
            /></UiButton>
            <UiButton
              variant="danger-ghost"
              size="sm"
              :aria-label="`Удалить баннер ${banner.title}`"
              @click="confirmDelete(banner)"
              ><Trash2 :size="17"
            /></UiButton>
          </div>
        </article>
      </div>
      <UiEmptyState v-else label="Баннеров пока нет." />
    </UiCard>
    <UiPagination
      v-if="banners.pagination.value"
      :meta="banners.pagination.value"
      :loading="banners.loading.value"
      @change="banners.load"
    />
    <BannerFormDialog
      :open="banners.editorOpen.value"
      :editing="Boolean(banners.editing.value)"
      :busy="banners.busy.value"
      :error="banners.formError.value"
      :form="banners.form.value"
      @close="banners.closeEditor"
      @submit="banners.submit"
    />
    <ConfirmDialog
      :open="Boolean(deleting)"
      title="Удалить баннер?"
      :description="`Баннер «${deleting?.title ?? ''}» будет удалён и исчезнет из публичной выдачи.`"
      :busy="banners.busy.value"
      :error="banners.deleteError.value"
      @close="deleting = null"
      @confirm="removeSelected"
    />
  </section>
</template>
