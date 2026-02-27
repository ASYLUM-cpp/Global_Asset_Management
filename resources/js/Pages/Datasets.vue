<template>
  <AppLayout>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6 anim-enter">
      <div>
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Datasets</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">AI training sets and classification data</p>
      </div>
      <button @click="router.visit('/datasets?new=1')" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-xs font-semibold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
        <i class="ri-database-2-line mr-1"></i> Create Dataset
      </button>
    </div>

    <!-- Stats row -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div v-for="(s, si) in stats" :key="si" class="glass rounded-2xl p-4 hover-lift anim-enter" :data-delay="si * 60">
        <div class="flex items-center gap-2.5 mb-2">
          <div :class="['w-8 h-8 rounded-lg flex items-center justify-center', s.bg]">
            <i :class="[s.icon, 'text-sm text-white']"></i>
          </div>
          <span class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold">{{ s.label }}</span>
        </div>
        <p class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ s.value }}</p>
        <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">{{ s.sub }}</p>
      </div>
    </div>

    <!-- Datasets List -->
    <div v-if="datasets.length === 0" class="glass rounded-2xl p-12 text-center anim-enter" data-delay="240">
      <i class="ri-database-2-line text-5xl text-slate-300 dark:text-slate-600 mb-3"></i>
      <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">No datasets yet</p>
      <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Create your first dataset to start training AI classification models.</p>
    </div>
    <div v-else class="space-y-4">
      <div v-for="(ds, di) in datasets" :key="di"
        class="glass rounded-2xl p-5 hover-lift group anim-enter"
        :data-delay="240 + di * 50"
      >
        <div class="flex items-start gap-4">
          <div :class="['w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0', ds.iconBg]">
            <i :class="[ds.icon, 'text-xl text-white']"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 group-hover:text-indigo-600 transition-colors">{{ ds.name }}</h3>
              <span :class="['text-[9px] font-bold px-2 py-0.5 rounded-full', ds.statusClass]">{{ ds.status }}</span>
            </div>
            <p class="text-[10px] text-slate-500 dark:text-slate-400 mb-3">{{ ds.desc }}</p>
            <div class="grid grid-cols-4 gap-4">
              <div v-for="(m, mi) in ds.metrics" :key="mi">
                <p class="text-[9px] text-slate-400 dark:text-slate-500">{{ m.label }}</p>
                <p class="text-xs font-bold text-slate-700 dark:text-slate-200">{{ m.value }}</p>
              </div>
            </div>
            <!-- Progress bar -->
            <div v-if="ds.progress !== undefined" class="mt-3">
              <div class="flex items-center justify-between mb-1">
                <span class="text-[9px] text-slate-400 dark:text-slate-500">Training Progress</span>
                <span class="text-[9px] font-bold text-indigo-600">{{ ds.progress }}%</span>
              </div>
              <div class="h-1.5 rounded-full bg-slate-100 dark:bg-slate-700/50 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r from-indigo-400 to-violet-500 transition-all duration-700" :style="{ width: ds.progress + '%' }"></div>
              </div>
            </div>
          </div>
          <div class="flex flex-col gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
            <button @click.stop="viewDataset(ds)" class="w-8 h-8 rounded-lg glass flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-indigo-500 transition-colors"><i class="ri-eye-line text-sm"></i></button>
            <button @click.stop="downloadDataset(ds)" class="w-8 h-8 rounded-lg glass flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-indigo-500 transition-colors"><i class="ri-download-line text-sm"></i></button>
            <button @click.stop="deleteDataset(ds)" class="w-8 h-8 rounded-lg glass flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-red-400 transition-colors"><i class="ri-delete-bin-line text-sm"></i></button>
          </div>
        </div>
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
  datasets: { type: Array, default: () => [] },
  stats: { type: Array, default: () => [] },
});

const stats = computed(() => props.stats.length > 0 ? props.stats : [
  { label: 'Total Datasets', value: '0', sub: 'None created', icon: 'ri-database-2-line', bg: 'bg-gradient-to-br from-indigo-400 to-violet-500' },
  { label: 'Training Samples', value: '0', sub: 'No data', icon: 'ri-file-list-3-line', bg: 'bg-gradient-to-br from-emerald-400 to-teal-500' },
  { label: 'Avg Accuracy', value: '—', sub: 'No models trained', icon: 'ri-line-chart-line', bg: 'bg-gradient-to-br from-sky-400 to-blue-500' },
  { label: 'GPU Hours', value: '0h', sub: 'This billing cycle', icon: 'ri-cpu-line', bg: 'bg-gradient-to-br from-amber-400 to-orange-500' },
]);

const datasets = computed(() => props.datasets || []);

function viewDataset(ds) {
  router.visit('/datasets?view=' + ds.id);
}

function downloadDataset(ds) {
  if (ds.id) window.location.href = '/datasets/' + ds.id + '/download';
}

function deleteDataset(ds) {
  if (confirm('Are you sure you want to delete this dataset?')) {
    router.delete('/datasets/' + ds.id, { preserveScroll: true });
  }
}
</script>
