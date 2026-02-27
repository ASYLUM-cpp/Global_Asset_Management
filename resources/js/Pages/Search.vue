<template>
  <AppLayout>
    <!-- Header -->
    <div class="mb-6 anim-enter">
      <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Search Assets</h1>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Find assets by filename, description, tags, or group</p>
    </div>

    <!-- Search Bar -->
    <div class="glass rounded-2xl p-5 mb-5 anim-enter" data-delay="60">
      <div class="flex items-center gap-3">
        <div class="relative flex-1">
          <i class="ri-search-line absolute left-4 top-1/2 -translate-y-1/2 text-lg text-slate-400"></i>
          <input
            ref="searchInput"
            v-model="searchQuery"
            type="text"
            placeholder="Search by filename, description, tags, or group…"
            @keydown.enter="doSearch"
            class="w-full text-sm pl-11 pr-4 py-3.5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-300 transition-all"
          />
        </div>
        <button @click="doSearch" class="px-6 py-3.5 rounded-2xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-sm font-semibold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
          <i class="ri-search-line mr-1.5"></i> Search
        </button>
      </div>
      <!-- Quick filters -->
      <div class="flex items-center gap-2 mt-3 flex-wrap">
        <span class="text-[10px] text-slate-400 dark:text-slate-500 font-bold uppercase tracking-widest mr-1">Quick:</span>
        <span v-for="(tag, i) in popularTagNames" :key="i"
          :class="['text-[10px] px-2.5 py-1 rounded-full border cursor-pointer transition-all duration-300',
            activeTag === tag
              ? 'bg-indigo-500 text-white border-indigo-500'
              : 'border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:text-indigo-600 hover:border-indigo-200']"
          @click="toggleTag(tag)"
        >{{ tag }}</span>
      </div>
    </div>

    <div class="grid grid-cols-[220px_1fr] gap-5">
      <!-- Faceted Sidebar -->
      <aside class="glass rounded-2xl p-5 h-fit sticky top-24 anim-enter-left">
        <h3 class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold mb-3">Refine Results</h3>

        <!-- Extension facet -->
        <div class="mb-5">
          <p class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold mb-2.5">File Type</p>
          <div class="space-y-1.5">
            <label v-for="ext in extensionFacets" :key="ext.label"
              class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl cursor-pointer hover:bg-indigo-50/50 dark:hover:bg-indigo-500/10 transition-all group"
              @click.prevent="applyFilter('extension', ext.value)"
            >
              <input type="checkbox" :checked="currentFilters.extension === ext.value" class="w-3.5 h-3.5 rounded border-slate-300 text-indigo-500 focus:ring-indigo-500/30 transition" />
              <span class="text-xs text-slate-600 dark:text-slate-300 group-hover:text-slate-800 dark:group-hover:text-slate-100 transition-colors">{{ ext.label }}</span>
              <span class="ml-auto text-[10px] font-medium text-slate-400 dark:text-slate-500 bg-slate-100 dark:bg-slate-700/50 px-1.5 py-0.5 rounded-full">{{ ext.count }}</span>
            </label>
          </div>
        </div>

        <!-- Status facet -->
        <div class="mb-5">
          <p class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold mb-2.5">Status</p>
          <div class="space-y-1.5">
            <label v-for="s in statusFacets" :key="s.value"
              class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl cursor-pointer hover:bg-indigo-50/50 dark:hover:bg-indigo-500/10 transition-all group"
              @click.prevent="applyFilter('status', s.value)"
            >
              <input type="checkbox" :checked="currentFilters.status === s.value" class="w-3.5 h-3.5 rounded border-slate-300 text-indigo-500 focus:ring-indigo-500/30 transition" />
              <span class="text-xs text-slate-600 dark:text-slate-300 group-hover:text-slate-800 dark:group-hover:text-slate-100 transition-colors">{{ s.label }}</span>
              <span :class="['ml-auto text-[10px] font-medium px-1.5 py-0.5 rounded-full', s.badgeClass]">{{ s.count }}</span>
            </label>
          </div>
        </div>

        <!-- Group facet -->
        <div class="mb-5">
          <p class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold mb-2.5">Group</p>
          <div class="space-y-1.5">
            <label v-for="g in groups" :key="g"
              class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl cursor-pointer hover:bg-indigo-50/50 dark:hover:bg-indigo-500/10 transition-all group"
              @click.prevent="applyFilter('group', g)"
            >
              <input type="checkbox" :checked="currentFilters.group === g" class="w-3.5 h-3.5 rounded border-slate-300 text-indigo-500 focus:ring-indigo-500/30 transition" />
              <span class="text-xs text-slate-600 dark:text-slate-300 group-hover:text-slate-800 dark:group-hover:text-slate-100 transition-colors">{{ g }}</span>
            </label>
          </div>
        </div>

        <!-- Sort -->
        <div class="mb-4">
          <p class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold mb-2.5">Sort By</p>
          <select :value="currentFilters.sort || 'relevance'" @change="applyFilter('sort', $event.target.value)" class="w-full text-[11px] border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-2 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all">
            <option value="relevance">Relevance</option>
            <option value="newest">Newest First</option>
            <option value="oldest">Oldest First</option>
            <option value="largest">Largest First</option>
            <option value="smallest">Smallest First</option>
          </select>
        </div>

        <button @click="clearFilters" class="w-full text-[10px] font-semibold text-red-500 hover:text-red-700 transition-colors py-2">
          <i class="ri-filter-off-line mr-1"></i> Clear All Filters
        </button>
      </aside>

      <!-- Results -->
      <div>
        <!-- Results info -->
        <div class="flex items-center justify-between mb-4 anim-enter" data-delay="120">
          <p class="text-xs text-slate-500 dark:text-slate-400">
            <span v-if="currentFilters.search">Results for "<strong class="text-slate-800 dark:text-slate-100">{{ currentFilters.search }}</strong>" — </span>
            <strong class="text-slate-800 dark:text-slate-100">{{ totalCount.toLocaleString() }}</strong> assets found
          </p>
          <div v-if="Object.keys(activeFilterChips).length" class="flex items-center gap-1.5">
            <span v-for="(val, key) in activeFilterChips" :key="key"
              class="text-[10px] px-2.5 py-1 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/20 flex items-center gap-1"
            >
              {{ key }}: {{ val }}
              <button @click="applyFilter(key, val)" class="hover:text-red-500 transition-colors"><i class="ri-close-line text-xs"></i></button>
            </span>
          </div>
        </div>

        <!-- No results -->
        <div v-if="results.length === 0" class="glass rounded-2xl p-12 text-center anim-enter">
          <i class="ri-search-eye-line text-5xl text-slate-300 dark:text-slate-600 mb-3"></i>
          <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">
            {{ currentFilters.search ? 'No results found' : 'Enter a search query to find assets' }}
          </p>
          <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Try different keywords or adjust filters</p>
        </div>

        <!-- Results Grid -->
        <div v-else class="grid grid-cols-3 gap-3.5">
          <div v-for="(asset, i) in results" :key="asset.id || i"
            class="glass rounded-2xl overflow-hidden group cursor-pointer hover-lift anim-enter-scale"
            :data-delay="150 + i * 40"
            @click="router.visit('/preview/' + asset.id)"
          >
            <div class="relative h-36 bg-gradient-to-br overflow-hidden" :class="asset.gradient">
              <div class="absolute inset-0 flex items-center justify-center">
                <i :class="[asset.icon, 'text-4xl text-white/40']"></i>
              </div>
              <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end justify-center pb-3 gap-2">
                <button @click.stop="router.visit('/preview/' + asset.id)" class="w-8 h-8 rounded-lg bg-white/20 backdrop-blur-sm text-white flex items-center justify-center hover:bg-white/40 transition-all"><i class="ri-eye-line text-sm"></i></button>
                <button @click.stop="window.location.href = '/assets/' + asset.id + '/download'" class="w-8 h-8 rounded-lg bg-white/20 backdrop-blur-sm text-white flex items-center justify-center hover:bg-white/40 transition-all"><i class="ri-download-line text-sm"></i></button>
              </div>
              <span :class="['absolute top-2.5 right-2.5 text-[9px] font-bold px-2 py-0.5 rounded-full backdrop-blur-sm', asset.statusClass]">{{ asset.statusLabel }}</span>
            </div>
            <div class="p-3.5">
              <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate mb-0.5">{{ asset.name }}</p>
              <div class="flex items-center justify-between">
                <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ asset.type }} · {{ asset.size }}</p>
                <p class="text-[9px] text-slate-400 dark:text-slate-500">{{ asset.uploaded }}</p>
              </div>
              <p v-if="asset.group" class="text-[9px] text-indigo-500 dark:text-indigo-400 mt-1 font-medium">{{ asset.group }}</p>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="paginationLinks.length > 3" class="flex justify-center mt-8 anim-enter" data-delay="200">
          <div class="flex items-center gap-1.5">
            <button
              v-for="(link, li) in paginationLinks" :key="li"
              :disabled="!link.url"
              @click="goToPage(link.url)"
              :class="['w-9 h-9 rounded-xl text-xs font-semibold flex items-center justify-center transition-all duration-300',
                link.active
                  ? 'bg-indigo-500 text-white shadow-md shadow-indigo-200 dark:shadow-indigo-500/10'
                  : 'border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:border-indigo-300 hover:text-indigo-500',
                !link.url ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer']"
              v-html="link.label"
            ></button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useScrollReveal } from '@/composables/useAnimations';
