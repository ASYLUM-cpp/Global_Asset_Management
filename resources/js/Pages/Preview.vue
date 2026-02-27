<template>
  <AppLayout>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6 anim-enter">
      <div>
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Asset Preview</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Full detail view with metadata and version history</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="goBack" class="glass rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all"><i class="ri-arrow-left-line mr-1"></i> Back</button>
        <button @click="downloadAsset" class="glass rounded-xl px-3.5 py-2 text-xs font-semibold text-indigo-600 hover:bg-indigo-50/50 dark:hover:bg-indigo-500/10 transition-all"><i class="ri-download-line mr-1"></i> Download</button>
        <button @click="shareAsset" class="px-4 py-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-xs font-semibold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:-translate-y-0.5 transition-all duration-300"><i class="ri-share-line mr-1"></i> Share</button>
      </div>
    </div>

    <div class="grid grid-cols-[1fr_340px] gap-5">
      <!-- Preview Area -->
      <div class="space-y-4">
        <!-- Main image -->
        <div class="glass rounded-3xl overflow-hidden anim-enter-scale">
          <div ref="previewContainer" :class="['relative flex items-center justify-center bg-slate-100 dark:bg-slate-900/50 transition-all duration-300', expanded ? 'h-[600px]' : 'h-96']">
            <!-- Real preview image -->
            <img v-if="previewUrl && !previewError"
              :src="previewUrl"
              :alt="a.name || 'Asset preview'"
              class="max-w-full max-h-full object-contain"
              @error="previewError = true"
            />
            <!-- SVG inline preview -->
            <iframe v-else-if="a.extension?.toLowerCase() === 'svg' && previewUrl"
              :src="previewUrl"
              class="w-full h-full border-0"
            ></iframe>
            <!-- Fallback gradient -->
            <div v-else class="w-full h-full bg-gradient-to-br from-indigo-300 via-violet-300 to-purple-400 flex items-center justify-center">
              <div class="text-center">
                <i :class="[fileTypeIcon, 'text-7xl text-white/30']"></i>
                <p v-if="a.preview === 'unsupported'" class="text-white/50 text-xs mt-2">Preview not available for this format</p>
                <p v-else-if="a.preview === 'failed'" class="text-white/50 text-xs mt-2">Preview generation failed</p>
                <p v-else-if="a.pipeline !== 'done'" class="text-white/50 text-xs mt-2">Processing…</p>
              </div>
            </div>
            <!-- Controls overlay -->
            <div class="absolute top-4 right-4 flex gap-2">
              <button @click="expanded = !expanded" class="w-9 h-9 rounded-xl bg-black/20 backdrop-blur-sm text-white flex items-center justify-center hover:bg-black/40 transition"><i :class="expanded ? 'ri-contract-up-down-line' : 'ri-zoom-in-line'"></i></button>
              <button @click="toggleFullscreen" class="w-9 h-9 rounded-xl bg-black/20 backdrop-blur-sm text-white flex items-center justify-center hover:bg-black/40 transition"><i class="ri-fullscreen-line"></i></button>
            </div>
            <div class="absolute bottom-4 left-4 right-4 flex items-center justify-between">
              <span class="text-[10px] px-3 py-1 rounded-full bg-black/20 backdrop-blur-sm text-white font-semibold">{{ a.extension?.toUpperCase() || '—' }} · {{ a.size || '—' }}</span>
              <span :class="['text-[10px] px-3 py-1 rounded-full font-bold backdrop-blur-sm',
                a.review === 'approved' ? 'bg-emerald-500/80 text-white' :
                a.review === 'rejected' ? 'bg-red-500/80 text-white' :
                'bg-amber-500/80 text-white']">{{ a.review ? a.review.charAt(0).toUpperCase() + a.review.slice(1) : 'Processing' }}</span>
            </div>
          </div>
        </div>

        <!-- Version Thumbnails -->
        <div class="glass rounded-2xl p-4 anim-enter" data-delay="80">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold">Version History</h3>
            <button @click="showVersionUpload = true" class="text-[10px] font-semibold text-indigo-500 hover:text-indigo-700 transition-colors"><i class="ri-upload-2-line mr-0.5"></i> Upload New Version</button>
          </div>
          <div class="flex gap-2.5">
            <div v-for="(ver, vi) in versions" :key="vi"
              :class="['rounded-xl overflow-hidden cursor-pointer transition-all duration-300 flex-shrink-0', vi === 0 ? 'ring-2 ring-indigo-400 shadow-md' : 'opacity-60 hover:opacity-100']"
              @click="activeVersion = vi"
            >
              <div :class="['w-20 h-14 bg-gradient-to-br flex items-center justify-center', ver.gradient]">
                <img v-if="vi === 0 && thumbnailUrl" :src="thumbnailUrl" :alt="'Thumbnail'" class="w-full h-full object-cover" />
                <i v-else class="ri-image-line text-lg text-white/40"></i>
              </div>
              <div class="px-2 py-1 bg-white dark:bg-slate-800">
                <div class="flex items-center justify-between">
                  <p class="text-[9px] font-bold text-slate-700 dark:text-slate-200">{{ ver.label }}</p>
                  <button v-if="vi !== 0 && ver.id" @click.stop="restoreVersion(ver.id)" class="text-[8px] text-indigo-500 hover:text-indigo-700 font-semibold" title="Restore this version">
                    <i class="ri-arrow-go-back-line"></i>
                  </button>
                </div>
                <p class="text-[8px] text-slate-400 dark:text-slate-500">{{ ver.date }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- AI Tags -->
        <div class="glass rounded-2xl p-5 anim-enter" data-delay="160">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3"><i class="ri-brain-line mr-1.5 text-indigo-500"></i> AI-Generated Tags</h3>
          <div class="flex flex-wrap gap-2">
            <span v-for="(tag, ti) in aiTags" :key="ti"
              :class="['text-[10px] px-3 py-1.5 rounded-full border transition-all duration-300 cursor-pointer hover:shadow-md',
                tag.confidence > 90 ? 'bg-indigo-50 border-indigo-200 text-indigo-600 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:border-indigo-500/20 dark:text-indigo-400 dark:hover:bg-indigo-500/15' :
                tag.confidence > 80 ? 'bg-violet-50 border-violet-200 text-violet-600 hover:bg-violet-100 dark:bg-violet-500/10 dark:border-violet-500/20 dark:text-violet-400 dark:hover:bg-violet-500/15' :
                'bg-slate-50 border-slate-200 text-slate-500 hover:bg-slate-100 dark:bg-slate-800/50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-700']"
              @click="router.visit('/assets?tag=' + encodeURIComponent(tag.label))"
            >
              {{ tag.label }} <span class="ml-1 opacity-60">{{ tag.confidence }}%</span>
            </span>
          </div>
        </div>
      </div>

      <!-- Sidebar Info -->
      <div class="space-y-4">
        <!-- File info -->
        <div class="glass rounded-2xl p-5 anim-enter-right" data-delay="80">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">File Information</h3>
            <button @click="openEditMeta" class="text-[10px] font-semibold text-indigo-500 hover:text-indigo-700 transition-colors"><i class="ri-pencil-line mr-0.5"></i> Edit</button>
          </div>
          <div class="space-y-2.5">
            <div v-for="(info, fi) in fileInfo" :key="fi" class="flex justify-between py-1.5 border-b border-slate-50 dark:border-slate-800 last:border-0">
              <span class="text-[10px] text-slate-400 dark:text-slate-500">{{ info.key }}</span>
              <span class="text-[10px] font-semibold text-slate-700 dark:text-slate-200 text-right">{{ info.value }}</span>
            </div>
          </div>
        </div>

        <!-- Collections -->
        <div class="glass rounded-2xl p-5 anim-enter-right" data-delay="160">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3">Collections</h3>
          <div class="space-y-2">
            <div v-for="(col, ci) in assetCollections" :key="ci" class="flex items-center gap-2.5 px-3 py-2 rounded-xl bg-white/50 dark:bg-white/5 border border-slate-100 dark:border-slate-700/40 hover:border-indigo-100 transition-all cursor-pointer" @click="router.visit('/assets?collection=' + encodeURIComponent(col.name))">
              <span class="text-sm">{{ col.emoji }}</span>
              <p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200">{{ col.name }}</p>
            </div>
          </div>
        </div>

        <!-- Activity Timeline -->
        <div class="glass rounded-2xl p-5 anim-enter-right" data-delay="240">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3">Activity</h3>
          <div class="space-y-3">
            <div v-for="(act, ai) in activity" :key="ai" class="flex gap-3 relative">
              <div v-if="ai < activity.length - 1" class="absolute left-[11px] top-6 bottom-0 w-px bg-slate-100 dark:bg-slate-700"></div>
              <div :class="['w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 z-10', act.bg]">
                <i :class="[act.icon, 'text-[10px] text-white']"></i>
              </div>
              <div class="flex-1 pb-3">
                <p class="text-[11px] text-slate-700 dark:text-slate-200"><strong>{{ act.user }}</strong> {{ act.action }}</p>
                <p class="text-[9px] text-slate-400 dark:text-slate-500">{{ act.time }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Metadata Modal -->
    <div v-if="showEditMeta" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="showEditMeta = false">
      <div class="glass rounded-2xl p-6 w-[420px] anim-enter-scale">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-4"><i class="ri-pencil-line mr-1.5 text-indigo-500"></i> Edit Metadata</h3>
        <div class="space-y-3">
          <div>
            <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Description</label>
            <textarea v-model="editMeta.description" rows="3" class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 resize-none" placeholder="Asset description..."></textarea>
          </div>
          <div>
            <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Group / Classification</label>
            <input v-model="editMeta.group" type="text" class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" placeholder="e.g. Branding, Photography..." />
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-5">
          <button @click="showEditMeta = false" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all">Cancel</button>
          <button @click="saveMetadata" :disabled="savingMeta" class="px-5 py-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-xs font-semibold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:-translate-y-0.5 transition-all duration-300 disabled:opacity-50">
            <i :class="savingMeta ? 'ri-loader-4-line ri-spin' : 'ri-save-line'" class="mr-1"></i>
            {{ savingMeta ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Version Upload Modal -->
    <div v-if="showVersionUpload" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="showVersionUpload = false">
      <div class="glass rounded-2xl p-6 w-[420px] anim-enter-scale">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-4"><i class="ri-upload-2-line mr-1.5 text-indigo-500"></i> Upload New Version</h3>
        <div class="space-y-3">
          <div>
            <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">File</label>
            <input type="file" ref="versionFileInput" @change="handleVersionFile" class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
          </div>
          <div>
            <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Change Notes</label>
            <textarea v-model="versionNotes" rows="2" placeholder="What changed in this version..." class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 resize-none"></textarea>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-5">
          <button @click="showVersionUpload = false" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all">Cancel</button>
          <button @click="submitVersionUpload" :disabled="!versionFile || uploadingVersion" class="px-5 py-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-xs font-semibold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:-translate-y-0.5 transition-all duration-300 disabled:opacity-50">
            <i :class="uploadingVersion ? 'ri-loader-4-line ri-spin' : 'ri-upload-2-line'" class="mr-1"></i>
            {{ uploadingVersion ? 'Uploading...' : 'Upload Version' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useScrollReveal } from '@/composables/useAnimations';
useScrollReveal();

const props = defineProps({
  assetId: String,
  asset: Object,
});

const a = computed(() => props.asset || {});
const activeVersion = ref(0);
const expanded = ref(false);
const previewError = ref(false);
const previewContainer = ref(null);

/** URL to serve the medium preview image */
const previewUrl = computed(() => {
  if (!props.assetId) return null;
  if (a.value.preview !== 'done') return null;
  return `/serve/preview/${props.assetId}`;
});

/** URL to serve the thumbnail image */
const thumbnailUrl = computed(() => {
  if (!props.assetId) return null;
  if (a.value.preview !== 'done') return null;
  return `/serve/thumbnail/${props.assetId}`;
});

/** Icon based on file type */
const fileTypeIcon = computed(() => {
  const ext = (a.value.extension || '').toLowerCase();
  const icons = {
    pdf: 'ri-file-pdf-2-line', psd: 'ri-file-psd-2-line',
    ai: 'ri-file-line', eps: 'ri-file-line', svg: 'ri-file-code-line',
    mp4: 'ri-film-line', mov: 'ri-film-line', avi: 'ri-film-line',
    doc: 'ri-file-word-line', docx: 'ri-file-word-line',
    xls: 'ri-file-excel-line', xlsx: 'ri-file-excel-line',
  };
  return icons[ext] || 'ri-image-line';
});

function toggleFullscreen() {
  const el = previewContainer.value?.parentElement;
  if (!el) return;
  if (document.fullscreenElement) {
    document.exitFullscreen();
  } else {
    el.requestFullscreen?.();
  }
}

const versions = computed(() => {
  const raw = a.value.versions || [];
  const gradients = ['from-indigo-300 to-violet-400', 'from-indigo-200 to-violet-300', 'from-slate-200 to-slate-300'];
  return raw.map((v, i) => ({
    id: v.id,
    label: i === 0 ? `v${v.version} (Current)` : `v${v.version}`,
    date: v.date || '—',
    gradient: gradients[i % gradients.length],
  }));
});

const aiTags = computed(() => {
  return (a.value.tags || []).map(t => ({
    label: t.tag,
    confidence: t.confidence,
  }));
});

const fileInfo = computed(() => [
  { key: 'Filename', value: a.value.name || '—' },
  { key: 'Type', value: (a.value.extension || '—').toUpperCase() },
  { key: 'Size', value: a.value.size || '—' },
  { key: 'MIME', value: a.value.mime || '—' },
  { key: 'Hash', value: a.value.hash ? a.value.hash.substring(0, 16) + '…' : '—' },
  { key: 'Group', value: a.value.group || 'Unclassified' },
  { key: 'Confidence', value: a.value.confidence ? a.value.confidence + '%' : '—' },
  { key: 'Pipeline', value: a.value.pipeline || '—' },
  { key: 'Review', value: a.value.review || '—' },
  { key: 'Uploaded', value: a.value.ingestedAt || '—' },
  { key: 'Uploaded By', value: a.value.uploader || '—' },
]);

const assetCollections = computed(() => {
  const emojis = ['📂', '🎨', '📱', '📦', '📸'];
  return (a.value.collections || []).map((name, i) => ({
    emoji: emojis[i % emojis.length],
    name,
  }));
});

const activity = computed(() => {
  const items = [];
  if (a.value.reviewer) items.push({ user: a.value.reviewer, action: `${a.value.review || 'reviewed'} this asset`, time: a.value.reviewedAt || '—', icon: 'ri-check-line', bg: 'bg-emerald-500' });
  if (a.value.uploader) items.push({ user: a.value.uploader, action: 'uploaded this asset', time: a.value.ingestedAt || '—', icon: 'ri-upload-2-line', bg: 'bg-indigo-500' });
  if (a.value.group) items.push({ user: 'AI Pipeline', action: `classified as ${a.value.group}`, time: a.value.ingestedAt || '—', icon: 'ri-brain-line', bg: 'bg-violet-500' });
  if (items.length === 0) items.push({ user: 'System', action: 'No activity recorded', time: '—', icon: 'ri-information-line', bg: 'bg-slate-400' });
  return items;
});

function goBack() { window.history.back(); }

function downloadAsset() {
  if (props.assetId) window.location.href = `/assets/${props.assetId}/download`;
}

function shareAsset() {
  navigator.clipboard.writeText(window.location.href);
}

// ── Version management ──
const showVersionUpload = ref(false);
const versionFile = ref(null);
const versionNotes = ref('');
const uploadingVersion = ref(false);
const versionFileInput = ref(null);

// ── Metadata editing ──
const showEditMeta = ref(false);
const savingMeta = ref(false);
const editMeta = ref({ description: '', group: '' });

function openEditMeta() {
  editMeta.value = {
    description: a.value.description || '',
    group: a.value.group || '',
  };
  showEditMeta.value = true;
}

async function saveMetadata() {
  if (!props.assetId) return;
  savingMeta.value = true;
  try {
    await axios.patch(`/assets/${props.assetId}`, {
      description: editMeta.value.description,
      group: editMeta.value.group,
    });
    showEditMeta.value = false;
    router.reload({ preserveScroll: true });
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save metadata.');
  } finally {
    savingMeta.value = false;
  }
}

function handleVersionFile(e) {
  versionFile.value = e.target.files[0] || null;
}

async function submitVersionUpload() {
  if (!versionFile.value || !props.assetId) return;
  uploadingVersion.value = true;
  const formData = new FormData();
  formData.append('file', versionFile.value);
  formData.append('change_notes', versionNotes.value || 'New version uploaded');
  try {
    await axios.post(`/assets/${props.assetId}/versions`, formData, { headers: { 'Content-Type': 'multipart/form-data' } });
    showVersionUpload.value = false;
    versionFile.value = null;
    versionNotes.value = '';
    router.reload({ preserveScroll: true });
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to upload version.');
  } finally {
    uploadingVersion.value = false;
  }
}

function restoreVersion(versionId) {
  if (!confirm('Restore this version? The current file will be replaced.')) return;
  router.patch(`/assets/${props.assetId}/versions/${versionId}/restore`, {}, { preserveScroll: true });
}
</script>
