<script setup lang="ts">
import { ChevronDown } from '@lucide/vue'
import {
  categoryBranchContains,
  type CatalogCategoryNode,
} from '../utils/categoryTree'

const props = withDefaults(
  defineProps<{
    node: CatalogCategoryNode
    selected?: string
    number?: number
  }>(),
  { selected: '' },
)
defineEmits<{ select: [category: string] }>()
const open = ref(false)
const trigger = ref<HTMLButtonElement | null>(null)
const groupId = useId()
const current = computed(() =>
  categoryBranchContains(props.node, props.selected),
)

watch(
  () => [props.selected, props.node] as const,
  () => {
    if (
      props.node.children.some((child) =>
        categoryBranchContains(child, props.selected),
      )
    )
      open.value = true
  },
  { immediate: true },
)

function escape(event: KeyboardEvent) {
  if (!open.value) return
  event.preventDefault()
  event.stopPropagation()
  open.value = false
  trigger.value?.focus({ preventScroll: true })
}
</script>

<template>
  <li
    class="catalog-category-branch"
    :class="{ 'ui-drawer-navigation__item': number !== undefined }"
    @keydown.esc="escape"
  >
    <button
      v-if="node.children.length"
      ref="trigger"
      type="button"
      class="ui-drawer-navigation__link catalog-category-branch__trigger"
      :class="{ 'ui-drawer-navigation__link--current': current }"
      :aria-expanded="open"
      :aria-controls="groupId"
      @click="open = !open"
    >
      <span>{{ node.category.name }}</span>
      <ChevronDown :size="16" :stroke-width="1.6" aria-hidden="true" />
    </button>
    <button
      v-else
      type="button"
      class="ui-drawer-navigation__link"
      :class="{
        'ui-drawer-navigation__link--current': selected === node.category.slug,
      }"
      :aria-current="selected === node.category.slug ? 'true' : undefined"
      @click="$emit('select', node.category.slug)"
    >
      <span>{{ node.category.name }}</span>
      <span
        v-if="number !== undefined"
        class="ui-drawer-navigation__number"
        aria-hidden="true"
        >{{ String(number).padStart(2, '0') }}</span
      >
    </button>
    <div
      v-if="node.children.length"
      :id="groupId"
      class="ui-submenu-drawer"
      :class="{ 'is-open': open }"
      :inert="!open"
      :aria-hidden="!open"
    >
      <div class="catalog-category-branch__content">
        <ul class="catalog-category-branch__children">
          <CatalogCategoryBranch
            v-for="child in node.children"
            :key="child.category.id"
            :node="child"
            :selected="selected"
            @select="$emit('select', $event)"
          />
        </ul>
      </div>
    </div>
  </li>
</template>

<style scoped>
.catalog-category-branch {
  min-width: 0;
}
.catalog-category-branch__trigger svg {
  flex-shrink: 0;
  transition: transform var(--motion-disclosure-duration) var(--ease-out);
}
.catalog-category-branch__trigger[aria-expanded='true'] svg {
  transform: rotate(180deg);
}
.catalog-category-branch__content {
  min-height: 0;
  overflow: hidden;
}
.catalog-category-branch__children {
  margin: 0;
  padding: 0 0 12px 12px;
  list-style: none;
}
</style>
