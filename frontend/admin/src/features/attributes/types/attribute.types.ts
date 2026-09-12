export type AttributeOption = { id?: number; value: string; label: string; sort_order: number }
export type AttributeType = 'string' | 'text' | 'integer' | 'decimal' | 'boolean' | 'select' | 'multiselect' | 'date'
export type Attribute = { id: number; attribute_group_id: number | null; name: string; slug: string; type: AttributeType; unit: string | null; is_filterable: boolean; is_required: boolean; is_visible_on_product_page: boolean; sort_order: number; options: AttributeOption[]; created_at: string; updated_at: string }
export type AttributePayload = Omit<Attribute, 'id' | 'is_required' | 'created_at' | 'updated_at'>
