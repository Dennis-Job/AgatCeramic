<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import { useHomePage } from '../composables/useHomePage'
import type { EditableSection } from '../types/homepage.types'
import HomePageHeroEditor from './HomePageHeroEditor.vue'
import HomePageBodyEditor from './HomePageBodyEditor.vue'
import HomePageChromeEditor from './HomePageChromeEditor.vue'
import HomePageSeoEditor from './HomePageSeoEditor.vue'

withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false })

const workspace = useHomePage()
const section = ref<EditableSection>('hero_slider_id')
const sections: { key: EditableSection; label: string }[] = [
  { key: 'hero_slider_id', label: 'Главный слайдер' },
  { key: 'marquee', label: 'Бегущая строка' },
  { key: 'categories', label: 'Материалы' },
  { key: 'materials', label: 'Блок фактур' },
  { key: 'promo', label: 'Промо' },
  { key: 'about', label: 'О проекте' },
  { key: 'guide', label: 'Советы' },
  { key: 'header', label: 'Шапка' },
  { key: 'footer', label: 'Подвал' },
  { key: 'seo', label: 'SEO' },
]

function selectSection(value: EditableSection): void {
  section.value = value
  workspace.error.value = ''
  workspace.success.value = ''
}

function hasUnsavedChanges(): boolean {
  return sections.some((item) => workspace.isDirty(item.key))
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

onBeforeRouteUpdate(confirmNavigation)
onBeforeRouteLeave(confirmNavigation)
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
      description="Изменения каждого блока сохраняются отдельно. Опубликованный слайдер и его баннеры появятся на сайте после выбора."
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
      <nav
        class="mb-5 flex flex-wrap gap-2"
        aria-label="Разделы главной страницы"
      >
        <UiButton
          v-for="item in sections"
          :key="item.key"
          size="sm"
          :variant="section === item.key ? 'primary' : 'secondary'"
          :aria-pressed="section === item.key"
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
        v-if="workspace.isDirty(section)"
        class="mb-4 text-sm text-gray-600"
        role="status"
      >
        В этом разделе есть несохранённые изменения.
      </p>
      <UiCard
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
        <HomePageChromeEditor
          v-else-if="section === 'header' || section === 'footer'"
          :content="workspace.content.value"
          :section="section"
        />
        <HomePageSeoEditor v-else v-model="workspace.content.value.seo" />
        <div class="mt-7 flex justify-end border-t border-gray-200 pt-5">
          <UiButton
            :loading="workspace.saving.value"
            @click="workspace.save(section)"
            >Сохранить раздел</UiButton
          >
        </div>
      </UiCard>
    </template>
  </section>
</template>
