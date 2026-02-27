<template>
  <AppLayout>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6 anim-enter">
      <div>
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Asset Pipeline</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Track assets through every processing stage</p>
      </div>
      <div class="flex items-center gap-2">
        <div class="glass rounded-xl px-3 py-2 flex items-center gap-2">
          <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
          <span class="text-[10px] font-bold text-slate-600 dark:text-slate-300">Pipeline Active</span>
        </div>
        <button @click="router.visit('/settings')" class="glass rounded-xl px-3.5 py-2 text-xs font-semibold text-indigo-600 hover:bg-indigo-50/50 dark:hover:bg-indigo-500/10 transition-all">
          <i class="ri-settings-3-line mr-1"></i> Configure
        </button>
      </div>
    </div>

    <!-- Stage Cards -->
    <div v-if="stages.length > 0" class="flex gap-4 overflow-x-auto pb-4 snap-x snap-mandatory">
      <div v-for="(stage, si) in stages" :key="si"
        class="glass rounded-2xl p-4 min-w-[280px] max-w-[280px] flex-shrink-0 snap-start anim-enter"
        :data-delay="80 + si * 60"
      >
        <!-- Stage header -->
        <div class="flex items-center gap-2.5 mb-3.5 pb-3 border-b border-slate-100/60 dark:border-slate-700/40">
          <div :class="['w-9 h-9 rounded-xl flex items-center justify-center', stage.headerBg]">
            <i :class="[stage.icon, 'text-lg text-white']"></i>
          </div>
          <div class="flex-1">
            <p class="text-xs font-bold text-slate-800 dark:text-slate-100">{{ stage.title }}</p>
            <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ stage.items.length }} assets</p>
          </div>
          <span :class="['text-[10px] font-bold px-2 py-0.5 rounded-full', stage.badge]">{{ stage.avgTime }}</span>
        </div>

        <!-- Stage items -->
        <div class="space-y-2">
          <div v-for="(item, ii) in stage.items" :key="ii"
            class="px-3 py-2.5 rounded-xl bg-white/50 dark:bg-white/5 border border-slate-100 dark:border-slate-700/40 hover:border-indigo-100 hover:shadow-md hover:shadow-indigo-50/50 dark:hover:shadow-indigo-500/5 cursor-pointer transition-all duration-300 group"
            @click="router.visit('/preview/' + item.id)"
          >
            <div class="flex items-center gap-2 mb-1.5">
              <div :class="['w-2 h-2 rounded-full flex-shrink-0', item.dot]"></div>
              <p class="text-[11px] font-semibold text-slate-800 dark:text-slate-100 truncate flex-1">{{ item.name }}</p>
            </div>
            <div class="flex items-center gap-1.5 justify-between">
              <div class="flex items-center gap-1">
                <span :class="['text-[9px] font-bold px-1.5 py-0.5 rounded', item.typeBadge]">{{ item.type }}</span>
                <span class="text-[9px] text-slate-400 dark:text-slate-500">{{ item.size }}</span>
              </div>
              <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button @click.stop="router.visit('/preview/' + item.id)" class="w-5 h-5 rounded bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center hover:bg-indigo-100 dark:hover:bg-indigo-500/15 transition"><i class="ri-eye-line text-[10px] text-slate-500 dark:text-slate-400"></i></button>
                <button @click.stop="router.visit('/preview/' + item.id)" class="w-5 h-5 rounded bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center hover:bg-indigo-100 dark:hover:bg-indigo-500/15 transition"><i class="ri-arrow-right-line text-[10px] text-slate-500 dark:text-slate-400"></i></button>
              </div>
            </div>
            <!-- Progress bar -->
            <div v-if="item.progress !== undefined" class="mt-2">
              <div class="h-1 rounded-full bg-slate-100 dark:bg-slate-700/50 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r transition-all duration-700" :class="item.barColor" :style="{ width: item.progress + '%' }"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Stage footer -->
        <div class="mt-3 pt-3 border-t border-slate-100/60 dark:border-slate-700/40 flex items-center justify-between">
          <span class="text-[9px] text-slate-400 dark:text-slate-500">Throughput</span>
          <span class="text-[10px] font-bold text-slate-600 dark:text-slate-300">{{ stage.throughput }}/hr</span>
        </div>
      </div>
    </div>
    <div v-else class="glass rounded-2xl p-12 text-center anim-enter">
      <i class="ri-flow-chart text-5xl text-slate-300 dark:text-slate-600 mb-3"></i>
      <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">No assets in the pipeline</p>
      <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Upload assets to see them flow through the processing pipeline.</p>
    </div>

    <!-- Bottom Stats -->
    <div class="grid grid-cols-4 gap-4 mt-6">
      <div v-for="(stat, si) in bottomStats" :key="si"
        class="glass rounded-2xl p-4 hover-lift anim-enter"
        :data-delay="400 + si * 60"
      >
        <div class="flex items-center gap-2.5 mb-2">
          <div :class="['w-8 h-8 rounded-lg flex items-center justify-center', stat.bg]">
            <i :class="[stat.icon, 'text-sm text-white']"></i>
          </div>
          <p class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold">{{ stat.label }}</p>
        </div>
        <p class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ stat.value }}</p>
        <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">{{ stat.sub }}</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { useScrollReveal } from '@/composables/useAnimations';
