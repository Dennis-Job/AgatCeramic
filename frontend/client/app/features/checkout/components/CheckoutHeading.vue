<script setup lang="ts">
import { Check } from '@lucide/vue'

const props = defineProps<{
  title: string
  eyebrow: string
  step: 'cart' | 'checkout' | 'done'
}>()
const steps = [
  { id: 'cart', label: 'Корзина' },
  { id: 'checkout', label: 'Оформление' },
  { id: 'done', label: 'Заказ принят' },
] as const
const current = computed(() => steps.findIndex(({ id }) => id === props.step))
</script>

<template>
  <div class="checkout-heading">
    <div>
      <p class="eyebrow">{{ eyebrow }}</p>
      <h1>{{ title }}</h1>
    </div>
    <ol class="checkout-progress" aria-label="Этапы заказа">
      <li
        v-for="(entry, index) in steps"
        :key="entry.id"
        :aria-current="index === current ? 'step' : undefined"
        :class="{ 'is-complete': index < current }"
      >
        <span class="checkout-progress__number" aria-hidden="true">
          <Check v-if="index < current" :size="12" />
          <template v-else>{{ index + 1 }}</template>
        </span>
        {{ entry.label }}
      </li>
    </ol>
  </div>
</template>

<style scoped>
.checkout-heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 24px 40px;
  padding: 44px 0;
}
.eyebrow {
  margin: 0;
}
h1 {
  margin: 14px 0 0;
  font-size: clamp(34px, 4vw, 56px);
  font-weight: 500;
  line-height: 1.04;
  letter-spacing: -0.02em;
  overflow-wrap: anywhere;
}
.checkout-progress {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 14px;
  margin: 0;
  padding: 0;
  list-style: none;
  color: var(--color-muted);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.18em;
  text-transform: uppercase;
}
.checkout-progress li {
  display: flex;
  align-items: center;
  gap: 10px;
}
.checkout-progress li + li::before {
  width: 28px;
  height: 1px;
  background: var(--color-line-strong);
  content: '';
}
.checkout-progress [aria-current],
.checkout-progress .is-complete {
  color: var(--color-ink);
}
.checkout-progress__number {
  display: grid;
  width: 22px;
  height: 22px;
  place-items: center;
  border: 1px solid currentColor;
  border-radius: 50%;
  font-size: 10px;
  letter-spacing: 0;
}
[aria-current] .checkout-progress__number {
  border-color: var(--color-ink);
  background: var(--color-ink);
  color: var(--color-white);
}
@media (max-width: 700px) {
  .checkout-heading {
    padding: 32px 0 30px;
  }
  .checkout-progress {
    letter-spacing: 0.08em;
  }
  .checkout-progress li {
    gap: 5px;
  }
  .checkout-progress li + li::before {
    display: none;
  }
}
</style>
