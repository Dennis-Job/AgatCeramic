export const PRODUCT_IMPORT_MAX_BYTES = 10 * 1024 * 1024
export const PRODUCT_IMAGE_IMPORT_MAX_BYTES = 500 * 1024 * 1024

export function validateXlsxImport(
  file: File,
  message = 'Прикрепите XLSX-файл размером не более 10 МБ.',
): string {
  return file.name.toLowerCase().endsWith('.xlsx') &&
    file.size <= PRODUCT_IMPORT_MAX_BYTES
    ? ''
    : message
}

export function validateZipImport(file: File): string {
  const isZip =
    file.name.toLowerCase().endsWith('.zip') || file.type === 'application/zip'
  return isZip && file.size <= PRODUCT_IMAGE_IMPORT_MAX_BYTES
    ? ''
    : 'Прикрепите ZIP-архив размером не более 500 МБ.'
}
