<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  onBeforeRouteLeave,
  onBeforeRouteUpdate,
  useRoute,
  useRouter,
} from 'vue-router'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import { useHomePage } from '../composables/useHomePage'
import type { EditableSection } from '../types/homepage.types'
import HomePageHeroEditor from './HomePageHeroEditor.vue'
import HomePageBodyEditor from './HomePageBodyEditor.vue'
import HomePageSeoEditor from './HomePageSeoEditor.vue'
import PageBlocksEditor from '../../pages/components/PageBlocksEditor.vue'
import { getPage } from '../../pages/services/pages'
import type { ContentPage } from '../../pages/types/page.types'

withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false })

const workspace = useHomePage()
const route = useRoute()
const router = useRouter()
const section = ref<EditableSection>('hero_slider_id')
const blocksOpen = ref(
  route.query.editor === 'blocks' || typeof route.query.block === 'string',
)
const blocksPage = ref<ContentPage | null>(null)
const blocksLoading = ref(false)
const blocksError = ref('')
const blocksPending = ref(false)
const blocksBusy = ref(false)
const sections: { key: EditableSection; label: string }[] = [
  { key: 'hero_slider_id', label: 'Главный слайдер' },
  { key: 'marquee', label: 'Бегущая строка' },
  { key: 'categories', label: 'Материалы' },
  { key: 'materials', label: 'Блок фактур' },
  { key: 'promo', label: 'Промо' },
  { key: 'about', label: 'О проекте' },
  { key: 'guide', label: 'Советы' },
  { key: 'seo', label: 'SEO' },
]

async function selectSection(value: EditableSection): Promise<void> {
  if (blocksBusy.value) return
  if (blocksPending.value && !confirmNavigation()) return
  if (blocksOpen.value) await workspace.load()
  section.value = value
  blocksOpen.value = false
  blocksPage.value = null
  await router.replace({
    query: { ...route.query, editor: undefined, block: undefined },
  })
  workspace.error.value = ''
  workspace.success.value = ''
}

async function openBlocks(): Promise<void> {
  if (blocksOpen.value || !workspace.content.value) return
  if (hasUnsavedChanges()) {
    if (!confirmNavigation()) return
    await workspace.load()
    if (!workspace.content.value) return
  }
  blocksOpen.value = true
  await router.replace({ query: { ...route.query, editor: 'blocks' } })
  if (!blocksPage.value) await loadBlocks()
}
async function loadBlocks(): Promise<void> {
  if (!workspace.content.value || blocksLoading.value) return
  blocksLoading.value = true
  blocksError.value = ''
  try {
    blocksPage.value = await getPage(workspace.content.value.page_id)
  } catch (reason) {
    blocksError.value =
      reason instanceof Error ? reason.message : 'Не удалось загрузить блоки.'
  } finally {
    blocksLoading.value = false
  }
}
watch(
  [
    () => workspace.content.value?.page_id,
    () => route.query.editor,
    () => route.query.block,
  ],
  () => {
    if (
      route.query.editor !== 'blocks' &&
      typeof route.query.block !== 'string'
    )
      return
    blocksOpen.value = true
    if (!blocksPage.value) void loadBlocks()
  },
  { immediate: true },
)
function blocksSaved(page: ContentPage): void {
  blocksPage.value = page
  if (workspace.content.value) {
    workspace.content.value.is_published = page.is_published
    workspace.content.value.has_unpublished_changes =
      page.has_unpublished_changes
    workspace.content.value.published_at = page.published_at
  }
}

function hasUnsavedChanges(): boolean {
  return (
    blocksPending.value || sections.some((item) => workspace.isDirty(item.key))
  )
}

function confirmNavigation(): boolean {
  return (
    !hasUnsavedChanges() ||
    window.confirm(
      'Есть несохранённые изменения. Покинуть редактор и потерять их?',
    )
  )
}

function handleBeforeUnload(event: BeforeUnloadEvent): void {
  if (!hasUnsavedChanges()) return
  event.preventDefault()
  event.returnValue = ''
}

onBeforeRouteUpdate((to, from) => {
  if (
    to.query.page === from.query.page &&
    to.query.section === from.query.section
  )
    return true
  if (blocksOpen.value) return true // The block editor owns this navigation guard.
  return confirmNavigation()
})
onBeforeRouteLeave(() => blocksOpen.value || confirmNavigation())
onMounted(() => window.addEventListener('beforeunload', handleBeforeUnload))
onBeforeUnmount(() =>
  window.removeEventListener('beforeunload', handleBeforeUnload),
)
</script>

