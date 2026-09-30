<script setup lang="ts">
import { computed } from 'vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import HomePageBodyEditor from '../../homepage/components/HomePageBodyEditor.vue'
import type { BodyContent, PageBlock } from '../types/block.types'
import { blankBlockData } from '../validation/blocks'
import SliderBlockEditor from './SliderBlockEditor.vue'

const props = defineProps<{ block: PageBlock; disabled: boolean }>()
const emit = defineEmits<{ dirty: [value: boolean]; busy: [value: boolean] }>()
const body = computed<BodyContent>(() => ({
  ...blankBlockData(),
  [props.block.type]: props.block.data,
}))
</script>

<template>
  <fieldset :disabled="disabled" class="min-w-0 space-y-4">
    <legend class="sr-only">Настройки блока</legend>
    <SliderBlockEditor
      v-if="block.type === 'hero'"
      v-model="block.data.slider_id"
      :disabled="disabled"
      @dirty="emit('dirty', $event)"
      @busy="emit('busy', $event)"
    />
    <template v-else-if="block.type === 'text'">
      <UiField label="Заголовок блока" required
        ><UiInput v-model="block.data.title" required maxlength="255"
      /></UiField>
      <UiField label="Текст блока" help="Обычный текст; HTML не исполняется."
        ><UiTextarea v-model="block.data.body" rows="8" maxlength="100000"
      /></UiField>
    </template>
    <template v-else-if="block.type === 'stores' || block.type === 'catalog'">
      <UiField label="Заголовок блока" required
        ><UiInput v-model="block.data.title" required maxlength="255"
      /></UiField>
      <UiField v-if="block.type === 'catalog'" label="Описание каталога"
        ><UiTextarea
          v-model="block.data.description"
          rows="3"
          maxlength="100000"
      /></UiField>
      <p class="text-sm text-gray-500">
        {{
          block.type === 'stores'
            ? 'Адреса и часы работы берутся из опубликованных магазинов.'
            : 'Товары, цены и категории берутся из каталога.'
        }}
      </p>
    </template>
    <HomePageBodyEditor
      v-else
      :content="body"
      :section="block.type"
      inline-upload
      @pending="emit('dirty', $event)"
      @uploading="emit('busy', $event)"
    />
  </fieldset>
</template>
