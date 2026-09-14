import { computed } from 'vue'
import {
  getProductGroupImport,
  getProductGroupImportErrors,
  getProductGroupImportTemplate,
  uploadProductGroupImport,
} from '../services/products'
import { validateXlsxImport } from '../validation/importFiles'
import { useFileImport } from './useFileImport'

export function useProductGroupImport(onCompleted: () => void) {
  const workflow = useFileImport({
    upload: uploadProductGroupImport,
    getStatus: getProductGroupImport,
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
  const resultClass = computed(() => {
    if (workflow.result.value?.status === 'failed')
      return 'border-error-200 bg-error-50'
    if (workflow.finished.value)
      return workflow.result.value?.failed_rows
        ? 'border-warning-200 bg-warning-50'
        : 'border-success-200 bg-success-50'
    return 'border-gray-200'
  })

  async function downloadTemplate(errors = false) {
    await workflow.download(
      () =>
        errors && workflow.result.value
          ? getProductGroupImportErrors(workflow.result.value.id)
          : getProductGroupImportTemplate(),
      errors
        ? 'Отчёт с ошибками скачан.'
        : 'Выгрузка групп скачана. Измените только нужные строки и загрузите файл.',
      'Не удалось скачать файл.',
      true,
    )
  }

  return { ...workflow, progress, resultClass, downloadTemplate }
}
