<template>
  <AppLayout>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6 anim-enter">
      <div>
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Analytics</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Insights into asset performance and usage</p>
      </div>
      <div class="flex items-center gap-2">
        <div class="glass rounded-xl flex">
          <button v-for="(r, ri) in ranges" :key="ri"
            :class="['px-3 py-2 text-[10px] font-bold transition-all duration-300', activeRange === ri ? 'bg-indigo-500 text-white rounded-xl shadow-md shadow-indigo-200 dark:shadow-indigo-500/10' : 'text-slate-500 dark:text-slate-400 hover:text-indigo-500']"
            @click="changeRange(ri)"
          >{{ r }}</button>
        </div>
        <button @click="exportAnalytics" class="glass rounded-xl px-3.5 py-2 text-xs font-semibold text-indigo-600 hover:bg-indigo-50/50 dark:hover:bg-indigo-500/10 transition-all">
          <i class="ri-download-line mr-1"></i> Export
        </button>
      </div>
    </div>

    <!-- KPI Row -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div v-for="(kpi, ki) in kpis" :key="ki"
        class="glass rounded-2xl p-4 hover-lift anim-enter"
        :data-delay="ki * 60"
      >
        <div class="flex items-center justify-between mb-2">
          <span class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold">{{ kpi.label }}</span>
          <div :class="['w-8 h-8 rounded-lg flex items-center justify-center', kpi.iconBg]">
            <i :class="[kpi.icon, 'text-sm text-white']"></i>
          </div>
        </div>
        <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ kpi.value }}</p>
        <div class="flex items-center gap-1 mt-1">
          <span :class="['text-[10px] font-bold', kpi.trendUp ? 'text-emerald-500' : 'text-red-500']">
            <i :class="kpi.trendUp ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'"></i>{{ kpi.trend }}
          </span>
          <span class="text-[10px] text-slate-400 dark:text-slate-500">vs last period</span>
        </div>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-[2fr_1fr] gap-4 mb-6">
      <!-- Main Chart -->
      <div class="glass rounded-2xl p-5 anim-enter" data-delay="240">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Asset Activity</h3>
          <div class="flex gap-3">
            <div class="flex items-center gap-1.5"><div class="w-2.5 h-2.5 rounded-full bg-indigo-500"></div><span class="text-[10px] text-slate-500 dark:text-slate-400">Uploads</span></div>
            <div class="flex items-center gap-1.5"><div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div><span class="text-[10px] text-slate-500 dark:text-slate-400">Downloads</span></div>
            <div class="flex items-center gap-1.5"><div class="w-2.5 h-2.5 rounded-full bg-amber-500"></div><span class="text-[10px] text-slate-500 dark:text-slate-400">Reviews</span></div>
          </div>
        </div>
        <!-- Grouped bar chart -->
        <div class="flex items-end gap-1 h-44">
          <div v-for="(bar, bi) in activityBars" :key="bi" class="flex-1 flex items-end justify-center gap-px group">
            <div class="w-1/4 min-w-[4px] rounded-t bg-gradient-to-t from-indigo-500 to-indigo-400 transition-all duration-500 hover:brightness-110" :style="{ height: bar.uploads * 1.6 + 'px' }" :title="bar.uploads + ' uploads'"></div>
            <div class="w-1/4 min-w-[4px] rounded-t bg-gradient-to-t from-emerald-500 to-emerald-400 transition-all duration-500 hover:brightness-110" :style="{ height: bar.downloads * 1.6 + 'px' }" :title="bar.downloads + ' downloads'"></div>
            <div class="w-1/4 min-w-[4px] rounded-t bg-gradient-to-t from-amber-400 to-amber-300 transition-all duration-500 hover:brightness-110" :style="{ height: bar.reviews * 1.6 + 'px' }" :title="bar.reviews + ' reviews'"></div>
          </div>
        </div>
        <div class="flex gap-1 mt-1.5 border-t border-slate-100 dark:border-slate-700/40 pt-1.5">
          <span v-for="(bar, bi) in activityBars" :key="'l'+bi" class="flex-1 text-center text-[8px] text-slate-400 dark:text-slate-500">{{ bar.label }}</span>
        </div>
      </div>

      <!-- Donut / Breakdown -->
      <div class="glass rounded-2xl p-5 anim-enter-right" data-delay="300">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-4">Asset Types</h3>
        <!-- Visual donut made with CSS conic-gradient -->
        <div class="relative w-36 h-36 mx-auto mb-4">
          <div class="w-full h-full rounded-full" :style="{ background: donutGradient }"></div>
          <div class="absolute inset-4 rounded-full bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm flex items-center justify-center">
            <div class="text-center">
              <p class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ totalAssetsCount.toLocaleString() }}</p>
              <p class="text-[9px] text-slate-400 dark:text-slate-500">Total Assets</p>
            </div>
          </div>
        </div>
        <div class="space-y-2">
          <div v-for="(seg, si) in segments" :key="si" class="flex items-center gap-2">
            <div :class="['w-2.5 h-2.5 rounded-full', seg.color]"></div>
            <span class="text-[10px] text-slate-600 dark:text-slate-300 flex-1">{{ seg.label }}</span>
            <span class="text-[10px] font-bold text-slate-800 dark:text-slate-100">{{ seg.pct }}%</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom Row -->
    <div class="grid grid-cols-2 gap-4">
      <!-- Top Assets -->
      <div class="glass rounded-2xl p-5 anim-enter" data-delay="360">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3">Top Performing Assets</h3>
        <div class="space-y-2">
          <template v-if="topAssets.length > 0">
          <div v-for="(a, ai) in topAssets" :key="ai"
            class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-white/50 dark:bg-white/5 border border-slate-100 dark:border-slate-700/40 hover:border-indigo-100 transition-all duration-300 group"
          >
            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 w-5 text-center">{{ ai + 1 }}</span>
            <div :class="['w-9 h-9 rounded-lg flex items-center justify-center', a.bg]">
              <i :class="[a.icon, 'text-sm text-white']"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[11px] font-semibold text-slate-800 dark:text-slate-100 truncate">{{ a.name }}</p>
              <p class="text-[9px] text-slate-400 dark:text-slate-500">{{ a.downloads }} downloads</p>
            </div>
            <div class="w-16">
              <div class="h-1 rounded-full bg-slate-100 dark:bg-slate-700/50"><div class="h-full rounded-full bg-gradient-to-r from-indigo-400 to-violet-500" :style="{ width: a.score + '%' }"></div></div>
            </div>
          </div>
          </template>
          <div v-else class="py-6 text-center">
            <i class="ri-trophy-line text-2xl text-slate-300 dark:text-slate-600"></i>
            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">No asset data yet</p>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="glass rounded-2xl p-5 anim-enter-right" data-delay="420">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3">Activity Feed</h3>
        <div class="space-y-3">
          <template v-if="events.length > 0">
          <div v-for="(ev, ei) in events" :key="ei" class="flex items-start gap-3">
            <div :class="['w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0', ev.bg]">
              <i :class="[ev.icon, 'text-xs text-white']"></i>
            </div>
            <div class="flex-1">
              <p class="text-[11px] text-slate-700 dark:text-slate-200"><strong>{{ ev.user }}</strong> {{ ev.action }}</p>
              <p class="text-[9px] text-slate-400 dark:text-slate-500">{{ ev.time }}</p>
            </div>
          </div>
          </template>
          <div v-else class="py-6 text-center">
            <i class="ri-pulse-line text-2xl text-slate-300 dark:text-slate-600"></i>
            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">No recent activity</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { useScrollReveal } from '@/composables/useAnimations';
