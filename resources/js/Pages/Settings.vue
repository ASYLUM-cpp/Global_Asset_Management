<template>
  <AppLayout>
    <!-- Header -->
    <div class="mb-6 anim-enter">
      <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Settings</h1>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Configure system preferences and integrations</p>
    </div>

    <div class="grid grid-cols-[220px_1fr] gap-5">
      <!-- Settings Nav -->
      <nav class="glass rounded-2xl p-3 h-fit sticky top-24 anim-enter-left">
        <div class="space-y-0.5">
          <button v-for="(s, si) in sections" :key="si"
            :class="['w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-left transition-all duration-300',
              activeSection === si
                ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600'
                : 'text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-200']"
            @click="scrollToSection(si)"
          >
            <i :class="[s.icon, 'text-sm']"></i>
            <span class="text-[11px] font-semibold">{{ s.label }}</span>
          </button>
        </div>
      </nav>

      <!-- Settings Content -->
      <div class="space-y-5">
        <!-- General -->
        <div id="settings-section-0" class="glass rounded-2xl p-5 anim-enter" data-delay="80">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-1">General</h3>
          <p class="text-[10px] text-slate-400 dark:text-slate-500 mb-4">Basic system configuration</p>
          <div class="space-y-4">
            <div v-for="(f, fi) in generalFields" :key="fi" class="flex items-center justify-between py-3 border-b border-slate-50 dark:border-slate-800 last:border-0">
              <div>
                <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ f.label }}</p>
                <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ f.desc }}</p>
              </div>
              <div v-if="f.type === 'toggle'" class="relative">
                <div :class="['w-10 h-5.5 rounded-full cursor-pointer transition-all duration-300', f.on ? 'bg-indigo-500' : 'bg-slate-200 dark:bg-slate-600']" @click="f.on = !f.on">
                  <div :class="['w-4.5 h-4.5 bg-white rounded-full shadow-md absolute top-0.5 transition-all duration-300', f.on ? 'left-5' : 'left-0.5']"></div>
                </div>
              </div>
              <select v-else-if="f.type === 'select'" v-model="f.value" class="text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition">
                <option v-for="o in f.options" :key="o">{{ o }}</option>
              </select>
              <input v-else type="text" v-model="f.value" class="text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 w-64 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition" />
            </div>
          </div>
        </div>

        <!-- Pipeline Config -->
        <div id="settings-section-1" class="glass rounded-2xl p-5 anim-enter" data-delay="160">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-1">Pipeline Configuration</h3>
          <p class="text-[10px] text-slate-400 dark:text-slate-500 mb-4">Adjust processing stages and thresholds</p>
          <div class="grid grid-cols-2 gap-4">
            <div v-for="(p, pi) in pipelineSettings" :key="pi" class="glass rounded-xl p-3.5">
              <div class="flex items-center gap-2 mb-2">
                <div :class="['w-7 h-7 rounded-lg flex items-center justify-center', p.bg]">
                  <i :class="[p.icon, 'text-xs text-white']"></i>
                </div>
                <p class="text-[11px] font-bold text-slate-700 dark:text-slate-200">{{ p.label }}</p>
              </div>
              <div class="flex items-center gap-2">
                <input type="range" :min="p.min" :max="p.max" v-model="p.value" class="flex-1 accent-indigo-500" />
                <span class="text-[10px] font-bold text-indigo-600 w-10 text-right">{{ p.display }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Integrations -->
        <div id="settings-section-2" class="glass rounded-2xl p-5 anim-enter" data-delay="240">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-1">Integrations</h3>
          <p class="text-[10px] text-slate-400 dark:text-slate-500 mb-4">Connect with external services</p>
          <div class="grid grid-cols-3 gap-3">
            <div v-for="(integ, ii) in integrations" :key="ii" class="glass rounded-xl p-4 text-center hover-lift group cursor-pointer" @click="integ.connected = !integ.connected">
              <div :class="['w-10 h-10 rounded-xl mx-auto mb-2 flex items-center justify-center', integ.bg]">
                <i :class="[integ.icon, 'text-lg text-white']"></i>
              </div>
              <p class="text-[11px] font-bold text-slate-700 dark:text-slate-200 mb-0.5">{{ integ.name }}</p>
              <span :class="['text-[9px] font-bold px-2 py-0.5 rounded-full', integ.connected ? 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400']">
                {{ integ.connected ? 'Connected' : 'Available' }}
              </span>
            </div>
          </div>
        </div>

        <!-- API Tokens (REQ-01) -->
        <div id="settings-section-3" class="glass rounded-2xl p-5 anim-enter" data-delay="320">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-1">API Tokens</h3>
          <p class="text-[10px] text-slate-400 dark:text-slate-500 mb-4">Manage Bearer tokens for external API access</p>

          <!-- Flash: show new token -->
          <div v-if="$page.props.flash?.token" class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30">
            <div class="flex items-center gap-2 mb-1">
              <i class="ri-key-line text-emerald-500"></i>
              <span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-300">New Token Created</span>
            </div>
            <p class="text-[10px] text-emerald-600 dark:text-emerald-400 mb-2">Copy this token now — it won't be shown again.</p>
            <div class="flex items-center gap-2">
              <code class="flex-1 text-[10px] bg-white dark:bg-slate-800 rounded-lg px-3 py-2 font-mono text-slate-700 dark:text-slate-200 break-all border border-emerald-200 dark:border-emerald-500/30">{{ $page.props.flash.token }}</code>
              <button @click="copyToken($page.props.flash.token)" class="px-3 py-2 rounded-lg bg-emerald-500 text-white text-[10px] font-bold hover:bg-emerald-600 transition-colors">
                <i class="ri-clipboard-line mr-0.5"></i> Copy
              </button>
            </div>
          </div>

          <!-- Create new token -->
          <div class="flex gap-2 mb-4">
            <input v-model="newTokenName" type="text" placeholder="Token name (e.g. CI Pipeline)" class="flex-1 text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition" />
            <button @click="createToken" :disabled="!newTokenName.trim()" class="px-4 py-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-[11px] font-bold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:-translate-y-0.5 transition-all duration-300 disabled:opacity-40 disabled:hover:translate-y-0">
              <i class="ri-add-line mr-0.5"></i> Generate
            </button>
          </div>

          <!-- Token list -->
          <div class="space-y-2">
            <div v-if="tokens.length === 0" class="py-6 text-center">
              <i class="ri-key-line text-2xl text-slate-300 dark:text-slate-600"></i>
              <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">No API tokens yet</p>
            </div>
            <div v-for="tk in tokens" :key="tk.id" class="flex items-center justify-between px-4 py-3 rounded-xl border border-slate-100 dark:border-slate-700/40 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center">
                  <i class="ri-key-line text-indigo-500 text-sm"></i>
                </div>
                <div>
                  <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ tk.name }}</p>
                  <p class="text-[10px] text-slate-400 dark:text-slate-500">Created {{ tk.created_at }} · {{ tk.last_used_at ? 'Last used ' + tk.last_used_at : 'Never used' }}</p>
                </div>
              </div>
              <button @click="revokeToken(tk.id)" class="px-3 py-1.5 rounded-lg text-[10px] font-bold text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 border border-red-200 dark:border-red-500/30 transition-colors">
                <i class="ri-delete-bin-line mr-0.5"></i> Revoke
              </button>
            </div>
          </div>
        </div>

        <!-- Backup (REQ-16) -->
        <div id="settings-section-5" class="glass rounded-2xl p-5 anim-enter" data-delay="400">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-0.5">Backup & Recovery</h3>
              <p class="text-[10px] text-slate-400 dark:text-slate-500">Scheduled daily at 02:00 · Last 10 backups shown</p>
            </div>
            <button @click="runBackupNow" :disabled="backupRunning" class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-[11px] font-bold shadow-lg shadow-emerald-200/50 dark:shadow-emerald-500/10 hover:-translate-y-0.5 transition-all duration-300 disabled:opacity-40 disabled:hover:translate-y-0">
              <i :class="backupRunning ? 'ri-loader-4-line ri-spin' : 'ri-shield-check-line'" class="mr-0.5"></i>
              {{ backupRunning ? 'Running...' : 'Run Backup Now' }}
            </button>
          </div>

          <div class="space-y-2">
            <div v-if="backupList.length === 0" class="py-6 text-center">
              <i class="ri-shield-line text-2xl text-slate-300 dark:text-slate-600"></i>
              <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">No backups yet</p>
            </div>
            <div v-for="bk in backupList" :key="bk.filename" class="flex items-center justify-between px-4 py-3 rounded-xl border border-slate-100 dark:border-slate-700/40 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
              <div class="flex items-center gap-3">
                <div :class="['w-8 h-8 rounded-lg flex items-center justify-center', bk.type === 'Database' ? 'bg-blue-50 dark:bg-blue-500/10' : bk.type === 'Full' ? 'bg-emerald-50 dark:bg-emerald-500/10' : 'bg-amber-50 dark:bg-amber-500/10']">
                  <i :class="[bk.type === 'Database' ? 'ri-database-2-line text-blue-500' : bk.type === 'Full' ? 'ri-archive-line text-emerald-500' : 'ri-folder-zip-line text-amber-500', 'text-sm']"></i>
                </div>
                <div>
                  <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ bk.filename }}</p>
                  <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ bk.size }} · {{ bk.dateHuman || bk.date }}</p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <span :class="['text-[9px] font-bold px-2 py-0.5 rounded-full', bk.type === 'Database' ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400' : bk.type === 'Full' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400']">
                  {{ bk.type }}
                </span>
                <button @click="deleteBackup(bk.filename)" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-all">
                  <i class="ri-delete-bin-line text-sm"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Save -->
        <div class="flex justify-end gap-3 anim-enter" data-delay="300">
          <button @click="discardSettings" class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all">Discard Changes</button>
          <button @click="saveSettings" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-xs font-semibold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:-translate-y-0.5 transition-all duration-300">
            <i class="ri-save-line mr-1"></i> Save Settings
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { useScrollReveal } from '@/composables/useAnimations';
useScrollReveal();

