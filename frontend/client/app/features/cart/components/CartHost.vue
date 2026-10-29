<script setup lang="ts">
import CartDialog from './CartDialog.vue'
import { useCart } from '../composables/useCart'

const { open } = useCart()
const route = useRoute()
watch(
  () => route.fullPath,
  () => {
    open.value = false
  },
)
</script>

<template>
  <CartDialog v-if="open" @close="open = false" />
</template>
