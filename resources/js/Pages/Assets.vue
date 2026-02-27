<template>
  <AppLayout>
    <div class="grid grid-cols-[250px_1fr] gap-5">
      <!-- Filter Sidebar -->
      <aside class="glass rounded-2xl p-5 h-fit sticky top-24 anim-enter-left">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-4">Filters</h3>
        <div v-for="(group, gi) in filterGroups" :key="gi" class="mb-5 last:mb-0">
          <p class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold mb-2.5">{{ group.title }}</p>
          <div class="space-y-1.5">
            <label
              v-for="(opt, oi) in group.options" :key="oi"
              class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl cursor-pointer hover:bg-indigo-50/50 dark:hover:bg-indigo-500/10 transition-all duration-300 group"
              @click.prevent="opt.action"
            >
              <input type="checkbox" :checked="opt.checked" class="w-3.5 h-3.5 rounded border-slate-300 text-indigo-500 focus:ring-indigo-500/30 transition" />
              <span class="text-xs text-slate-600 dark:text-slate-300 group-hover:text-slate-800 dark:group-hover:text-slate-100 transition-colors">{{ opt.label }}</span>
              <span class="ml-auto text-[10px] text-slate-400 dark:text-slate-500">{{ opt.count }}</span>
            </label>
          </div>
        </div>
        <div class="pt-4 border-t border-slate-100/60 dark:border-slate-700/40 mt-4">
          <p class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold mb-2.5">Tags</p>
          <div class="flex flex-wrap gap-1.5">
            <span
              v-for="(tag, i) in tags" :key="i"
              :class="['text-[10px] px-2.5 py-1 rounded-full border cursor-pointer transition-all duration-300',
                currentFilters.tag === tag
                  ? 'bg-indigo-500 text-white border-indigo-500'
                  : 'border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:text-indigo-600 hover:border-indigo-200']"
              @click="applyFilter('tag', tag)"
            >{{ tag }}</span>
          </div>
        </div>
        <!-- Date Range -->
        <div class="pt-4 border-t border-slate-100/60 dark:border-slate-700/40 mt-4">
          <p class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold mb-2.5">Date Range</p>
          <div class="space-y-2">
            <div>
              <label class="text-[9px] text-slate-400 dark:text-slate-500">From</label>
              <input type="date" :value="currentFilters.date_from || ''" @change="applyFilter('date_from', $event.target.value)" class="w-full text-[11px] border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all" />
            </div>
            <div>
              <label class="text-[9px] text-slate-400 dark:text-slate-500">To</label>
              <input type="date" :value="currentFilters.date_to || ''" @change="applyFilter('date_to', $event.target.value)" class="w-full text-[11px] border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all" />
            </div>
          </div>
        </div>
      </aside>

      <!-- Main Content -->
      <div>
        <!-- Toolbar -->
        <div class="glass rounded-2xl px-5 py-3.5 mb-5 flex items-center justify-between anim-enter" data-delay="80">
          <div class="flex items-center gap-2">
            <span v-for="(chip, i) in groupChips" :key="i"
              :class="['text-[11px] px-3 py-1.5 rounded-full font-medium cursor-pointer transition-all duration-300',
                chip.active
                  ? 'bg-indigo-500 text-white shadow-md shadow-indigo-200 dark:shadow-indigo-500/10'
                  : 'bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:text-indigo-600']"
              @click="chip.action"
            >{{ chip.label }}</span>
          </div>
          <div class="flex items-center gap-2">
            <select ref="sortSelect" v-model="sortValue" @change="applySort" class="text-xs text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 bg-white dark:bg-slate-800 appearance-auto focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition">
              <option value="newest" selected>Newest First</option>
              <option value="oldest">Oldest First</option>
              <option value="name">Name A–Z</option>
              <option value="size">Size</option>
            </select>
            <div class="flex rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
              <button :class="['px-3 py-2 text-xs transition-all', viewMode === 'grid' ? 'bg-indigo-500 text-white' : 'bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700']" @click="viewMode = 'grid'"><i class="ri-grid-fill"></i></button>
              <button :class="['px-3 py-2 text-xs transition-all', viewMode === 'list' ? 'bg-indigo-500 text-white' : 'bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700']" @click="viewMode = 'list'"><i class="ri-list-check"></i></button>
            </div>
          </div>
        </div>

        <!-- Info bar -->
        <div class="flex items-center justify-between mb-4 anim-enter" data-delay="120">
          <p class="text-xs text-slate-500 dark:text-slate-400">Showing <strong class="text-slate-800 dark:text-slate-100">{{ displayedAssets.length }}</strong> of <strong class="text-slate-800 dark:text-slate-100">{{ (props.totalCount || 0).toLocaleString() }}</strong> assets</p>
        </div>

        <!-- Asset Grid -->
        <div v-if="displayedAssets.length === 0" class="glass rounded-2xl p-12 text-center anim-enter">
          <i class="ri-image-line text-5xl text-slate-300 dark:text-slate-600 mb-3"></i>
          <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">No assets found</p>
          <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Upload your first assets or adjust filters to see results.</p>
        </div>
        <!-- Grid View -->
        <div v-else-if="viewMode === 'grid'" class="grid grid-cols-4 gap-3.5">
          <div
            v-for="(asset, i) in displayedAssets" :key="asset.id || i"
            class="glass rounded-2xl overflow-hidden group cursor-pointer hover-lift anim-enter-scale relative"
            :class="[selectedAssets.has(asset.id) ? 'ring-2 ring-red-400/70 shadow-lg shadow-red-100 dark:shadow-red-500/10' : '']"
            :data-delay="150 + i * 40"
            @click="props.isAdmin && selectedAssets.size > 0 ? toggleSelectAsset(asset.id) : router.visit('/preview/' + asset.id)"
          >
            <div class="relative h-36 bg-gradient-to-br overflow-hidden" :class="asset.gradient">
              <!-- Thumbnail image (shown when available) -->
              <img v-if="asset.thumbnailUrl" :src="asset.thumbnailUrl" :alt="asset.name" class="absolute inset-0 w-full h-full object-cover" loading="lazy" @error="$event.target.style.display='none'" />
              <!-- Fallback icon (shown when no thumbnail) -->
              <div v-else class="absolute inset-0 flex items-center justify-center">
                <i :class="[asset.icon, 'text-4xl text-white/40']"></i>
              </div>
              <!-- Admin select checkbox -->
              <label v-if="props.isAdmin"
                class="absolute top-2.5 left-2.5 z-10 opacity-0 group-hover:opacity-100 transition-all duration-200"
                :class="selectedAssets.has(asset.id) ? '!opacity-100' : ''"
                @click.stop>
                <input type="checkbox"
                  :checked="selectedAssets.has(asset.id)"
                  @change="toggleSelectAsset(asset.id)"
                  class="w-4.5 h-4.5 rounded-md border-2 border-white/90 text-red-500 focus:ring-red-500/30 cursor-pointer shadow-lg backdrop-blur-sm transition-all duration-200 hover:scale-110" />
              </label>
              <!-- Hover overlay -->
              <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end justify-center pb-3 gap-2">
                <button @click.stop="router.visit('/preview/' + asset.id)" class="w-8 h-8 rounded-lg bg-white/20 backdrop-blur-sm text-white flex items-center justify-center hover:bg-white/40 transition-all" title="Preview"><i class="ri-eye-line text-sm"></i></button>
                <a :href="'/assets/' + asset.id + '/download'" @click.stop class="w-8 h-8 rounded-lg bg-white/20 backdrop-blur-sm text-white flex items-center justify-center hover:bg-white/40 transition-all" title="Download"><i class="ri-download-line text-sm"></i></a>
                <button @click.stop="copyLink(asset.id)" class="w-8 h-8 rounded-lg bg-white/20 backdrop-blur-sm text-white flex items-center justify-center hover:bg-white/40 transition-all" title="Copy link"><i class="ri-link text-sm"></i></button>
              </div>
              <span :class="['absolute top-2.5 right-2.5 text-[9px] font-bold px-2 py-0.5 rounded-full backdrop-blur-sm', asset.statusClass]">{{ asset.statusLabel }}</span>
            </div>
            <div class="p-3.5">
              <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate mb-0.5">{{ asset.name }}</p>
              <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ asset.type }} · {{ asset.size }}</p>
            </div>
          </div>
        </div>

        <!-- List View -->
        <div v-else class="space-y-2">
          <div
            v-for="(asset, i) in displayedAssets" :key="asset.id || i"
            class="glass rounded-xl px-4 py-3 flex items-center gap-4 group cursor-pointer hover-lift anim-enter relative"
            :class="[selectedAssets.has(asset.id) ? 'ring-2 ring-red-400/70 shadow-lg shadow-red-100 dark:shadow-red-500/10' : '']"
            :data-delay="150 + i * 25"
            @click="props.isAdmin && selectedAssets.size > 0 ? toggleSelectAsset(asset.id) : router.visit('/preview/' + asset.id)"
          >
            <!-- Admin checkbox -->
            <label v-if="props.isAdmin" @click.stop>
              <input type="checkbox"
                :checked="selectedAssets.has(asset.id)"
                @change="toggleSelectAsset(asset.id)"
                class="w-4 h-4 rounded border-slate-300 text-red-500 focus:ring-red-500/30 cursor-pointer transition" />
            </label>
            <!-- Thumbnail -->
            <div v-if="asset.thumbnailUrl" class="w-10 h-10 rounded-lg overflow-hidden shrink-0 bg-gradient-to-br" :class="asset.gradient">
              <img :src="asset.thumbnailUrl" :alt="asset.name" class="w-full h-full object-cover" loading="lazy" @error="$event.target.style.display='none'" />
            </div>
            <!-- Fallback Icon -->
            <div v-else class="w-10 h-10 rounded-lg bg-gradient-to-br flex items-center justify-center shrink-0" :class="asset.gradient">
              <i :class="[asset.icon, 'text-lg text-white/70']"></i>
            </div>
            <!-- Info -->
            <div class="flex-1 min-w-0">
              <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate">{{ asset.name }}</p>
              <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ asset.type }} · {{ asset.size }}</p>
            </div>
            <!-- Tags -->
            <div class="hidden md:flex items-center gap-1 shrink-0">
              <span v-for="tag in (asset.tags || []).slice(0, 3)" :key="tag"
                class="text-[9px] px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-500/20">
                {{ tag }}
              </span>
            </div>
            <!-- Status -->
            <span :class="['text-[9px] font-bold px-2 py-0.5 rounded-full shrink-0', asset.statusClass]">{{ asset.statusLabel }}</span>
            <!-- Actions -->
            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
              <button @click.stop="router.visit('/preview/' + asset.id)" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 flex items-center justify-center hover:bg-indigo-100 hover:text-indigo-500 transition-all" title="Preview"><i class="ri-eye-line text-xs"></i></button>
              <a :href="'/assets/' + asset.id + '/download'" @click.stop class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 flex items-center justify-center hover:bg-indigo-100 hover:text-indigo-500 transition-all" title="Download"><i class="ri-download-line text-xs"></i></a>
              <button @click.stop="copyLink(asset.id)" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 flex items-center justify-center hover:bg-indigo-100 hover:text-indigo-500 transition-all" title="Copy link"><i class="ri-link text-xs"></i></button>
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

    <!-- Admin Floating Delete Bar -->
    <transition name="slide-up">
      <div v-if="props.isAdmin && selectedAssets.size > 0"
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-2xl bg-gradient-to-r from-slate-900 to-slate-800 dark:from-slate-700 dark:to-slate-600 shadow-2xl shadow-slate-900/30 flex items-center gap-4 backdrop-blur-xl border border-slate-700/50">
        <span class="text-sm font-semibold text-white flex items-center">
          <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-500 text-white text-xs font-bold mr-2">{{ selectedAssets.size }}</span>
          asset{{ selectedAssets.size > 1 ? 's' : '' }} selected
        </span>
        <div class="w-px h-6 bg-slate-600"></div>
        <button @click="deleteSelected" :disabled="deleting"
          class="px-4 py-2 rounded-xl bg-gradient-to-r from-red-500 to-rose-500 text-white text-xs font-bold shadow-lg shadow-red-500/25 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300 disabled:opacity-50">
          <i :class="deleting ? 'ri-loader-4-line ri-spin' : 'ri-delete-bin-line'" class="mr-1"></i>
          {{ deleting ? 'Deleting...' : 'Delete Selected' }}
        </button>
        <button @click="clearAssetSelection"
          class="px-3 py-2 rounded-xl text-slate-400 hover:text-white text-xs font-semibold transition-colors">
          Clear
        </button>
      </div>
    </transition>

    <!-- Copy link toast -->
    <transition name="slide-up">
      <div v-if="copyToast" class="fixed bottom-6 right-6 z-50 px-4 py-2.5 rounded-xl bg-slate-800 dark:bg-slate-700 text-white text-xs font-medium shadow-xl flex items-center gap-2">
        <i class="ri-check-line text-emerald-400"></i> Link copied to clipboard
      </div>
    </transition>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useScrollReveal } from '@/composables/useAnimations';
