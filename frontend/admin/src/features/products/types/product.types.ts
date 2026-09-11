export type ProductEditorStep = 'main' | 'attributes' | 'images' | 'group' | 'review'

export type ProductEditorStepDefinition = {
  id: ProductEditorStep
  label: string
}
