<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiImagePreview from '../../../components/ui/UiImagePreview.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import BannerFormDialog from '../../banners/components/BannerFormDialog.vue'
import SliderFormDialog from '../../sliders/components/SliderFormDialog.vue'
import { useSliderBlock } from '../composables/useSliderBlock'

const props = defineProps<{ modelValue: number | null; disabled?: boolean }>()
const emit = defineEmits<{
  'update:modelValue': [value: number | null]
  dirty: [value: boolean]
  busy: [value: boolean]
}>()
const state = useSliderBlock(
  () => props.modelValue,
  (id) => emit('update:modelValue', id),
)
const { sliders, banners } = state
const options = computed(() =>
  state.items.value.map((slider) => ({
    value: String(slider.id),
    label: `${slider.name}${slider.is_published ? '' : ' (черновик)'}`,
  })),
)
const controlsDisabled = computed(() => props.disabled || state.busy.value)
watch(state.dirty, (dirty) => emit('dirty', dirty), { immediate: true })
watch(state.busy, (busy) => emit('busy', busy), { immediate: true })
onMounted(() => state.load())
</script>

<template>
  <div class="min-w-0 space-y-4">
    <UiAlert tone="warning" live="polite">
      Баннеры и слайдеры можно использовать на нескольких страницах. Сохранение
      опубликованного ресурса сразу меняет его во всех местах использования,
      независимо от публикации страницы.
    </UiAlert>
    <UiNotification v-if="state.success.value" tone="success" live="polite">
      {{ state.success.value }}
    </UiNotification>
    <div v-if="state.error.value" class="space-y-2">
      <UiNotification>{{ state.error.value }}</UiNotification>
      <UiButton
        type="button"
        variant="secondary"
        :disabled="controlsDisabled || state.loading.value"
        @click="state.load()"
        >Повторить загрузку слайдеров</UiButton
      >
    </div>
    <p v-if="state.loading.value" role="status" class="text-sm text-gray-500">
      Загрузка слайдеров…
    </p>
    <UiField
      label="Слайдер блока"
      help="Выберите существующий ресурс или создайте новый. Изменение выбора входит в черновик страницы."
    >
      <UiSelect
        :model-value="modelValue === null ? '' : String(modelValue)"
        :options="options"
        accessible-name="Слайдер блока"
        searchable
        clearable
        :disabled="controlsDisabled || state.loading.value"
        @update:model-value="
          emit('update:modelValue', $event ? Number($event) : null)
        "
      />
    </UiField>
    <div class="flex flex-wrap gap-2">
      <UiButton
        type="button"
        variant="secondary"
        :disabled="controlsDisabled || state.loading.value"
        @click="state.openSlider(null)"
        >Создать слайдер</UiButton
      >
      <UiButton
        v-if="state.selected.value"
        type="button"
        variant="secondary"
        :disabled="controlsDisabled"
        @click="state.openSlider(state.selected.value)"
        >Настроить слайдер и порядок баннеров</UiButton
      >
    </div>
    <p
      v-if="
        !state.loading.value &&
        !state.error.value &&
        modelValue &&
        !state.selected.value
      "
      role="alert"
      class="text-sm text-error-600"
    >
      Выбранный слайдер недоступен. Выберите другой ресурс.
    </p>
    <template v-if="state.selected.value">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h4 class="min-w-0 break-words font-semibold text-gray-900">
          {{ state.selected.value.name }}
        </h4>
        <UiBadge
          :tone="state.selected.value.is_published ? 'success' : 'neutral'"
        >
          {{ state.selected.value.is_published ? 'Опубликован' : 'Черновик' }}
        </UiBadge>
      </div>
      <p class="text-sm text-gray-500">
        На сайте показываются опубликованный слайдер и его опубликованные
        баннеры.
      </p>
      <ol v-if="state.selected.value.banners.length" class="space-y-3">
        <li
          v-for="(banner, index) in state.selected.value.banners"
          :key="banner.id"
          class="min-w-0 space-y-2 rounded-lg border border-gray-200 p-3"
        >
          <UiImagePreview
            :url="banner.image_url"
            :alt="banner.image_alt || banner.title"
          />
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="min-w-0 break-words text-sm font-medium">
              {{ index + 1 }}. {{ banner.title }}
            </span>
            <UiBadge :tone="banner.is_published ? 'success' : 'neutral'">
              {{ banner.is_published ? 'Опубликован' : 'Черновик' }}
            </UiBadge>
          </div>
          <p
            v-if="banner.description"
            class="break-words text-sm text-gray-500"
          >
            {{ banner.description }}
          </p>
          <UiButton
            type="button"
            variant="secondary"
            size="sm"
            :disabled="controlsDisabled"
            :aria-label="`Редактировать баннер ${banner.title}`"
            @click="state.openBanner(banner)"
            >Редактировать баннер</UiButton
          >
        </li>
      </ol>
      <p v-else role="status" class="text-sm text-gray-500">
        В слайдере пока нет баннеров. Добавьте существующий через настройки или
        создайте новый.
      </p>
      <UiButton
        type="button"
        variant="secondary"
        :disabled="
          controlsDisabled || state.selected.value.banners.length >= 50
        "
        @click="state.openBanner(null)"
        >Создать баннер для слайдера</UiButton
      >
    </template>
    <p
      v-else-if="!modelValue && !state.loading.value"
      role="status"
      class="text-sm text-gray-500"
    >
      Слайдер не выбран. Блок будет пустым до выбора и публикации ресурса.
    </p>
    <SliderFormDialog
      :suspended="state.discarding.value !== null"
      :open="sliders.editorOpen.value"
      :editing="Boolean(sliders.editing.value)"
      :busy="state.busy.value"
      :error="sliders.formError.value"
      :form="sliders.form.value"
      :selected-banners="sliders.selectedBanners.value"
      :options="sliders.options.value"
      :options-loading="sliders.optionsLoading.value"
      :options-error="sliders.optionsError.value"
      @close="state.requestClose('slider')"
      @submit="state.submitSlider()"
      @search="sliders.searchBanners"
      @add="sliders.addBanner"
      @move="sliders.moveBanner"
      @remove="sliders.removeBanner"
    />
    <BannerFormDialog
      :suspended="state.discarding.value !== null"
      inline-upload
      :open="banners.editorOpen.value"
      :editing="Boolean(banners.editing.value)"
      :busy="state.busy.value"
      :error="banners.formError.value"
      :form="banners.form.value"
      @close="state.requestClose('banner')"
      @submit="state.submitBanner()"
      @media-pending="state.mediaPending.value = $event"
      @media-uploading="state.mediaUploading.value = $event"
    />
    <ConfirmDialog
      :open="state.discarding.value !== null"
      title="Отменить изменения ресурса?"
      description="Несохранённые изменения в этом окне будут потеряны. Ранее сохранённые баннеры и слайдеры останутся в библиотеке."
      confirm-label="Отменить изменения"
      @close="state.discarding.value = null"
      @confirm="state.discard()"
    />
  </div>
</template>
