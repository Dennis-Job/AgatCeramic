import type { BrandPayload } from '../types/brand.types'
export function emptyBrand(): BrandPayload { return { name: '', slug: '', description: '', country_code: null, is_active: true } }
export function validateBrand(payload: BrandPayload): string { if (!payload.name.trim()) return 'Укажите название бренда.'; if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(payload.slug)) return 'Технический код может содержать латинские буквы, цифры и дефисы.'; return '' }
