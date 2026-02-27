<template>
  <AppLayout>
    <!-- Header -->
    <div class="mb-6 anim-enter">
      <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-emerald-400 to-green-500 flex items-center justify-center">
          <i class="ri-google-line text-xl text-white"></i>
        </div>
        <div>
          <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Google Drive Import</h1>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Browse and import assets from your connected Google Drive</p>
        </div>
      </div>
    </div>

    <!-- Not configured banner -->
    <div v-if="!props.configured" class="glass rounded-2xl p-5 mb-5 border border-amber-200 dark:border-amber-500/30 anim-enter" data-delay="40">
      <div class="flex items-center gap-3">
        <i class="ri-alert-line text-lg text-amber-500"></i>
        <div>
          <p class="text-xs font-bold text-slate-700 dark:text-slate-200">Google Drive not configured</p>
          <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">Add <span class="font-mono bg-slate-100 dark:bg-slate-700 px-1 rounded">GOOGLE_CLIENT_ID</span> and <span class="font-mono bg-slate-100 dark:bg-slate-700 px-1 rounded">GOOGLE_CLIENT_SECRET</span> to your <span class="font-mono">.env</span> file to enable Google Drive integration.</p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-[280px_1fr] gap-5">
      <!-- Sidebar: Connection Status -->
      <div class="space-y-4">
        <!-- Connection Card -->
        <div class="glass rounded-2xl p-5 anim-enter-left" data-delay="80">
          <div class="flex items-center gap-3 mb-4">
            <div :class="['w-3 h-3 rounded-full', connected ? 'bg-emerald-400' : 'bg-slate-300 dark:bg-slate-600']"></div>
            <span class="text-xs font-bold" :class="connected ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400'">
              {{ connected ? 'Connected' : 'Not Connected' }}
            </span>
          </div>
          <p v-if="connected && props.email" class="text-[10px] text-slate-400 dark:text-slate-500 mb-3 truncate"><i class="ri-mail-line mr-0.5"></i> {{ props.email }}</p>

          <button v-if="!connected" @click="connectDrive" :disabled="!props.configured" class="w-full px-4 py-3 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-xs font-bold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:-translate-y-0.5 transition-all duration-300 flex items-center justify-center gap-2 disabled:opacity-40 disabled:hover:translate-y-0">
            <i class="ri-link text-sm"></i> Connect Google Drive
          </button>
          <div v-else class="space-y-3">
            <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-700/40">
              <span class="text-[11px] text-slate-500 dark:text-slate-400">Total Imports</span>
              <span class="text-xs font-bold text-slate-800 dark:text-slate-100">{{ totalImports }}</span>
            </div>
            <button @click="disconnectDrive" class="w-full px-4 py-2 rounded-xl border border-red-200 dark:border-red-500/30 text-red-500 text-[11px] font-bold hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">
              <i class="ri-unlink mr-0.5"></i> Disconnect
            </button>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="glass rounded-2xl p-5 anim-enter-left" data-delay="160">
          <h3 class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-3">Import Options</h3>
          <div class="space-y-2">
            <button :disabled="!connected" @click="switchMode('browse')" :class="['w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-left transition-all duration-300', importMode === 'browse' ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-200', !connected ? 'opacity-40 cursor-not-allowed' : '']">
              <i class="ri-folder-open-line text-sm"></i>
              <span class="text-[11px] font-semibold">Browse Folders</span>
            </button>
            <button :disabled="!connected" @click="switchMode('recent')" :class="['w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-left transition-all duration-300', importMode === 'recent' ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-200', !connected ? 'opacity-40 cursor-not-allowed' : '']">
              <i class="ri-time-line text-sm"></i>
              <span class="text-[11px] font-semibold">Recent Files</span>
            </button>
            <button :disabled="!connected" @click="switchMode('shared')" :class="['w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-left transition-all duration-300', importMode === 'shared' ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-200', !connected ? 'opacity-40 cursor-not-allowed' : '']">
              <i class="ri-share-line text-sm"></i>
              <span class="text-[11px] font-semibold">Shared with Me</span>
            </button>
          </div>
        </div>

        <!-- Supported Formats -->
        <div class="glass rounded-2xl p-5 anim-enter-left" data-delay="240">
          <h3 class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-3">Supported</h3>
          <div class="flex flex-wrap gap-1.5">
            <span v-for="ext in supportedFormats" :key="ext" class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400">{{ ext }}</span>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="space-y-4">
        <!-- Not connected state -->
        <div v-if="!connected" class="glass rounded-2xl p-12 text-center anim-enter">
          <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-emerald-50 to-green-50 dark:from-emerald-500/10 dark:to-green-500/10 mx-auto mb-4 flex items-center justify-center">
            <i class="ri-google-line text-4xl text-emerald-500"></i>
          </div>
          <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-2">Connect Your Google Drive</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto mb-6">Authorize GAM to access your Google Drive and import files directly into the asset pipeline.</p>
          <button @click="connectDrive" :disabled="!props.configured" class="px-6 py-3 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-xs font-bold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:-translate-y-0.5 transition-all duration-300 disabled:opacity-40 disabled:hover:translate-y-0">
            <i class="ri-link mr-1"></i> Authorize Google Drive
          </button>
        </div>

        <!-- Connected: Folder Browser -->
        <div v-else class="glass rounded-2xl overflow-hidden anim-enter">
          <!-- Toolbar -->
          <div class="px-5 py-3 border-b border-slate-100/60 dark:border-slate-700/40 flex items-center justify-between">
            <div class="flex items-center gap-1">
              <template v-for="(crumb, ci) in breadcrumbs" :key="crumb.id">
                <span v-if="ci > 0" class="text-[10px] text-slate-300 dark:text-slate-600">/</span>
                <button @click="navigateTo(crumb.id, ci)" class="text-[11px] font-medium text-indigo-500 hover:text-indigo-700 transition-colors px-1">
                  {{ crumb.name }}
                </button>
              </template>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-[10px] text-slate-400 dark:text-slate-500">{{ selectedFiles.length }} selected</span>
              <button :disabled="selectedFiles.length === 0 || importing" @click="importSelected" class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-[11px] font-bold shadow-lg shadow-emerald-200/50 dark:shadow-emerald-500/10 hover:-translate-y-0.5 transition-all duration-300 disabled:opacity-40 disabled:hover:translate-y-0">
                <i :class="importing ? 'ri-loader-4-line animate-spin' : 'ri-download-cloud-line'" class="mr-0.5"></i>
                {{ importing ? 'Importing...' : 'Import Selected' }}
              </button>
            </div>
          </div>

          <!-- File Grid -->
          <div class="p-5">
            <!-- Loading state -->
            <div v-if="loading" class="py-12 text-center">
              <i class="ri-loader-4-line animate-spin text-3xl text-indigo-400"></i>
              <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-2">Loading files from Google Drive...</p>
            </div>
            <!-- Error state -->
            <div v-else-if="loadError" class="py-12 text-center">
              <i class="ri-error-warning-line text-3xl text-rose-400"></i>
              <p class="text-[11px] text-rose-500 mt-2">{{ loadError }}</p>
              <button @click="fetchFiles()" class="mt-3 text-[10px] text-indigo-500 hover:text-indigo-700">Retry</button>
            </div>
            <!-- Empty state -->
            <div v-else-if="driveFiles.length === 0" class="py-12 text-center">
              <i class="ri-folder-open-line text-3xl text-slate-300 dark:text-slate-600"></i>
              <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-2">{{ importMode === 'browse' ? 'This folder is empty' : importMode === 'recent' ? 'No recent files found' : 'No shared files found' }}</p>
            </div>
            <!-- File grid -->
            <div v-else class="grid grid-cols-4 gap-3">
              <div v-for="file in driveFiles" :key="file.id"
                @click="toggleFileSelect(file)"
                :class="['glass rounded-xl p-4 text-center hover-lift cursor-pointer transition-all duration-300', selectedFiles.includes(file.id) ? 'ring-2 ring-indigo-400/60 bg-indigo-50/40 dark:bg-indigo-500/10' : '']">
                <div class="w-12 h-12 rounded-xl bg-slate-100 dark:bg-slate-700/50 mx-auto mb-2 flex items-center justify-center">
                  <i :class="[getFileIcon(file), 'text-xl']"></i>
                </div>
                <p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200 truncate" :title="file.name">{{ file.name }}</p>
                <p class="text-[9px] text-slate-400 dark:text-slate-500">{{ file.size || '' }}</p>
              </div>
            </div>
            <!-- Load more -->
            <div v-if="nextPageToken && !loading" class="text-center mt-4">
              <button @click="fetchFiles(true)" class="text-[10px] font-semibold text-indigo-500 hover:text-indigo-700 transition-colors">
                <i class="ri-arrow-down-line mr-0.5"></i> Load more files
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { useScrollReveal } from '@/composables/useAnimations';
useScrollReveal();

