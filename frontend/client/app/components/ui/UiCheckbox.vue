<script setup lang="ts">
defineProps<{ label: string; disabled?: boolean }>()
const model = defineModel<boolean>({ required: true })
</script>

<template>
  <label class="ui-checkbox">
    <input v-model="model" type="checkbox" :disabled="disabled" />
    <span>{{ label }}</span>
  </label>
</template>

<style scoped>
.ui-checkbox {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 44px;
  font-size: 13px;
  line-height: 1.6;
  overflow-wrap: anywhere;
  cursor: pointer;
}
input {
  flex: none;
  width: 18px;
  height: 18px;
  accent-color: var(--color-ink);
}
input:disabled,
input:disabled + span {
  opacity: 0.55;
  cursor: not-allowed;
}
</style>
