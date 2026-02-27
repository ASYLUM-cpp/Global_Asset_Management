<template>
  <AppLayout>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6 anim-enter">
      <div>
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Audit Log</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Track all system activities, user actions, and changes</p>
      </div>
      <a :href="exportUrl" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-xs font-semibold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
        <i class="ri-download-line mr-1"></i> Export CSV
      </a>
    </div>

    <!-- KPI Row -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div v-for="(kpi, ki) in kpis" :key="ki" class="glass rounded-2xl p-4 hover-lift anim-enter" :data-delay="ki * 60">
        <div class="flex items-center justify-between mb-2">
          <span class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold">{{ kpi.label }}</span>
          <div :class="['w-8 h-8 rounded-lg flex items-center justify-center', kpi.bg]">
            <i :class="[kpi.icon, 'text-sm text-white']"></i>
          </div>
        </div>
        <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ kpi.value }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="glass rounded-2xl p-4 mb-5 anim-enter" data-delay="200">
      <div class="flex items-end gap-3 flex-wrap">
        <div class="flex-1 min-w-[150px]">
          <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Action</label>
          <input v-model="filters.action" type="text" placeholder="Search actions..." class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all" />
        </div>
        <div class="min-w-[140px]">
          <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">User</label>
          <select v-model="filters.user_id" class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all">
            <option value="">All Users</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div class="min-w-[140px]">
          <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Log Name</label>
          <select v-model="filters.log_name" class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all">
            <option value="">All</option>
            <option v-for="l in logNames" :key="l" :value="l">{{ l }}</option>
          </select>
        </div>
        <div class="min-w-[130px]">
          <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">From</label>
          <input v-model="filters.date_from" type="date" class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all" />
        </div>
        <div class="min-w-[130px]">
          <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">To</label>
          <input v-model="filters.date_to" type="date" class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all" />
        </div>
        <div class="flex gap-2">
          <button @click="applyFilters" class="px-4 py-2.5 rounded-xl bg-indigo-500 text-white text-xs font-semibold hover:bg-indigo-600 transition-all">
            <i class="ri-filter-line mr-1"></i> Filter
          </button>
          <button @click="resetFilters" class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all">
            Clear
          </button>
        </div>
      </div>
    </div>

    <!-- Activity Table -->
    <div class="glass rounded-2xl overflow-hidden anim-enter" data-delay="260">
      <div class="px-5 py-3.5 border-b border-slate-100/60 dark:border-slate-700/40 flex items-center justify-between">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Activity Stream</h3>
        <span class="text-[10px] text-slate-400 dark:text-slate-500">{{ activities.total ?? 0 }} total entries</span>
      </div>

      <div class="divide-y divide-slate-50 dark:divide-slate-800">
        <div v-for="(item, i) in activities.data" :key="item.id"
          class="flex items-center gap-4 px-5 py-3.5 hover:bg-indigo-50/20 dark:hover:bg-indigo-500/10 transition-all duration-200 anim-enter"
          :data-delay="300 + i * 30"
        >
          <!-- Icon -->
          <div :class="['w-9 h-9 rounded-xl flex items-center justify-center shrink-0', actionBg(item.description)]">
            <i :class="[actionIcon(item.description), 'text-sm text-white']"></i>
          </div>

          <!-- Details -->
          <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate">{{ item.description }}</p>
            <p class="text-[10px] text-slate-400 dark:text-slate-500">
              <span class="font-medium text-slate-500 dark:text-slate-300">{{ item.causer }}</span>
              <span v-if="item.subject_type"> &middot; {{ item.subject_type }}</span>
              <span v-if="item.subject_id"> #{{ item.subject_id }}</span>
            </p>
          </div>

          <!-- Log name badge -->
          <span v-if="item.log_name" class="text-[9px] font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-700/50 dark:text-slate-400 shrink-0">{{ item.log_name }}</span>

          <!-- Time -->
          <div class="text-right shrink-0 w-28">
            <p class="text-[10px] font-medium text-slate-500 dark:text-slate-400">{{ item.time }}</p>
            <p class="text-[9px] text-slate-400 dark:text-slate-500">{{ item.date }}</p>
          </div>
        </div>

        <!-- Empty state -->
        <div v-if="!activities.data || activities.data.length === 0" class="flex flex-col items-center justify-center py-16 text-slate-400 dark:text-slate-500">
          <i class="ri-history-line text-4xl mb-2 opacity-30"></i>
          <p class="text-sm font-medium">No activity found</p>
          <p class="text-xs mt-1">Try adjusting your filters</p>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="activities.last_page > 1" class="px-5 py-3.5 border-t border-slate-100/60 dark:border-slate-700/40 flex items-center justify-between">
        <p class="text-[10px] text-slate-400 dark:text-slate-500">
          Showing {{ activities.from }}–{{ activities.to }} of {{ activities.total }}
        </p>
        <div class="flex gap-1">
          <template v-for="link in activities.links" :key="link.label">
            <button
              v-if="link.url"
              @click="goToPage(link.url)"
              :class="['px-3 py-1.5 rounded-lg text-[10px] font-semibold transition-all', link.active ? 'bg-indigo-500 text-white shadow-md shadow-indigo-200 dark:shadow-indigo-500/10' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700/50']"
              v-html="link.label"
            ></button>
            <span v-else class="px-3 py-1.5 text-[10px] text-slate-300 dark:text-slate-600" v-html="link.label"></span>
          </template>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { useScrollReveal } from '@/composables/useAnimations';
