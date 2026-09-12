export type AttributeGroup = { id: number; name: string; slug: string; description: string | null; sort_order: number; created_at: string; updated_at: string }
export type AttributeGroupPayload = Omit<AttributeGroup, 'id' | 'created_at' | 'updated_at'>
