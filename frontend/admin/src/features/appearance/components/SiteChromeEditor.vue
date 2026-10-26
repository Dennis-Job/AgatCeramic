<script setup lang="ts">
import {
  ArrowDown,
  ArrowUp,
  Image,
  Menu,
  PanelTop,
  Plus,
  Trash2,
} from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import MediaImageEditor from '../../media/components/MediaImageEditor.vue'
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
    <UiCard aria-labelledby="appearance-topbar-title">
      <template #header>
        <h3
          id="appearance-topbar-title"
          class="flex items-center gap-3 font-semibold text-gray-500"
        >
          <PanelTop :size="20" class="shrink-0" aria-hidden="true" />Верхняя
          строка
        </h3>
      </template>
      <UiField label="Верхняя строка слева"
        ><UiInput v-model="content.header.topbar_right"
      /></UiField>
      <p class="mt-3 text-xs text-gray-500">
        Справа — «Заказать звонок», телефон и email из «Настроек сайта». Заявки
        на обратный звонок поступают в «Обращения».
      </p>
    </UiCard>
    <UiCard aria-labelledby="appearance-logo-title">
      <template #header>
        <h3
          id="appearance-logo-title"
          class="flex items-center gap-3 font-semibold text-gray-500"
        >
          <Image :size="20" class="shrink-0" aria-hidden="true" />Логотип
        </h3>
      </template>
      <MediaImageEditor
        v-model="content.header.logo_media_id"
        label="Логотип сайта"
        upload-label="Выберите или перетащите логотип"
        preview-label="Увеличить логотип сайта"
        remove-label="Убрать логотип сайта"
        require-manage-permission
        compact
        :disabled="disabled"
        @pending="emit('pending', $event)"
        @uploading="emit('uploading', $event)"
      />
      <UiField label="Описание логотипа" class="mt-4"
        ><UiInput v-model="content.header.logo_alt"
      /></UiField>
    </UiCard>
    <UiCard aria-labelledby="appearance-navigation-title">
      <template #header>
        <h3
          id="appearance-navigation-title"
          class="flex items-center gap-3 font-semibold text-gray-500"
        >
          <Menu :size="20" class="shrink-0" aria-hidden="true" />Навигация
        </h3>
      </template>
      <p class="mb-4 text-xs text-gray-500">
        На компьютере — между логотипом и иконками, на узких экранах — в боковом
        меню. Измените названия, ссылки и порядок пунктов.
      </p>
      <ol class="divide-y divide-gray-100">
        <li
          v-for="(link, index) in content.header.navigation"
          :key="index"
          class="space-y-2 py-3 first:pt-0"
          :aria-label="`Пункт меню ${index + 1}`"
        >
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-xs text-gray-500">Пункт {{ index + 1 }}</span>
            <div class="flex gap-1">
              <UiButton
                variant="surface"
                size="sm"
                :disabled="index === 0"
                :aria-label="`Поднять пункт меню ${index + 1}`"
                @click="move(content.header.navigation, index, -1)"
                ><ArrowUp :size="16" aria-hidden="true" /></UiButton
              ><UiButton
                variant="surface"
                size="sm"
                :disabled="index === content.header.navigation.length - 1"
                :aria-label="`Опустить пункт меню ${index + 1}`"
                @click="move(content.header.navigation, index, 1)"
                ><ArrowDown :size="16" aria-hidden="true" /></UiButton
              ><UiButton
                variant="danger-ghost"
                size="sm"
                :aria-label="`Удалить пункт меню ${index + 1}`"
                :disabled="content.header.navigation.length === 1"
                @click="content.header.navigation.splice(index, 1)"
                ><Trash2 :size="16" aria-hidden="true"
              /></UiButton>
            </div>
          </div>
          <div class="grid min-w-0 gap-3 sm:grid-cols-2">
            <UiField label="Название"><UiInput v-model="link.label" /></UiField
            ><UiField
              label="Ссылка"
              help="Внутренний путь /... или HTTPS-адрес."
              ><UiInput v-model="link.to"
            /></UiField>
          </div>
        </li>
      </ol>
      <UiButton
        variant="primary-ghost"
        size="sm"
        class="mt-3"
        @click="content.header.navigation.push({ label: '', to: '' })"
        ><Plus :size="16" aria-hidden="true" />Добавить пункт меню</UiButton
      >
    </UiCard>
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
            variant="surface"
            size="sm"
            :disabled="index === 0"
            :aria-label="`Поднять ссылку ${index + 1}`"
            @click="move(content.footer.explore_links, index, -1)"
            >↑</UiButton
          ><UiButton
            variant="surface"
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
