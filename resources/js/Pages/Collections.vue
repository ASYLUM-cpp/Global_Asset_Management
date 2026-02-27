<template>
  <AppLayout>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6 anim-enter">
      <div>
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Collections</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Organize assets into themed groups</p>
      </div>
      <button @click="showCreateModal = true" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-xs font-semibold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
        <i class="ri-add-line mr-1"></i> New Collection
      </button>
    </div>

    <!-- Search / Filter Bar -->
    <div class="glass rounded-2xl px-5 py-3.5 mb-5 anim-enter" data-delay="40">
      <div class="flex items-center gap-3">
        <div class="relative flex-1">
          <i class="ri-search-line absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
          <input
            v-model="collectionSearch"
            type="text"
            placeholder="Filter collections by name or description…"
            class="w-full text-sm pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-300 transition-all"
          />
        </div>
        <div class="flex items-center gap-1.5">
          <button v-for="access in ['all', 'public', 'private', 'role-based']" :key="access"
            :class="['text-[11px] px-3 py-1.5 rounded-full font-medium cursor-pointer transition-all duration-300',
              accessFilter === access
                ? 'bg-indigo-500 text-white shadow-md shadow-indigo-200 dark:shadow-indigo-500/10'
                : 'bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-indigo-50 dark:hover:bg-indigo-500/10']"
            @click="accessFilter = access"
          >{{ access === 'all' ? 'All' : access.charAt(0).toUpperCase() + access.slice(1) }}</button>
        </div>
      </div>
    </div>

    <!-- Featured Collection -->
    <div v-if="featured" class="glass rounded-3xl overflow-hidden mb-6 group anim-enter-scale" data-delay="60">
      <div class="relative h-52 bg-gradient-to-br from-indigo-500 via-violet-500 to-purple-600 overflow-hidden">
        <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full bg-white/10 float"></div>
        <div class="absolute -bottom-12 -left-8 w-48 h-48 rounded-full bg-white/10 float" style="animation-delay:-3s"></div>
        <div class="absolute inset-0 flex items-center px-8">
          <div>
            <span class="text-[10px] uppercase tracking-widest text-white/60 font-bold">Featured Collection</span>
            <h2 class="text-2xl font-bold text-white mt-1">{{ featured.name }}</h2>
            <p class="text-sm text-white/70 mt-1 max-w-lg">{{ featured.description || 'No description available.' }}</p>
            <div class="flex items-center gap-4 mt-3">
              <span class="text-[10px] text-white/60"><strong class="text-white">{{ featured.assets_count ?? 0 }}</strong> assets</span>
              <span class="text-[10px] text-white/60"><strong class="text-white">{{ featured.approved_pct ?? 0 }}%</strong> approved</span>
              <span class="text-[10px] text-white/60">Updated <strong class="text-white">{{ featured.updated_ago ?? '—' }}</strong></span>
            </div>
            <button @click="router.visit('/assets?collection=' + featured.id)" class="mt-4 px-5 py-2 rounded-xl bg-white/20 backdrop-blur-sm text-white text-xs font-semibold hover:bg-white/30 transition-all">
              <i class="ri-arrow-right-line mr-1"></i> Open Collection
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Collection Grid -->
    <div v-if="filteredCollections.length === 0" class="glass rounded-2xl p-12 text-center anim-enter" data-delay="120">
      <i class="ri-folders-line text-5xl text-slate-300 dark:text-slate-600 mb-3"></i>
      <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">
        {{ collectionSearch || accessFilter !== 'all' ? 'No matching collections' : 'No collections yet' }}
      </p>
      <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
        {{ collectionSearch || accessFilter !== 'all' ? 'Try a different search or filter.' : 'Create your first collection to organize and share assets.' }}
      </p>
    </div>
    <div v-else class="grid grid-cols-3 gap-4">
      <div v-for="(col, ci) in filteredCollections" :key="ci"
        class="glass rounded-2xl overflow-hidden hover-lift cursor-pointer group anim-enter relative"
        :class="[selectedCollections.has(col.id) ? 'ring-2 ring-red-400/70 shadow-lg shadow-red-100 dark:shadow-red-500/10' : '']"
        :data-delay="120 + ci * 50"
        @click="props.isAdmin && selectedCollections.size > 0 ? toggleSelectCollection(col.id) : router.visit('/assets?collection=' + col.id)"
      >
        <!-- Thumbnail strip -->
        <div class="h-24 flex relative">
          <div v-for="(t, ti) in col.thumbs" :key="ti" :class="['flex-1 bg-gradient-to-br', t]"></div>
          <!-- Admin select checkbox -->
          <label v-if="props.isAdmin"
            class="absolute top-2.5 left-2.5 z-10 opacity-0 group-hover:opacity-100 transition-all duration-200"
            :class="selectedCollections.has(col.id) ? '!opacity-100' : ''"
            @click.stop>
            <input type="checkbox"
              :checked="selectedCollections.has(col.id)"
              @change="toggleSelectCollection(col.id)"
              class="w-4.5 h-4.5 rounded-md border-2 border-white/90 text-red-500 focus:ring-red-500/30 cursor-pointer shadow-lg backdrop-blur-sm transition-all duration-200 hover:scale-110" />
          </label>
        </div>
        <div class="p-4">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-lg">{{ col.emoji }}</span>
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 group-hover:text-indigo-600 transition-colors">{{ col.name }}</h3>
          </div>
          <p class="text-[10px] text-slate-500 dark:text-slate-400 mb-3 line-clamp-2">{{ col.desc }}</p>
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <span class="text-[10px] text-slate-400 dark:text-slate-500"><strong class="text-slate-700 dark:text-slate-200">{{ col.count }}</strong> assets</span>
              <span :class="['text-[9px] font-bold px-2 py-0.5 rounded-full', col.statusClass]">{{ col.status }}</span>
            </div>
            <div class="flex -space-x-1.5">
              <div v-for="(a, ai) in col.avatars" :key="ai" :class="['w-5 h-5 rounded-full border-2 border-white text-[7px] font-bold flex items-center justify-center text-white', a.bg]">{{ a.init }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Collection Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="showCreateModal = false">
      <div class="glass rounded-2xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-4">New Collection</h3>
        <div class="space-y-3">
          <input v-model="newCollection.name" type="text" placeholder="Collection name" class="w-full text-sm border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 bg-white dark:bg-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
          <textarea v-model="newCollection.description" placeholder="Description (optional)" rows="3" class="w-full text-sm border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 bg-white dark:bg-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"></textarea>
          <select v-model="newCollection.access_level" class="w-full text-sm border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 bg-white dark:bg-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
            <option value="public">Public</option>
            <option value="private">Private</option>
            <option value="role-based">Role-based</option>
          </select>
        </div>
        <div class="flex justify-end gap-3 mt-5">
          <button @click="showCreateModal = false" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300">Cancel</button>
          <button @click="createCollection" class="px-5 py-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-xs font-bold shadow-lg hover:-translate-y-0.5 transition-all">Create</button>
        </div>
      </div>
    </div>

    <!-- Admin Floating Delete Bar -->
    <transition name="slide-up">
      <div v-if="props.isAdmin && selectedCollections.size > 0"
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-2xl bg-gradient-to-r from-slate-900 to-slate-800 dark:from-slate-700 dark:to-slate-600 shadow-2xl shadow-slate-900/30 flex items-center gap-4 backdrop-blur-xl border border-slate-700/50">
        <span class="text-sm font-semibold text-white flex items-center">
          <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-500 text-white text-xs font-bold mr-2">{{ selectedCollections.size }}</span>
          collection{{ selectedCollections.size > 1 ? 's' : '' }} selected
        </span>
        <div class="w-px h-6 bg-slate-600"></div>
        <button @click="deleteSelectedCollections" :disabled="deletingCollections"
          class="px-4 py-2 rounded-xl bg-gradient-to-r from-red-500 to-rose-500 text-white text-xs font-bold shadow-lg shadow-red-500/25 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300 disabled:opacity-50">
          <i :class="deletingCollections ? 'ri-loader-4-line ri-spin' : 'ri-delete-bin-line'" class="mr-1"></i>
          {{ deletingCollections ? 'Deleting...' : 'Delete Selected' }}
        </button>
        <button @click="clearCollectionSelection"
          class="px-3 py-2 rounded-xl text-slate-400 hover:text-white text-xs font-semibold transition-colors">
          Clear
        </button>
      </div>
    </transition>
  </AppLayout>
</template>

<script setup>
import { computed, ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useScrollReveal } from '@/composables/useAnimations';
useScrollReveal();

const props = defineProps({
  collections: Array,
  featured: Object,
  isAdmin: Boolean,
});

const showCreateModal = ref(false);
const newCollection = reactive({ name: '', description: '', access_level: 'public' });
const collectionSearch = ref('');
const accessFilter = ref('all');

function createCollection() {
  router.post('/collections', newCollection, {
    preserveScroll: true,
    onSuccess: () => { showCreateModal.value = false; },
  });
}

function deleteCollection(id) {
  if (confirm('Are you sure you want to delete this collection?')) {
    router.delete('/collections/' + id, { preserveScroll: true });
  }
}

const emojis = ['🎨', '📦', '📱', '🍂', '📸', '🎬', '🖨️', '📁', '🤝', '🏖️', '📊', '🎵'];
const thumbSets = [
  ['from-indigo-300 to-violet-400', 'from-sky-300 to-blue-400', 'from-violet-300 to-purple-400'],
  ['from-amber-300 to-orange-400', 'from-emerald-300 to-teal-400', 'from-rose-300 to-pink-400'],
  ['from-pink-300 to-rose-400', 'from-fuchsia-300 to-purple-400', 'from-indigo-300 to-blue-400'],
  ['from-orange-300 to-red-400', 'from-amber-300 to-yellow-400', 'from-emerald-300 to-green-400'],
  ['from-sky-300 to-cyan-400', 'from-blue-300 to-indigo-400', 'from-violet-300 to-blue-400'],
  ['from-purple-300 to-indigo-400', 'from-rose-300 to-red-400', 'from-amber-300 to-orange-400'],
  ['from-teal-300 to-emerald-400', 'from-lime-300 to-green-400', 'from-cyan-300 to-sky-400'],
  ['from-slate-300 to-gray-400', 'from-zinc-300 to-slate-400', 'from-gray-300 to-zinc-400'],
  ['from-indigo-300 to-sky-400', 'from-emerald-300 to-cyan-400', 'from-violet-300 to-indigo-400'],
];
const avatarBgs = ['bg-indigo-500', 'bg-emerald-500', 'bg-rose-500', 'bg-amber-500', 'bg-sky-500', 'bg-pink-500'];

const collections = computed(() => (props.collections || []).map((c, i) => ({
  ...c,
  emoji: emojis[i % emojis.length],
  name: c.name,
  desc: c.description || 'No description',
  count: c.assetCount || 0,
  status: c.access === 'public' ? 'Active' : c.access === 'private' ? 'Private' : 'Draft',
  statusClass: c.access === 'public' ? 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : c.access === 'private' ? 'bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400' : 'bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400',
  thumbs: thumbSets[i % thumbSets.length],
  avatars: c.creator ? [{ init: c.creator.split(' ').map(n => n[0]).join('').slice(0, 2), bg: avatarBgs[i % avatarBgs.length] }] : [],
})));

const filteredCollections = computed(() => {
  let result = collections.value;
  const q = collectionSearch.value.trim().toLowerCase();
  if (q) {
    result = result.filter(c =>
      c.name.toLowerCase().includes(q) || c.desc.toLowerCase().includes(q)
    );
  }
  if (accessFilter.value !== 'all') {
    result = result.filter(c => c.access === accessFilter.value);
  }
  return result;
});

// ── Admin multi-select delete ──────────────────────────
const selectedCollections = ref(new Set());
const deletingCollections = ref(false);

const toggleSelectCollection = (id) => {
  if (selectedCollections.value.has(id)) {
    selectedCollections.value.delete(id);
  } else {
    selectedCollections.value.add(id);
  }
  selectedCollections.value = new Set(selectedCollections.value);
};

const deleteSelectedCollections = async () => {
  if (!confirm(`Delete ${selectedCollections.value.size} collection(s)? This cannot be undone.`)) return;
  deletingCollections.value = true;
  try {
    await axios.post('/collections/bulk-delete', { ids: [...selectedCollections.value] });
    selectedCollections.value = new Set();
    router.reload({ preserveScroll: true });
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to delete some collections.');
  } finally {
    deletingCollections.value = false;
  }
};

const clearCollectionSelection = () => { selectedCollections.value = new Set(); };
</script>

<style scoped>
.slide-up-enter-active { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
.slide-up-leave-active { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.slide-up-enter-from, .slide-up-leave-to { opacity: 0; transform: translateY(20px) translateX(-50%); }
.slide-up-enter-to, .slide-up-leave-from { opacity: 1; transform: translateY(0) translateX(-50%); }
</style>