useScrollReveal();

const props = defineProps({
  assets: Object,      // paginated
  totalCount: Number,
  filters: Object,
  groups: Array,
  extensions: Array,
  extensionCounts: Object,
  statusCounts: Object,
  popularTags: Object,
  isAdmin: Boolean,
});

const viewMode = ref('grid');
const sortSelect = ref(null);

// ── Current filter state derived from URL props ──
const currentFilters = computed(() => props.filters || {});

const sortValue = ref(props.filters?.sort || 'newest');

// Force the native select element to show the correct value on mount
onMounted(() => {
  if (sortSelect.value) {
    sortSelect.value.value = sortValue.value;
  }
});

function applyFilter(key, value) {
  const params = { ...currentFilters.value };
  if (params[key] === value) {
    delete params[key]; // toggle off
  } else {
    params[key] = value;
  }
  router.get('/assets', params, { preserveState: false, preserveScroll: true });
}

function applySort() {
  const params = { ...currentFilters.value, sort: sortValue.value };
  router.get('/assets', params, { preserveState: false, preserveScroll: true });
}

function clearFilters() {
  router.get('/assets', {}, { preserveState: false, preserveScroll: true });
}

// ── Copy link helper with toast ──
const copyToast = ref(false);
function copyLink(assetId) {
  const url = window.location.origin + '/preview/' + assetId;
  navigator.clipboard.writeText(url).then(() => {
    copyToast.value = true;
    setTimeout(() => { copyToast.value = false; }, 2000);
  });
}

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

