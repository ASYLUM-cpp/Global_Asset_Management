<template>
  <AppLayout>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6 anim-enter">
      <div>
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Asset Review</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Approve, reject, or flag assets before publication</p>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-[10px] px-3 py-1.5 rounded-full bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400 font-bold">
          <i class="ri-time-line mr-1"></i> {{ props.pendingCount || 0 }} pending
        </span>
        <select v-model="priorityFilter" class="glass rounded-xl px-4 py-2 text-xs font-semibold text-indigo-600 dark:text-indigo-300 border-0 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer">
          <option value="all">All Priorities</option>
          <option value="High">High Priority</option>
          <option value="Medium">Medium Priority</option>
          <option value="Low">Low Priority</option>
        </select>
      </div>
    </div>

    <!-- Split View -->
    <div class="grid grid-cols-[340px_1fr] gap-5">
      <!-- Review Queue List -->
      <div class="glass rounded-2xl p-4 h-[calc(100vh-200px)] overflow-y-auto anim-enter-left">
        <div v-if="filteredReviewItems.length === 0" class="py-12 text-center">
          <i class="ri-checkbox-circle-line text-4xl text-slate-300 dark:text-slate-600 mb-2"></i>
          <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Review queue is empty</p>
          <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">No assets pending review. All caught up!</p>
        </div>
        <div v-else class="space-y-2">
          <div v-for="(item, i) in filteredReviewItems" :key="item.id || i"
            :class="['px-3 py-3 rounded-xl border cursor-pointer transition-all duration-300',
              selectedIdx === i
                ? 'bg-indigo-50/80 dark:bg-indigo-500/10 border-indigo-200 dark:border-indigo-500/30 shadow-md shadow-indigo-50 dark:shadow-indigo-500/5'
                : 'bg-white/50 dark:bg-white/5 border-slate-100 dark:border-slate-700/40 hover:border-indigo-100 hover:shadow-sm']"
            @click="selectedIdx = i"
          >
            <div class="flex items-center gap-2.5">
              <div :class="['w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0', item.thumbBg]">
                <i :class="[item.icon, 'text-lg text-white']"></i>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-[11px] font-semibold text-slate-800 dark:text-slate-100 truncate">{{ item.name }}</p>
                <div class="flex items-center gap-1.5 mt-0.5">
                  <span :class="['text-[9px] font-bold px-1.5 py-0.5 rounded', item.priorityBadge]">{{ item.priority }}</span>
                  <span class="text-[9px] text-slate-400 dark:text-slate-500">{{ item.time }}</span>
                </div>
              </div>
              <div :class="['w-2.5 h-2.5 rounded-full flex-shrink-0', item.dot]"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Review Detail -->
      <div class="space-y-4">
        <template v-if="filteredReviewItems.length > 0">
        <!-- Preview Card -->
        <div class="glass rounded-2xl overflow-hidden anim-enter" data-delay="80">
          <div class="h-64 bg-gradient-to-br relative overflow-hidden" :class="selected.gradient">
            <div class="absolute inset-0 flex items-center justify-center">
              <i :class="[selected.icon, 'text-6xl text-white/30']"></i>
            </div>
            <div class="absolute bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-black/50 to-transparent">
              <p class="text-sm font-bold text-white">{{ selected.name }}</p>
              <p class="text-[10px] text-white/70">{{ selected.type }} · {{ selected.size }} · {{ selected.dimensions }}</p>
            </div>
          </div>
        </div>

        <!-- AI Analysis -->
        <div class="glass rounded-2xl p-5 anim-enter" data-delay="160">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">
              <i class="ri-brain-line mr-1.5 text-indigo-500"></i> AI Analysis
            </h3>
          </div>
          <div class="grid grid-cols-3 gap-3">
            <div v-for="(tag, ti) in selected.aiTags" :key="ti" class="glass rounded-xl p-3 text-center hover-lift relative group">
              <button @click="removeTag(selected.id, tag.tagId)" class="absolute top-1 right-1 hidden group-hover:flex w-4 h-4 rounded-full bg-red-500 text-white items-center justify-center text-[8px] hover:bg-red-600 transition-all" title="Remove tag">
                <i class="ri-close-line"></i>
              </button>
              <p class="text-lg mb-1">{{ tag.emoji }}</p>
              <p class="text-[10px] font-bold text-slate-700 dark:text-slate-200">{{ tag.label }}</p>
              <div class="mt-1.5 h-1 rounded-full bg-slate-100 dark:bg-slate-700/50">
                <div class="h-full rounded-full bg-gradient-to-r from-indigo-400 to-violet-500" :style="{ width: tag.confidence + '%' }"></div>
              </div>
              <p class="text-[9px] text-slate-400 dark:text-slate-500 mt-0.5">{{ tag.confidence }}%</p>
            </div>
          </div>
          <!-- Add tag row -->
          <div class="flex items-center gap-2 mt-3 pt-3 border-t border-slate-100 dark:border-slate-700/40">
            <input v-model="newTagText" type="text" placeholder="Add a tag..." class="flex-1 text-xs border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-1.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all" @keydown.enter="addTag(selected.id)" />
            <button @click="addTag(selected.id)" :disabled="!newTagText.trim()" class="px-3 py-1.5 rounded-lg bg-indigo-500 text-white text-[10px] font-bold hover:-translate-y-0.5 transition-all disabled:opacity-40">
              <i class="ri-add-line mr-0.5"></i> Add
            </button>
          </div>
        </div>

        <!-- Metadata Summary -->
        <div class="glass rounded-2xl p-5 anim-enter" data-delay="240">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3">Metadata</h3>
          <div class="grid grid-cols-2 gap-x-6 gap-y-2">
            <div v-for="(m, mi) in selected.meta" :key="mi" class="flex items-center justify-between py-1.5 border-b border-slate-50 dark:border-slate-800">
              <span class="text-[10px] text-slate-400 dark:text-slate-500">{{ m.key }}</span>
              <span class="text-[10px] font-semibold text-slate-700 dark:text-slate-200">{{ m.value }}</span>
            </div>
          </div>
        </div>

        <!-- Action Bar -->
        <div class="glass rounded-2xl p-4 anim-enter" data-delay="320">
          <!-- Override group dropdown -->
          <div class="flex items-center gap-3 mb-3">
            <label class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold">Override Group</label>
            <select v-model="overrideGroup" class="text-xs border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-1.5 bg-white dark:bg-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
              <option value="">Keep current</option>
              <option v-for="g in groups" :key="g" :value="g">{{ g }}</option>
            </select>
            <button v-if="overrideGroup" @click="overrideAsset" class="px-3 py-1.5 rounded-lg bg-violet-500 text-white text-[10px] font-bold hover:-translate-y-0.5 transition-all">
              <i class="ri-exchange-line mr-1"></i> Apply Override
            </button>
          </div>
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <button @click="flagAsset" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-amber-50 dark:hover:bg-amber-500/10 hover:border-amber-200 dark:hover:border-amber-500/30 transition-all">
                <i class="ri-flag-line mr-1"></i> Flag
              </button>
              <button @click="rejectAsset" class="px-4 py-2 rounded-xl bg-gradient-to-r from-red-500 to-rose-500 text-white text-xs font-semibold shadow-lg shadow-red-100 dark:shadow-red-500/10 hover:-translate-y-0.5 transition-all duration-300">
                <i class="ri-close-line mr-1"></i> Reject
              </button>
            </div>
            <div class="flex items-center gap-2">
              <button @click="approveAsset" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-xs font-bold shadow-lg shadow-emerald-100 dark:shadow-emerald-500/10 hover:-translate-y-0.5 transition-all duration-300 btn-pulse">
                <i class="ri-check-line mr-1"></i> Approve
              </button>
            </div>
          </div>
        </div>
        </template>
        <div v-else class="glass rounded-2xl p-12 text-center">
          <i class="ri-checkbox-circle-line text-5xl text-slate-300 dark:text-slate-600 mb-3"></i>
          <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">No assets to review</p>
          <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">The review queue is empty. Assets will appear here once they go through the processing pipeline.</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useScrollReveal } from '@/composables/useAnimations';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