const props = defineProps({
  aiThreshold: Number,
  aiModel: String,
  groups: Object,
  storageUsed: Number,
  totalAssets: Number,
  queuePending: Number,
  tokens: { type: Array, default: () => [] },
  backups: { type: Array, default: () => [] },
  uploadTrend: { type: Array, default: () => [] },
});

const activeSection = ref(0);

function scrollToSection(si) {
  activeSection.value = si;
  const el = document.getElementById('settings-section-' + si);
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

const sections = [
  { label: 'General', icon: 'ri-settings-3-line' },
  { label: 'Pipeline', icon: 'ri-route-line' },
  { label: 'Integrations', icon: 'ri-plug-line' },
  { label: 'API Tokens', icon: 'ri-key-line' },
  { label: 'Notifications', icon: 'ri-notification-3-line' },
  { label: 'Backup', icon: 'ri-shield-check-line' },
];

function formatBytes(b) {
  if (!b) return '0 B';
  const u = ['B', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(b) / Math.log(1024));
  return (b / Math.pow(1024, i)).toFixed(1) + ' ' + u[i];
}

const settingsForm = reactive({
  ai_threshold: props.aiThreshold || 0.7,
  ai_model: props.aiModel || 'gpt-4o',
  concurrent_uploads: 5,
});

function saveSettings() {
  router.post('/settings', settingsForm, { preserveScroll: true });
}

function discardSettings() {
  settingsForm.ai_threshold = props.aiThreshold || 0.7;
  settingsForm.ai_model = props.aiModel || 'gpt-4o';
  settingsForm.concurrent_uploads = 5;
}

const generalFields = reactive([
  { label: 'System Name', desc: 'Display name for the GAM instance', type: 'text', value: 'Global Asset Manager' },
  { label: 'AI Model', desc: 'Active classification model', type: 'text', value: props.aiModel || 'gpt-4o-mini' },
  { label: 'Auto-Classification', desc: 'Enable AI-powered automatic tagging', type: 'toggle', on: true },
  { label: 'Dark Mode', desc: 'Toggle dark theme across the interface', type: 'toggle', on: false },
  { label: 'Session Timeout', desc: 'Auto-logout after inactivity', type: 'select', options: ['15 minutes', '30 minutes', '1 hour', '4 hours'], value: '30 minutes' },
]);

const thresholdPct = Math.round((props.aiThreshold || 0.70) * 100);
const pipelineSettings = reactive([
  { label: 'AI Confidence Threshold', icon: 'ri-brain-line', bg: 'bg-gradient-to-br from-violet-400 to-purple-500', min: 50, max: 99, value: thresholdPct, display: thresholdPct + '%' },
  { label: 'Max Concurrent Jobs', icon: 'ri-stack-line', bg: 'bg-gradient-to-br from-sky-400 to-blue-500', min: 1, max: 20, value: 8, display: '8' },
  { label: 'Queue Pending', icon: 'ri-user-search-line', bg: 'bg-gradient-to-br from-amber-400 to-orange-500', min: 0, max: 200, value: props.queuePending || 0, display: String(props.queuePending || 0) },
  { label: 'Total Assets', icon: 'ri-archive-line', bg: 'bg-gradient-to-br from-emerald-400 to-teal-500', min: 0, max: 10000, value: props.totalAssets || 0, display: (props.totalAssets || 0).toLocaleString() },
]);

const integrations = reactive([
  { name: 'AWS S3', icon: 'ri-cloud-line', bg: 'bg-gradient-to-br from-amber-400 to-orange-500', connected: true },
  { name: 'Slack', icon: 'ri-chat-3-line', bg: 'bg-gradient-to-br from-purple-400 to-violet-500', connected: true },
  { name: 'Jira', icon: 'ri-bug-line', bg: 'bg-gradient-to-br from-blue-400 to-indigo-500', connected: false },
  { name: 'Google Drive', icon: 'ri-google-line', bg: 'bg-gradient-to-br from-emerald-400 to-green-500', connected: true },
  { name: 'Adobe CC', icon: 'ri-adobe-line', bg: 'bg-gradient-to-br from-red-400 to-rose-500', connected: false },
  { name: 'Webhooks', icon: 'ri-terminal-box-line', bg: 'bg-gradient-to-br from-slate-400 to-gray-500', connected: false },
]);

// ── API Token Management (REQ-01) ──────────────────
const tokens = computed(() => props.tokens || []);
const newTokenName = ref('');

function createToken() {
  if (!newTokenName.value.trim()) return;
  router.post('/settings/tokens', { name: newTokenName.value.trim() }, {
    preserveScroll: true,
    onSuccess: () => { newTokenName.value = ''; },
  });
}

function revokeToken(id) {
  if (!confirm('Revoke this token? Any systems using it will lose access.')) return;
  router.delete(`/settings/tokens/${id}`, { preserveScroll: true });
}

function copyToken(text) {
  navigator.clipboard.writeText(text);
}

// ── Backup Management (REQ-16) ─────────────────────
const backupList = computed(() => props.backups || []);
const backupRunning = ref(false);

function runBackupNow() {
  backupRunning.value = true;
  router.post('/settings/backups', {}, {
    preserveScroll: true,
    onFinish: () => { backupRunning.value = false; },
  });
}

function deleteBackup(filename) {
  if (!confirm(`Delete backup "${filename}"?`)) return;
  router.delete(`/settings/backups/${filename}`, { preserveScroll: true });
}
</script>
