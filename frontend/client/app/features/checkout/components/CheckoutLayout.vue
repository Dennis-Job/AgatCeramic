<script setup lang="ts">
const props = defineProps<{ summaryFirst?: boolean }>()
const layout = useTemplateRef('layout')
const stacked = ref(false)
const columns = computed(() =>
  props.summaryFirst && stacked.value
    ? ['summary', 'main']
    : ['main', 'summary'],
)

let narrowLayout: MediaQueryList | undefined

async function syncLayout() {
  const narrow = narrowLayout?.matches ?? false
  if (stacked.value === narrow) return

  const focused = document.activeElement
  stacked.value = narrow
  if (!(focused instanceof HTMLElement) || !layout.value?.contains(focused))
    return

  await nextTick()
  if (document.activeElement === document.body)
    focused.focus({ preventScroll: true })
}

onMounted(() => {
  if (!props.summaryFirst) return
  narrowLayout = window.matchMedia('(max-width: 1100px)')
  syncLayout()
  narrowLayout.addEventListener('change', syncLayout)
})
onBeforeUnmount(() => narrowLayout?.removeEventListener('change', syncLayout))
</script>

<template>
  <div ref="layout" class="checkout-layout">
    <div
      v-for="column in columns"
      :key="column"
      :class="'checkout-layout__' + column"
    >
      <slot v-if="column === 'summary'" name="summary" />
      <slot v-else />
    </div>
  </div>
</template>

<style scoped>
.checkout-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
  grid-template-areas: 'main summary';
  gap: clamp(40px, 5vw, 80px);
  align-items: start;
  padding-bottom: var(--section-space);
}
.checkout-layout__main {
  grid-area: main;
  min-width: 0;
}
.checkout-layout__summary {
  grid-area: summary;
  position: sticky;
  top: 132px;
  min-width: 0;
}
@media (max-width: 1100px) {
  .checkout-layout {
    display: flex;
    flex-direction: column;
  }
  .checkout-layout > div {
    width: 100%;
  }
  .checkout-layout__summary {
    position: static;
  }
}
</style>