useScrollReveal();

const props = defineProps({
  totalUploads: Number,
  totalDownloads: Number,
  avgReviewTime: String,
  rejectionRate: Number,
  uploadTrend: Array,
  groupDistribution: Array,
  topAssets: Array,
  recentActivity: Array,
});

const ranges = ['24h', '7d', '30d', '90d'];
const activeRange = ref(2);

function changeRange(ri) {
  activeRange.value = ri;
  router.get('/analytics', { range: ranges[ri] }, { preserveState: true, preserveScroll: true });
}

function exportAnalytics() {
  // Build CSV from activity bars data
  const headers = ['Date', 'Uploads', 'Downloads', 'Reviews'];
  const rows = activityBars.value.map(b => [b.label, b.uploads, b.downloads, b.reviews].join(','));
  const csv = [headers.join(','), ...rows].join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'analytics-export.csv';
  a.click();
  URL.revokeObjectURL(url);
}

const kpis = computed(() => [
  { label: 'Total Uploads', value: (props.totalUploads || 0).toLocaleString(), trend: '—', trendUp: true, icon: 'ri-upload-cloud-line', iconBg: 'bg-gradient-to-br from-indigo-400 to-violet-500' },
  { label: 'Downloads', value: (props.totalDownloads || 0).toLocaleString(), trend: '—', trendUp: true, icon: 'ri-download-line', iconBg: 'bg-gradient-to-br from-emerald-400 to-teal-500' },
  { label: 'Avg Review Time', value: props.avgReviewTime || '—', trend: '—', trendUp: true, icon: 'ri-time-line', iconBg: 'bg-gradient-to-br from-sky-400 to-blue-500' },
  { label: 'Rejection Rate', value: (props.rejectionRate || 0) + '%', trend: '—', trendUp: false, icon: 'ri-close-circle-line', iconBg: 'bg-gradient-to-br from-rose-400 to-pink-500' },
]);

