import { computed } from 'vue'
import {
  getProductPriceStatusImport,
  getProductPriceStatusImportErrors,
  getProductPriceStatusTemplate,
  uploadProductPriceStatusImport,
} from '../services/products'
import { validateXlsxImport } from '../validation/importFiles'
import { useFileImport } from './useFileImport'

export function useProductPriceStatusImport(onCompleted: () => void) {
  const workflow = useFileImport({
    upload: uploadProductPriceStatusImport,
    getStatus: getProductPriceStatusImport,
    validate: validateXlsxImport,
    uploadError: 'Не удалось загрузить XLSX-файл.',
    onCompleted,
  })
  const progress = computed(() =>
    workflow.result.value?.total_rows
      ? Math.round(
          (workflow.result.value.processed_rows /
            workflow.result.value.total_rows) *
            100,
        )
      : null,
  )

  async function downloadTemplate(errors = false) {
    await workflow.download(
      () =>
        errors && workflow.result.value
          ? getProductPriceStatusImportErrors(workflow.result.value.id)
          : getProductPriceStatusTemplate(),
      errors
        ? 'Отчёт с ошибками скачан.'
        : 'Шаблон скачан: заполните любые из трёх листов и загрузите файл.',
      'Не удалось скачать файл.',
      true,
    )
  }

  return { ...workflow, progress, downloadTemplate }
}
