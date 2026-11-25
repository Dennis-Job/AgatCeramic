import type { Ref } from 'vue'
import type { Product } from './product.types'

export type ProductEditorSession = {
  editing: Readonly<Ref<Product | null>>
  saving: Ref<boolean>
  error: Ref<string>
  success: Ref<string>
}