useScrollReveal();

const props = defineProps({
  assets: Object,
  totalCount: Number,
  filters: Object,
  groups: Array,
  extensions: Array,
  extensionCounts: Object,
  statusCounts: Object,
  popularTags: Object,
});

const searchQuery = ref('');
const searchInput = ref(null);
const activeTag = ref('');

const currentFilters = computed(() => props.filters || {});

onMounted(() => {
  searchQuery.value = currentFilters.value.search || '';
  searchInput.value?.focus();
});

function doSearch() {
  if (!searchQuery.value.trim()) return;
  const params = { ...currentFilters.value, q: searchQuery.value.trim() };
  delete params.search;
  router.get('/search', params, { preserveState: false, preserveScroll: true });
}

function toggleTag(tag) {
  if (activeTag.value === tag) {
    activeTag.value = '';
    searchQuery.value = searchQuery.value.replace(tag, '').trim();
  } else {
    activeTag.value = tag;
    searchQuery.value = tag;
  }
  doSearch();
}

function applyFilter(key, value) {
  const params = { ...currentFilters.value };
  delete params.search;
  if (currentFilters.value.search) params.q = currentFilters.value.search;
  if (params[key] === value) {
    delete params[key];
  } else {
    params[key] = value;
  }
  router.get('/search', params, { preserveState: false, preserveScroll: true });
}

