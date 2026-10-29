import { imageUrl } from '~/services/homePage'
import type { CartData, CartItem } from '../types/cart'

export class CartApiError extends Error {
  constructor(
    readonly status: number,
    message: string,
  ) {
    super(message)
  }
}

type CartRequest = {
  method?: 'GET' | 'POST' | 'PATCH' | 'DELETE'
  body?: { product_id?: number; quantity: number }
}

async function request<T>(
  base: string,
  path: string,
  token: string | null,
  options: CartRequest = {},
): Promise<T> {
  const endpoint = new URL(path, base.endsWith('/') ? base : base + '/')
  try {
    return (await $fetch<T>(endpoint.toString(), {
      ...options,
      ...(token ? { headers: { 'X-Cart-Token': token } } : {}),
      credentials: 'omit',
      retry: 0,
      timeout: 10_000,
    })) as T
  } catch (failure) {
    const status =
      (failure as { statusCode?: number; status?: number })?.statusCode ??
      (failure as { status?: number })?.status ??
      0
    const message =
      status === 422
        ? 'Товар недоступен в запрошенном количестве. Проверьте количество и попробуйте ещё раз.'
        : status === 404
          ? 'Корзина или товар больше недоступны. Обновите корзину и попробуйте ещё раз.'
          : status === 429
            ? 'Слишком много запросов. Попробуйте чуть позже.'
            : 'Не удалось обновить корзину. Попробуйте ещё раз.'
    // Do not retain a FetchError: it can contain the bearer request headers.
    throw new CartApiError(status, message)
  }
}

function mapItem(item: CartItem, base: string): CartItem {
  return {
    ...item,
    product: item.product
      ? {
          ...item.product,
          primary_image_url:
            imageUrl(item.product.primary_image_url, base) || null,
        }
      : null,
  }
}

export async function fetchCart(
  base: string,
  token: string | null,
): Promise<CartData> {
  const response = await request<{ data: CartData }>(base, 'cart', token)
  return {
    identifier: response.data.identifier,
    items: response.data.items.map((item) => mapItem(item, base)),
  }
}

export async function createCartItem(
  base: string,
  token: string,
  productId: number,
  quantity: number,
): Promise<CartItem> {
  const response = await request<{ data: CartItem }>(
    base,
    'cart/items',
    token,
    {
      method: 'POST',
      body: { product_id: productId, quantity },
    },
  )
  return mapItem(response.data, base)
}

export async function changeCartItem(
  base: string,
  token: string,
  itemId: number,
  quantity: number,
): Promise<CartItem> {
  const response = await request<{ data: CartItem }>(
    base,
    'cart/items/' + itemId,
    token,
    { method: 'PATCH', body: { quantity } },
  )
  return mapItem(response.data, base)
}

export async function deleteCartItem(
  base: string,
  token: string,
  itemId: number,
): Promise<void> {
  await request(base, 'cart/items/' + itemId, token, { method: 'DELETE' })
}
