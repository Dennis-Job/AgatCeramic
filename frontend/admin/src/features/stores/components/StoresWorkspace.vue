<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router'
import { Clock3, Pencil, Plus, Trash2 } from '@lucide/vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import { useStores } from '../composables/useStores'
import type { Store } from '../types/store.types'
import StoreFormDialog from './StoreFormDialog.vue'
import WorkingHoursDialog from './WorkingHoursDialog.vue'

const stores = useStores()
const deleting = ref<Store | null>(null)

async function removeSelected(): Promise<void> {
  if (deleting.value && (await stores.remove(deleting.value)))
    deleting.value = null
}

function confirmDelete(store: Store): void {
  stores.deleteError.value = ''
  deleting.value = store
}

onMounted(() => stores.load())
onBeforeRouteLeave(stores.confirmDiscard)
onBeforeRouteUpdate((to, from) =>
  to.query.section === from.query.section ? true : stores.confirmDiscard(),
)
function beforeUnload(event: BeforeUnloadEvent): void {
  if (!stores.dirty.value && !stores.busy.value) return
  event.preventDefault()
  event.returnValue = ''
}
onMounted(() => window.addEventListener('beforeunload', beforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))
</script>

<template>
  <section class="min-w-0">
    <PageHeader class="mb-7" eyebrow="Контент" title="Магазины">
      <template #actions>
        <UiButton @click="stores.openEditor()"
          ><Plus :size="18" />Добавить магазин</UiButton
        >
      </template>
    </PageHeader>
    <UiNotification v-if="stores.error.value">{{
      stores.error.value
    }}</UiNotification>
    <UiButton
      v-if="stores.error.value"
      class="mb-4"
      variant="secondary"
      @click="stores.load()"
      >Повторить загрузку</UiButton
    >
    <UiNotification v-if="stores.success.value" tone="success">{{
      stores.success.value
    }}</UiNotification>
    <UiCard
      v-if="!stores.error.value || stores.loading.value"
      class="overflow-hidden"
    >
      <UiLoadingState v-if="stores.loading.value" label="Загрузка магазинов…" />
      <div v-else-if="stores.items.value.length" class="divide-y">
        <article
          v-for="store in stores.items.value"
          :key="store.id"
          class="flex flex-wrap items-center gap-3 p-4"
        >
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="break-words font-semibold">{{ store.name }}</h2>
              <UiBadge :tone="store.is_published ? 'success' : 'neutral'">
                {{ store.is_published ? 'Опубликован' : 'Черновик' }}
              </UiBadge>
            </div>
            <p class="break-words text-sm text-gray-500">{{ store.address }}</p>
            <p v-if="store.phone" class="text-sm text-gray-500">
              {{ store.phone }}
            </p>
          </div>
          <div class="ml-auto flex flex-wrap items-center gap-1">
            <UiButton
              variant="secondary"
              size="sm"
              :aria-label="`Часы работы магазина ${store.name}`"
              @click="stores.openHours(store)"
              ><Clock3 :size="17" /><span class="hidden sm:inline"
                >Часы работы</span
              ></UiButton
            >
            <UiButton
              variant="primary-ghost"
              size="sm"
              :aria-label="`Редактировать магазин ${store.name}`"
              @click="stores.openEditor(store)"
              ><Pencil :size="17"
            /></UiButton>
            <UiButton
              variant="danger-ghost"
              size="sm"
              :aria-label="`Удалить магазин ${store.name}`"
              @click="confirmDelete(store)"
              ><Trash2 :size="17"
            /></UiButton>
          </div>
        </article>
      </div>
      <UiEmptyState v-else label="Магазинов пока нет." />
    </UiCard>
    <UiPagination
      v-if="stores.pagination.value"
      :meta="stores.pagination.value"
      :loading="stores.loading.value"
      @change="stores.load"
    />
    <StoreFormDialog
      :open="stores.editorOpen.value"
      :editing="Boolean(stores.editing.value)"
      :busy="stores.busy.value"
      :error="stores.formError.value"
      :form="stores.form.value"
      @close="stores.closeEditor"
      @submit="stores.submit"
    />
    <WorkingHoursDialog
      :open="Boolean(stores.hoursStore.value)"
      :store-name="stores.hoursStore.value?.name ?? ''"
      :busy="stores.busy.value"
      :error="stores.hoursError.value"
      :hours="stores.hours.value"
      @close="stores.closeHours"
      @submit="stores.submitHours"
    />
    <ConfirmDialog
      :open="Boolean(deleting)"
      title="Удалить магазин?"
      :description="`Магазин «${deleting?.name ?? ''}» и его часы работы будут удалены.`"
      :busy="stores.busy.value"
      :error="stores.deleteError.value"
      @close="deleting = null"
      @confirm="removeSelected"
    />
  </section>
</template>
