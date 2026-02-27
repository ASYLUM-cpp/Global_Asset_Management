<template>
  <AppLayout>
    <!-- Profile Header -->
    <div class="glass rounded-3xl overflow-hidden mb-6 anim-enter-scale">
      <div class="relative h-44 bg-gradient-to-br from-indigo-500 via-violet-500 to-purple-600 overflow-hidden">
        <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full bg-white/10 float"></div>
        <div class="absolute -bottom-12 -left-8 w-48 h-48 rounded-full bg-white/10 float" style="animation-delay:-3s"></div>
        <div class="absolute bottom-0 left-0 right-0 h-20 bg-gradient-to-t from-black/20 to-transparent"></div>
      </div>
      <div class="relative px-6 pb-5 -mt-12">
        <div class="flex items-end gap-5">
          <div class="w-24 h-24 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white text-2xl font-bold shadow-xl shadow-indigo-500/30 dark:shadow-indigo-500/20 border-4 border-white">
            {{ profile.initials || 'U' }}
          </div>
          <div class="flex-1 pb-1">
            <div class="flex items-center gap-3">
              <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ profile.name || 'User' }}</h1>
              <span class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 text-white shadow-md shadow-indigo-200/50 dark:shadow-indigo-500/10">
                <i class="ri-shield-star-line mr-0.5"></i> {{ profile.role || 'Member' }}
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ profile.email || '' }} · Joined {{ profile.joined || '—' }}</p>
          </div>
          <div class="flex gap-2 pb-1">
            <div @click="showEditModal = true" class="glass rounded-xl px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer transition-all">
              <i class="ri-edit-line mr-1"></i> Edit Profile
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-5 gap-4 mb-6">
      <div v-for="(stat, si) in profileStats" :key="si"
        class="glass rounded-2xl p-4 hover-lift anim-enter"
        :data-delay="si * 50"
      >
        <div class="flex items-center gap-2.5 mb-2">
          <div :class="['w-9 h-9 rounded-xl flex items-center justify-center', stat.bg]">
            <i :class="[stat.icon, 'text-sm text-white']"></i>
          </div>
          <span class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold">{{ stat.label }}</span>
        </div>
        <p class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ stat.value }}</p>
        <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">{{ stat.sub }}</p>
      </div>
    </div>

    <div class="grid grid-cols-[1fr_340px] gap-5">
      <!-- Left Column -->
      <div class="space-y-5">
        <!-- About -->
        <div class="glass rounded-2xl p-5 anim-enter" data-delay="250">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3"><i class="ri-user-line mr-1.5 text-indigo-500"></i> About</h3>
          <div class="grid grid-cols-2 gap-x-6 gap-y-3">
            <div v-for="(info, ii) in aboutInfo" :key="ii" class="flex items-start gap-2.5">
              <i :class="[info.icon, 'text-sm text-indigo-400 mt-0.5']"></i>
              <div>
                <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ info.label }}</p>
                <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ info.value }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="glass rounded-2xl p-5 anim-enter" data-delay="300">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3"><i class="ri-history-line mr-1.5 text-indigo-500"></i> Recent Activity</h3>
          <div class="space-y-3">
            <div v-for="(act, ai) in recentActivity" :key="ai" class="flex gap-3 relative">
              <div v-if="ai < recentActivity.length - 1" class="absolute left-[11px] top-6 bottom-0 w-px bg-slate-100 dark:bg-slate-700"></div>
              <div :class="['w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 z-10', act.bg]">
                <i :class="[act.icon, 'text-[10px] text-white']"></i>
              </div>
              <div class="flex-1 pb-3">
                <p class="text-[11px] text-slate-700 dark:text-slate-200">{{ act.text }}</p>
                <p class="text-[9px] text-slate-400 dark:text-slate-500">{{ act.time }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Managed Collections -->
        <div class="glass rounded-2xl p-5 anim-enter" data-delay="350">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3"><i class="ri-folders-line mr-1.5 text-indigo-500"></i> Managed Collections</h3>
          <div class="grid grid-cols-2 gap-3">
            <div v-for="(col, ci) in collections" :key="ci"
              class="glass rounded-xl p-3 hover-lift cursor-pointer group"
              @click="router.visit('/assets?collection=' + encodeURIComponent(col.name))"
            >
              <div class="flex items-center gap-2 mb-1.5">
                <span class="text-lg">{{ col.emoji }}</span>
                <h4 class="text-[11px] font-bold text-slate-800 dark:text-slate-100 group-hover:text-indigo-600 transition-colors">{{ col.name }}</h4>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-[10px] text-slate-400 dark:text-slate-500">{{ col.count }} assets</span>
                <span :class="['text-[9px] font-bold px-2 py-0.5 rounded-full', col.statusClass]">{{ col.status }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Sidebar -->
      <div class="space-y-5">
        <!-- Permissions Summary -->
        <div class="glass rounded-2xl p-5 anim-enter-right" data-delay="250">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3"><i class="ri-shield-keyhole-line mr-1.5 text-indigo-500"></i> Permissions</h3>
          <div class="space-y-2">
            <div v-for="(perm, pi) in permissions" :key="pi" class="flex items-center gap-2.5 px-3 py-2 rounded-xl bg-white/50 dark:bg-white/5 border border-slate-100 dark:border-slate-700/40">
              <span :class="['inline-flex w-5 h-5 rounded-full items-center justify-center', perm.granted ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-slate-100 text-slate-300 dark:bg-slate-700/50 dark:text-slate-400']">
                <i :class="perm.granted ? 'ri-check-line text-xs' : 'ri-close-line text-xs'"></i>
              </span>
              <span class="text-[11px] font-medium text-slate-700 dark:text-slate-200">{{ perm.label }}</span>
            </div>
          </div>
        </div>

        <!-- Performance -->
        <div class="glass rounded-2xl p-5 anim-enter-right" data-delay="300">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3"><i class="ri-bar-chart-line mr-1.5 text-indigo-500"></i> Performance</h3>
          <div class="space-y-3">
            <div v-for="(metric, mi) in performanceMetrics" :key="mi">
              <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-medium text-slate-600 dark:text-slate-300">{{ metric.label }}</span>
                <span class="text-[10px] font-bold text-indigo-600">{{ metric.value }}</span>
              </div>
              <div class="h-1.5 rounded-full bg-slate-100 dark:bg-slate-700/50 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r transition-all duration-700" :class="metric.barColor" :style="{ width: metric.pct + '%' }"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Team -->
        <div class="glass rounded-2xl p-5 anim-enter-right" data-delay="350">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3"><i class="ri-team-line mr-1.5 text-indigo-500"></i> Team</h3>
          <div class="space-y-2">
            <div v-for="(member, mi) in teamMembers" :key="mi" class="flex items-center gap-2.5 px-3 py-2 rounded-xl hover:bg-indigo-50/50 dark:hover:bg-indigo-500/10 transition-colors cursor-pointer" @click="router.visit('/permissions')">
              <div :class="['w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-bold text-white', member.bg]">{{ member.initials }}</div>
              <div class="flex-1 min-w-0">
                <p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200 truncate">{{ member.name }}</p>
                <p class="text-[9px] text-slate-400 dark:text-slate-500">{{ member.role }}</p>
              </div>
              <div :class="['w-2 h-2 rounded-full', member.online ? 'bg-emerald-400' : 'bg-slate-300']"></div>
            </div>
          </div>
        </div>

        <!-- Sessions -->
        <div class="glass rounded-2xl p-5 anim-enter-right" data-delay="400">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3"><i class="ri-device-line mr-1.5 text-indigo-500"></i> Active Sessions</h3>
          <div class="space-y-2.5">
            <div v-for="(session, si) in sessions" :key="si" class="flex items-center gap-2.5 px-3 py-2 rounded-xl bg-white/50 dark:bg-white/5 border border-slate-100 dark:border-slate-700/40">
              <i :class="[session.icon, 'text-sm text-indigo-400']"></i>
              <div class="flex-1">
                <p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200">{{ session.device }}</p>
                <p class="text-[9px] text-slate-400 dark:text-slate-500">{{ session.location }} · {{ session.time }}</p>
              </div>
              <span v-if="session.current" class="text-[8px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600">Current</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Profile Modal -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="showEditModal = false">
      <div class="glass rounded-2xl p-6 w-full max-w-md shadow-2xl">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-4">Edit Profile</h3>
        <div class="space-y-3">
          <div>
            <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Name</label>
            <input v-model="editForm.name" type="text" class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition" />
          </div>
          <div>
            <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Email</label>
            <input v-model="editForm.email" type="email" class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition" />
          </div>
        </div>
        <div class="flex justify-end gap-3 mt-5">
          <button @click="showEditModal = false" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all">Cancel</button>
          <button @click="updateProfile" class="px-5 py-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-xs font-semibold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:-translate-y-0.5 transition-all duration-300">Save Changes</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref, reactive } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { useScrollReveal } from '@/composables/useAnimations';
useScrollReveal();

const props = defineProps({
  userProfile: Object,
  stats: Object,
  recentActivity: Array,
  userPermissions: Array,
  userCollections: Array,
  teamMembers: Array,
});

function formatBytes(b) {
  if (!b) return '0 B';
  const u = ['B', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(b) / Math.log(1024));
  return (b / Math.pow(1024, i)).toFixed(1) + ' ' + u[i];
}

const showEditModal = ref(false);
const editForm = reactive({
  name: props.userProfile?.name || '',
  email: props.userProfile?.email || '',
});

function updateProfile() {
  router.put('/profile', editForm, {
    preserveScroll: true,
    onSuccess: () => { showEditModal.value = false; },
  });
}

const profile = computed(() => props.userProfile || {});
const st = computed(() => props.stats || {});

const profileStats = computed(() => [
  { label: 'Uploads', value: (st.value.uploads || 0).toLocaleString(), sub: 'Total uploads', icon: 'ri-upload-cloud-line', bg: 'bg-gradient-to-br from-indigo-400 to-violet-500' },
  { label: 'Reviews', value: (st.value.reviews || 0).toLocaleString(), sub: 'Total reviews', icon: 'ri-checkbox-circle-line', bg: 'bg-gradient-to-br from-emerald-400 to-teal-500' },
  { label: 'Approval Rate', value: (st.value.approvalRate || 0) + '%', sub: 'Of reviewed assets', icon: 'ri-thumb-up-line', bg: 'bg-gradient-to-br from-sky-400 to-blue-500' },
  { label: 'Collections', value: String(st.value.collections || 0), sub: 'Managed collections', icon: 'ri-folders-line', bg: 'bg-gradient-to-br from-amber-400 to-orange-500' },
  { label: 'Storage Used', value: formatBytes(st.value.storageUsed), sub: 'Personal usage', icon: 'ri-hard-drive-3-line', bg: 'bg-gradient-to-br from-rose-400 to-pink-500' },
]);

const aboutInfo = computed(() => [
  { label: 'Full Name', value: profile.value.name || '—', icon: 'ri-user-line' },
  { label: 'Role', value: profile.value.role || '—', icon: 'ri-shield-star-line' },
  { label: 'Department', value: 'Digital Asset Management', icon: 'ri-building-line' },
  { label: 'Email', value: profile.value.email || '—', icon: 'ri-mail-line' },
  { label: 'Member Since', value: profile.value.joined || '—', icon: 'ri-calendar-line' },
  { label: 'Timezone', value: 'PKT (UTC+5)', icon: 'ri-time-line' },
]);

const actBgs = ['bg-emerald-500', 'bg-indigo-500', 'bg-violet-500', 'bg-amber-500', 'bg-rose-500', 'bg-sky-500'];
const actIcons = ['ri-check-line', 'ri-upload-2-line', 'ri-folder-add-line', 'ri-settings-3-line', 'ri-flag-line', 'ri-user-add-line'];
const recentActivity = computed(() => (props.recentActivity || []).map((a, i) => ({
  text: a.description,
  time: a.time,
  icon: actIcons[i % actIcons.length],
  bg: actBgs[i % actBgs.length],
})));

const collectionEmojis = ['📁', '📸', '🎬', '📦', '🎨', '📊'];
const collections = computed(() => (props.userCollections || []).map((c, i) => ({
  emoji: collectionEmojis[i % collectionEmojis.length],
  name: c.name,
  count: c.assets_count ?? 0,
  status: c.is_active ? 'Active' : 'Inactive',
  statusClass: c.is_active ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-700/50 dark:text-slate-400',
})));

const allPermLabels = [
  { key: 'upload assets', label: 'Upload Assets' },
  { key: 'approve assets', label: 'Approve / Reject' },
  { key: 'delete assets', label: 'Delete Assets' },
  { key: 'manage collections', label: 'Manage Collections' },
  { key: 'configure pipeline', label: 'Configure Pipeline' },
  { key: 'manage users', label: 'Manage Users' },
  { key: 'system settings', label: 'System Settings' },
];
const permissions = computed(() => {
  const granted = (props.userPermissions || []).map(p => p.toLowerCase());
  return allPermLabels.map(p => ({ label: p.label, granted: granted.includes(p.key) }));
});

const performanceMetrics = computed(() => [
  { label: 'Review Speed', value: '—', pct: 0, barColor: 'from-indigo-400 to-violet-500' },
  { label: 'Accuracy Score', value: (st.value.approvalRate || 0) + '%', pct: st.value.approvalRate || 0, barColor: 'from-emerald-400 to-teal-500' },
  { label: 'Activity Index', value: '—', pct: 0, barColor: 'from-sky-400 to-blue-500' },
  { label: 'SLA Compliance', value: '—', pct: 0, barColor: 'from-amber-400 to-orange-500' },
]);

const memberBgs = ['bg-indigo-500', 'bg-violet-500', 'bg-emerald-500', 'bg-amber-500', 'bg-rose-500', 'bg-sky-500'];
const teamMembers = computed(() => (props.teamMembers || []).map((m, i) => ({
  name: m.name,
  initials: m.name ? m.name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2) : '??',
  role: m.role || 'Member',
  bg: memberBgs[i % memberBgs.length],
  online: !!m.online,
})));

const sessions = computed(() => [
  { device: 'Current Session', location: '—', time: 'Active now', icon: 'ri-computer-line', current: true },
]);
</script>
