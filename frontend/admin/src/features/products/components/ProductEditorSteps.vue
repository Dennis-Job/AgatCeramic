<script setup lang="ts">
import UiButton from '../../../components/ui/UiButton.vue'

defineProps<{ steps: Array<{ id: string; label: string }>; active: string; enabled: (id: string) => boolean }>()
const emit = defineEmits<{ select: [id: string] }>()
</script>

<template>
  <nav class="border-b border-gray-200 px-4 sm:px-6" aria-label="Этапы карточки товара">
    <ol class="grid grid-cols-1 gap-1 py-2 min-[380px]:grid-cols-2 sm:flex sm:flex-wrap">
      <li v-for="(step, index) in steps" :key="step.id">
        <UiButton
          type="button"
          variant="ghost"
          size="sm"
          class="w-full justify-start text-left sm:w-auto"
          :class="active === step.id ? 'bg-primary-50 text-primary-600' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800'"
          :disabled="!enabled(step.id)"
          :aria-current="active === step.id ? 'step' : undefined"
          @click="emit('select', step.id)"
        >
          <span class="grid h-6 w-6 place-items-center rounded-full text-xs" :class="active === step.id ? 'bg-primary-500 text-white' : 'bg-gray-100 text-gray-600'">{{ index + 1 }}</span>
          {{ step.label }}
        </UiButton>
      </li>
    </ol>
  </nav>
</template>
