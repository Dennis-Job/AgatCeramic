<script setup lang="ts">
import { computed, useTemplateRef } from 'vue'
import { EyeOff, Save, X } from '@lucide/vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiDialogFooter from '../../../components/ui/UiDialogFooter.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiHelpTooltip from '../../../components/ui/UiHelpTooltip.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import type { useProductEditor } from '../composables/useProductEditor'
import EditorSteps from './ProductEditorSteps.vue'
import ProductMainSection from './ProductMainSection.vue'
import ProductAttributesSection from './ProductAttributesSection.vue'
import ProductImagesSection from './ProductImagesSection.vue'
import ProductVariantsSection from './ProductVariantsSection.vue'
import ProductRelationsSection from './ProductRelationsSection.vue'
import ProductReviewSection from './ProductReviewSection.vue'

const { state } = defineProps<{ state: ReturnType<typeof useProductEditor> }>()
const {
  main,
  attributes,
  images,
  groups,
  relations,
  opened,
  photosOnly,
  saving,
  editing,
  activeStep,
  steps,
  error,
  success,
  canManage,
  copyingAttributes,
  enabled,
  sectionReady,
  sectionError,
  retrySection,
  close,
  saveMain,
  saveVisibility,
} = state
const { form, copiedFromProduct } = main
const {
  currentGroup,
  groupRolesPending,
  selectedGroupId,
  selectionLoading,
  groupDeleting,
  requestGroupDeletion,
  closeGroupDeletion,
  ungroup,
} = groups
const {
  imageDeleting,
  photosLoading,
  photosLoadFailed,
  loadPhotos,
  closeImageDeletion,
  removeImage,
} = images
const { missingRequiredNames } = attributes
const stepNavigation = useTemplateRef('stepNavigation')
function retryAndFocusStep() {
  stepNavigation.value?.focusActiveStep()
  void retrySection()
}
const sectionLabel = computed(() => {
  switch (activeStep.value) {
    case 'attributes':
      return 'Загрузка характеристик…'
    case 'images':
      return 'Загрузка фотографий…'
    case 'group':
      return 'Загрузка вариантов модели…'
    default:
      return 'Загрузка данных для проверки…'
  }
})

const dateFormatter = new Intl.DateTimeFormat('ru-RU', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
})
function formatDate(value: string): string {
  return dateFormatter.format(new Date(value))
}

type Step = typeof activeStep.value
const footerNotes = computed(() => {
  if (photosOnly.value)
    return ['Загрузка, удаление и порядок фото сохраняются сразу.']
  if (activeStep.value === 'main')
    return editing.value
      ? []
      : [
          'Новый товар сохранится как черновик. Публикация доступна на шаге «Проверка».',
        ]
  if (activeStep.value === 'attributes')
    return sectionReady.value
      ? [
          currentGroup.value
            ? 'Параметры выбора и индивидуальные характеристики изменяются только у этой позиции. Общие характеристики автоматически применяются ко всем товарам группы, включая очистку значений.'
            : 'Характеристики принадлежат только этой позиции. Черновик можно сохранить незаполненным.',
        ]
      : []
  if (activeStep.value === 'images')
    return ['Загрузка, удаление и порядок фото сохраняются сразу.']
  if (activeStep.value === 'group')
    return [
      'Объедините самостоятельные товары и назначьте характеристики для выбора варианта, индивидуальные и общие.',
      'Первые 25 совпадений той же категории и бренда. Уточните поиск, если товара нет.',
    ]
  if (activeStep.value === 'review')
    return [
      'Проверьте основные данные перед завершением.',
      'Добавьте товары, которые стоит предложить покупателю вместе с этой позицией.',
    ]
  return []
})
</script>