useScrollReveal();

const props = defineProps({
  activities: Object,
  logNames: Array,
  users: Array,
  filters: Object,
});

const filters = reactive({
  action:    props.filters?.action ?? '',
  user_id:   props.filters?.user_id ?? '',
  log_name:  props.filters?.log_name ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to:   props.filters?.date_to ?? '',
});

function applyFilters() {
  const params = {};
  if (filters.action)    params.action    = filters.action;
  if (filters.user_id)   params.user_id   = filters.user_id;
  if (filters.log_name)  params.log_name  = filters.log_name;
  if (filters.date_from) params.date_from = filters.date_from;
  if (filters.date_to)   params.date_to   = filters.date_to;
  router.get('/audit-log', params, { preserveState: true, replace: true });
}

function resetFilters() {
  filters.action    = '';
  filters.user_id   = '';
  filters.log_name  = '';
  filters.date_from = '';
  filters.date_to   = '';
  router.get('/audit-log', {}, { preserveState: true, replace: true });
}

function goToPage(url) {
  router.get(url, {}, { preserveState: true, replace: true });
}

// Build export URL with current filters
const exportUrl = computed(() => {
  const params = new URLSearchParams();
  if (filters.action)    params.set('action',    filters.action);
  if (filters.user_id)   params.set('user_id',   filters.user_id);
  if (filters.log_name)  params.set('log_name',  filters.log_name);
  if (filters.date_from) params.set('date_from', filters.date_from);
  if (filters.date_to)   params.set('date_to',   filters.date_to);
  const qs = params.toString();
  return '/audit-log/export' + (qs ? '?' + qs : '');
});

// KPI summaries
const kpis = computed(() => {
  const total = props.activities?.total ?? 0;
  const users = props.users?.length ?? 0;
  const logs  = props.logNames?.length ?? 0;
  const today = (props.activities?.data ?? []).filter(a => a.date === new Date().toISOString().slice(0, 10)).length;
  return [
    { label: 'Total Entries', value: total.toLocaleString(), icon: 'ri-file-list-3-line', bg: 'bg-gradient-to-br from-indigo-400 to-violet-500' },
    { label: 'Active Users', value: users, icon: 'ri-team-line', bg: 'bg-gradient-to-br from-emerald-400 to-teal-500' },
    { label: 'Log Channels', value: logs, icon: 'ri-folder-chart-line', bg: 'bg-gradient-to-br from-amber-400 to-orange-500' },
    { label: 'Today', value: today, icon: 'ri-calendar-check-line', bg: 'bg-gradient-to-br from-sky-400 to-blue-500' },
  ];
});

// Activity icon & color mapping
function actionIcon(desc) {
  if (!desc) return 'ri-history-line';
  const d = desc.toLowerCase();
  if (d.includes('upload'))   return 'ri-upload-2-line';
  if (d.includes('approv'))   return 'ri-check-double-line';
  if (d.includes('reject'))   return 'ri-close-circle-line';
  if (d.includes('delet'))    return 'ri-delete-bin-line';
  if (d.includes('login'))    return 'ri-login-circle-line';
  if (d.includes('register')) return 'ri-user-add-line';
  if (d.includes('tag'))      return 'ri-price-tag-3-line';
  if (d.includes('version'))  return 'ri-git-branch-line';
  if (d.includes('export'))   return 'ri-download-line';
  if (d.includes('password')) return 'ri-lock-line';
  if (d.includes('collection')) return 'ri-folder-add-line';
  if (d.includes('review'))   return 'ri-eye-line';
  return 'ri-history-line';
}

function actionBg(desc) {
  if (!desc) return 'bg-gradient-to-br from-slate-400 to-gray-500';
  const d = desc.toLowerCase();
  if (d.includes('upload'))   return 'bg-gradient-to-br from-indigo-400 to-violet-500';
  if (d.includes('approv'))   return 'bg-gradient-to-br from-emerald-400 to-teal-500';
  if (d.includes('reject'))   return 'bg-gradient-to-br from-rose-400 to-pink-500';
  if (d.includes('delet'))    return 'bg-gradient-to-br from-red-400 to-rose-500';
  if (d.includes('login'))    return 'bg-gradient-to-br from-sky-400 to-blue-500';
  if (d.includes('tag'))      return 'bg-gradient-to-br from-amber-400 to-orange-500';
  if (d.includes('version'))  return 'bg-gradient-to-br from-violet-400 to-purple-500';
  return 'bg-gradient-to-br from-slate-400 to-gray-500';
}
</script>
