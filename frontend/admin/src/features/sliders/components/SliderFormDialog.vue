<script setup lang="ts">
import { ref, watch } from 'vue'
import { ArrowDown, ArrowUp, X } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import type { Banner } from '../../banners/types/banner.types'
import type { SliderPayload } from '../types/slider.types'

const props = defineProps<{
  open: boolean
  editing: boolean
  busy: boolean
  error: string
  form: SliderPayload
  selectedBanners: Banner[]
  options: Banner[]
  optionsLoading: boolean
  optionsError: string
}>()

const emit = defineEmits<{
  close: []
  submit: []
  search: [query: string]
  add: [banner: Banner]
  move: [index: number, offset: number]
  remove: [id: number]
}>()
const search = ref('')
watch(
  () => props.open,
  (open) => {
    if (open) search.value = ''
  },
)
</script>

<template>
  <UiDialog
    :open="open"
    labelledby="slider-dialog-title"
    :close-disabled="busy"
    panel-class="w-full max-w-2xl"
    @close="emit('close')"
  >
    <form
      class="max-h-[90vh] w-full overflow-y-auto rounded-xl bg-white p-6 shadow-xl"
      @submit.prevent="emit('submit')"
    >
      <div class="flex items-start justify-between gap-3">
        <h2 id="slider-dialog-title" class="text-lg font-bold">
          {{ editing ? 'Редактировать слайдер' : 'Новый слайдер' }}
        </h2>
        <UiButton
          type="button"
          variant="ghost"
          size="sm"
          aria-label="Закрыть окно слайдера"
          :disabled="busy"
          @click="emit('close')"
          ><X :size="20"
        /></UiButton>
      </div>
      <p v-if="error" role="alert" class="mt-3 text-sm text-error-600">
        {{ error }}
      </p>
      <div class="mt-6 grid gap-4">
        <UiField label="Название" required>
          <UiInput
            v-model="form.name"
            required
            maxlength="255"
            :disabled="busy"
            data-autofocus
          />
        </UiField>
        <UiField
          label="Код (slug)"
          help="Латинские строчные буквы, цифры и дефис. Используется в публичном URL."
          required
        >
          <UiInput
            v-model="form.slug"
            required
            maxlength="255"
            pattern="[a-z0-9]+(-[a-z0-9]+)*"
            :disabled="busy"
          />
        </UiField>
        <UiCheckbox
          mode="boolean"
          :checked="form.is_published"
          :disabled="busy"
          @update:checked="form.is_published = $event"
          >Опубликовать слайдер</UiCheckbox
        >
      </div>

      <h3 class="mt-6 font-semibold">Баннеры в порядке показа</h3>
      <p class="mt-1 text-sm text-gray-500">
        В публичном слайдере показываются только опубликованные баннеры.
      </p>
      <ol v-if="selectedBanners.length" class="mt-3 space-y-2">
        <li
          v-for="(banner, index) in selectedBanners"
          :key="banner.id"
          class="flex flex-wrap items-center gap-2 rounded-lg border border-gray-100 p-2"
        >
          <span class="min-w-0 flex-1 break-words text-sm">
            {{ index + 1 }}. {{ banner.title }}
            <span v-if="!banner.is_published" class="text-gray-500">
              (черновик)
            </span>
          </span>
          <UiButton
            type="button"
            variant="ghost"
            size="sm"
            :disabled="busy || index === 0"
            :aria-label="`Поднять баннер ${banner.title}`"
            @click="emit('move', index, -1)"
            ><ArrowUp :size="17"
          /></UiButton>
          <UiButton
            type="button"
            variant="ghost"
            size="sm"
            :disabled="busy || index === selectedBanners.length - 1"
            :aria-label="`Опустить баннер ${banner.title}`"
            @click="emit('move', index, 1)"
            ><ArrowDown :size="17"
          /></UiButton>
          <UiButton
            type="button"
            variant="danger-ghost"
            size="sm"
            :disabled="busy"
            :aria-label="`Убрать баннер ${banner.title}`"
            @click="emit('remove', banner.id)"
            ><X :size="17"
          /></UiButton>
        </li>
      </ol>
      <p v-else class="mt-3 text-sm text-gray-500" role="status">
        Баннеры пока не добавлены.
      </p>

      <div class="mt-5 flex flex-wrap items-end gap-2">
        <UiField label="Найти баннер по названию" class="min-w-0 flex-1">
          <UiInput
            v-model="search"
            maxlength="100"
            :disabled="busy"
            @keydown.enter.prevent="emit('search', search)"
          />
        </UiField>
        <UiButton
          type="button"
          variant="secondary"
          :disabled="busy || optionsLoading"
          @click="emit('search', search)"
          >Найти</UiButton
        >
      </div>
      <p v-if="optionsError" role="alert" class="mt-2 text-sm text-error-600">
        {{ optionsError }}
      </p>
      <p v-else-if="optionsLoading" role="status" class="mt-2 text-sm">
        Поиск баннеров…
      </p>
      <div
        v-else
        class="mt-3 max-h-48 overflow-y-auto rounded-lg border border-gray-100"
      >
        <div
          v-for="banner in options"
          :key="banner.id"
          class="flex items-center justify-between gap-2 border-b border-gray-100 p-2 last:border-0"
        >
          <span class="min-w-0 break-words text-sm">{{ banner.title }}</span>
          <UiButton
            type="button"
            variant="secondary"
            size="sm"
            :disabled="
              busy || selectedBanners.some((item) => item.id === banner.id)
            "
            :aria-label="`Добавить баннер ${banner.title}`"
            @click="emit('add', banner)"
            >Добавить</UiButton
          >
        </div>
        <p
          v-if="!options.length"
          class="p-3 text-sm text-gray-500"
          role="status"
        >
          Баннеры не найдены.
        </p>
      </div>
      <div class="mt-6 flex flex-wrap justify-end gap-3">
        <UiButton
          type="button"
          variant="secondary"
          :disabled="busy"
          @click="emit('close')"
          >Отмена</UiButton
        >
        <UiButton :loading="busy">Сохранить</UiButton>
      </div>
    </form>
  </UiDialog>
</template>