function clearFilters() {
  const q = currentFilters.value.search;
  router.get('/search', q ? { q } : {}, { preserveState: false, preserveScroll: true });
}

const totalCount = computed(() => props.totalCount || 0);

const gradientMap = {
  jpg: 'from-indigo-300 to-violet-400', jpeg: 'from-indigo-300 to-violet-400',
  png: 'from-sky-300 to-blue-400', tiff: 'from-fuchsia-300 to-purple-400',
  svg: 'from-emerald-300 to-teal-400', pdf: 'from-rose-300 to-pink-400',
  mp4: 'from-amber-300 to-orange-400', mov: 'from-lime-300 to-green-400',
  psd: 'from-cyan-300 to-sky-400', default: 'from-slate-300 to-gray-400',
};
const iconMap = {
  jpg: 'ri-image-line', jpeg: 'ri-image-line', png: 'ri-image-line', tiff: 'ri-image-line',
  svg: 'ri-shape-line', pdf: 'ri-file-pdf-line',
  mp4: 'ri-video-line', mov: 'ri-video-line',
  psd: 'ri-artboard-line', default: 'ri-file-line',
};
const statusClassMap = {
  approved: 'bg-emerald-500/80 text-white',
  pending: 'bg-amber-500/80 text-white',
  rejected: 'bg-red-500/80 text-white',
  none: 'bg-blue-500/80 text-white',
};

const results = computed(() => {
  const items = props.assets?.data || props.assets || [];
  return items.map(a => ({
    ...a,
    type: (a.extension || '').toUpperCase(),
    gradient: gradientMap[(a.extension || '').toLowerCase()] || gradientMap.default,
    icon: iconMap[(a.extension || '').toLowerCase()] || iconMap.default,
    statusClass: statusClassMap[a.status] || statusClassMap.none,
    statusLabel: (a.status || 'Processing').charAt(0).toUpperCase() + (a.status || 'processing').slice(1),
  }));
});

const extensionFacets = computed(() => {
  const exts = props.extensions || [];
  const counts = props.extensionCounts || {};
  return exts.map(ext => ({
    label: ext.toUpperCase(),
    value: ext,
    count: counts[ext] ?? 0,
  })).sort((a, b) => b.count - a.count);
});

const statusFacets = computed(() => {
  const counts = props.statusCounts || {};
  return [
    { label: 'Approved', value: 'approved', count: counts.approved ?? 0, badgeClass: 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' },
    { label: 'Pending', value: 'pending', count: counts.pending ?? 0, badgeClass: 'bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400' },
    { label: 'Rejected', value: 'rejected', count: counts.rejected ?? 0, badgeClass: 'bg-red-100 dark:bg-red-500/15 text-red-600 dark:text-red-400' },
  ];
});

const popularTagNames = computed(() => Object.keys(props.popularTags || {}).slice(0, 12));
const groups = computed(() => props.groups || []);
const paginationLinks = computed(() => props.assets?.links || []);

const activeFilterChips = computed(() => {
  const chips = {};
  if (currentFilters.value.extension) chips.extension = currentFilters.value.extension.toUpperCase();
  if (currentFilters.value.status) chips.status = currentFilters.value.status;
  if (currentFilters.value.group) chips.group = currentFilters.value.group;
  return chips;
});

function goToPage(url) {
  if (url) router.visit(url, { preserveState: false, preserveScroll: true });
}
</script>
