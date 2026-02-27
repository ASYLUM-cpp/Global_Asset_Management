<template>
  <AppLayout>
    <!-- Hero Banner -->
    <section class="relative rounded-2xl overflow-hidden mb-6 bg-gradient-to-br from-indigo-600 via-violet-600 to-purple-700 p-8 text-white anim-enter">
      <div class="absolute inset-0 opacity-10">
        <div class="absolute top-[-50%] right-[-20%] w-[500px] h-[500px] rounded-full bg-white/20 blur-3xl"></div>
        <div class="absolute bottom-[-30%] left-[-10%] w-[400px] h-[400px] rounded-full bg-indigo-300/30 blur-3xl"></div>
      </div>
      <div class="relative z-10 flex items-center justify-between">
        <div>
          <p class="text-indigo-200 text-xs font-semibold uppercase tracking-widest mb-1">Welcome back</p>
          <h2 class="text-2xl font-extrabold mb-1">Global Asset Management</h2>
          <p class="text-indigo-100 text-sm max-w-md">Monitor ingestion health, review queues, and AI classification accuracy from your command center.</p>
        </div>
        <button @click="go('/upload')" class="px-5 py-3 rounded-xl bg-white/15 hover:bg-white/25 backdrop-blur-sm border border-white/20 text-sm font-bold transition-all duration-300 hover:scale-105 ripple btn-pulse flex items-center gap-2">
          <i class="ri-upload-cloud-2-line text-lg"></i> Upload Batch
        </button>
      </div>
    </section>

    <!-- KPI Cards -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div
        v-for="(kpi, i) in kpis" :key="i"
        class="glass rounded-2xl p-5 hover-lift cursor-default anim-enter"
        :data-delay="i * 80"
      >
        <div class="flex items-center justify-between mb-3">
          <div :class="['w-10 h-10 rounded-xl flex items-center justify-center text-lg', kpi.iconBg]">
            <i :class="kpi.icon"></i>
          </div>
          <span :class="['text-[10px] font-bold px-2 py-0.5 rounded-full', kpi.deltaClass]">{{ kpi.delta }}</span>
        </div>
        <p class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ kpi.value }}</p>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ kpi.label }}</p>
        <div class="mt-3 h-1.5 rounded-full bg-slate-100 dark:bg-slate-700/50 overflow-hidden">
          <div class="h-full rounded-full transition-all duration-1000 ease-out" :class="kpi.barClass" :style="{ width: kpi.progress + '%' }"></div>
        </div>
      </div>
    </div>

    <!-- Charts Row (REQ-15) -->
    <div class="grid grid-cols-[1fr_1.2fr] gap-4 mb-6">
      <!-- Donut: Asset Distribution by Group -->
      <section class="glass rounded-2xl p-5 anim-enter" data-delay="80">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-4">Asset Distribution</h3>
        <div class="flex items-center justify-center gap-6">
          <!-- SVG Donut -->
          <svg viewBox="0 0 120 120" class="w-32 h-32 shrink-0">
            <circle cx="60" cy="60" r="50" fill="none" stroke="currentColor" stroke-width="12" class="text-slate-100 dark:text-slate-700/50" />
            <circle v-for="(seg, si) in donutSegments" :key="si"
              cx="60" cy="60" r="50"
              fill="none"
              :stroke="seg.color"
              stroke-width="12"
              :stroke-dasharray="seg.dash"
              :stroke-dashoffset="seg.offset"
              stroke-linecap="round"
              class="transition-all duration-1000 ease-out"
            />
            <text x="60" y="56" text-anchor="middle" class="fill-slate-800 dark:fill-white text-lg font-extrabold" style="font-size:18px">{{ props.totalAssets || 0 }}</text>
            <text x="60" y="72" text-anchor="middle" class="fill-slate-400 dark:fill-slate-500 text-[7px]" style="font-size:9px">assets</text>
          </svg>
          <!-- Legend -->
          <div class="space-y-2">
            <div v-for="(seg, si) in donutSegments" :key="'l'+si" class="flex items-center gap-2">
              <div class="w-3 h-3 rounded-full shrink-0" :style="{ backgroundColor: seg.color }"></div>
              <span class="text-[11px] text-slate-600 dark:text-slate-300 font-medium">{{ seg.name }}</span>
              <span class="text-[10px] font-bold text-slate-800 dark:text-slate-100 ml-auto">{{ seg.pct }}%</span>
            </div>
            <div v-if="donutSegments.length === 0" class="text-[11px] text-slate-400 dark:text-slate-500">No data yet</div>
          </div>
        </div>
      </section>

      <!-- Upload Trend (last 7 days) -->
      <section class="glass rounded-2xl p-5 anim-enter" data-delay="160">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Upload Trend</h3>
          <span class="text-[10px] text-slate-400 dark:text-slate-500">Last 7 days</span>
        </div>
        <div class="flex items-end gap-2 h-32">
          <div v-for="(day, di) in trendData" :key="di" class="flex-1 flex flex-col items-center gap-1 group">
            <span class="text-[9px] font-bold text-slate-500 dark:text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity">{{ day.count }}</span>
            <div class="w-full rounded-t-lg transition-all duration-700 ease-out group-hover:opacity-80"
              :class="['bg-gradient-to-t', di === trendData.length - 1 ? 'from-indigo-500 to-violet-400' : 'from-indigo-300 to-violet-300 dark:from-indigo-500/60 dark:to-violet-500/60']"
              :style="{ height: (day.height || 4) + '%', minHeight: '4px' }"></div>
            <span class="text-[9px] text-slate-400 dark:text-slate-500 font-medium">{{ day.date }}</span>
          </div>
        </div>
      </section>
    </div>

    <!-- Middle Row -->
    <div class="grid grid-cols-[1.4fr_1fr] gap-4 mb-6">
      <!-- Review Pressure -->
      <section class="glass rounded-2xl overflow-hidden anim-enter-left" data-delay="100">
        <div class="px-5 py-4 border-b border-gray-100/60 dark:border-slate-700/40 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <div class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse"></div>
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Review Pressure</h3>
          </div>
          <button @click="go('/review')" class="text-xs font-semibold text-indigo-500 hover:text-indigo-700 transition-colors">Open Queue →</button>
        </div>
        <div class="p-5">
          <div class="grid grid-cols-3 gap-3 mb-5">
            <div v-for="(m, i) in reviewMetrics" :key="i" class="rounded-xl bg-slate-50 dark:bg-slate-800/50 p-3.5 border border-slate-100 dark:border-slate-700/40 hover-lift anim-enter" :data-delay="200 + i * 60">
              <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">{{ m.label }}</p>
              <p class="text-xl font-extrabold text-slate-900 dark:text-white mt-1">{{ m.value }}</p>
            </div>
          </div>
          <div class="space-y-2.5">
            <template v-if="reviewQueue.length > 0">
            <div
              v-for="(item, i) in reviewQueue" :key="i"
              class="rounded-xl border border-slate-200/60 dark:border-slate-700/40 px-4 py-3 flex items-center gap-3 hover:bg-indigo-50/40 dark:hover:bg-indigo-500/10 hover:border-indigo-200 transition-all duration-300 cursor-pointer group anim-enter"
              :data-delay="350 + i * 60"
              @click="go('/preview/' + item.id)"
            >
              <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-50 to-violet-50 dark:from-indigo-500/10 dark:to-violet-500/10 text-indigo-500 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform duration-300">
                <i class="ri-file-line text-lg"></i>
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate">{{ item.file }}</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ item.group }} · {{ item.reason }}</p>
              </div>
              <div class="shrink-0">
                <div class="w-12 h-12 rounded-xl flex flex-col items-center justify-center" :class="item.confidence < 70 ? 'bg-red-50 dark:bg-red-500/15 text-red-600 dark:text-red-400' : 'bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400'">
                  <span class="text-sm font-extrabold leading-none">{{ item.confidence }}%</span>
                  <span class="text-[8px] font-medium mt-0.5">conf.</span>
                </div>
              </div>
            </div>
            </template>
            <div v-else class="py-6 text-center">
              <i class="ri-inbox-line text-2xl text-slate-300 dark:text-slate-600"></i>
              <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">No items pending review</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Pipeline + Alerts -->
      <div class="flex flex-col gap-4">
        <section class="glass rounded-2xl p-5 flex-1 anim-enter-right" data-delay="100">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-4">Pipeline Throughput</h3>
          <div class="space-y-3.5">
            <div v-for="(stage, i) in stages" :key="i" class="anim-enter" :data-delay="250 + i * 60">
              <div class="flex justify-between text-[11px] mb-1.5">
                <span class="text-slate-600 dark:text-slate-300 font-medium">{{ stage.name }}</span>
                <span class="font-bold text-slate-800 dark:text-slate-100">{{ stage.percent }}%</span>
              </div>
              <div class="h-2.5 rounded-full bg-slate-100 dark:bg-slate-700/50 overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-1000 ease-out"
                  :class="stage.barClass"
                  :style="{ width: stage.percent + '%' }"
                ></div>
              </div>
            </div>
          </div>
        </section>

        <section class="rounded-2xl p-4 border border-amber-200/60 dark:border-amber-500/20 bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-950/40 dark:to-orange-950/40 anim-enter-right" data-delay="300">
          <div class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-500/20 text-amber-600 flex items-center justify-center shrink-0 float">
              <i class="ri-alert-line text-lg"></i>
            </div>
            <div>
              <p class="text-xs font-bold text-amber-800 dark:text-amber-300">Capacity Alert</p>
              <p class="text-[11px] text-amber-700 dark:text-amber-200 leading-relaxed mt-0.5">Human review queue is at {{ props.reviewCapacityPct || 0 }}% capacity ({{ props.pendingReview || 0 }} pending). {{ (props.reviewCapacityPct || 0) > 70 ? 'Consider assigning more reviewers.' : 'Queue is healthy.' }}</p>
            </div>
          </div>
        </section>
      </div>
    </div>

    <!-- Bottom Row -->
    <div class="grid grid-cols-2 gap-4">
      <!-- Activity -->
      <section class="glass rounded-2xl overflow-hidden anim-enter-left" data-delay="200">
        <div class="px-5 py-4 border-b border-gray-100/60 dark:border-slate-700/40 flex items-center justify-between">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Live Feed</h3>
          <span class="text-[10px] text-slate-400 dark:text-slate-500">Auto-updating</span>
        </div>
        <div class="divide-y divide-slate-100/60 dark:divide-slate-700/40">
          <template v-if="events.length > 0">
          <div
            v-for="(ev, i) in events" :key="i"
            class="px-5 py-3.5 flex gap-3 hover:bg-indigo-50/30 dark:hover:bg-indigo-500/10 transition-colors duration-300 anim-enter"
            :data-delay="350 + i * 60"
          >
            <div :class="['w-2 h-2 rounded-full mt-1.5 shrink-0 ring-4', ev.ring]"></div>
            <div>
              <p class="text-xs text-slate-700 dark:text-slate-200 leading-relaxed" v-html="ev.text"></p>
              <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">{{ ev.time }}</p>
            </div>
          </div>
          </template>
          <div v-else class="px-5 py-8 text-center">
            <i class="ri-pulse-line text-2xl text-slate-300 dark:text-slate-600"></i>
            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">No recent activity</p>
          </div>
        </div>
      </section>

      <!-- Taxonomy Breakdown -->
      <section class="glass rounded-2xl p-5 anim-enter-right" data-delay="200">
        <!-- Service Health Indicators -->
        <div class="mb-5">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">System Health</h3>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div v-for="(svc, si) in serviceHealth" :key="si" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-700/40 hover-lift anim-enter" :data-delay="250 + si * 40">
              <div :class="['w-2 h-2 rounded-full shrink-0', svc.status === 'up' ? 'bg-emerald-400' : svc.status === 'warn' ? 'bg-amber-400 animate-pulse' : 'bg-red-400 animate-pulse']" />
              <i :class="[svc.icon, 'text-sm', svc.status === 'up' ? 'text-emerald-500' : svc.status === 'warn' ? 'text-amber-500' : 'text-red-500']" />
              <span class="text-[11px] font-semibold text-slate-700 dark:text-slate-200">{{ svc.name }}</span>
              <span :class="['ml-auto text-[9px] font-bold px-2 py-0.5 rounded-full', svc.status === 'up' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400' : svc.status === 'warn' ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400' : 'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-400']">
                {{ svc.status === 'up' ? 'Online' : svc.status === 'warn' ? 'Idle' : 'Offline' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Storage Gauge -->
        <div class="mb-5 p-3 rounded-xl border border-slate-100 dark:border-slate-700/40">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Storage</span>
            <span class="text-[10px] font-medium text-slate-400 dark:text-slate-500">{{ formatBytes(storageUsedBytes) }} / {{ formatBytes(storageCapacity) }}</span>
          </div>
          <div class="h-3 rounded-full bg-slate-100 dark:bg-slate-700/50 overflow-hidden">
            <div class="h-full rounded-full transition-all duration-1000 ease-out" :class="storagePct > 85 ? 'bg-gradient-to-r from-red-400 to-rose-500' : storagePct > 60 ? 'bg-gradient-to-r from-amber-400 to-orange-500' : 'bg-gradient-to-r from-indigo-400 to-violet-500'" :style="{ width: storagePct + '%' }" />
          </div>
          <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">{{ storagePct }}% used</p>
        </div>

        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Taxonomy Breakdown</h3>
          <button @click="go('/assets')" class="text-xs font-semibold text-indigo-500 hover:text-indigo-700 transition-colors">Browse →</button>
        </div>
        <div class="space-y-3">
          <template v-if="groups.length > 0">
          <div
            v-for="(g, i) in groups" :key="i"
            class="flex items-center gap-3 group cursor-pointer anim-enter"
            :data-delay="350 + i * 50"
            @click="go('/assets?group=' + encodeURIComponent(g.name))"
          >
            <span class="text-lg group-hover:scale-125 transition-transform duration-300">{{ g.emoji }}</span>
            <div class="flex-1">
              <div class="flex justify-between text-[11px] mb-1">
                <span class="text-slate-600 dark:text-slate-300 font-medium">{{ g.name }}</span>
                <span class="font-bold text-slate-800 dark:text-slate-100">{{ g.share }}%</span>
              </div>
              <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700/50 overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-700 ease-out group-hover:opacity-80"
                  :class="g.barClass"
                  :style="{ width: g.share + '%' }"
                ></div>
              </div>
            </div>
          </div>
          </template>
          <div v-else class="py-6 text-center">
            <i class="ri-node-tree text-2xl text-slate-300 dark:text-slate-600"></i>
            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">No classification data yet</p>
          </div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useScrollReveal } from '@/composables/useAnimations';

useScrollReveal();

function go(path) { router.visit(path); }

const props = defineProps({
  totalAssets: Number,
  aiClassified: Number,
  aiClassifiedPct: Number,
  previewDone: Number,
  previewPct: Number,
  storageUsedBytes: Number,
  storageCapacity: Number,
  pendingReview: Number,
  autoApproved: Number,
  escalations: Number,
  reviewCapacityPct: Number,
  reviewQueue: Array,
  pipelineStages: Array,
  recentActivity: Array,
  taxonomyBreakdown: Array,
  serviceHealth: Array,
  uploadTrend: { type: Array, default: () => [] },
});

function formatBytes(bytes) {
  if (!bytes) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
}

const storagePct = computed(() => {
  const max = props.storageCapacity || (60 * 1024 * 1024 * 1024);
  return Math.min(Math.round((props.storageUsedBytes || 0) / max * 100), 100);
});

const kpis = computed(() => [
  { value: (props.totalAssets || 0).toLocaleString(), label: 'Total Assets', delta: '+' + (props.totalAssets || 0) + '', deltaClass: 'bg-emerald-50 text-emerald-600', progress: Math.min(Math.round((props.totalAssets || 0) / 60), 100), barClass: 'bg-gradient-to-r from-emerald-400 to-emerald-500', icon: 'ri-stack-line', iconBg: 'bg-emerald-50 text-emerald-500' },
  { value: (props.aiClassified || 0).toLocaleString(), label: 'AI Classified', delta: (props.aiClassifiedPct || 0) + '%', deltaClass: 'bg-blue-50 text-blue-600', progress: Math.round(props.aiClassifiedPct || 0), barClass: 'bg-gradient-to-r from-blue-400 to-blue-500', icon: 'ri-brain-line', iconBg: 'bg-blue-50 text-blue-500' },
  { value: (props.previewPct || 0) + '%', label: 'Preview Coverage', delta: (props.previewDone || 0).toLocaleString() + ' done', deltaClass: 'bg-violet-50 text-violet-600', progress: Math.round(props.previewPct || 0), barClass: 'bg-gradient-to-r from-violet-400 to-violet-500', icon: 'ri-eye-line', iconBg: 'bg-violet-50 text-violet-500' },
  { value: formatBytes(props.storageUsedBytes), label: 'Storage Used', delta: storagePct.value + '%', deltaClass: 'bg-amber-50 text-amber-600', progress: storagePct.value, barClass: 'bg-gradient-to-r from-amber-400 to-amber-500', icon: 'ri-hard-drive-3-line', iconBg: 'bg-amber-50 text-amber-500' },
]);

const reviewMetrics = computed(() => [
  { label: 'Pending Review', value: (props.pendingReview || 0).toLocaleString() },
  { label: 'Auto-Approved', value: (props.autoApproved || 0).toLocaleString() },
  { label: 'Escalations', value: (props.escalations || 0).toLocaleString() },
]);

const reviewQueue = computed(() => (props.reviewQueue || []).map(item => ({
  file: item.name,
  group: item.group || 'Unclassified',
  reason: (item.confidence || 0) < 70 ? 'Low confidence' : 'Needs review',
  confidence: Math.round(item.confidence || 0),
})));

const stageBarMap = {
  'Ingestion': 'bg-gradient-to-r from-sky-400 to-sky-500',
  'Hashing': 'bg-gradient-to-r from-violet-400 to-violet-500',
  'Preview': 'bg-gradient-to-r from-indigo-400 to-indigo-500',
  'AI Tagging': 'bg-gradient-to-r from-blue-400 to-blue-500',
  'Classification': 'bg-gradient-to-r from-emerald-400 to-emerald-500',
  'Indexing': 'bg-gradient-to-r from-amber-400 to-amber-500',
  'Complete': 'bg-gradient-to-r from-emerald-400 to-emerald-500',
};

const stages = computed(() => {
  const raw = props.pipelineStages || [];
  const total = raw.reduce((s, r) => s + r.count, 0) || 1;
  return raw.filter(s => s.stage !== 'Complete').slice(0, 4).map(s => ({
    name: s.stage,
    percent: Math.round(s.count / total * 100),
    barClass: stageBarMap[s.stage] || 'bg-gradient-to-r from-slate-400 to-slate-500',
  }));
});

const eventRings = ['ring-emerald-500/20 bg-emerald-500', 'ring-amber-500/20 bg-amber-500', 'ring-blue-500/20 bg-blue-500', 'ring-violet-500/20 bg-violet-500', 'ring-slate-400/20 bg-slate-400'];

const events = computed(() => (props.recentActivity || []).map((ev, i) => ({
  text: `<strong>${ev.causer}</strong> ${ev.description}`,
  time: ev.time,
  ring: eventRings[i % eventRings.length],
})));

const groupEmojis = { Food: '🍎', Media: '📺', Business: '💼', Location: '📍', Nature: '🌿', Lifestyle: '🏃', Specialty: '🎨' };
const groupBars = { Food: 'bg-gradient-to-r from-emerald-400 to-emerald-500', Media: 'bg-gradient-to-r from-blue-400 to-blue-500', Business: 'bg-gradient-to-r from-amber-400 to-amber-500', Location: 'bg-gradient-to-r from-purple-400 to-purple-500', Nature: 'bg-gradient-to-r from-teal-400 to-teal-500', Lifestyle: 'bg-gradient-to-r from-yellow-400 to-yellow-500', Specialty: 'bg-gradient-to-r from-pink-400 to-pink-500' };

const serviceHealth = computed(() => props.serviceHealth || []);
const storageUsedBytes = computed(() => props.storageUsedBytes || 0);
const storageCapacity = computed(() => props.storageCapacity || (60 * 1024 * 1024 * 1024));

const groups = computed(() => (props.taxonomyBreakdown || []).map(g => ({
  emoji: groupEmojis[g.group] || '📁',
  name: g.group,
  share: g.pct,
  barClass: groupBars[g.group] || 'bg-gradient-to-r from-slate-400 to-slate-500',
})));

// ── Donut Chart (REQ-15) ─────────────────────────────
const donutColors = { Food: '#10b981', Media: '#3b82f6', Business: '#f59e0b', Location: '#8b5cf6', Nature: '#14b8a6', Lifestyle: '#eab308', Specialty: '#ec4899' };

const donutSegments = computed(() => {
  const data = props.taxonomyBreakdown || [];
  if (!data.length) return [];
  const circumference = 2 * Math.PI * 50; // r=50
  let cumulativeOffset = circumference * 0.25; // start at 12 o'clock
  return data.map(g => {
    const fraction = (g.pct || 0) / 100;
    const dash = fraction * circumference;
    const gap = circumference - dash;
    const offset = cumulativeOffset;
    cumulativeOffset -= dash;
    return {
      name: g.group,
      pct: g.pct,
      color: donutColors[g.group] || '#94a3b8',
      dash: `${dash} ${gap}`,
      offset: offset,
    };
  });
});

// ── Upload Trend (REQ-15) ────────────────────────────
const trendData = computed(() => {
  const raw = props.uploadTrend || [];
  const maxCount = Math.max(...raw.map(d => d.count), 1);
  return raw.map(d => ({
    date: d.date,
    count: d.count,
    height: Math.max(Math.round((d.count / maxCount) * 100), 4),
  }));
});
</script>
