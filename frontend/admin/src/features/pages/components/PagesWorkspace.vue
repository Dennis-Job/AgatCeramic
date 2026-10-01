<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Pencil, Plus, Trash2 } from '@lucide/vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import AdminEditorLayout from '../../../components/shared/AdminEditorLayout.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import { usePages } from '../composables/usePages'
import { getPage } from '../services/pages'
import type { ContentPage } from '../types/page.types'
import HomePageWorkspace from '../../homepage/components/HomePageWorkspace.vue'
import PageFormDialog from './PageFormDialog.vue'
import { systemPageSlugs } from '../validation/page'
import PageBlocksEditor from './PageBlocksEditor.vue'
import DraftPreview from '../../content-preview/components/DraftPreview.vue'

const pages = usePages()
const route = useRoute()
const router = useRouter()
const deleting = ref<ContentPage | null>(null)
const fetchedPage = ref<ContentPage | null>(null)
const selectedLoading = ref(false)
const selectedError = ref('')
const blocksPending = ref(false)
const previewRevision = ref(0)
const isHomeSelected = computed(() => route.query.page === 'home')
const selectedId = computed(() => {
  const value = route.query.page
  if (typeof value !== 'string' || !/^\d+$/.test(value)) return null
  return Number(value)
})
const selectedPage = computed(
  () =>
    fetchedPage.value ??
    pages.items.value.find((page) => page.id === selectedId.value),
)
function blocksSaved(page: ContentPage): void {
  previewRevision.value++
  fetchedPage.value = page
  const index = pages.items.value.findIndex((item) => item.id === page.id)
  if (index !== -1) pages.items.value[index] = page
}
const contentPages = computed(() =>
  pages.items.value.filter((page) => page.slug !== 'home'),
)
let selectedRequest = 0

watch(selectedPage, (page) => {
  if (page?.slug === 'home')
    void router.replace({
      path: '/content',
      query: { ...route.query, page: 'home' },
    })
})

watch([selectedId, () => pages.pagination.value], async ([id, pagination]) => {
  const request = ++selectedRequest
  fetchedPage.value = null
  selectedError.value = ''
  pages.publishError.value = ''
  selectedLoading.value = false
  if (
    id === null ||
    !pagination ||
    deleting.value?.id === id ||
    pages.items.value.some((page) => page.id === id)
  )
    return
  selectedLoading.value = true
  try {
    const result = await getPage(id)
    if (request === selectedRequest) fetchedPage.value = result
  } catch (reason) {
    if (request === selectedRequest)
      selectedError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось загрузить страницу.'
  } finally {
    if (request === selectedRequest) selectedLoading.value = false
  }
})

async function removeSelected(): Promise<void> {
  if (deleting.value && (await pages.remove(deleting.value))) {
    deleting.value = null
    await router.replace({
      path: '/content',
      query: { ...route.query, page: undefined },
    })
  }
}

async function submitPage(): Promise<void> {
  const saved = await pages.submit()
  if (saved) {
    blocksSaved(saved)
    await router.replace({
      path: '/content',
      query: { ...route.query, page: String(saved.id) },
    })
  }
}

async function publishSelected(withdraw = false): Promise<void> {
  if (!selectedPage.value) return
  const result = await pages.publish(selectedPage.value, withdraw)
  if (result && selectedId.value === result.id) fetchedPage.value = result
}

function confirmDelete(page: ContentPage): void {
  pages.deleteError.value = ''
  deleting.value = page
}

onMounted(() => pages.load())
</script>