const props = defineProps({
  configured: { type: Boolean, default: false },
  connected: { type: Boolean, default: false },
  email: { type: String, default: null },
  totalImports: { type: Number, default: 0 },
});

const importMode = ref('browse');
const driveFiles = ref([]);
const selectedFiles = ref([]);
const breadcrumbs = ref([{ id: 'root', name: 'My Drive' }]);
const loading = ref(false);
const loadError = ref('');
const nextPageToken = ref(null);
const importing = ref(false);

const supportedFormats = ['JPG', 'PNG', 'GIF', 'WEBP', 'SVG', 'MP4', 'MOV', 'PDF', 'PSD', 'AI', 'TIFF', 'XLSX', 'DOCX'];

// Auto-load files on mount if already connected
onMounted(() => {
  if (props.connected) fetchFiles();
});

function connectDrive() {
  window.location.href = '/google-drive/auth';
}

function disconnectDrive() {
  if (!confirm('Disconnect Google Drive? Existing imports will not be affected.')) return;
  router.post('/google-drive/disconnect', {}, { preserveScroll: true });
}

function switchMode(mode) {
  importMode.value = mode;
  selectedFiles.value = [];
  breadcrumbs.value = [{ id: 'root', name: mode === 'browse' ? 'My Drive' : mode === 'recent' ? 'Recent' : 'Shared' }];
  fetchFiles();
}

