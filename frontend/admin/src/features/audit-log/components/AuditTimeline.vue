<script setup lang="ts">
import { computed } from 'vue'
import AuditEvent from './AuditEvent.vue'
import type { AuditLog } from '../types/auditLog.types'
import { eventCount, groupAuditLogs } from '../utils/auditPresentation'
const props = defineProps<{ logs: AuditLog[] }>()
const groups = computed(() => groupAuditLogs(props.logs))
</script>
<template>
  <div
    role="region"
    aria-label="Записи журнала аудита"
    class="admin-container py-6"
  >
    <section
      v-for="group in groups"
      :key="group.key"
      class="mb-6 last:mb-0"
      :aria-labelledby="`audit-day-${group.key}`"
    >
      <div
        class="flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-100 pb-3"
      >
        <h2
          :id="`audit-day-${group.key}`"
          class="text-base font-semibold text-gray-500"
        >
          {{ group.title }}
        </h2>
        <p class="text-xs text-gray-500">{{ eventCount(group.logs.length) }}</p>
      </div>
      <ol class="divide-y divide-gray-100">
        <li v-for="log in group.logs" :key="log.id">
          <AuditEvent :log="log" />
        </li>
      </ol>
    </section>
  </div>
</template>
