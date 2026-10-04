<script setup lang="ts">
import { Pencil, Plus } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiImagePreview from '../../../components/ui/UiImagePreview.vue'

defineProps<{
  url: string | null
  alt: string
  name: string
  editable: boolean
}>()
defineEmits<{ edit: [] }>()
</script>

<template>
  <UiButton
    v-if="editable"
    type="button"
    variant="ghost"
    class="product-photo-action"
    :class="{ 'product-photo-action-filled': url }"
    :aria-label="`${url ? 'Редактировать' : 'Добавить'} фото товара ${name}`"
    :tooltip="url ? 'Редактировать фото' : 'Добавить фото'"
    @click="$emit('edit')"
  >
    <UiImagePreview v-if="url" compact :url="url" :alt="alt" />
    <span v-if="url" class="product-photo-edit" aria-hidden="true">
      <Pencil :size="22" />
    </span>
    <span v-else class="product-photo-add" aria-hidden="true">
      <Plus :size="16" :stroke-width="3" />
    </span>
  </UiButton>
  <UiImagePreview v-else compact :url="url" :alt="alt" />
</template>

<style scoped>
.product-photo-action {
  position: relative;
  width: calc(var(--admin-spacing-4) * 3);
  height: calc(var(--admin-spacing-4) * 3);
  flex-shrink: 0;
  padding: 0;
  background: var(--color-gray-50);
}
.product-photo-add {
  display: grid;
  place-items: center;
  width: var(--admin-spacing-6);
  height: var(--admin-spacing-6);
  border-radius: var(--admin-radius-2xl);
  color: var(--color-white);
  background: var(--color-gray-400);
}
.product-photo-edit {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  border-radius: inherit;
  color: var(--color-white);
  background: color-mix(in srgb, var(--color-gray-900) 65%, transparent);
  opacity: 0;
}
.product-photo-action-filled:hover .product-photo-edit,
.product-photo-action-filled:focus-visible .product-photo-edit {
  opacity: 1;
}
@media (hover: none) {
  .product-photo-edit {
    inset: auto 0 0 auto;
    width: var(--admin-spacing-6);
    height: var(--admin-spacing-6);
    opacity: 1;
  }
  .product-photo-edit svg {
    width: var(--admin-spacing-4);
    height: var(--admin-spacing-4);
  }
}
</style>