useScrollReveal();

const overrideGroup = ref('');
const newTagText = ref('');

function approveAsset() {
  const asset = selected.value;
  if (!asset || !asset.id) return;
  router.post(`/review/${asset.id}/approve`, {}, { preserveScroll: true });
}
function rejectAsset() {
  const asset = selected.value;
  if (!asset || !asset.id) return;
  router.post(`/review/${asset.id}/reject`, {}, { preserveScroll: true });
}
function flagAsset() {
  const asset = selected.value;
  if (!asset || !asset.id) return;
  router.post(`/review/${asset.id}/flag`, { reason: 'Flagged by reviewer' }, { preserveScroll: true });
}
function overrideAsset() {
  const asset = selected.value;
  if (!asset || !asset.id || !overrideGroup.value) return;
  router.post(`/review/${asset.id}/override`, { group: overrideGroup.value }, { preserveScroll: true, onSuccess: () => { overrideGroup.value = ''; } });
}

function addTag(assetId) {
  if (!assetId || !newTagText.value.trim()) return;
  router.post(`/assets/${assetId}/tags`, { tag: newTagText.value.trim() }, { preserveScroll: true, onSuccess: () => { newTagText.value = ''; } });
}

function removeTag(assetId, tagId) {
  if (!assetId || !tagId) return;
  if (!confirm('Remove this tag?')) return;
  router.delete(`/assets/${assetId}/tags/${tagId}`, { preserveScroll: true });
}

