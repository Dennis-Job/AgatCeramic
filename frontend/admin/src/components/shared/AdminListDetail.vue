<script setup lang="ts">
import { nextTick, ref } from 'vue'
import UiButton from '../ui/UiButton.vue'

const detail = ref<HTMLElement | null>(null)
const hasOpenedDetail = ref(false)
let opener: HTMLElement | null = null

async function showDetail(load: () => Promise<void>): Promise<void> {
  opener = document.activeElement as HTMLElement | null
  hasOpenedDetail.value = true
  await load()
  await nextTick()
  detail.value?.focus({ preventScroll: true })
  detail.value?.scrollIntoView({ block: 'start' })
}

function returnToList(): void {
  if (opener?.isConnected) {
    opener.focus({ preventScroll: true })
    opener.scrollIntoView({ block: 'center' })
  }
}

defineExpose({ showDetail })
</script>

<template>
  <div class="admin-list-detail">
    <div class="min-w-0"><slot name="list" /></div>
    <section
      ref="detail"
      class="admin-container admin-list-detail-panel admin-focus"
      tabindex="-1"
      aria-label="Детали выбранной записи"
    >
      <UiButton
        v-if="hasOpenedDetail"
        class="mb-3"
        variant="secondary"
        @click="returnToList"
        >Вернуться к списку</UiButton
      >
      <slot name="detail" />
    </section>
  </div>
</template>

<style scoped>
.admin-list-detail {
  display: grid;
  min-width: 0;
  align-items: start;
  gap: var(--admin-spacing-5);
}
.admin-list-detail-panel {
  scroll-margin-top: calc(var(--admin-shell-height) + var(--admin-spacing-4));
}
</style>