<template>
  <UiDialog
    :open="opened"
    labelledby="product-editor-title"
    :close-disabled="saving"
    :suspended="Boolean(imageDeleting || groupDeleting)"
    overlay-class="z-50 p-3 sm:p-6"
    panel-class="mx-auto flex h-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-xl"
    @close="close"
  >
    <header
      class="flex shrink-0 items-start justify-between gap-3 border-b border-gray-200 px-5 py-4"
    >
      <div class="min-w-0 [overflow-wrap:anywhere]">
        <h2
          id="product-editor-title"
          class="admin-focus text-lg font-bold text-gray-500 focus:outline-none"
          :data-autofocus="photosOnly ? '' : undefined"
          :tabindex="photosOnly ? -1 : undefined"
        >
          {{
            photosOnly
              ? editing?.primary_image
                ? 'Редактирование фото'
                : 'Добавление фото'
              : editing
                ? editing.name
                : 'Новый товар'
          }}
        </h2>
        <p v-if="editing && !photosOnly" class="mt-1 text-xs text-gray-500">
          Создан
          <time :datetime="editing.created_at">{{
            formatDate(editing.created_at)
          }}</time>
          · Изменён
          <time :datetime="editing.updated_at">{{
            formatDate(editing.updated_at)
          }}</time>
        </p>
        <p v-if="photosOnly" class="mt-1 text-sm text-gray-500">
          {{ editing?.name }}
        </p>
      </div>
      <UiButton
        type="button"
        variant="surface"
        size="sm"
        class="shrink-0"
        :disabled="saving"
        :aria-label="
          photosOnly ? 'Закрыть фотографии товара' : 'Закрыть карточку товара'
        "
        @click="close"
        ><X :size="20"
      /></UiButton>
    </header>
    <EditorSteps
      v-if="!photosOnly"
      ref="stepNavigation"
      :steps="steps"
      :active="activeStep"
      :enabled="enabled"
      @select="activeStep = $event as Step"
    />
    <div
      data-testid="product-editor-body"
      class="min-h-0 flex-1 overflow-y-auto p-5 sm:p-6"
    >
      <UiNotification v-if="error">{{ error }}</UiNotification>
      <UiNotification v-if="success" tone="success" live="polite">{{
        success
      }}</UiNotification>
      <UiAlert v-if="copyingAttributes" tone="info" live="polite"
        >Копирование характеристик…</UiAlert
      >
      <template v-if="photosOnly">
        <UiLoadingState v-if="photosLoading" label="Загрузка фотографий…" />
        <div v-else-if="photosLoadFailed" class="space-y-4">
          <p class="text-sm text-gray-500" role="status">
            Не удалось загрузить фотографии товара.
          </p>
          <UiButton type="button" variant="secondary" @click="loadPhotos"
            >Повторить загрузку</UiButton
          >
        </div>
        <ProductImagesSection
          v-else
          :state="images"
          :saving="saving"
          :can-manage="canManage"
          :product-name="form.name"
        />
      </template>
      <ProductMainSection
        v-else-if="activeStep === 'main'"
        :state="main"
        :editing="editing"
        @save="saveMain"
      />
      <div v-else-if="sectionError" class="space-y-4">
        <UiAlert>{{ sectionError }}</UiAlert>
        <UiButton type="button" variant="secondary" @click="retryAndFocusStep"
          >Повторить загрузку</UiButton
        >
      </div>
      <UiLoadingState v-else-if="!sectionReady" :label="sectionLabel" />
      <ProductAttributesSection
        v-else-if="activeStep === 'attributes'"
        :state="attributes"
        :group-roles-pending="groupRolesPending"
        :attribute-scope="groups.attributeScope"
        :is-active="form.is_active"
      />
      <ProductImagesSection
        v-else-if="activeStep === 'images'"
        :state="images"
        :saving="saving"
        :can-manage="canManage"
        :product-name="form.name"
      />
      <ProductVariantsSection
        v-else-if="activeStep === 'group'"
        :state="groups"
        :editing="editing"
        :saving="saving"
        :copied-from-product="copiedFromProduct"
        :attributes="attributes.attributes.value"
        :display-attribute-value="attributes.displayAttributeValue"
      />
      <div v-else id="product-review" class="space-y-5">
        <ProductReviewSection
          :form="form"
          :sku="editing?.sku"
          :image-count="images.images.value.length"
          :group-name="editing?.group?.name"
          :missing-required-names="missingRequiredNames"
        /><ProductRelationsSection :state="relations" :saving="saving" />
      </div>
    </div>
    <UiDialogFooter flush data-testid="product-editor-footer">
      <template v-if="footerNotes.length && activeStep !== 'group'" #note>
        <span v-for="note in footerNotes" :key="note" class="block">{{
          note
        }}</span>
      </template>
      <template v-if="photosOnly">
        <UiButton type="button" :disabled="saving" @click="close"
          >Готово</UiButton
        >
      </template>
      <UiButton
        v-else-if="activeStep === 'main'"
        type="submit"
        form="product-main-form"
        :loading="saving"
        :disabled="saving || copyingAttributes"
        ><Save :size="17" />{{
          saving ? 'Сохранение…' : 'Сохранить и продолжить'
        }}</UiButton
      >
      <UiButton
        v-else-if="activeStep === 'attributes'"
        type="submit"
        form="product-attributes-form"
        :loading="saving"
        :disabled="saving || !sectionReady || groupRolesPending"
        >{{ saving ? 'Сохранение…' : 'Сохранить и продолжить' }}</UiButton
      >
      <UiButton
        v-else-if="activeStep === 'images'"
        type="button"
        :disabled="saving || !sectionReady"
        @click="activeStep = 'group'"
        >Продолжить</UiButton
      >
      <template v-else-if="activeStep === 'group'">
        <UiHelpTooltip
          label="Варианты модели"
          :help="footerNotes.join(' ')"
          class="mr-auto self-center"
        />
        <UiButton
          v-if="sectionReady && selectedGroupId"
          type="button"
          variant="danger-ghost"
          :disabled="saving || selectionLoading"
          @click="requestGroupDeletion"
          >Удалить группу</UiButton
        ><UiButton
          type="button"
          variant="surface"
          :disabled="saving"
          @click="activeStep = 'review'"
          >Не объединять</UiButton
        ><UiButton
          type="submit"
          form="product-group-form"
          :loading="saving"
          :disabled="saving || !sectionReady || selectionLoading"
          >{{ saving ? 'Сохранение…' : 'Сохранить группу' }}</UiButton
        ></template
      >
      <template v-else
        ><UiButton
          type="button"
          variant="surface"
          :disabled="saving"
          @click="close"
          >Закрыть</UiButton
        ><UiButton
          v-if="!form.is_active"
          type="button"
          :loading="saving"
          :disabled="saving"
          @click="saveVisibility(true)"
          >{{ saving ? 'Публикация…' : 'Опубликовать товар' }}</UiButton
        ><UiButton
          v-else
          type="button"
          variant="secondary"
          :loading="saving"
          :disabled="saving"
          @click="saveVisibility(false)"
          ><EyeOff :size="17" />{{
            saving ? 'Скрытие…' : 'Скрыть товар'
          }}</UiButton
        ></template
      >
    </UiDialogFooter>
  </UiDialog>

  <ConfirmDialog
    :open="Boolean(imageDeleting)"
    title="Удалить фотографию?"
    description="Файл будет удалён без возможности восстановления."
    :busy="saving"
    :error="images.confirmError.value"
    @close="closeImageDeletion"
    @confirm="removeImage"
  />
  <ConfirmDialog
    :open="groupDeleting"
    title="Удалить группу вариантов?"
    description="Товары останутся самостоятельными позициями."
    confirm-label="Удалить группу"
    :busy="saving"
    :error="groups.confirmError.value"
    @close="closeGroupDeletion"
    @confirm="ungroup"
  />
</template>
