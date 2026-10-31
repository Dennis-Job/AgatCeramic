<script setup lang="ts">
import { computed, onMounted } from 'vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import { useCollectionAssignment } from '../composables/useCollectionAssignment'
import type { ProductCollection } from '../types/collection.types'
const props = defineProps<{
  modelValue: number | null
  brandId: number | null
  categoryAllowed: boolean
  current: ProductCollection | null
}>()
const emit = defineEmits<{ 'update:modelValue': [number | null] }>()
const value = computed({
  get: () => props.modelValue,
  set: (id) => emit('update:modelValue', id),
})
const {
  search,
  options,
  visible,
  incompatible,
  loading,
  error,
  load,
  select,
  hasMore,
  empty,
  loadMore,
} = useCollectionAssignment(
  computed(() => props.brandId),
  computed(() => props.categoryAllowed),
  value,
  computed(() => props.current),
)
onMounted(() => void load())
</script>
<template>
  <div v-if="visible" class="min-w-0 space-y-2 sm:col-span-2">
    <div class="grid min-w-0 gap-2 sm:grid-cols-2">
      <UiField
        label="Коллекция"
        help="Необязательно. Коллекции доступны только внутри выбранного бренда."
      >
        <UiSelect
          :model-value="modelValue === null ? '' : String(modelValue)"
          :options="options"
          accessible-name="Коллекция товара"
          :disabled="loading"
          @update:model-value="select"
        />
      </UiField>
      <UiField label="Поиск коллекции">
        <UiInput
          v-model="search"
          searchable
          aria-label="Поиск коллекции товара"
          placeholder="Название коллекции"
          maxlength="255"
          :disabled="!brandId || !categoryAllowed"
        />
      </UiField>
    </div>
    <p v-if="!brandId" class="text-sm text-gray-500" role="status">
      Сначала выберите бренд.
    </p>
    <p v-if="loading" class="text-sm text-gray-500" role="status">
      Загрузка коллекций…
    </p>
    <p v-if="empty" class="text-sm text-gray-500" role="status">
      Подходящие активные коллекции не найдены. Измените поиск или оставьте
      товар без коллекции.
    </p>
    <UiNotification v-if="incompatible"
      >Текущая коллекция несовместима с выбранным брендом или категорией.
      Выберите другую коллекцию или явно установите «Без
      коллекции».</UiNotification
    >
    <UiNotification v-if="error"
      >{{ error
      }}<UiButton
        type="button"
        variant="surface"
        :disabled="loading"
        @click="load()"
        >Повторить загрузку коллекций</UiButton
      ></UiNotification
    >
    <UiButton
      v-if="hasMore"
      type="button"
      variant="surface"
      size="sm"
      :disabled="loading"
      @click="loadMore"
      >Загрузить ещё коллекции</UiButton
    >
  </div>
</template>
