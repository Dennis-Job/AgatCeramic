<script setup lang="ts">
import { computed } from 'vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import MediaImageEditor from '../../media/components/MediaImageEditor.vue'
import type { BodyContent } from '../../pages/types/block.types'
import type { HomeBannerItem } from '../types/homepage.types'

const props = defineProps<{
  content: BodyContent
  section: 'marquee' | 'categories' | 'materials' | 'promo' | 'about' | 'guide'
  hideHeading?: boolean
  disabled?: boolean
}>()
const bannerContent = computed(() =>
  props.section === 'materials'
    ? props.content.materials
    : props.content.categories,
)
const emit = defineEmits<{
  pending: [value: boolean]
  uploading: [value: boolean]
}>()
const pendingFields = new Map<object, boolean>()
const uploadingFields = new Map<object, boolean>()
function bannerLink(item: HomeBannerItem): string {
  return 'link_url' in item && typeof item.link_url === 'string'
    ? item.link_url
    : ''
}
function setBannerLink(item: HomeBannerItem, value: string): void {
  Object.assign(item, { link_url: value })
}
function bannerNote(item: HomeBannerItem): string {
  return 'note' in item && typeof item.note === 'string' ? item.note : ''
}
function setBannerNote(item: HomeBannerItem, value: string): void {
  if (props.section === 'materials') Object.assign(item, { note: value })
}
const itemKeys = new WeakMap<object, string>()
function itemKey(item: object): string {
  let key = itemKeys.get(item)
  if (!key) {
    key = crypto.randomUUID()
    itemKeys.set(item, key)
  }
  return key
}
function mediaState(key: object, value: boolean, uploading = false): void {
  const states = uploading ? uploadingFields : pendingFields
  states.set(key, value)
  const active = [...states.values()].some(Boolean)
  if (uploading) emit('uploading', active)
  else emit('pending', active)
}

function move<T>(items: T[], index: number, offset: -1 | 1): void {
  const next = index + offset
  if (next < 0 || next >= items.length) return
  ;[items[index], items[next]] = [items[next]!, items[index]!]
}
</script>

