export type Permission = { id: number; name: string; code: string; description: string | null }
export type AccessRole = { id: number; name: string; slug: string; description: string | null; is_system: boolean; permissions: Permission[] }
export type RolePayload = { name: string; slug: string; description: string; permission_ids: number[] }
export type AssignedRole = { id: number; name: string; slug: string }
export type CataloguePermission = Permission & { roles: AssignedRole[] }