async function fetchFiles(loadMore = false) {
  loading.value = true;
  loadError.value = '';

  const currentFolder = breadcrumbs.value[breadcrumbs.value.length - 1]?.id || 'root';
  const params = new URLSearchParams({
    mode: importMode.value,
    folder: currentFolder,
  });
  if (loadMore && nextPageToken.value) {
    params.set('pageToken', nextPageToken.value);
  }

  try {
    const resp = await fetch(`/google-drive/files?${params}`, {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (!resp.ok) {
      const data = await resp.json().catch(() => ({}));
      loadError.value = data.error || `Error ${resp.status}`;
      if (!loadMore) driveFiles.value = [];
      loading.value = false;
      return;
    }

    const data = await resp.json();
    if (loadMore) {
      driveFiles.value.push(...(data.files || []));
    } else {
      driveFiles.value = data.files || [];
    }
    nextPageToken.value = data.nextPageToken || null;
  } catch (e) {
    loadError.value = 'Network error. Please try again.';
    if (!loadMore) driveFiles.value = [];
  }

  loading.value = false;
}

function navigateTo(folderId, idx) {
  breadcrumbs.value = breadcrumbs.value.slice(0, idx + 1);
  selectedFiles.value = [];
  fetchFiles();
}

function toggleFileSelect(file) {
  if (file.isFolder) {
    breadcrumbs.value.push({ id: file.id, name: file.name });
    selectedFiles.value = [];
    fetchFiles();
    return;
  }
  const idx = selectedFiles.value.indexOf(file.id);
  if (idx >= 0) selectedFiles.value.splice(idx, 1);
  else selectedFiles.value.push(file.id);
}

function importSelected() {
  if (selectedFiles.value.length === 0) return;
  importing.value = true;
  router.post('/google-drive/import', { fileIds: selectedFiles.value }, {
    preserveScroll: true,
    onSuccess: () => {
      selectedFiles.value = [];
      importing.value = false;
    },
    onError: () => {
      importing.value = false;
    },
  });
}

function getFileIcon(file) {
  if (file.isFolder) return 'ri-folder-fill text-amber-400';
  const mime = (file.mimeType || '').toLowerCase();
  if (mime.startsWith('image/')) return 'ri-image-line text-sky-400';
  if (mime.startsWith('video/')) return 'ri-video-line text-purple-400';
  if (mime.includes('pdf')) return 'ri-file-pdf-2-line text-rose-400';
  if (mime.includes('spreadsheet') || mime.includes('excel')) return 'ri-file-excel-line text-emerald-400';
  if (mime.includes('document') || mime.includes('word')) return 'ri-file-word-line text-blue-400';
  if (mime.includes('presentation') || mime.includes('powerpoint')) return 'ri-file-ppt-line text-orange-400';
  return 'ri-file-line text-slate-400';
}
</script>
