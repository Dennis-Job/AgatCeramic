import { inject, provide, type InjectionKey } from 'vue'

// The editor is an incremental migration from a single component. A typed context
// can replace this boundary once the editor state is split into focused composables.
type ProductEditorContext = Record<string, any>

const productEditorContextKey: InjectionKey<ProductEditorContext> = Symbol('product-editor-context')

export function provideProductEditorContext(context: ProductEditorContext) {
  provide(productEditorContextKey, context)
}

export function useProductEditorContext(): any {
  const context = inject(productEditorContextKey)

  if (!context) {
    throw new Error('Product editor section must be rendered inside ProductEditor.')
  }

  return context
}
