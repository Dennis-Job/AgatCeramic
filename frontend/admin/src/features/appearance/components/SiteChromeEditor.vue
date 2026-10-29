<script setup lang="ts">
import { FileText, Image, PanelTop, PanelBottom, Text } from '@lucide/vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import NavigationEditor from './NavigationEditor.vue'
import FooterLinksEditor from './FooterLinksEditor.vue'
import MediaImageEditor from '../../media/components/MediaImageEditor.vue'
import type {
  NavigationEditorDraft,
  SiteAppearance,
} from '../types/appearance.types'

withDefaults(
  defineProps<{
    content: SiteAppearance
    section: 'header' | 'footer'
    disabled?: boolean
    navigationDraft?: NavigationEditorDraft
  }>(),
  { navigationDraft: () => ({ rows: [] }) },
)
const emit = defineEmits<{
  pending: [value: boolean]
  uploading: [value: boolean]
}>()
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
  </div>
  <NavigationEditor
    v-if="section === 'header'"
    v-model="content.header.navigation"
    :draft="navigationDraft"
    class="mt-5"
    :disabled="disabled"
  />
  <UiCard
    v-if="section === 'header'"
    class="mt-5"
    aria-labelledby="appearance-menu-title"
  >
    <template #header>
      <h3
        id="appearance-menu-title"
        class="flex items-center gap-3 font-semibold text-gray-500"
      >
        <FileText :size="20" class="shrink-0" aria-hidden="true" />Боковое меню
      </h3>
    </template>
    <UiField
      label="Текст под контактами"
      help="Необязательно. Например, часы работы или короткое сообщение. До 1000 символов."
    >
      <UiTextarea
        v-model="content.header.menu_note"
        compact
        rows="3"
        :maxlength="1000"
        :disabled="disabled"
      />
    </UiField>
    <p class="mt-3 text-xs text-gray-500">
      Телефон и email берутся из «Настроек сайта». Ссылки на соцсети — из
      раздела «Подвал → Мы в соцсетях».
    </p>
  </UiCard>
  <div v-if="section === 'footer'" class="space-y-5">
    <UiCard aria-labelledby="appearance-footer-tagline-title">
      <template #header>
        <h3
          id="appearance-footer-tagline-title"
          class="flex items-center gap-3 font-semibold text-gray-500"
        >
          <Text :size="20" class="shrink-0" aria-hidden="true" />Слоган
        </h3>
      </template>
      <UiField label="Слоган под логотипом"
        ><UiInput v-model="content.footer.tagline"
      /></UiField>
    </UiCard>
    <FooterLinksEditor
      id="appearance-footer-navigation-title"
      :model-value="content.footer.navigation_links ?? []"
      title="Навигация"
      description="Ссылки подвала задаются отдельно от меню шапки."
      :max="256"
      :disabled="disabled"
      @update:model-value="content.footer.navigation_links = $event"
    />
    <FooterLinksEditor
      id="appearance-footer-links-title"
      v-model="content.footer.explore_links"
      title="Ссылки «Исследовать»"
      :max="12"
      :disabled="disabled"
    />
    <FooterLinksEditor
      id="appearance-footer-social-title"
      :model-value="content.footer.social_links ?? []"
      title="Мы в соцсетях"
      description="Добавьте названия соцсетей и ссылки на страницы вашей компании."
      :max="12"
      :disabled="disabled"
      @update:model-value="content.footer.social_links = $event"
    />
    <UiCard aria-labelledby="appearance-footer-message-title">
      <template #header>
        <h3
          id="appearance-footer-message-title"
          class="flex items-center gap-3 font-semibold text-gray-500"
        >
          <FileText :size="20" class="shrink-0" aria-hidden="true" />Текстовый
          блок
        </h3>
      </template>
      <div class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
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
      </div>
    </UiCard>
    <UiCard aria-labelledby="appearance-footer-bottom-title">
      <template #header>
        <h3
          id="appearance-footer-bottom-title"
          class="flex items-center gap-3 font-semibold text-gray-500"
        >
          <PanelBottom :size="20" class="shrink-0" aria-hidden="true" />Нижняя
          строка
        </h3>
      </template>
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="text-sm text-gray-500">
          <p class="font-medium">Слева · автоматически</p>
          <p class="mt-2">{{ content.footer.bottom_left }}</p>
          <p class="mt-2 text-xs">
            Год обновляется автоматически по часовому поясу сайта.
          </p>
        </div>
        <div class="space-y-4">
          <UiField label="Справа — текст"
            ><UiInput v-model="content.footer.bottom_right"
          /></UiField>
          <UiField
            label="Справа — ссылка"
            help="Необязательно. Внутренний путь /... или HTTPS-адрес. Без ссылки текст не будет кликабельным."
            ><UiInput
              v-model="content.footer.bottom_right_url"
              :maxlength="2048"
          /></UiField>
        </div>
      </div>
    </UiCard>
  </div>
</template>