const props = defineProps({
  pendingAssets: Array,
  pendingCount: Number,
  groups: Array,
});

const selectedIdx = ref(0);
const priorityFilter = ref('all');

const gradientMap = {
  jpg: 'from-indigo-300 to-violet-400', jpeg: 'from-indigo-300 to-violet-400',
  png: 'from-sky-300 to-blue-400', tiff: 'from-emerald-300 to-teal-400',
  pdf: 'from-rose-300 to-pink-400', mp4: 'from-amber-300 to-orange-400',
  mov: 'from-amber-300 to-orange-400', psd: 'from-cyan-300 to-sky-400',
  svg: 'from-emerald-300 to-teal-400', default: 'from-slate-300 to-gray-400',
};
const iconMap = {
  jpg: 'ri-image-line', jpeg: 'ri-image-line', png: 'ri-image-line', tiff: 'ri-image-line',
  svg: 'ri-shape-line', pdf: 'ri-file-pdf-line', mp4: 'ri-video-line', mov: 'ri-video-line',
  psd: 'ri-artboard-line', default: 'ri-file-line',
};
const thumbBgs = [
  'bg-gradient-to-br from-indigo-400 to-violet-500',
  'bg-gradient-to-br from-amber-400 to-orange-500',
  'bg-gradient-to-br from-rose-400 to-pink-500',
  'bg-gradient-to-br from-emerald-400 to-teal-500',
  'bg-gradient-to-br from-sky-400 to-blue-500',
];

const reviewItems = computed(() => (props.pendingAssets || []).map((a, i) => {
  const ext = (a.extension || '').toLowerCase();
  const conf = a.confidence || 0;
  return {
    ...a,
    icon: iconMap[ext] || iconMap.default,
    thumbBg: thumbBgs[i % thumbBgs.length],
    gradient: gradientMap[ext] || gradientMap.default,
    type: ext.toUpperCase(),
    dimensions: '—',
    priority: conf < 60 ? 'High' : conf < 75 ? 'Medium' : 'Low',
    priorityBadge: conf < 60 ? 'bg-red-50 text-red-600' : conf < 75 ? 'bg-amber-50 text-amber-600' : 'bg-slate-100 text-slate-500',
    dot: conf < 60 ? 'bg-red-400' : conf < 75 ? 'bg-amber-400' : 'bg-slate-300',
    time: a.uploadDate || 'Recently',
    aiTags: (a.tags || []).slice(0, 6).map(t => ({
      emoji: '🏷️',
      label: t.tag,
      confidence: t.confidence,
      tagId: t.id,
    })),
    meta: [
      { key: 'Group', value: a.group || 'Unknown' },
      { key: 'Confidence', value: conf + '%' },
      { key: 'Uploader', value: a.uploader || 'Unknown' },
      { key: 'Uploaded', value: a.uploadDate || '—' },
      { key: 'Size', value: a.size || '—' },
      { key: 'Reason', value: a.reason || 'Low confidence' },
    ],
  };
}));

const filteredReviewItems = computed(() => {
  if (priorityFilter.value === 'all') return reviewItems.value;
  return reviewItems.value.filter(item => item.priority === priorityFilter.value);
});

const selected = computed(() => filteredReviewItems.value[selectedIdx.value] || filteredReviewItems.value[0] || {});
</script>
