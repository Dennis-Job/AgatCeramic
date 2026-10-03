export function validateProductImageFiles(files: FileList): string {
  if (files.length !== 1) return 'Выберите одно фото за раз.'
  const file = files[0]
  if (!file || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type))
    return 'Выберите фото в формате JPG, PNG или WebP.'
  if (file.size > 10 * 1024 * 1024)
    return 'Размер фото не должен превышать 10 МБ.'
  return ''
}
