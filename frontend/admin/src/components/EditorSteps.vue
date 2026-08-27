<script setup lang="ts">
defineProps<{ steps: Array<{ id: string; label: string }>; active: string; enabled: (id: string) => boolean }>()
const emit = defineEmits<{ select: [id: string] }>()
</script>

<template>
  <nav class="overflow-x-auto border-b border-gray-200 px-4 sm:px-6" aria-label="Этапы карточки товара">
    <ol class="flex min-w-max gap-1 py-2">
      <li v-for="(step, index) in steps" :key="step.id">
        <button
          type="button"
          class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-50 disabled:cursor-not-allowed disabled:opacity-50"
          :class="active === step.id ? 'bg-primary-50 text-primary-600' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800'"
          :disabled="!enabled(step.id)"
          :aria-current="active === step.id ? 'step' : undefined"
          @click="emit('select', step.id)"
        >
          <span class="grid h-6 w-6 place-items-center rounded-full text-xs" :class="active === step.id ? 'bg-primary-500 text-white' : 'bg-gray-100 text-gray-600'">{{ index + 1 }}</span>
          {{ step.label }}
        </button>
      </li>
    </ol>
  </nav>
</template>
