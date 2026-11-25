import { apiFetch } from '../../../services/api'
import type { Attribute } from '../../attributes/types/attribute.types'
import type {
  ProductAttributePayload,
  ProductAttributeValue,
} from '../types/product.types'

export async function getCategoryAttributes(
  categoryId: number,
): Promise<Attribute[]> {
  const response = await apiFetch(`/admin/categories/${categoryId}/attributes`)
  return ((await response.json()) as { data: Attribute[] }).data.filter(
    (attribute) => attribute.slug !== 'kollektsiya',
  )
}
export async function getProductAttributeValues(
  productId: number,
): Promise<ProductAttributeValue[]> {
  const response = await apiFetch(`/admin/products/${productId}/attributes`)
  return ((await response.json()) as { data: ProductAttributeValue[] }).data
}
export async function saveProductAttributeValues(
  productId: number,
  payload: ProductAttributePayload,
): Promise<ProductAttributeValue[]> {
  const response = await apiFetch(`/admin/products/${productId}/attributes`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
  return ((await response.json()) as { data: ProductAttributeValue[] }).data
}