<template>
  <section class="min-w-0">
    <PageHeader
      class="mb-6"
      eyebrow="Управление сайтом"
      title="Страницы"
      description="Выберите страницу слева, чтобы настроить её содержимое."
    />
    <UiAlert
      v-if="pages.success.value"
      class="mb-4"
      tone="success"
      role="status"
      >{{ pages.success.value }}</UiAlert
    >
    <AdminEditorLayout>
      <template #navigation>
        <UiCard class="min-w-0 self-start p-3" aria-label="Страницы сайта">
          <div
            class="mb-3 flex flex-wrap items-center justify-between gap-2 px-2"
          >
            <h2 class="font-semibold">Страницы сайта</h2>
            <UiButton
              size="sm"
              variant="secondary"
              aria-label="Добавить страницу"
              :disabled="pages.busy.value || blocksPending"
              @click="pages.openEditor()"
              ><Plus :size="17" aria-hidden="true"
            /></UiButton>
          </div>
          <nav class="space-y-1" aria-label="Выбор страницы сайта">
            <RouterLink
              :to="{
                path: '/content',
                query: { ...route.query, page: 'home' },
              }"
              class="block rounded-lg px-3 py-2 text-sm font-medium hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
              :class="
                isHomeSelected
                  ? 'bg-primary-50 text-primary-700'
                  : 'text-gray-700'
              "
              :aria-current="isHomeSelected ? 'page' : undefined"
              >Главная
              <span class="block text-xs font-normal text-gray-500"
                >/</span
              ></RouterLink
            >
            <UiLoadingState
              v-if="pages.loading.value"
              label="Загрузка страниц…"
            />
            <template v-else>
              <RouterLink
                v-if="
                  fetchedPage &&
                  !pages.items.value.some((page) => page.id === fetchedPage?.id)
                "
                :to="{
                  path: '/content',
                  query: { ...route.query, page: String(fetchedPage.id) },
                }"
                class="block min-w-0 break-words rounded-lg bg-primary-50 px-3 py-2 text-sm text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                aria-current="page"
                >{{ fetchedPage.title
                }}<span class="block break-all text-xs text-gray-500"
                  >/{{ fetchedPage.slug }}</span
                ></RouterLink
              >
              <RouterLink
                v-for="page in contentPages"
                :key="page.id"
                :to="{
                  path: '/content',
                  query: { ...route.query, page: String(page.id) },
                }"
                class="block min-w-0 rounded-lg px-3 py-2 text-sm hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                :class="
                  selectedId === page.id
                    ? 'bg-primary-50 text-primary-700'
                    : 'text-gray-700'
                "
                :aria-current="selectedId === page.id ? 'page' : undefined"
                ><span class="block break-words font-medium">{{
                  page.title
                }}</span
                ><span class="block break-all text-xs text-gray-500"
                  >/{{ page.slug }} ·
                  {{ page.is_published ? 'Опубликована' : 'Черновик' }}
                  {{
                    page.has_unpublished_changes ? ' · Есть изменения' : ''
                  }}</span
                ></RouterLink
              >
              <p
                v-if="
                  !pages.items.value.length &&
                  !pages.error.value &&
                  pages.pagination.value?.total === 0
                "
                class="px-3 py-2 text-sm text-gray-500"
                role="status"
              >
                Страниц пока нет.
              </p>
            </template>
          </nav>
          <UiAlert v-if="pages.error.value" class="mt-3" role="alert">{{
            pages.error.value
          }}</UiAlert>
          <UiButton
            v-if="pages.error.value"
            class="mt-2"
            size="sm"
            variant="secondary"
            :disabled="blocksPending"
            @click="pages.load()"
            >Повторить загрузку</UiButton
          >
          <UiPagination
            v-if="pages.pagination.value"
            :meta="pages.pagination.value"
            :loading="pages.loading.value || blocksPending"
            @change="pages.load"
          />
        </UiCard>
      </template>
      <template #editor>
        <div class="min-w-0">
          <HomePageWorkspace
            v-if="isHomeSelected"
            @saved="previewRevision++"
            @pending="blocksPending = $event"
          />
          <UiLoadingState
            v-else-if="pages.loading.value || selectedLoading"
            label="Загрузка страницы…"
          />
          <UiCard v-else-if="selectedPage" class="min-w-0 p-4 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="break-all text-xs font-medium text-gray-500">
                  /{{ selectedPage.slug }}
                </p>
                <h2 class="break-words text-xl font-semibold">
                  {{ selectedPage.title }}
                </h2>
                <UiBadge
                  class="mt-2"
                  :tone="selectedPage.is_published ? 'success' : 'neutral'"
                  >{{
                    selectedPage.is_published ? 'Опубликована' : 'Черновик'
                  }}</UiBadge
                >
                <p
                  v-if="selectedPage.has_unpublished_changes"
                  class="mt-2 text-sm text-gray-600"
                  role="status"
                >
                  В черновике есть неопубликованные изменения.
                </p>
                <p
                  v-if="selectedPage.published_at"
                  class="mt-2 text-xs text-gray-500"
                >
                  Последняя публикация:
                  {{
                    new Date(selectedPage.published_at).toLocaleString('ru-RU')
                  }}
                </p>
              </div>
              <div class="flex flex-wrap gap-1">
                <UiButton
                  size="sm"
                  :loading="pages.busy.value"
                  :disabled="
                    blocksPending ||
                    (selectedPage.is_published &&
                      !selectedPage.has_unpublished_changes)
                  "
                  @click="publishSelected()"
                  >Опубликовать черновик</UiButton
                >
                <UiButton
                  v-if="selectedPage.is_published"
                  variant="secondary"
                  size="sm"
                  :disabled="pages.busy.value || blocksPending"
                  @click="publishSelected(true)"
                  >Снять с публикации</UiButton
                >
                <UiButton
                  variant="ghost"
                  size="sm"
                  :aria-label="`Редактировать страницу ${selectedPage.title}`"
                  :disabled="pages.busy.value || blocksPending"
                  @click="pages.openEditor(selectedPage)"
                  ><Pencil
                    :size="17"
                    aria-hidden="true"
                  />Редактировать</UiButton
                >
                <UiButton
                  v-if="!systemPageSlugs.includes(selectedPage.slug)"
                  variant="danger-ghost"
                  size="sm"
                  :aria-label="`Удалить страницу ${selectedPage.title}`"
                  :disabled="pages.busy.value || blocksPending"
                  @click="confirmDelete(selectedPage)"
                  ><Trash2 :size="17" aria-hidden="true"
                /></UiButton>
              </div>
            </div>
            <UiAlert
              v-if="pages.publishError.value"
              class="mt-4"
              role="alert"
              >{{ pages.publishError.value }}</UiAlert
            >
            <p
              v-if="!selectedPage.blocks?.length"
              class="mt-5 whitespace-pre-wrap break-words text-sm text-gray-700"
            >
              {{ selectedPage.body }}
            </p>
            <PageBlocksEditor
              :key="selectedPage.id"
              :page="selectedPage"
              @saved="blocksSaved"
              @pending="blocksPending = $event"
            />
          </UiCard>
          <div v-else-if="selectedError">
            <UiAlert role="alert">{{ selectedError }}</UiAlert>
            <UiButton
              class="mt-2"
              size="sm"
              variant="secondary"
              @click="pages.load()"
              >Повторить загрузку</UiButton
            >
          </div>
          <UiEmptyState
            v-else-if="selectedId !== null"
            label="Выбранная страница не найдена в текущем списке. Переключите страницу списка или выберите главную."
          />
          <UiEmptyState
            v-else
            label="Выберите страницу слева, чтобы увидеть её настройки."
          />
        </div>
      </template>
      <template #preview>
        <DraftPreview
          :slug="isHomeSelected ? 'home' : selectedPage?.slug"
          :revision="previewRevision"
          :pending="blocksPending || pages.editorOpen.value"
        />
      </template>
    </AdminEditorLayout>
    <PageFormDialog
      :open="pages.editorOpen.value"
      :editing="Boolean(pages.editing.value)"
      :busy="pages.busy.value"
      :error="pages.formError.value"
      :form="pages.form.value"
      @close="pages.closeEditor"
      @submit="submitPage"
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
