// Synthetic cart API, confined to the test server; no development database access.
const TOKEN = 'c'.repeat(64)
const defaultProductDefinitions = new Map([
  [
    1,
    {
      id: 1,
      name: 'Тестовый керамогранит',
      slug: 'test',
      sku: 'TEST-1',
      unit: 'square_meter',
      price: '1234.50',
      stock: 10,
      image_url: '/images/home/category-porcelain.webp',
    },
  ],
  [
    2,
    {
      id: 2,
      name: 'Тестовая затирка',
      slug: 'test-grout',
      sku: 'TEST-2',
      unit: 'piece',
      price: '505.00',
      stock: 1,
      image_url: null,
    },
  ],
])
let productDefinitions = new Map(defaultProductDefinitions)
let items = []
let requests = []
let nextId = 1
let mode = 'default'
let order = null

export function configureCartProducts(products) {
  for (const product of products)
    productDefinitions.set(product.id, { stock: 10, ...product })
}

function json(res, status, data) {
  res.writeHead(status, { 'Content-Type': 'application/json' })
  res.end(JSON.stringify(data))
}
function failure(res, status, code, details = {}) {
  json(res, status, {
    error: { code, message: 'Cart request failed', details },
  })
}
async function body(req) {
  const chunks = []
  for await (const chunk of req) chunks.push(chunk)
  try {
    return JSON.parse(Buffer.concat(chunks).toString() || '{}')
  } catch {
    return {}
  }
}
function line(item) {
  const product = productDefinitions.get(item.product_id)
  const cents = Math.round(Number(product.price) * 100) * item.quantity
  return {
    id: item.id,
    product_id: item.product_id,
    quantity: item.quantity,
    unit_price: product.price,
    line_total: (cents / 100).toFixed(2),
    product: {
      name: product.name,
      slug: product.slug,
      sku: product.sku,
      unit: product.unit,
      primary_image_url: product.image_url,
    },
  }
}

function createOrder(req, res, token, payload) {
  const key = req.headers['idempotency-key']
  if (req.method !== 'POST') return failure(res, 405, 'method_not_allowed')
  if (token !== TOKEN) return failure(res, 404, 'not_found')
  if (typeof key !== 'string' || key.length < 16)
    return failure(res, 422, 'validation_failed', {
      idempotency_key: ['invalid'],
    })
  const fingerprint = JSON.stringify(payload)
  if (order) {
    if (order.key !== key) return failure(res, 404, 'not_found')
    if (order.fingerprint !== fingerprint) return failure(res, 409, 'conflict')
    res.setHeader('Idempotent-Replayed', 'true')
    return json(res, 200, { data: order.data })
  }
  if (mode === 'order-unavailable' || !items.length) {
    return failure(res, 422, 'validation_failed', {
      cart: [
        'Корзина содержит недоступный товар или недостаточное количество.',
      ],
    })
  }
  const orderItems = items.map(line).map((item) => ({
    product_id: item.product_id,
    product_name: item.product.name,
    product_sku: item.product.sku,
    unit_price: item.unit_price,
    quantity: item.quantity,
    line_total: item.line_total,
  }))
  const totalCents = orderItems.reduce(
    (sum, item) => sum + Math.round(Number(item.line_total) * 100),
    0,
  )
  order = {
    key,
    fingerprint,
    data: {
      order_number: 'AC-20261103-A1B2C3D4E5',
      status: 'new',
      payment_status: 'not_paid',
      total_amount: (totalCents / 100).toFixed(2),
      items: orderItems,
      created_at: '2026-11-03T09:00:00+00:00',
    },
  }
  items = []
  res.setHeader('Idempotent-Replayed', 'false')
  return json(res, 201, { data: order.data })
}

export async function handleCart(req, res, url) {
  if (url.pathname === '/__cart/reset') {
    productDefinitions = new Map(defaultProductDefinitions)
    items = []
    requests = []
    nextId = 1
    mode = url.searchParams.get('mode') || 'default'
    order = null
    json(res, 200, { token: TOKEN })
    return true
  }
  if (url.pathname === '/__cart/stats') {
    json(res, 200, { requests, items })
    return true
  }
  if (url.pathname === '/__cart/seed') {
    const payload = await body(req)
    items = (payload.items || [{ product_id: 1, quantity: 2 }]).map((item) => ({
      ...item,
      id: nextId++,
    }))
    json(res, 200, { token: TOKEN })
    return true
  }
  const isOrder = url.pathname === '/api/v1/orders'
  if (!isOrder && !url.pathname.startsWith('/api/v1/cart')) return false
  const token = req.headers['x-cart-token']
  const payload = ['POST', 'PATCH'].includes(req.method)
    ? await body(req)
    : null
  requests.push({
    method: req.method,
    pathname: url.pathname,
    token: token || null,
    payload,
    ...(isOrder ? { key: req.headers['idempotency-key'] ?? null } : {}),
  })
  if (isOrder) {
    createOrder(req, res, token, payload)
    return true
  }
  if (mode === 'error') {
    failure(res, 503, 'unavailable')
    return true
  }
  if (mode === 'expired' && token) {
    mode = 'default'
    failure(res, 404, 'not_found')
    return true
  }
  if (req.method === 'GET' && url.pathname === '/api/v1/cart') {
    if (token && token !== TOKEN) {
      failure(res, 404, 'not_found')
      return true
    }
    json(res, 200, { data: { identifier: TOKEN, items: items.map(line) } })
    return true
  }
  if (token !== TOKEN) {
    failure(res, token ? 404 : 422, 'not_found')
    return true
  }
  if (req.method === 'POST' && url.pathname === '/api/v1/cart/items') {
    const product = productDefinitions.get(payload.product_id)
    const existing = items.find(
      (item) => item.product_id === payload.product_id,
    )
    if (
      !Number.isInteger(payload.quantity) ||
      payload.quantity < 1 ||
      payload.quantity > 9999
    ) {
      failure(res, 422, 'validation_failed', { quantity: ['invalid'] })
      return true
    }
    if (
      !product ||
      product.price === null ||
      (existing?.quantity || 0) + payload.quantity > product.stock
    ) {
      failure(res, 422, 'validation_failed', { product_id: ['unavailable'] })
      return true
    }
    const item = existing || {
      id: nextId++,
      product_id: payload.product_id,
      quantity: 0,
    }
    item.quantity += payload.quantity
    if (!existing) items.push(item)
    json(res, existing ? 200 : 201, { data: line(item) })
    return true
  }
  const id = Number(url.pathname.split('/').at(-1))
  const item = items.find((entry) => entry.id === id)
  if (!item) {
    failure(res, 404, 'not_found')
    return true
  }
  if (req.method === 'PATCH') {
    if (
      !Number.isInteger(payload.quantity) ||
      payload.quantity < 1 ||
      payload.quantity > 9999
    ) {
      failure(res, 422, 'validation_failed', { quantity: ['invalid'] })
      return true
    }
    const stock = productDefinitions.get(item.product_id).stock
    if (payload.quantity > stock) {
      failure(res, 422, 'validation_failed', {
        product_id: ['unavailable'],
        available_quantity: [String(stock)],
      })
      return true
    }
    item.quantity = payload.quantity
    json(res, 200, { data: line(item) })
    return true
  }
  if (req.method === 'DELETE') {
    items = items.filter((entry) => entry !== item)
    res.writeHead(204)
    res.end()
    return true
  }
  failure(res, 405, 'method_not_allowed')
  return true
}
