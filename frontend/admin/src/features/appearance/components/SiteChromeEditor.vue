<script setup lang="ts">
import UiButton from '../../../components/ui/UiButton.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import MediaReferenceField from '../../media/components/MediaReferenceField.vue'
import type { SiteAppearance, SiteLink } from '../types/appearance.types'

defineProps<{
  content: SiteAppearance
  section: 'header' | 'footer'
  disabled?: boolean
}>()
const emit = defineEmits<{
  pending: [value: boolean]
  uploading: [value: boolean]
}>()

function move(items: SiteLink[], index: number, offset: -1 | 1): void {
  const next = index + offset
  if (next < 0 || next >= items.length) return
  ;[items[index], items[next]] = [items[next]!, items[index]!]
}
</script>

<template>
  <div v-if="section === 'header'" class="space-y-5">
    <div>
      <h2 class="text-lg font-semibold text-gray-500">Шапка сайта</h2>
      <p class="mt-1 text-sm text-gray-500">
        Эти тексты и ссылки видны на всех страницах сайта.
      </p>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
      <UiField label="Верхняя строка слева"
        ><UiInput v-model="content.header.topbar_left" /></UiField
      ><UiField label="Верхняя строка справа"
        ><UiInput v-model="content.header.topbar_right"
      /></UiField>
    </div>
    <MediaReferenceField
      kind="image"
      label="Логотип сайта"
      inline-upload
      require-manage-permission
      :disabled="disabled"
      :model-value="content.header.logo_media_id"
      @pending="emit('pending', $event)"
      @uploading="emit('uploading', $event)"
      @update:model-value="
        content.header.logo_media_id = Array.isArray($event) ? null : $event
      "
    />
    <UiField label="Описание логотипа"
      ><UiInput v-model="content.header.logo_alt"
    /></UiField>
    <h3 class="font-medium text-gray-500">Навигация</h3>
    <div
      v-for="(link, index) in content.header.navigation"
      :key="index"
      class="admin-panel--inset p-4"
    >
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <span class="text-sm font-medium text-gray-500"
          >Пункт {{ index + 1 }}</span
        >
        <div class="flex gap-1">
          <UiButton
            variant="ghost"
            size="sm"
            :disabled="index === 0"
            :aria-label="`Поднять пункт меню ${index + 1}`"
            @click="move(content.header.navigation, index, -1)"
            >↑</UiButton
          ><UiButton
            variant="ghost"
            size="sm"
            :disabled="index === content.header.navigation.length - 1"
            :aria-label="`Опустить пункт меню ${index + 1}`"
            @click="move(content.header.navigation, index, 1)"
            >↓</UiButton
          ><UiButton
            variant="danger-ghost"
            size="sm"
            :aria-label="`Удалить пункт меню ${index + 1}`"
            :disabled="content.header.navigation.length === 1"
            @click="content.header.navigation.splice(index, 1)"
            >Удалить</UiButton
          >
        </div>
      </div>
      <div class="grid gap-4 md:grid-cols-2">
        <UiField label="Название"><UiInput v-model="link.label" /></UiField
        ><UiField label="Ссылка" help="Внутренний путь /... или HTTPS-адрес."
          ><UiInput v-model="link.to"
        /></UiField>
      </div>
    </div>
    <UiButton
      variant="secondary"
      size="sm"
      @click="content.header.navigation.push({ label: '', to: '' })"
      >Добавить пункт меню</UiButton
    >
  </div>
  <div v-else class="space-y-5">
    <div>
      <h2 class="text-lg font-semibold text-gray-500">Подвал сайта</h2>
      <p class="mt-1 text-sm text-gray-500">
        Изменения применяются на всех страницах сайта.
      </p>
    </div>
    <UiField label="Слоган под логотипом"
      ><UiInput v-model="content.footer.tagline"
    /></UiField>
    <h3 class="font-medium text-gray-500">Ссылки «Исследовать»</h3>
    <div
      v-for="(link, index) in content.footer.explore_links"
      :key="index"
      class="admin-panel--inset p-4"
    >
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <span class="text-sm font-medium text-gray-500"
          >Ссылка {{ index + 1 }}</span
        >
        <div class="flex gap-1">
          <UiButton
            variant="ghost"
            size="sm"
            :disabled="index === 0"
            :aria-label="`Поднять ссылку ${index + 1}`"
            @click="move(content.footer.explore_links, index, -1)"
            >↑</UiButton
          ><UiButton
            variant="ghost"
            size="sm"
            :disabled="index === content.footer.explore_links.length - 1"
            :aria-label="`Опустить ссылку ${index + 1}`"
            @click="move(content.footer.explore_links, index, 1)"
            >↓</UiButton
          ><UiButton
            variant="danger-ghost"
            size="sm"
            :aria-label="`Удалить ссылку ${index + 1}`"
            @click="content.footer.explore_links.splice(index, 1)"
            >Удалить</UiButton
          >
        </div>
      </div>
      <div class="grid gap-4 md:grid-cols-2">
        <UiField label="Название"><UiInput v-model="link.label" /></UiField
        ><UiField label="Ссылка" help="Внутренний путь /... или HTTPS-адрес."
          ><UiInput v-model="link.to"
        /></UiField>
      </div>
    </div>
    <UiButton
      variant="secondary"
      size="sm"
      @click="content.footer.explore_links.push({ label: '', to: '' })"
      >Добавить ссылку</UiButton
    >
    <h3 class="border-t border-gray-200 pt-5 font-medium text-gray-500">
      Текстовый блок
    </h3>
    <div class="grid gap-4 md:grid-cols-2">
      <UiField label="Подпись"
        ><UiInput v-model="content.footer.message.eyebrow" /></UiField
      ><UiField label="Текст ссылки"
        ><UiInput v-model="content.footer.message.link_label"
      /></UiField>
    </div>
    <UiField label="Основной текст"
      ><UiTextarea v-model="content.footer.message.text" rows="3"
    /></UiField>
    <UiField label="Ссылка" help="Внутренний путь /... или HTTPS-адрес."
      ><UiInput v-model="content.footer.message.link_url"
    /></UiField>
    <h3 class="border-t border-gray-200 pt-5 font-medium text-gray-500">
      Нижняя строка
    </h3>
    <div class="grid gap-4 md:grid-cols-2">
      <UiField label="Слева"
        ><UiInput v-model="content.footer.bottom_left" /></UiField
      ><UiField label="Справа"
        ><UiInput v-model="content.footer.bottom_right"
      /></UiField>
    </div>
  </div>
</template>
