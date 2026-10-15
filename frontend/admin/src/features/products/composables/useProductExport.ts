import { ref } from 'vue'
import { getProductExport } from '../services/products'
import { parseExportSkus, validateExportSkus } from '../validation/exportSkus'

export function useProductExport() {
  const exporting = ref(false)
  const dialogOpen = ref(false)
  const skuInput = ref('')
  const skuError = ref('')
  const error = ref('')
  const success = ref('')

  function openDialog() {
    if (exporting.value) return
    skuInput.value = ''
    skuError.value = ''
    error.value = ''
    success.value = ''
    dialogOpen.value = true
  }

  function closeDialog() {
    if (!exporting.value) dialogOpen.value = false
  }

  async function download(skus?: string[]) {
    if (exporting.value) return
    exporting.value = true
    error.value = ''
    success.value = ''
    skuError.value = ''
    try {
      const file = await getProductExport({}, skus)
      const url = URL.createObjectURL(file.blob)
      try {
        const link = document.createElement('a')
        link.href = url
        link.download = file.filename
        document.body.appendChild(link)
        link.click()
        link.remove()
      } finally {
        URL.revokeObjectURL(url)
      }
      dialogOpen.value = false
      success.value = skus
        ? 'Товары по SKU экспортированы в Excel.'
        : 'Все товары экспортированы в Excel.'
    } catch (reason) {
      const message =
        reason instanceof Error
          ? reason.message
          : 'Не удалось экспортировать товары.'
      if (skus) skuError.value = message
      else error.value = message
    } finally {
      exporting.value = false
    }
  }

  async function downloadSkus() {
    if (exporting.value) return
    const skus = parseExportSkus(skuInput.value)
    skuError.value = validateExportSkus(skus)
    if (!skuError.value) await download(skus)
  }

  return {
    exporting,
    dialogOpen,
    skuInput,
    skuError,
    error,
    success,
    openDialog,
    closeDialog,
    download,
    downloadSkus,
  }
}
