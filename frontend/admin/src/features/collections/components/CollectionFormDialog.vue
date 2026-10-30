<script setup lang="ts">
import { computed, ref } from 'vue'
import { X } from '@lucide/vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiDialogFooter from '../../../components/ui/UiDialogFooter.vue'
import UiField from '../../../components/ui/UiField.vue'
import { countryName } from '../../../constants/countries'
import type { CollectionPayload } from '../types/collection.types'
import type { Brand } from '../../brands/types/brand.types'
import { slugPattern } from '../../catalog/validation/slug'
import MediaImageEditor from '../../media/components/MediaImageEditor.vue'
const props = defineProps<{
  open: boolean
  title: string
  busy: boolean
  mediaPending: boolean
  mediaUploading: boolean
  error: string
  form: CollectionPayload
  brands: Brand[]
  brandLocked: boolean
  brandsLoading: boolean
  brandsError: string
}>()
const emit = defineEmits<{
  close: []
  submit: []
  updateName: [string]
  updateSlug: [string]
  mediaPending: [boolean]
  mediaUploading: [boolean]
  retryBrands: []
}>()
const imagePreviewOpen = ref(false)
const selectedBrand = computed(() =>
  props.brands.find((item) => item.id === props.form.brand_id),
)
const brandOptions = computed(() => [
  { value: '', label: 'Выберите бренд' },
  ...props.brands.map((item) => ({ value: String(item.id), label: item.name })),
])
</script>
<template>
  <UiDialog
    :open="open"
    :suspended="imagePreviewOpen"
    labelledby="collection-dialog-title"
    describedby="collection-dialog-description"
    :close-disabled="busy || mediaUploading"
    panel-class="w-full max-w-3xl"
    @close="emit('close')"
  >
    <form
      class="flex max-h-[90dvh] w-full flex-col rounded-xl bg-white p-6 shadow-xl"
      @submit.prevent="emit('submit')"
    >
      <div class="flex shrink-0 items-start justify-between gap-4">
        <h2
          id="collection-dialog-title"
          class="min-w-0 break-words text-lg font-bold text-gray-500 [overflow-wrap:anywhere]"
        >
          {{ title }}
        </h2>
        <UiButton
          type="button"
          variant="surface"
          size="sm"
          aria-label="Закрыть окно коллекции"
          :disabled="busy || mediaUploading"
          @click="emit('close')"
          ><X :size="20"
        /></UiButton>
      </div>
      <div
        class="-m-[var(--admin-focus-halo-width)] min-h-0 overflow-y-auto overscroll-contain p-[var(--admin-focus-halo-width)]"
      >
        <UiNotification v-if="error">{{ error }}</UiNotification>
        <UiNotification v-if="brandsError"
          >{{ brandsError
          }}<UiButton
            type="button"
            variant="surface"
            @click="emit('retryBrands')"
            >Повторить загрузку брендов</UiButton
          ></UiNotification
        >
        <fieldset
          :disabled="busy || mediaUploading"
          class="mt-6 grid min-w-0 gap-4"
        >
          <legend class="sr-only">Информация о коллекции</legend>
          <div class="grid gap-4 sm:grid-cols-2">
            <UiField label="Название" required
              ><UiInput
                :model-value="form.name"
                required
                data-autofocus
                maxlength="255"
                @update:model-value="emit('updateName', $event)"
            /></UiField>
            <UiField label="Технический код (slug)" required
              ><UiInput
                :model-value="form.slug"
                required
                maxlength="255"
                :pattern="slugPattern"
                @update:model-value="emit('updateSlug', $event)"
            /></UiField>
            <UiField
              label="Бренд"
              required
              :help="
                brandLocked
                  ? 'Нельзя менять бренд коллекции с товарами.'
                  : undefined
              "
              ><UiSelect
                :model-value="form.brand_id ? String(form.brand_id) : ''"
                :options="brandOptions"
                accessible-name="Бренд коллекции"
                searchable
                teleport-menu
                :disabled="brandLocked || brandsLoading || Boolean(brandsError)"
                @update:model-value="
                  form.brand_id = $event ? Number($event) : null
                "
            /></UiField>
            <div class="text-sm text-gray-500">
              <p class="font-medium">Страна бренда</p>
              <p class="mt-2" role="status">
                {{
                  selectedBrand?.country_code
                    ? countryName(selectedBrand.country_code)
                    : 'Страна не указана'
                }}
              </p>
              <p class="mt-1 text-xs">Наследуется из выбранного бренда.</p>
            </div>
          </div>
          <UiField
            label="Заголовок блока"
            help="Если оставить пустым, на витрине используется название коллекции."
            ><UiInput
              :model-value="form.headline ?? ''"
              maxlength="255"
              @update:model-value="form.headline = $event"
          /></UiField>
          <UiField label="Описание" help="До 10000 символов."
            ><UiTextarea v-model="form.description" maxlength="10000"
          /></UiField>
          <MediaImageEditor
            label="Изображение коллекции"
            preview-label="Увеличить изображение коллекции"
            remove-label="Убрать изображение коллекции"
            upload-success-message="Изображение загружено и выбрано. Сохраните коллекцию, чтобы применить его."
            :model-value="form.image_id"
            :disabled="busy"
            @pending="emit('mediaPending', $event)"
            @uploading="emit('mediaUploading', $event)"
            @preview-open="imagePreviewOpen = $event"
            @update:model-value="form.image_id = $event"
          />
          <p class="text-sm text-gray-500" role="status">
            {{
              form.description?.trim() && form.image_id
                ? 'Описание и изображение заполнены. Публичный блок доступен у активной коллекции.'
                : 'Блок на витрине скрыт, пока не добавлены описание и изображение. Назначение товарам доступно.'
            }}
          </p>
          <UiCheckbox
            mode="boolean"
            :checked="form.is_active"
            @update:checked="form.is_active = $event"
            >Коллекция активна</UiCheckbox
          >
        </fieldset>
      </div>
      <UiDialogFooter>
        <template #note
          ><span id="collection-dialog-description">{{
            mediaUploading
              ? 'Файлы загружаются…'
              : mediaPending
                ? 'Загрузите выбранный файл или отмените выбор перед сохранением.'
                : 'Коллекция объединяет товары одного бренда.'
          }}</span></template
        >
        <div class="flex shrink-0 items-center gap-3">
          <UiButton
            type="button"
            variant="surface"
            :disabled="busy || mediaUploading"
            @click="emit('close')"
            >Отмена</UiButton
          ><UiButton
            :loading="busy"
            :disabled="
              mediaPending ||
              mediaUploading ||
              brandsLoading ||
              Boolean(brandsError)
            "
            >Сохранить</UiButton
          >
        </div>
      </UiDialogFooter>
    </form>
  </UiDialog>
</template>
