<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[210] flex items-center justify-center" @click.self="$emit('close')">
      <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
      <div class="relative w-full max-w-lg mx-4 glass rounded-2xl shadow-2xl overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 dark:border-slate-700/40">
          <div>
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Link an Asset</h3>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">
              {{ context === 'bookstack' ? 'Link a GAM asset to this document' : 'Link a GAM asset to this note' }}
              <span v-if="targetTitle" class="font-semibold text-indigo-500">· {{ targetTitle }}</span>
            </p>
          </div>
          <button @click="$emit('close')" class="w-8 h-8 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center transition-colors">
            <i class="ri-close-line text-lg text-slate-400"></i>
          </button>
        </div>

        <!-- Search -->
        <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-700/40">
          <div class="flex items-center gap-2 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2">
            <i class="ri-search-line text-slate-400"></i>
            <input type="text" v-model="searchQuery" @input="debouncedSearch" placeholder="Search assets by name..." class="flex-1 text-xs bg-transparent border-none outline-none placeholder-slate-400 dark:placeholder-slate-500" />
            <i v-if="searching" class="ri-loader-4-line animate-spin text-indigo-400 text-sm"></i>
          </div>
        </div>

        <!-- Results -->
        <div class="max-h-72 overflow-y-auto px-5 py-3">
          <div v-if="searching && results.length === 0" class="py-8 text-center">
            <i class="ri-loader-4-line animate-spin text-2xl text-indigo-400"></i>
            <p class="text-[11px] text-slate-400 mt-2">Searching...</p>
          </div>
          <div v-else-if="!searching && results.length === 0 && searchQuery.trim()" class="py-8 text-center">
            <i class="ri-inbox-2-line text-3xl text-slate-300 dark:text-slate-600"></i>
            <p class="text-[11px] text-slate-400 mt-2">No matching assets found</p>
          </div>
          <div v-else-if="results.length === 0 && !searchQuery.trim() && !initialLoading" class="py-8 text-center">
            <i class="ri-inbox-2-line text-3xl text-slate-300 dark:text-slate-600"></i>
            <p class="text-[11px] text-slate-400 mt-2">No assets available</p>
          </div>
          <div v-else-if="initialLoading" class="py-8 text-center">
            <i class="ri-loader-4-line animate-spin text-2xl text-indigo-400"></i>
            <p class="text-[11px] text-slate-400 mt-2">Loading assets...</p>
          </div>
          <div v-else class="space-y-2">
            <div v-for="asset in results" :key="asset.id"
              :class="['flex items-center gap-3 px-3 py-2.5 rounded-xl cursor-pointer transition-all duration-200',
                selectedAsset?.id === asset.id
                  ? 'bg-indigo-50 dark:bg-indigo-500/10 ring-2 ring-indigo-500/40'
                  : 'hover:bg-slate-50 dark:hover:bg-slate-800/50']"
              @click="selectedAsset = asset"
            >
              <div v-if="asset.thumbnail" class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-800 overflow-hidden flex-shrink-0">
                <img :src="'/serve/thumbnail/' + asset.id" class="w-full h-full object-cover" :alt="asset.name" />
              </div>
              <div v-else class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center flex-shrink-0">
                <i class="ri-file-line text-slate-400"></i>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-slate-700 dark:text-slate-200 truncate">{{ asset.name }}</p>
                <div class="flex items-center gap-2 mt-0.5">
                  <span class="text-[9px] text-slate-400 uppercase">{{ asset.extension }}</span>
                  <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400">{{ asset.group }}</span>
                </div>
              </div>
              <i v-if="selectedAsset?.id === asset.id" class="ri-checkbox-circle-fill text-indigo-500 text-lg"></i>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-slate-100 dark:border-slate-700/40">
          <button @click="$emit('close')" class="px-4 py-2 rounded-xl text-[11px] font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition-colors">Cancel</button>
          <button @click="linkAsset" :disabled="!selectedAsset || linking"
            class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-[11px] font-bold shadow-md shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:-translate-y-0.5 transition-all duration-300 disabled:opacity-40 disabled:hover:translate-y-0">
            <i v-if="linking" class="ri-loader-4-line animate-spin mr-1"></i>
            {{ linking ? 'Linking...' : 'Link Asset' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  show: { type: Boolean, default: false },
  context: { type: String, required: true },
  targetId: { type: [String, Number], required: true },
  targetTitle: { type: String, default: '' },
});

const emit = defineEmits(['linked', 'close']);

const searchQuery = ref('');
const results = ref([]);
const allAssets = ref([]);
const searching = ref(false);
const initialLoading = ref(false);
const selectedAsset = ref(null);
const linking = ref(false);
let debounceTimer = null;

// Load all assets when modal opens
watch(() => props.show, async (opened) => {
  if (opened) {
    selectedAsset.value = null;
    searchQuery.value = '';
    results.value = [];
    await loadAllAssets();
  }
});

async function loadAllAssets() {
  initialLoading.value = true;
  try {
    const resp = await fetch('/search?q=', {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await resp.json();
    const items = (data.data || data || []).map(mapAsset);
    allAssets.value = items;
    results.value = items;
  } catch {
    allAssets.value = [];
    results.value = [];
  } finally {
    initialLoading.value = false;
  }
}

function mapAsset(a) {
  return {
    id: a.id,
    name: a.name || a.original_filename,
    extension: a.extension || a.file_extension,
    group: a.group || a.group_classification,
    thumbnail: a.thumbnail || a.thumbnail_path,
    size: a.size || a.file_size_formatted,
  };
}

function debouncedSearch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    performSearch();
  }, 300);
}

async function performSearch() {
  const q = searchQuery.value.trim();
  if (!q) {
    // Show all assets when search is cleared
    results.value = allAssets.value;
    return;
  }
  searching.value = true;
  try {
    const resp = await fetch('/search?q=' + encodeURIComponent(q), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await resp.json();
    results.value = (data.data || data || []).map(mapAsset);
  } catch {
    results.value = [];
  } finally {
    searching.value = false;
  }
}

function linkAsset() {
  if (!selectedAsset.value) return;
  linking.value = true;

  const url = props.context === 'bookstack' ? '/documents/link-asset' : '/notes/link-asset';
  const body = props.context === 'bookstack'
    ? { asset_id: selectedAsset.value.id, bookstack_page_id: props.targetId, page_title: props.targetTitle }
    : { asset_id: selectedAsset.value.id, trilium_note_id: props.targetId, note_title: props.targetTitle };

  router.post(url, body, {
    preserveScroll: true,
    onSuccess: () => {
      emit('linked');
      emit('close');
      selectedAsset.value = null;
      searchQuery.value = '';
      results.value = [];
      linking.value = false;
    },
    onError: () => {
      linking.value = false;
    },
  });
}
</script>
