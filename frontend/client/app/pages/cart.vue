<script setup lang="ts">
import CartPage from '~/features/cart/components/CartPage.vue'

useSeoMeta({
  title: 'Корзина',
  robots: 'noindex, follow',
})
</script>

<template>
  <CartPage />
</template>
