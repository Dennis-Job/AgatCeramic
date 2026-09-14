import { computed, ref, watch } from 'vue'
import { getCategories } from '../../categories/services/categories'
import type { Category } from '../../categories/types/category.types'
import { compareAlphabetically } from '../../../utils/alphabetical'
import {
  getProductImageImport,
  getProductImageImportErrors,
  uploadProductImageImport,
} from '../services/productImageImports'
import {
  getProductImport,
  getProductImportErrors,
  getProductImportTemplate,
  uploadProductImport,
} from '../services/products'
import {
  validateXlsxImport,
  validateZipImport,
} from '../validation/importFiles'
import { useFileImport } from './useFileImport'

export function useProductImportDialog(onCompleted: () => void) {
  const tab = ref<'products' | 'images'>('products')
  const importMode = ref<'template' | 'edit'>('template')
  const categories = ref<Category[]>([])
  const categoryId = ref('')
  const categoriesLoading = ref(false)
  const categoryError = ref('')

  const productImport = useFileImport({
    upload: (file) => uploadProductImport(file, Number(categoryId.value)),
    getStatus: getProductImport,
    validate: (file) =>
      validateXlsxImport(file, 'Прикрепите файл XLSX размером не более 10 МБ.'),
    uploadError: 'Не удалось загрузить XLSX-файл.',
    onCompleted,
  })
  const imageImport = useFileImport({
    upload: uploadProductImageImport,
    getStatus: getProductImageImport,
    validate: validateZipImport,
    uploadError: 'Не удалось загрузить ZIP-архив.',
    onCompleted,
  })

  const categoryOptions = computed(() => flattenCategories(categories.value))
  const successful = computed(
    () =>
      (productImport.result.value?.created_rows ?? 0) +
      (productImport.result.value?.updated_rows ?? 0),
  )
  const progress = computed(() =>
    productImport.result.value?.total_rows
      ? Math.min(
          100,
          Math.round(
            (productImport.result.value.processed_rows /
              productImport.result.value.total_rows) *
              100,
          ),
        )
      : undefined,
  )
  const imageProgress = computed(() =>
    imageImport.result.value?.total_folders
      ? Math.min(
          100,
          Math.round(
            (imageImport.result.value.processed_folders /
              imageImport.result.value.total_folders) *
              100,
          ),
        )
      : undefined,
  )
  const statusText = computed(() => {
    if (productImport.uploading.value) return 'Загружаем XLSX-файл…'
    if (productImport.result.value?.status === 'pending')
      return 'Файл ожидает обработки…'
    if (productImport.result.value?.status === 'processing')
      return productImport.result.value.total_rows
        ? `Обработано ${productImport.result.value.processed_rows} из ${productImport.result.value.total_rows} товаров`
        : 'Проверяем файл и импортируем товары…'
    return importMode.value === 'template'
      ? 'Выберите категорию, скачайте и заполните шаблон, затем загрузите файл.'
      : 'Выберите категорию, скачайте заполненный шаблон и загрузите отредактированный XLSX-файл.'
  })

  async function loadCategories() {
    categoriesLoading.value = true
    categoryError.value = ''
    try {
      categories.value = await getCategories()
    } catch (reason) {
      categoryError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось загрузить категории.'
    } finally {
      categoriesLoading.value = false
    }
  }

  async function downloadTemplate(errorsOnly = false) {
    await productImport.download(
      () =>
        errorsOnly && productImport.result.value
          ? getProductImportErrors(productImport.result.value.id)
          : getProductImportTemplate(
              Number(categoryId.value),
              importMode.value === 'edit',
            ),
      () =>
        errorsOnly
          ? productImport.result.value?.category_id === null
            ? 'Отчёт с ошибками скачан. Исправьте указанные строки в шаблоне редактирования и загрузите его повторно.'
            : 'Файл с ошибочными товарами скачан. Исправьте ошибки и загрузите его повторно.'
          : 'Шаблон Excel скачан. Заполните его и прикрепите ниже.',
      'Не удалось скачать файл.',
    )
  }

  async function uploadProducts() {
    if (categoryId.value) await productImport.upload()
  }

  async function downloadImageErrors() {
    if (!imageImport.result.value) return
    await imageImport.download(
      () => getProductImageImportErrors(imageImport.result.value!.id),
      '',
      'Не удалось скачать отчёт с ошибками.',
    )
  }

  watch(categoryId, () => productImport.resetSelection())
  watch(importMode, () => productImport.resetSelection(true))
  watch(tab, (activeTab) => {
    if (activeTab === 'images') productImport.clearFeedback()
    else imageImport.clearFeedback()
  })

  return {
    tab,
    importMode,
    categoryId,
    categoriesLoading,
    categoryError,
    categoryOptions,
    successful,
    progress,
    imageProgress,
    statusText,
    productImport,
    imageImport,
    loadCategories,
    downloadTemplate,
    uploadProducts,
    downloadImageErrors,
  }
}

function flattenCategories(
  nodes: Category[],
  depth = 0,
): { value: string; label: string }[] {
  return [...nodes]
    .sort((a, b) => compareAlphabetically(a.name, b.name))
    .flatMap((item) => [
      {
        value: String(item.id),
        label: `${'— '.repeat(depth)}${item.name}${item.is_active ? '' : ' (скрыта)'}`,
      },
      ...flattenCategories(item.children ?? [], depth + 1),
    ])
}
