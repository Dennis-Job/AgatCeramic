<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { Image } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import MediaImageEditor from '../../media/components/MediaImageEditor.vue'
import { useAuthStore } from '../../../stores/auth'
import type { PageIntro } from '../types/page.types'

const model = defineModel<PageIntro | null>({ required: true })
defineProps<{ disabled?: boolean; hasHero?: boolean }>()
const emit = defineEmits<{
  pending: [value: boolean]
  uploading: [value: boolean]
}>()
const auth = useAuthStore()
const imageEditor = ref<InstanceType<typeof MediaImageEditor> | null>(null)
const imagePending = ref(false)
const customImage = computed(() =>
  Boolean(model.value?.image_media_id || model.value?.image_url),
)
function selectImage(id: number | null): void {
  model.value =
    id === null
      ? null
      : {
          image_url: null,
          image_media_id: id,
          image_alt: model.value?.image_alt ?? '',
        }
}
function updatePending(value: boolean): void {
  imagePending.value = value
  emit('pending', value)
}
async function resetImage(): Promise<void> {
  model.value = null
  await nextTick()
  imageEditor.value?.$el.querySelector('input[type="checkbox"]')?.focus()
}
</script>

<template>
  <UiCard aria-label="Вступительный блок страницы">
    <template #header>
      <h2 class="flex items-center gap-3 font-semibold text-gray-500">
        <Image :size="20" class="shrink-0" aria-hidden="true" />Вступительный
        блок страницы
      </h2>
    </template>
    <div class="min-w-0 space-y-3">
      <p class="text-sm text-gray-500">
        Фон на всю ширину вступительного блока, название страницы поверх него.
        Под текстом фон плавно осветляется. Если своё фото не выбрано, сайт
        использует стандартную иллюстрацию по теме страницы. Она видна в
        предпросмотре сайта.
      </p>
      <p v-if="hasHero" class="text-sm text-gray-500" role="status">
        Первым блоком страницы включён главный баннер: вступительный блок скрыт.
        Настройки изображения сохранятся и применятся после отключения баннера
        или его перемещения ниже другого включённого блока.
      </p>
      <MediaImageEditor
        ref="imageEditor"
        compact
        label="Изображение вступительного блока"
        preview-label="Увеличить изображение вступительного блока"
        remove-label="Вернуть стандартную иллюстрацию страницы"
        empty-selection-label="Стандартная иллюстрация страницы"
        :model-value="model?.image_media_id ?? null"
        :fallback-url="model?.image_url"
        :fallback-alt="model?.image_alt || 'Изображение вступительного блока'"
        :disabled="disabled"
        require-manage-permission
        @update:model-value="selectImage"
        @pending="updatePending"
        @uploading="emit('uploading', $event)"
      />
      <UiField
        v-if="customImage"
        label="Описание изображения вступительного блока"
        help="Кратко опишите изображение для людей, использующих экранный диктор."
      >
        <UiInput
          :model-value="model?.image_alt ?? ''"
          maxlength="255"
          :disabled="disabled"
          @update:model-value="model && (model.image_alt = $event)"
        />
      </UiField>
      <UiButton
        v-if="customImage && auth.hasPermission('media.manage')"
        type="button"
        variant="surface"
        size="sm"
        :disabled="disabled || imagePending"
        @click="resetImage"
        >Вернуть стандартную иллюстрацию</UiButton
      >
      <p class="text-xs text-gray-500">
        Рекомендуем широкий кадр от 2400 × 900 px в тёплой нейтральной гамме
        сайта, со свободным местом под текст слева. Фон заполняет баннер и
        обрезается под размер экрана; важные детали лучше размещать справа ближе
        к центру кадра. Сохраните черновик и опубликуйте страницу, чтобы
        обновить сайт.
      </p>
    </div>
  </UiCard>
</template>
