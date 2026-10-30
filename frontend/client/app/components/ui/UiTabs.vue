<script setup lang="ts">
const props = defineProps<{
  label: string
  tabs: readonly { id: string; label: string }[]
}>()
const selected = defineModel<string>({ default: '' })
const prefix = useId()
const active = computed(() =>
  props.tabs.some((tab) => tab.id === selected.value)
    ? selected.value
    : props.tabs[0]?.id,
)
const buttons = ref<HTMLButtonElement[]>([])
function keydown(event: KeyboardEvent, index: number) {
  let next = index
  if (event.key === 'ArrowRight') next = (index + 1) % props.tabs.length
  else if (event.key === 'ArrowLeft')
    next = (index + props.tabs.length - 1) % props.tabs.length
  else if (event.key === 'Home') next = 0
  else if (event.key === 'End') next = props.tabs.length - 1
  else return
  event.preventDefault()
  selected.value = props.tabs[next]!.id
  buttons.value[next]?.focus()
}
</script>
<template>
  <div class="ui-tabs">
    <div role="tablist" :aria-label="label" class="ui-tabs__navigation">
      <button
        v-for="(tab, index) in tabs"
        :id="prefix + '-tab-' + tab.id"
        :key="tab.id"
        ref="buttons"
        type="button"
        role="tab"
        :aria-selected="active === tab.id"
        :aria-controls="prefix + '-panel-' + tab.id"
        :tabindex="active === tab.id ? 0 : -1"
        @click="selected = tab.id"
        @keydown="keydown($event, index)"
      >
        {{ tab.label }}
      </button>
    </div>
    <section
      v-for="tab in tabs"
      :id="prefix + '-panel-' + tab.id"
      :key="tab.id"
      role="tabpanel"
      :aria-labelledby="prefix + '-tab-' + tab.id"
      :hidden="active !== tab.id"
      tabindex="0"
    >
      <slot :name="tab.id" />
    </section>
  </div>
</template>
<style scoped>
.ui-tabs__navigation {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 36px;
  margin-bottom: 56px;
}
button {
  position: relative;
  min-height: 44px;
  padding: 10px 2px;
  border: 0;
  background: transparent;
  color: var(--color-muted);
  font: inherit;
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.18em;
  line-height: 1.6;
  text-transform: uppercase;
  transition: color 0.3s;
}
button[aria-selected='true'],
button:hover {
  color: var(--color-ink);
}
button::after {
  content: '';
  position: absolute;
  left: 0;
  bottom: 0;
  width: 100%;
  height: 1px;
  background: var(--color-ink);
  transform: scaleX(0);
  transform-origin: center;
  transition: transform 0.35s var(--ease-out);
}
button[aria-selected='true']::after {
  transform: scaleX(1);
}
section[hidden] {
  display: none;
}
@media (max-width: 760px) {
  .ui-tabs__navigation {
    gap: 24px;
    margin-bottom: 28px;
  }
  button {
    font-size: 10px;
    letter-spacing: 0.12em;
  }
}
@media (max-width: 520px) {
  .ui-tabs__navigation {
    gap: 18px;
  }
  button {
    font-size: 9px;
    letter-spacing: 0.1em;
  }
}
</style>