const displayedAssets = computed(() => {
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

const filterGroups = computed(() => [
  { title: 'File Type', options: (props.extensions || []).map(ext => ({
    label: ext.toUpperCase(),
    count: props.extensionCounts?.[ext] ?? '',
    checked: currentFilters.value.extension === ext,
    action: () => applyFilter('extension', ext),
  }))},
  { title: 'Status', options: [
    { label: 'Approved', count: props.statusCounts?.approved ?? '', checked: currentFilters.value.status === 'approved', action: () => applyFilter('status', 'approved') },
    { label: 'Pending', count: props.statusCounts?.pending ?? '', checked: currentFilters.value.status === 'pending', action: () => applyFilter('status', 'pending') },
    { label: 'Rejected', count: props.statusCounts?.rejected ?? '', checked: currentFilters.value.status === 'rejected', action: () => applyFilter('status', 'rejected') },
  ]},
]);

const tags = computed(() => Object.keys(props.popularTags || {}));

const groupChips = computed(() => [
  { label: 'All', active: !currentFilters.value.group, action: () => applyFilter('group', currentFilters.value.group) },
  ...(props.groups || []).map(g => ({
    label: ({ Food: '🍎', Media: '📺', Business: '💼', Location: '📍', Nature: '🌿', Lifestyle: '🏃', Specialty: '🎨' }[g] || '📁') + ' ' + g,
    active: currentFilters.value.group === g,
    action: () => applyFilter('group', g),
  })),
]);

// Use Inertia pagination
const paginationLinks = computed(() => props.assets?.links || []);
function goToPage(url) {
  if (url) router.visit(url, { preserveState: false, preserveScroll: true });
}

// ── Admin multi-select delete ──────────────────────────
const selectedAssets = ref(new Set());
const deleting = ref(false);

const toggleSelectAsset = (id) => {
  if (selectedAssets.value.has(id)) {
    selectedAssets.value.delete(id);
  } else {
    selectedAssets.value.add(id);
  }
  selectedAssets.value = new Set(selectedAssets.value);
};

const deleteSelected = async () => {
  if (!confirm(`Delete ${selectedAssets.value.size} asset(s)? This action uses soft-delete.`)) return;
  deleting.value = true;
  try {
    await axios.post('/assets/bulk-delete', { ids: [...selectedAssets.value] });
    selectedAssets.value = new Set();
    router.reload({ preserveScroll: true });
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to delete some assets.');
  } finally {
    deleting.value = false;
  }
};

const clearAssetSelection = () => { selectedAssets.value = new Set(); };

// Re-apply 'visible' class after Vue's DOM patch overwrites it.
// Vue's :class binding strips imperatively-added classes (like 'visible'
// from useScrollReveal's IntersectionObserver) whenever it patches the DOM.
function forceVisible() {
  nextTick(() => {
    document.querySelectorAll('.anim-enter-scale, .anim-enter, .anim-enter-left, .anim-enter-right').forEach(el => {
      el.classList.add('visible');
    });
  });
}
watch(selectedAssets, forceVisible);
watch(viewMode, forceVisible);
</script>

<style scoped>
.slide-up-enter-active { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
.slide-up-leave-active { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.slide-up-enter-from, .slide-up-leave-to { opacity: 0; transform: translateY(20px) translateX(-50%); }
.slide-up-enter-to, .slide-up-leave-from { opacity: 1; transform: translateY(0) translateX(-50%); }
</style>