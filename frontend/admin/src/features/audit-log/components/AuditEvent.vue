<script setup lang="ts">
import { computed, ref } from 'vue'
import { ChevronDown, ChevronUp, Settings } from '@lucide/vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import type { AuditLog } from '../types/auditLog.types'
import { presentAuditLog } from '../utils/auditPresentation'

const props = defineProps<{ log: AuditLog }>()
const event = computed(() => presentAuditLog(props.log))
const expanded = ref(false)
</script>

<template>
  <article
    :aria-labelledby="`audit-event-${log.id}-title`"
    class="grid min-w-0 grid-cols-[2rem_minmax(0,1fr)] gap-x-3 py-5 text-sm text-gray-500 sm:grid-cols-[3rem_2rem_minmax(0,1fr)] sm:gap-x-4"
  >
    <time
      :datetime="log.occurred_at || undefined"
      class="col-start-2 row-start-1 mb-2 text-xs tabular-nums sm:col-start-1 sm:mb-0 sm:pt-2"
      >{{ event.time }}</time
    >
    <div
      class="relative col-start-1 row-span-2 row-start-1 sm:col-start-2"
      aria-hidden="true"
    >
      <span
        class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-primary-50 text-xs font-semibold text-primary-500"
      >
        <template v-if="event.initials">{{ event.initials }}</template
        ><Settings v-else :size="16" />
      </span>
      <span class="absolute bottom-0 left-4 top-10 w-px bg-gray-100" />
    </div>
    <div class="col-start-2 min-w-0 sm:col-start-3 sm:row-start-1">
      <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
        <h3
          :id="`audit-event-${log.id}-title`"
          class="min-w-0 font-semibold [overflow-wrap:anywhere]"
        >
          {{ event.title }}
        </h3>
        <UiBadge>{{ event.module }}</UiBadge>
      </div>
      <p class="mt-1 [overflow-wrap:anywhere]">{{ event.actor }}</p>
      <p
        v-if="event.object || event.sku"
        class="mt-2 font-medium text-primary-500 [overflow-wrap:anywhere]"
      >
        <span v-if="event.sku">SKU {{ event.sku }}</span
        ><span v-if="event.sku && event.object"> · </span
        ><span v-if="event.object">{{ event.object }}</span>
      </p>
      <p v-if="event.preview" class="mt-2 [overflow-wrap:anywhere]">
        {{ event.preview
        }}<span v-if="event.additionalChanges">
          · Ещё {{ event.additionalChanges }}</span
        >
      </p>
      <UiButton
        class="mt-2"
        variant="primary-ghost"
        size="sm"
        :aria-expanded="expanded"
        :aria-controls="`audit-event-${log.id}-details`"
        @click="expanded = !expanded"
      >
        {{ event.changes.length ? 'Показать изменения' : 'Подробности'
        }}<component
          :is="expanded ? ChevronUp : ChevronDown"
          :size="14"
          aria-hidden="true"
        />
      </UiButton>
      <div
        v-if="expanded"
        :id="`audit-event-${log.id}-details`"
        class="mt-3 min-w-0 rounded-lg border border-gray-100 bg-gray-50 p-3 sm:p-4"
      >
        <div v-if="event.changes.length" class="space-y-3">
          <div
            v-for="(change, index) in event.changes"
            :key="index"
            class="grid min-w-0 grid-cols-2 gap-3 border-b border-gray-200 pb-3 sm:grid-cols-3"
          >
            <p
              class="col-span-2 font-semibold [overflow-wrap:anywhere] sm:col-span-1"
            >
              {{ change.label }}
            </p>
            <div class="min-w-0">
              <p class="mb-1 text-xs">Было</p>
              <p class="whitespace-pre-wrap [overflow-wrap:anywhere]">
                {{ change.before }}
              </p>
            </div>
            <div class="min-w-0">
              <p class="mb-1 text-xs">Стало</p>
              <p
                class="whitespace-pre-wrap font-semibold [overflow-wrap:anywhere]"
              >
                {{ change.after }}
              </p>
            </div>
          </div>
        </div>
        <dl class="space-y-3" :class="{ 'mt-3': event.changes.length }">
          <div
            v-for="(detail, index) in event.details"
            :key="index"
            class="grid min-w-0 gap-1 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] sm:gap-3"
          >
            <dt class="font-medium [overflow-wrap:anywhere]">
              {{ detail.label }}
            </dt>
            <dd class="min-w-0 whitespace-pre-wrap [overflow-wrap:anywhere]">
              {{ detail.value }}
            </dd>
          </div>
          <div
            class="grid min-w-0 gap-1 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] sm:gap-3"
          >
            <dt class="font-medium">Код действия</dt>
            <dd class="min-w-0 [overflow-wrap:anywhere]">{{ log.action }}</dd>
          </div>
        </dl>
      </div>
    </div>
  </article>
</template>
