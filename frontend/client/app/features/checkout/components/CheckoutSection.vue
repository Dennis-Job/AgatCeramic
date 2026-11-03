<script setup lang="ts">
defineProps<{
  number: string
  title: string
  active: boolean
  complete: boolean
  busy: boolean
}>()
defineEmits<{ edit: [] }>()
</script>

<template>
  <section
    class="checkout-step"
    :class="{ 'checkout-step--pending': !active && !complete }"
  >
    <div class="checkout-step__heading">
      <span aria-hidden="true">{{ number }}</span>
      <h2 :data-checkout-active="active || undefined" tabindex="-1">
        {{ title }}
      </h2>
      <button
        v-if="complete"
        type="button"
        :disabled="busy"
        :aria-label="'Изменить: ' + title"
        @click="$emit('edit')"
      >
        Изменить
      </button>
    </div>
    <div v-if="complete" class="checkout-step__review">
      <slot name="review" />
    </div>
    <div v-if="active" class="checkout-step__body"><slot /></div>
  </section>
</template>

<style scoped>
.checkout-step {
  border-top: 1px solid var(--color-line-strong);
  padding: 26px 0;
  overflow-wrap: anywhere;
}
.checkout-step--pending {
  color: var(--color-muted);
}
.checkout-step__heading {
  display: grid;
  grid-template-columns: 36px minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px 20px;
}
.checkout-step__heading > span {
  font-size: 11px;
  letter-spacing: 0.2em;
}
h2 {
  margin: 0;
  font-size: clamp(19px, 2.3vw, 24px);
  font-weight: 500;
}
button {
  border: 0;
  border-bottom: 1px solid currentColor;
  padding: 6px 0;
  background: transparent;
  color: var(--color-muted);
  font-size: 11px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}
button:hover {
  color: var(--color-ink);
}
.checkout-step__review {
  margin: 12px 0 0 56px;
  color: var(--color-muted);
  font-size: 13px;
  line-height: 1.7;
}
.checkout-step__body {
  margin: 28px 0 0 56px;
}
@media (max-width: 700px) {
  .checkout-step__heading {
    grid-template-columns: 24px minmax(0, 1fr) auto;
    gap: 8px;
  }
  .checkout-step__review,
  .checkout-step__body {
    margin-left: 0;
  }
}
</style>