<template>
  <div v-if="section === 'marquee'" class="space-y-3">
    <div>
      <h2 v-if="!hideHeading" class="text-lg font-semibold text-gray-500">
        Бегущая строка
      </h2>
      <p class="mt-1 text-sm text-gray-500">
        Темы показываются в указанном порядке.
      </p>
    </div>
    <div
      v-for="(topic, index) in content.marquee.topics"
      :key="index"
      class="flex flex-wrap items-center gap-2"
    >
      <span class="w-6 text-sm text-gray-500">{{ index + 1 }}</span>
      <UiInput
        v-model="content.marquee.topics[index]"
        :aria-label="`Тема ${index + 1}: ${topic}`"
        class="min-w-44 flex-1"
      />
      <UiButton
        variant="surface"
        size="sm"
        :disabled="index === 0"
        :aria-label="`Поднять тему ${index + 1}`"
        @click="move(content.marquee.topics, index, -1)"
        >↑</UiButton
      >
      <UiButton
        variant="surface"
        size="sm"
        :disabled="index === content.marquee.topics.length - 1"
        :aria-label="`Опустить тему ${index + 1}`"
        @click="move(content.marquee.topics, index, 1)"
        >↓</UiButton
      >
      <UiButton
        variant="danger-ghost"
        size="sm"
        :aria-label="`Удалить тему ${index + 1}`"
        :disabled="content.marquee.topics.length === 1"
        @click="content.marquee.topics.splice(index, 1)"
        >Удалить</UiButton
      >
    </div>
    <UiButton
      variant="secondary"
      size="sm"
      @click="content.marquee.topics.push('')"
      >Добавить тему</UiButton
    >
  </div>

  <div
    v-else-if="section === 'categories' || section === 'materials'"
    class="space-y-3"
  >
    <h2 v-if="!hideHeading" class="text-lg font-semibold text-gray-500">
      {{ section === 'categories' ? 'Сетка баннеров' : 'Меню баннеров' }}
    </h2>
    <div class="grid gap-3 sm:grid-cols-2">
      <UiField label="Подпись над заголовком"
        ><UiInput v-model="bannerContent.eyebrow"
      /></UiField>
      <UiField label="Заголовок"
        ><UiInput v-model="bannerContent.title"
      /></UiField>
    </div>
    <UiField label="Описание"
      ><UiTextarea v-model="bannerContent.description" compact rows="2"
    /></UiField>
    <UiEmptyState v-if="!bannerContent.items.length" label="Баннеров пока нет">
      Добавьте первый баннер в меню.
    </UiEmptyState>
    <div
      v-for="(item, index) in bannerContent.items"
      :key="itemKey(item)"
      class="admin-panel--inset space-y-3 p-3"
    >
      <div class="flex flex-wrap items-center justify-between gap-2">
        <component
          :is="hideHeading ? 'h4' : 'h3'"
          class="min-w-0 break-words font-medium text-gray-500"
          >Баннер {{ index + 1
          }}{{ item.name ? ` · ${item.name}` : '' }}</component
        >
        <div class="flex gap-1">
          <UiButton
            variant="surface"
            size="sm"
            :disabled="index === 0"
            :aria-label="`Поднять баннер ${index + 1}`"
            @click="move(bannerContent.items, index, -1)"
            >↑</UiButton
          ><UiButton
            variant="surface"
            size="sm"
            :disabled="index === bannerContent.items.length - 1"
            :aria-label="`Опустить баннер ${index + 1}`"
            @click="move(bannerContent.items, index, 1)"
            >↓</UiButton
          ><UiButton
            variant="danger-ghost"
            size="sm"
            :aria-label="`Удалить баннер ${index + 1}`"
            :disabled="
              section === 'categories' && bannerContent.items.length === 1
            "
            @click="bannerContent.items.splice(index, 1)"
            >Удалить</UiButton
          >
        </div>
      </div>
      <div class="grid gap-3 sm:grid-cols-2">
        <UiField
          label="Код баннера"
          help="Латинские буквы, цифры и дефис; код должен быть уникальным."
          ><UiInput
            v-model="item.id"
            required
            pattern="[a-z0-9]+(-[a-z0-9]+)*" /></UiField
        ><UiField label="Название"
          ><UiInput v-model="item.name" required
        /></UiField>
      </div>
      <UiField
        label="Ссылка баннера"
        help="Внутренний путь /catalog/... или HTTPS-ссылка. Без ссылки баннер показывается без перехода."
        ><UiInput
          :model-value="bannerLink(item)"
          :disabled="disabled"
          maxlength="2048"
          placeholder="/catalog/..."
          @update:model-value="setBannerLink(item, $event)"
      /></UiField>
      <UiField v-if="section === 'categories'" label="Краткое описание карточки"
        ><UiInput v-model="item.short_description"
      /></UiField>
      <UiField v-if="section === 'materials'" label="Описание баннера"
        ><UiTextarea v-model="item.description" compact rows="2"
      /></UiField>
      <UiField v-if="section === 'materials'" label="Примечание под баннером">
        <UiTextarea
          :model-value="bannerNote(item)"
          compact
          rows="2"
          maxlength="2000"
          :disabled="disabled"
          @update:model-value="setBannerNote(item, $event)"
        />
      </UiField>
      <MediaImageEditor
        compact
        :disabled="disabled"
        require-manage-permission
        :label="`Изображение баннера ${index + 1}`"
        :preview-label="`Увеличить изображение баннера ${index + 1}: ${item.name}`"
        :remove-label="`Снять выбранный файл баннера ${index + 1}`"
        :fallback-url="item.image_url"
        :fallback-alt="item.image_alt || item.name"
        :model-value="item.image_media_id"
        @pending="mediaState(item, $event)"
        @uploading="mediaState(item, $event, true)"
        @update:model-value="item.image_media_id = $event"
      >
        <template #details>
          <UiField label="Описание изображения для доступности"
            ><UiInput v-model="item.image_alt" required :disabled="disabled"
          /></UiField>
          <details class="text-sm text-gray-500">
            <summary class="cursor-pointer rounded admin-focus">
              Исходный адрес изображения
            </summary>
            <UiField
              class="mt-2"
              label="Локальное изображение"
              help="Путь /images/... используется, пока файл не выбран в медиатеке."
              ><UiInput v-model="item.image_url" :disabled="disabled"
            /></UiField>
          </details>
        </template>
      </MediaImageEditor>
    </div>
    <UiButton
      variant="secondary"
      size="sm"
      @click="
        bannerContent.items.push({
          id: '',
          name: '',
          short_description: '',
          description: '',
          image_url: '',
          image_media_id: null,
          image_alt: '',
        })
      "
      >Добавить баннер</UiButton
    >
  </div>

  <div v-else-if="section === 'promo'" class="space-y-3">
    <h2 v-if="!hideHeading" class="text-lg font-semibold text-gray-500">
      Промо блок
    </h2>
    <div class="grid gap-3 sm:grid-cols-2">
      <UiField label="Подпись над заголовком"
        ><UiInput v-model="content.promo.eyebrow" /></UiField
      ><UiField label="Заголовок"
        ><UiInput v-model="content.promo.title"
      /></UiField>
    </div>
    <UiField label="Описание"
      ><UiTextarea v-model="content.promo.description" compact rows="2"
    /></UiField>
    <div class="grid gap-3 sm:grid-cols-2">
      <UiField label="Текст кнопки"
        ><UiInput v-model="content.promo.link_label" /></UiField
      ><UiField
        label="Ссылка кнопки"
        help="Внутренний путь /... или HTTPS-адрес."
        ><UiInput v-model="content.promo.link_url"
      /></UiField>
    </div>
  </div>

  <div v-else-if="section === 'about'" class="space-y-3">
    <h2 v-if="!hideHeading" class="text-lg font-semibold text-gray-500">
      О проекте
    </h2>
    <div class="grid gap-3 sm:grid-cols-2">
      <UiField label="Подпись над заголовком"
        ><UiInput v-model="content.about.eyebrow" /></UiField
      ><UiField label="Заголовок"
        ><UiInput v-model="content.about.title"
      /></UiField>
    </div>
    <UiField label="Текст"
      ><UiTextarea v-model="content.about.description" compact rows="4"
    /></UiField>
    <MediaImageEditor
      compact
      :disabled="disabled"
      require-manage-permission
      label="Изображение блока"
      preview-label="Увеличить изображение блока «О проекте»"
      remove-label="Снять выбранный файл блока «О проекте»"
      :fallback-url="content.about.image_url"
      :fallback-alt="content.about.image_alt || 'О проекте'"
      :model-value="content.about.image_media_id"
      @pending="mediaState(content.about, $event)"
      @uploading="mediaState(content.about, $event, true)"
      @update:model-value="content.about.image_media_id = $event"
    >
      <template #details>
        <UiField label="Описание изображения для доступности"
          ><UiInput v-model="content.about.image_alt" :disabled="disabled"
        /></UiField>
        <details class="text-sm text-gray-500">
          <summary class="cursor-pointer rounded admin-focus">
            Исходный адрес изображения
          </summary>
          <UiField
            class="mt-2"
            label="Локальное изображение"
            help="Путь /images/... используется, пока файл не выбран в медиатеке."
            ><UiInput v-model="content.about.image_url" :disabled="disabled"
          /></UiField>
        </details>
      </template>
    </MediaImageEditor>
    <div class="grid gap-3 sm:grid-cols-2">
      <UiField label="Текст ссылки"
        ><UiInput v-model="content.about.link_label" /></UiField
      ><UiField label="Ссылка" help="Внутренний путь /... или HTTPS-адрес."
        ><UiInput v-model="content.about.link_url"
      /></UiField>
    </div>
  </div>

  <div v-else class="space-y-3">
    <h2 v-if="!hideHeading" class="text-lg font-semibold text-gray-500">
      Советы по выбору
    </h2>
    <div class="grid gap-3 sm:grid-cols-2">
      <UiField label="Подпись над заголовком"
        ><UiInput v-model="content.guide.eyebrow" /></UiField
      ><UiField label="Заголовок"
        ><UiInput v-model="content.guide.title"
      /></UiField>
    </div>
    <div
      v-for="(item, index) in content.guide.items"
      :key="index"
      class="admin-panel--inset space-y-3 p-3"
    >
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="font-medium text-gray-500">Совет {{ index + 1 }}</h3>
        <div class="flex gap-1">
          <UiButton
            variant="surface"
            size="sm"
            :disabled="index === 0"
            :aria-label="`Поднять совет ${index + 1}`"
            @click="move(content.guide.items, index, -1)"
            >↑</UiButton
          ><UiButton
            variant="surface"
            size="sm"
            :disabled="index === content.guide.items.length - 1"
            :aria-label="`Опустить совет ${index + 1}`"
            @click="move(content.guide.items, index, 1)"
            >↓</UiButton
          ><UiButton
            variant="danger-ghost"
            size="sm"
            :aria-label="`Удалить совет ${index + 1}`"
            :disabled="content.guide.items.length === 1"
            @click="content.guide.items.splice(index, 1)"
            >Удалить</UiButton
          >
        </div>
      </div>
      <UiField label="Заголовок совета"
        ><UiInput v-model="item.title"
      /></UiField>
      <UiField label="Текст совета"
        ><UiTextarea v-model="item.description" compact rows="2"
      /></UiField>
    </div>
    <UiButton
      variant="secondary"
      size="sm"
      @click="content.guide.items.push({ title: '', description: '' })"
      >Добавить совет</UiButton
    >
  </div>
</template>