<template>
  <section :class="embedded ? 'min-w-0' : 'admin-page mx-auto'">
    <PageHeader
      class="mb-6"
      eyebrow="Управление сайтом"
      title="Главная страница"
      description="Сохраняйте блоки в черновик, затем публикуйте страницу отдельным действием."
    />
    <UiAlert v-if="workspace.error.value" class="mb-4" role="alert">{{
      workspace.error.value
    }}</UiAlert>
    <UiAlert
      v-if="workspace.success.value"
      class="mb-4"
      tone="success"
      role="status"
      >{{ workspace.success.value }}</UiAlert
    >
    <UiButton
      v-if="workspace.error.value && !workspace.content.value"
      class="mb-4"
      variant="secondary"
      @click="workspace.load()"
      >Повторить загрузку</UiButton
    >
    <UiLoadingState
      v-if="workspace.loading.value"
      label="Загрузка главной страницы…"
    />
    <template v-else-if="workspace.content.value">
      <UiCard class="mb-5 min-w-0 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <UiBadge
              :tone="
                workspace.content.value.is_published ? 'success' : 'neutral'
              "
            >
              {{
                workspace.content.value.is_published
                  ? 'Опубликована'
                  : 'Черновик'
              }}
            </UiBadge>
            <p class="mt-2 text-sm text-gray-600" role="status">
              {{
                !workspace.content.value.is_published
                  ? 'Сохранённый черновик пока не опубликован.'
                  : workspace.content.value.has_unpublished_changes
                    ? 'В черновике есть неопубликованные изменения.'
                    : 'Сохранённый черновик соответствует опубликованной версии.'
              }}
            </p>
            <p
              v-if="workspace.content.value.published_at"
              class="mt-2 text-xs text-gray-500"
            >
              Последняя публикация:
              {{
                new Date(workspace.content.value.published_at).toLocaleString(
                  'ru-RU',
                )
              }}
            </p>
            <p
              v-if="hasUnsavedChanges()"
              class="mt-2 text-sm text-gray-600"
              role="status"
            >
              Перед публикацией сохраните изменения во всех разделах.
            </p>
          </div>
          <UiButton
            v-if="!blocksOpen"
            :loading="workspace.publishing.value"
            :disabled="
              workspace.saving.value ||
              hasUnsavedChanges() ||
              (workspace.content.value.is_published &&
                !workspace.content.value.has_unpublished_changes)
            "
            @click="workspace.publish()"
            >Опубликовать черновик</UiButton
          >
        </div>
      </UiCard>
      <nav
        class="mb-5 flex flex-wrap gap-2"
        aria-label="Разделы главной страницы"
      >
        <UiButton
          size="sm"
          :variant="blocksOpen ? 'primary' : 'secondary'"
          :aria-pressed="blocksOpen"
          :disabled="blocksBusy"
          @click="openBlocks"
          >Блоки и порядок</UiButton
        >
        <UiButton
          v-for="item in sections"
          :key="item.key"
          size="sm"
          :variant="
            !blocksOpen && section === item.key ? 'primary' : 'secondary'
          "
          :aria-pressed="!blocksOpen && section === item.key"
          :disabled="blocksBusy"
          @click="selectSection(item.key)"
          >{{ item.label
          }}<span
            v-if="workspace.isDirty(item.key)"
            class="ml-1"
            aria-label="Есть несохранённые изменения"
            >•</span
          ></UiButton
        >
      </nav>
      <p
        v-if="!blocksOpen && workspace.isDirty(section)"
        class="mb-4 text-sm text-gray-600"
        role="status"
      >
        В этом разделе есть несохранённые изменения.
      </p>
      <template v-if="blocksOpen">
        <UiLoadingState v-if="blocksLoading" label="Загрузка блоков…" />
        <UiAlert v-else-if="blocksError" role="alert"
          >{{ blocksError
          }}<UiButton variant="secondary" size="sm" @click="loadBlocks"
            >Повторить загрузку блоков</UiButton
          ></UiAlert
        >
        <PageBlocksEditor
          v-else-if="blocksPage"
          :page="blocksPage"
          @saved="blocksSaved"
          @pending="blocksPending = $event"
          @busy="blocksBusy = $event"
        />
      </template>
      <UiCard
        v-else
        class="p-4 sm:p-6"
        role="region"
        :aria-label="sections.find((item) => item.key === section)?.label"
      >
        <p class="mb-5 text-sm text-gray-500">
          Поля с текстом и ссылками обязательны. В списках оставьте хотя бы один
          элемент, кроме дополнительных ссылок в подвале.
        </p>
        <HomePageHeroEditor
          v-if="section === 'hero_slider_id'"
          v-model="workspace.content.value.hero_slider_id"
          :slides="workspace.content.value.hero_slides"
        />
        <HomePageBodyEditor
          v-else-if="
            section === 'marquee' ||
            section === 'categories' ||
            section === 'materials' ||
            section === 'promo' ||
            section === 'about' ||
            section === 'guide'
          "
          :content="workspace.content.value"
          :section="section"
        />
        <HomePageSeoEditor v-else v-model="workspace.content.value.seo" />
        <div class="mt-7 flex justify-end border-t border-gray-200 pt-5">
          <UiButton
            :loading="workspace.saving.value"
            :disabled="workspace.publishing.value"
            @click="workspace.save(section)"
            >Сохранить черновик раздела</UiButton
          >
        </div>
      </UiCard>
    </template>
  </section>
</template>
