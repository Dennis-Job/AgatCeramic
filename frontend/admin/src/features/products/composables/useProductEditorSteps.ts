import { ref } from 'vue'
import type { ProductEditorStep, ProductEditorStepDefinition } from '../types/product.types'

const steps: ProductEditorStepDefinition[] = [
  { id: 'main', label: 'Основное и продажа' },
  { id: 'attributes', label: 'Характеристики' },
  { id: 'images', label: 'Фото' },
  { id: 'group', label: 'Варианты модели' },
  { id: 'review', label: 'Проверка' },
]

export function useProductEditorSteps() {
  const activeStep = ref<ProductEditorStep>('main')

  function reset() {
    activeStep.value = 'main'
  }

  return { activeStep, reset, steps }
}