const activityBars = computed(() => {
  const trend = props.uploadTrend || [];
  if (trend.length === 0) return Array.from({ length: 14 }, (_, i) => ({ label: String(i + 1), uploads: 0, downloads: 0, reviews: 0 }));
  return trend.map(d => ({
    label: new Date(d.date).getDate().toString(),
    uploads: d.count || 0,
    downloads: 0,
    reviews: 0,
  }));
});

const segmentColors = ['bg-indigo-500', 'bg-emerald-500', 'bg-amber-500', 'bg-rose-500', 'bg-slate-400', 'bg-sky-500', 'bg-violet-500'];
const segments = computed(() => {
  const dist = props.groupDistribution || [];
  const total = dist.reduce((sum, g) => sum + g.count, 0) || 1;
  return dist.map((g, i) => ({
    label: g.group,
    pct: Math.round(g.count / total * 100),
    color: segmentColors[i % segmentColors.length],
  }));
});

const totalAssetsCount = computed(() => (props.groupDistribution || []).reduce((s, g) => s + g.count, 0));

const donutColors = ['#6366f1', '#10b981', '#f59e0b', '#f43f5e', '#94a3b8', '#0ea5e9', '#8b5cf6'];
const donutGradient = computed(() => {
  const dist = props.groupDistribution || [];
  const total = dist.reduce((s, g) => s + g.count, 0) || 1;
  let angle = 0;
  const stops = dist.map((g, i) => {
    const start = angle;
    angle += (g.count / total) * 360;
    return `${donutColors[i % donutColors.length]} ${start}deg ${angle}deg`;
  });
  return stops.length > 0 ? `conic-gradient(${stops.join(', ')})` : 'conic-gradient(#94a3b8 0deg 360deg)';
});

const assetIconMap = {
  jpg: 'ri-image-line', jpeg: 'ri-image-line', png: 'ri-image-line', tiff: 'ri-image-line',
  svg: 'ri-shape-line', pdf: 'ri-file-pdf-line', mp4: 'ri-video-line', mov: 'ri-video-line',
  psd: 'ri-artboard-line', default: 'ri-file-line',
};
const bgCycle = [
  'bg-gradient-to-br from-indigo-400 to-violet-500',
  'bg-gradient-to-br from-amber-400 to-orange-500',
  'bg-gradient-to-br from-rose-400 to-pink-500',
  'bg-gradient-to-br from-emerald-400 to-teal-500',
  'bg-gradient-to-br from-sky-400 to-blue-500',
];

const topAssets = computed(() => {
  const items = props.topAssets || [];
  const maxDl = Math.max(...items.map(a => a.downloads || 0), 1);
  return items.map((a, i) => {
    const ext = (a.name || '').split('.').pop().toLowerCase();
    return {
      name: a.name,
      downloads: (a.downloads || 0).toLocaleString(),
      score: Math.round((a.downloads || 0) / maxDl * 100),
      icon: assetIconMap[ext] || assetIconMap.default,
      bg: bgCycle[i % bgCycle.length],
    };
  });
});

const eventBgs = ['bg-emerald-500', 'bg-indigo-500', 'bg-violet-500', 'bg-sky-500', 'bg-slate-500'];
const eventIcons = ['ri-check-line', 'ri-upload-2-line', 'ri-brain-line', 'ri-folder-add-line', 'ri-shield-check-line'];
const events = computed(() => (props.recentActivity || []).map((a, i) => ({
  user: a.causer,
  action: a.description,
  time: a.time,
  icon: eventIcons[i % eventIcons.length],
  bg: eventBgs[i % eventBgs.length],
})));
</script>
