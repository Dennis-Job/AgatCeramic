<script setup lang="ts">
import UiButton from '../../../components/ui/UiButton.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import UiImagePreview from '../../../components/ui/UiImagePreview.vue'
import MediaReferenceField from '../../media/components/MediaReferenceField.vue'
import type { HomePageContent } from '../types/homepage.types'

defineProps<{
  content: HomePageContent
  section: 'marquee' | 'categories' | 'materials' | 'promo' | 'about' | 'guide'
}>()

function move<T>(items: T[], index: number, offset: -1 | 1): void {
  const next = index + offset
  if (next < 0 || next >= items.length) return
  ;[items[index], items[next]] = [items[next]!, items[index]!]
}
</script>

<template>
  <div v-if="section === 'marquee'" class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-gray-800">Бегущая строка</h2>
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
        variant="ghost"
        size="sm"
        :disabled="index === 0"
        :aria-label="`Поднять тему ${index + 1}`"
        @click="move(content.marquee.topics, index, -1)"
        >↑</UiButton
      >
      <UiButton
        variant="ghost"
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

  <div v-else-if="section === 'categories'" class="space-y-5">
    <div>
      <h2 class="text-lg font-semibold text-gray-800">Карточки материалов</h2>
      <p class="mt-1 text-sm text-gray-500">
        Эти же материалы используются во вкладках блока фактур.
      </p>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
      <UiField label="Подпись над заголовком"
        ><UiInput v-model="content.categories.eyebrow"
      /></UiField>
      <UiField label="Заголовок"
        ><UiInput v-model="content.categories.title"
      /></UiField>
    </div>
    <UiField label="Описание"
      ><UiTextarea v-model="content.categories.description" rows="2"
    /></UiField>
    <div
      v-for="(item, index) in content.categories.items"
      :key="item.id || index"
      class="space-y-4 rounded-xl border border-gray-200 p-4"
    >
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="font-medium text-gray-800">Материал {{ index + 1 }}</h3>
        <div class="flex gap-1">
          <UiButton
            variant="ghost"
            size="sm"
            :disabled="index === 0"
            :aria-label="`Поднять материал ${index + 1}`"
            @click="move(content.categories.items, index, -1)"
            >↑</UiButton
          ><UiButton
            variant="ghost"
            size="sm"
            :disabled="index === content.categories.items.length - 1"
            :aria-label="`Опустить материал ${index + 1}`"
            @click="move(content.categories.items, index, 1)"
            >↓</UiButton
          ><UiButton
            variant="danger-ghost"
            size="sm"
            :aria-label="`Удалить материал ${index + 1}`"
            :disabled="content.categories.items.length === 1"
            @click="content.categories.items.splice(index, 1)"
            >Удалить</UiButton
          >
        </div>
      </div>
      <div class="grid gap-4 md:grid-cols-2">
        <UiField
          label="Код для вкладки"
          help="Латинские буквы, цифры и дефис; код должен быть уникальным."
          ><UiInput
            v-model="item.id"
            required
            pattern="[a-z0-9]+(-[a-z0-9]+)*" /></UiField
        ><UiField label="Название"
          ><UiInput v-model="item.name" required
        /></UiField>
      </div>
      <UiField label="Краткое описание карточки"
        ><UiInput v-model="item.short_description"
      /></UiField>
      <UiField label="Описание в блоке фактур"
        ><UiTextarea v-model="item.description" rows="3"
      /></UiField>
      <MediaReferenceField
        kind="image"
        :label="`Изображение материала ${index + 1}`"
        :model-value="item.image_media_id"
        @update:model-value="
          item.image_media_id = Array.isArray($event) ? null : $event
        "
      />
      <UiField
        label="Локальное изображение"
        help="Путь /images/... используется, пока файл не выбран в медиатеке."
        ><UiInput v-model="item.image_url"
      /></UiField>
      <UiField label="Описание изображения для доступности"
        ><UiInput v-model="item.image_alt" required
      /></UiField>
      <UiImagePreview
        v-if="!item.image_media_id"
        :url="item.image_url || null"
        :alt="item.image_alt || item.name"
        class="max-w-sm"
      />
    </div>
    <UiButton
      variant="secondary"
      size="sm"
      @click="
        content.categories.items.push({
          id: '',
          name: '',
          short_description: '',
          description: '',
          image_url: '',
          image_media_id: null,
          image_alt: '',
        })
      "
      >Добавить материал</UiButton
    >
  </div>

  <div v-else-if="section === 'materials'" class="space-y-4">
    <h2 class="text-lg font-semibold text-gray-800">Блок фактур</h2>
    <div class="grid gap-4 md:grid-cols-2">
      <UiField label="Подпись над заголовком"
        ><UiInput v-model="content.materials.eyebrow" /></UiField
      ><UiField label="Заголовок"
        ><UiInput v-model="content.materials.title"
      /></UiField>
    </div>
    <UiField label="Описание"
      ><UiTextarea v-model="content.materials.description" rows="3"
    /></UiField>
    <UiField label="Примечание под материалом"
      ><UiTextarea v-model="content.materials.note" rows="3"
    /></UiField>
  </div>

  <div v-else-if="section === 'promo'" class="space-y-4">
    <h2 class="text-lg font-semibold text-gray-800">Промо блок</h2>
    <div class="grid gap-4 md:grid-cols-2">
      <UiField label="Подпись над заголовком"
        ><UiInput v-model="content.promo.eyebrow" /></UiField
      ><UiField label="Заголовок"
        ><UiInput v-model="content.promo.title"
      /></UiField>
    </div>
    <UiField label="Описание"
      ><UiTextarea v-model="content.promo.description" rows="3"
    /></UiField>
    <div class="grid gap-4 md:grid-cols-2">
      <UiField label="Текст кнопки"
        ><UiInput v-model="content.promo.link_label" /></UiField
      ><UiField
        label="Ссылка кнопки"
        help="Внутренний путь /... или HTTPS-адрес."
        ><UiInput v-model="content.promo.link_url"
      /></UiField>
    </div>
  </div>

  <div v-else-if="section === 'about'" class="space-y-4">
    <h2 class="text-lg font-semibold text-gray-800">О проекте</h2>
    <div class="grid gap-4 md:grid-cols-2">
      <UiField label="Подпись над заголовком"
        ><UiInput v-model="content.about.eyebrow" /></UiField
      ><UiField label="Заголовок"
        ><UiInput v-model="content.about.title"
      /></UiField>
    </div>
    <UiField label="Текст"
      ><UiTextarea v-model="content.about.description" rows="5"
    /></UiField>
    <MediaReferenceField
      kind="image"
      label="Изображение блока"
      :model-value="content.about.image_media_id"
      @update:model-value="
        content.about.image_media_id = Array.isArray($event) ? null : $event
      "
    />
    <UiField
      label="Локальное изображение"
      help="Путь /images/... используется, пока файл не выбран в медиатеке."
      ><UiInput v-model="content.about.image_url"
    /></UiField>
    <UiField label="Описание изображения для доступности"
      ><UiInput v-model="content.about.image_alt"
    /></UiField>
    <UiImagePreview
      v-if="!content.about.image_media_id"
      :url="content.about.image_url || null"
      :alt="content.about.image_alt || 'О проекте'"
      class="max-w-sm"
    />
    <div class="grid gap-4 md:grid-cols-2">
      <UiField label="Текст ссылки"
        ><UiInput v-model="content.about.link_label" /></UiField
      ><UiField label="Ссылка" help="Внутренний путь /... или HTTPS-адрес."
        ><UiInput v-model="content.about.link_url"
      /></UiField>
    </div>
  </div>

  <div v-else class="space-y-5">
    <h2 class="text-lg font-semibold text-gray-800">Советы по выбору</h2>
    <div class="grid gap-4 md:grid-cols-2">
      <UiField label="Подпись над заголовком"
        ><UiInput v-model="content.guide.eyebrow" /></UiField
      ><UiField label="Заголовок"
        ><UiInput v-model="content.guide.title"
      /></UiField>
    </div>
    <div
      v-for="(item, index) in content.guide.items"
      :key="index"
      class="space-y-3 rounded-xl border border-gray-200 p-4"
    >
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="font-medium text-gray-800">Совет {{ index + 1 }}</h3>
        <div class="flex gap-1">
          <UiButton
            variant="ghost"
            size="sm"
            :disabled="index === 0"
            :aria-label="`Поднять совет ${index + 1}`"
            @click="move(content.guide.items, index, -1)"
            >↑</UiButton
          ><UiButton
            variant="ghost"
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
        ><UiTextarea v-model="item.description" rows="2"
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