useScrollReveal();

const props = defineProps({
  stages: Array,
  totalInPipeline: Number,
  successRate: Number,
  failedCount: Number,
});

const stageStyles = {
  queued:      { headerBg: 'bg-gradient-to-br from-sky-400 to-blue-500', badge: 'bg-sky-50 text-sky-600', dot: 'bg-sky-400 animate-pulse', typeBadge: 'bg-sky-50 text-sky-600', barColor: 'from-sky-400 to-blue-500' },
  hashing:     { headerBg: 'bg-gradient-to-br from-violet-400 to-purple-500', badge: 'bg-violet-50 text-violet-600', dot: 'bg-violet-400 animate-pulse', typeBadge: 'bg-violet-50 text-violet-600', barColor: 'from-violet-400 to-purple-500' },
  previewing:  { headerBg: 'bg-gradient-to-br from-indigo-400 to-blue-600', badge: 'bg-indigo-50 text-indigo-600', dot: 'bg-indigo-400 animate-pulse', typeBadge: 'bg-indigo-50 text-indigo-600', barColor: 'from-indigo-400 to-blue-600' },
  tagging:     { headerBg: 'bg-gradient-to-br from-blue-400 to-indigo-600', badge: 'bg-blue-50 text-blue-600', dot: 'bg-blue-400 animate-pulse', typeBadge: 'bg-blue-50 text-blue-600', barColor: 'from-blue-400 to-indigo-600' },
  classifying: { headerBg: 'bg-gradient-to-br from-amber-400 to-orange-500', badge: 'bg-amber-50 text-amber-600', dot: 'bg-amber-400 animate-pulse', typeBadge: 'bg-amber-50 text-amber-600', barColor: 'from-amber-400 to-orange-500' },
  indexing:    { headerBg: 'bg-gradient-to-br from-rose-400 to-pink-500', badge: 'bg-rose-50 text-rose-600', dot: 'bg-rose-400', typeBadge: 'bg-rose-50 text-rose-600', barColor: 'from-rose-400 to-pink-500' },
  done:        { headerBg: 'bg-gradient-to-br from-emerald-400 to-teal-500', badge: 'bg-emerald-50 text-emerald-600', dot: 'bg-emerald-400', typeBadge: 'bg-emerald-50 text-emerald-600', barColor: 'from-emerald-400 to-teal-500' },
};

const stageOrder = ['queued', 'hashing', 'previewing', 'tagging', 'classifying', 'indexing', 'done'];
const stageProgressBase = { queued: 10, hashing: 25, previewing: 40, tagging: 55, classifying: 70, indexing: 85, done: 100 };

const stages = computed(() => (props.stages || []).map(s => {
  const style = stageStyles[s.key] || stageStyles.queued;
  const baseProgress = stageProgressBase[s.key] || 50;
  return {
    title: s.label,
    icon: s.icon,
    headerBg: style.headerBg,
    badge: style.badge,
    avgTime: s.count > 0 ? '~' + s.count + '' : '0',
    throughput: s.count,
    items: (s.assets || []).map(a => ({
      id: a.id,
      name: a.name,
      type: (a.extension || '').toUpperCase(),
      size: a.size,
      dot: style.dot,
      typeBadge: style.typeBadge,
      progress: s.key === 'done' ? undefined : baseProgress,
      barColor: style.barColor,
    })),
  };
}));

const bottomStats = computed(() => [
  { label: 'Total in Pipeline', value: (props.totalInPipeline || 0).toLocaleString(), sub: 'Active processing', icon: 'ri-route-line', bg: 'bg-gradient-to-br from-indigo-400 to-violet-500' },
  { label: 'Avg Processing', value: '—', sub: 'Tracking coming soon', icon: 'ri-time-line', bg: 'bg-gradient-to-br from-sky-400 to-blue-500' },
  { label: 'Success Rate', value: (props.successRate || 0) + '%', sub: (props.failedCount || 0) + ' failed', icon: 'ri-shield-star-line', bg: 'bg-gradient-to-br from-emerald-400 to-teal-500' },
  { label: 'Failed', value: (props.failedCount || 0).toLocaleString(), sub: 'Needs attention', icon: 'ri-alarm-warning-line', bg: 'bg-gradient-to-br from-amber-400 to-orange-500' },
]);
</script>
