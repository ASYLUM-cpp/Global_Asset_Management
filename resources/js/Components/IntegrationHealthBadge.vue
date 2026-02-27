<template>
  <div class="inline-flex items-center gap-1.5 text-[10px]">
    <span :class="['w-2 h-2 rounded-full flex-shrink-0', dotClass]"></span>
    <i :class="[iconClass, 'text-xs']"></i>
    <span :class="textClass">{{ label }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  service: { type: String, required: true },
  status: { type: String, default: 'unknown' },
  latencyMs: { type: Number, default: null },
});

const dotClass = computed(() => {
  switch (props.status) {
    case 'ok': return 'bg-emerald-500 animate-pulse';
    case 'degraded': return 'bg-amber-500 animate-pulse';
    case 'down': return 'bg-rose-500';
    default: return 'bg-slate-400';
  }
});

const iconClass = computed(() => {
  return props.status === 'down' || props.status === 'unknown'
    ? 'ri-wifi-off-line text-slate-400'
    : 'ri-wifi-line text-emerald-500';
});

const textClass = computed(() => {
  switch (props.status) {
    case 'ok': return 'text-emerald-600 dark:text-emerald-400 font-medium';
    case 'degraded': return 'text-amber-600 dark:text-amber-400 font-medium';
    case 'down': return 'text-rose-600 dark:text-rose-400 font-medium';
    default: return 'text-slate-400 dark:text-slate-500';
  }
});

const label = computed(() => {
  switch (props.status) {
    case 'ok': return props.latencyMs ? `Online (${props.latencyMs}ms)` : 'Online';
    case 'degraded': return 'Slow';
    case 'down': return 'Offline';
    default: return 'Unknown';
  }
});
</script>
