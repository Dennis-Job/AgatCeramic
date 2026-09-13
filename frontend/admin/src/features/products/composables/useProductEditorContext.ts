import { inject, provide, type InjectionKey } from 'vue'
import type { useProductEditor } from './useProductEditor'

type ProductEditorContext = ReturnType<typeof useProductEditor>

const productEditorContextKey: InjectionKey<ProductEditorContext> = Symbol('product-editor-context')

export function provideProductEditorContext(context: ProductEditorContext) {
  provide(productEditorContextKey, context)
}

export function useProductEditorContext(): ProductEditorContext {
  const context = inject(productEditorContextKey)

  if (!context) {
    throw new Error('Product editor section must be rendered inside ProductEditor.')
  }

  return context
}
