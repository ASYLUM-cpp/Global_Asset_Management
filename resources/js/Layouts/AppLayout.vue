<template>
  <div :class="['min-h-screen flex transition-colors duration-500', isDark ? 'bg-slate-950' : 'bg-slate-50']">
    <!-- Sidebar -->
    <aside
      @mouseenter="sidebarHover = true"
      @mouseleave="sidebarHover = false"
      :class="[
        'fixed inset-y-0 left-0 z-40 flex flex-col transition-all duration-[400ms] ease-[cubic-bezier(.22,1,.36,1)]',
        sidebarHover ? 'w-[240px] shadow-2xl shadow-black/30' : 'w-[68px]'
      ]"
      class="bg-gradient-to-b from-slate-900 via-indigo-950 to-slate-900 border-r border-white/5"
    >
      <!-- Logo -->
      <div class="h-16 flex items-center px-4 gap-3 border-b border-white/5 shrink-0">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white font-extrabold text-sm shadow-lg shadow-indigo-500/25 shrink-0">
          G
        </div>
        <transition name="fade-slide">
          <span v-if="sidebarHover" class="text-white font-bold text-base tracking-tight whitespace-nowrap">GAM</span>
        </transition>
      </div>

      <!-- Nav -->
      <nav class="flex-1 overflow-y-auto py-4 px-2.5 space-y-5 scrollbar-hide">
        <div v-for="(section, si) in navSections" :key="si">
          <transition name="fade-slide">
            <p v-if="sidebarHover" class="text-[10px] uppercase tracking-widest text-indigo-400/60 font-bold px-2.5 mb-2">{{ section.title }}</p>
          </transition>
          <div class="space-y-0.5">
            <div
              v-for="(item, ii) in section.items" :key="ii"
              @click="navigate(item.href)"
              :class="[
                'w-full flex items-center gap-3 rounded-xl text-[13px] font-medium transition-all duration-300 group relative cursor-pointer select-none',
                sidebarHover ? 'px-3 py-2.5' : 'justify-center px-2 py-2.5',
                isActive(item.href)
                  ? 'bg-white/10 text-white shadow-lg shadow-indigo-500/10'
                  : 'text-slate-400 hover:text-white hover:bg-white/[0.05]'
              ]"
            >
              <div :class="['absolute left-0 top-1/2 -translate-y-1/2 w-[3px] rounded-r-full transition-all duration-300', isActive(item.href) ? 'h-5 bg-indigo-400' : 'h-0 bg-transparent']"></div>
              <i :class="[item.icon, 'text-lg transition-transform duration-300 group-hover:scale-110']"></i>
              <transition name="fade-slide">
                <span v-if="sidebarHover">{{ item.label }}</span>
              </transition>
              <span v-if="item.badge && sidebarHover" class="ml-auto text-[10px] font-bold bg-indigo-500/80 text-white rounded-full px-2 py-0.5">{{ item.badge }}</span>
            </div>
          </div>
        </div>
      </nav>

      <!-- Sidebar Footer (decorative, no button) -->
      <div class="p-3 border-t border-white/5 shrink-0 flex items-center justify-center">
        <transition name="fade-slide">
          <span v-if="sidebarHover" class="text-[10px] text-slate-500 whitespace-nowrap">Hover to expand</span>
        </transition>
        <i v-if="!sidebarHover" class="ri-menu-unfold-4-line text-slate-500 text-lg"></i>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col transition-all duration-[400ms] ease-[cubic-bezier(.22,1,.36,1)] ml-[68px]">
      <!-- Header -->
      <header :class="['sticky top-0 z-30 h-16 flex items-center justify-between px-6 glass transition-colors duration-500', isDark ? 'border-b border-slate-800/60' : 'border-b border-gray-200/60']">
        <div class="flex items-center gap-3">
          <h1 :class="['text-[15px] font-bold transition-colors', isDark ? 'text-slate-100' : 'text-slate-800']">{{ pageTitle }}</h1>
        </div>

        <div class="flex items-center gap-2.5">
          <!-- Search -->
          <div class="relative group">
            <i :class="['ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-sm transition-colors group-focus-within:text-indigo-500', isDark ? 'text-slate-500' : 'text-slate-400']"></i>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search assets…"
              @keydown.enter="performSearch"
              :class="['pl-9 py-2 w-56 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition-all duration-300 focus:w-72',
                searchQuery ? 'pr-8' : 'pr-10',
                isDark ? 'border-slate-700 bg-slate-800/80 text-slate-200 placeholder-slate-500' : 'border-gray-200 bg-white/80 text-slate-800 placeholder-slate-400']"
            />
            <!-- Clear button (X) -->
            <button
              v-if="searchQuery"
              @click="clearSearch"
              :class="['absolute right-2.5 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full flex items-center justify-center transition-all duration-200 hover:scale-110',
                isDark ? 'text-slate-400 hover:text-red-400 hover:bg-red-500/10' : 'text-slate-400 hover:text-red-500 hover:bg-red-50']"
              title="Clear search"
            >
              <i class="ri-close-line text-sm"></i>
            </button>
            <!-- Enter hint (only when no query) -->
            <kbd v-if="!searchQuery" :class="['absolute right-3 top-1/2 -translate-y-1/2 text-[10px] rounded px-1.5 py-0.5 font-mono', isDark ? 'text-slate-500 bg-slate-800' : 'text-slate-400 bg-slate-100']">↵</kbd>
          </div>

          <!-- Dark Mode Toggle -->
          <button
            @click="toggle"
            :class="['w-9 h-9 rounded-xl flex items-center justify-center transition-all duration-300 relative overflow-hidden',
              isDark ? 'text-amber-400 hover:text-amber-300 hover:bg-amber-500/10' : 'text-slate-500 hover:text-indigo-500 hover:bg-indigo-50']"
            title="Toggle dark mode"
          >
            <i :class="[isDark ? 'ri-sun-line' : 'ri-moon-line', 'text-lg transition-transform duration-300']"></i>
          </button>

          <!-- Notifications -->
          <div class="relative">
            <button
              @click="showNotif = !showNotif"
              :class="['w-9 h-9 rounded-xl flex items-center justify-center transition-all duration-300 relative',
                isDark ? 'text-slate-400 hover:text-indigo-400 hover:bg-indigo-500/10' : 'text-slate-500 hover:text-indigo-500 hover:bg-indigo-50']"
            >
              <i class="ri-notification-3-line text-lg"></i>
              <span v-if="unreadNotifications.length > 0" class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-red-500 ring-2 ring-white dark:ring-slate-900"></span>
            </button>
            <transition name="dropdown">
              <div v-if="showNotif" :class="['absolute right-0 top-full mt-2 w-80 rounded-2xl shadow-2xl overflow-hidden',
                isDark ? 'bg-slate-900 border border-slate-700/60 shadow-black/40' : 'bg-white border-1.5 border-indigo-200/60 shadow-black/20']">
                <div :class="['px-4 py-3 border-b flex items-center justify-between',
                  isDark ? 'border-slate-700 bg-gradient-to-r from-indigo-950 to-violet-950' : 'border-indigo-100 bg-gradient-to-r from-indigo-50 to-violet-50']">
                  <span :class="['text-xs font-bold', isDark ? 'text-slate-100' : 'text-slate-900']">Notifications</span>
                  <span v-if="unreadNotifications.length > 0" @click="markAllRead" class="text-[10px] text-indigo-600 font-bold cursor-pointer hover:text-indigo-800 transition-colors">Mark all read</span>
                </div>
                <div class="max-h-64 overflow-y-auto">
                  <template v-if="unreadNotifications.length > 0">
                    <div v-for="(n, i) in unreadNotifications" :key="i" :class="['px-4 py-3 transition-colors border-b last:border-0',
                      isDark ? 'hover:bg-slate-800 border-slate-800' : 'hover:bg-indigo-50 border-slate-100']">
                      <p :class="['text-[13px] font-medium', isDark ? 'text-slate-200' : 'text-slate-800']">{{ n.text }}</p>
                      <p :class="['text-[10px] mt-0.5 font-medium', isDark ? 'text-slate-500' : 'text-slate-500']">{{ n.time }}</p>
                    </div>
                  </template>
                  <div v-else class="px-4 py-8 text-center">
                    <i class="ri-notification-off-line text-2xl text-slate-300 dark:text-slate-600 mb-2"></i>
                    <p class="text-xs text-slate-400 dark:text-slate-500">No new notifications</p>
                  </div>
                </div>
              </div>
            </transition>
          </div>

          <!-- Avatar -->
          <div class="relative" @mouseenter="showProfile = true" @mouseleave="showProfile = false">
            <div
              @click="navigate('/profile')"
              class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white text-xs font-bold shadow-md shadow-indigo-500/20 cursor-pointer hover:scale-105 transition-transform duration-300"
            >
              {{ authUser.initials || 'U' }}
            </div>
            <!-- Profile Hover Card -->
            <transition name="dropdown">
              <div v-if="showProfile" :class="['absolute right-0 top-full mt-2 w-72 rounded-2xl shadow-2xl overflow-hidden z-50',
                isDark ? 'bg-slate-900 border border-slate-700/60 shadow-black/40' : 'bg-white border-1.5 border-indigo-200/60 shadow-black/20']">
                <!-- Profile Header -->
                <div class="px-4 py-4 bg-gradient-to-r from-indigo-500 via-violet-500 to-purple-600 text-white">
                  <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-white font-bold text-sm">{{ authUser.initials || 'U' }}</div>
                    <div>
                      <p class="text-sm font-bold">{{ authUser.name || 'User' }}</p>
                      <p class="text-[10px] text-white/70">{{ authUser.email }}</p>
                    </div>
                  </div>
                  <div class="mt-2.5 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/15 backdrop-blur-sm">
                    <i class="ri-shield-star-line text-[10px]"></i>
                    <span class="text-[10px] font-bold">{{ authUser.role || 'User' }}</span>
                  </div>
                </div>
                <!-- Quick Stats -->
                <div :class="['grid grid-cols-3 gap-px', isDark ? 'bg-slate-800' : 'bg-slate-100']">
                  <div :class="['px-3 py-2.5 text-center', isDark ? 'bg-slate-900' : 'bg-white']">
                    <p :class="['text-sm font-bold', isDark ? 'text-slate-100' : 'text-slate-800']">{{ authUser.uploads_count ?? '—' }}</p>
                    <p :class="['text-[9px]', isDark ? 'text-slate-500' : 'text-slate-400']">Uploads</p>
                  </div>
                  <div :class="['px-3 py-2.5 text-center', isDark ? 'bg-slate-900' : 'bg-white']">
                    <p :class="['text-sm font-bold', isDark ? 'text-slate-100' : 'text-slate-800']">{{ authUser.reviews_count ?? '—' }}</p>
                    <p :class="['text-[9px]', isDark ? 'text-slate-500' : 'text-slate-400']">Reviews</p>
                  </div>
                  <div :class="['px-3 py-2.5 text-center', isDark ? 'bg-slate-900' : 'bg-white']">
                    <p :class="['text-sm font-bold', isDark ? 'text-slate-100' : 'text-slate-800']">{{ authUser.approval_rate != null ? authUser.approval_rate + '%' : '—' }}</p>
                    <p :class="['text-[9px]', isDark ? 'text-slate-500' : 'text-slate-400']">Approval</p>
                  </div>
                </div>
                <!-- Quick Links -->
                <div class="p-2">
                  <div @click="navigate('/profile')" :class="['flex items-center gap-2.5 px-3 py-2 rounded-xl cursor-pointer transition-colors', isDark ? 'hover:bg-slate-800' : 'hover:bg-indigo-50']">
                    <i class="ri-user-line text-sm text-indigo-500"></i>
                    <span :class="['text-xs font-semibold', isDark ? 'text-slate-300' : 'text-slate-700']">View Profile</span>
                  </div>
                  <div @click="navigate('/settings')" :class="['flex items-center gap-2.5 px-3 py-2 rounded-xl cursor-pointer transition-colors', isDark ? 'hover:bg-slate-800' : 'hover:bg-indigo-50']">
                    <i class="ri-settings-4-line text-sm text-indigo-500"></i>
                    <span :class="['text-xs font-semibold', isDark ? 'text-slate-300' : 'text-slate-700']">Settings</span>
                  </div>
                  <div @click="logout" :class="['flex items-center gap-2.5 px-3 py-2 rounded-xl cursor-pointer transition-colors', isDark ? 'hover:bg-red-950/50' : 'hover:bg-red-50']">
                    <i class="ri-logout-box-r-line text-sm text-red-500"></i>
                    <span class="text-xs font-semibold text-red-600">Sign Out</span>
                  </div>
                </div>
              </div>
            </transition>
          </div>
        </div>
      </header>

      <!-- Flash Toast -->
      <transition name="dropdown">
        <div v-if="flashMessage" :class="['fixed top-20 right-6 z-50 max-w-sm px-5 py-3 rounded-2xl shadow-2xl text-sm font-semibold flex items-center gap-2.5',
          flashType === 'success'
            ? (isDark ? 'bg-emerald-900/90 border border-emerald-700/50 text-emerald-200' : 'bg-emerald-50 border border-emerald-200 text-emerald-700 shadow-emerald-100')
            : (isDark ? 'bg-red-900/90 border border-red-700/50 text-red-200' : 'bg-red-50 border border-red-200 text-red-700 shadow-red-100')]">
          <i :class="[flashType === 'success' ? 'ri-check-line' : 'ri-error-warning-line', 'text-lg']"></i>
          {{ flashMessage }}
          <button @click="flashMessage = null" class="ml-2 opacity-60 hover:opacity-100 transition-opacity"><i class="ri-close-line"></i></button>
        </div>
      </transition>

      <!-- Page Content -->
      <main class="flex-1 p-6 page-enter">
        <slot />
      </main>
    </div>

    <!-- Click-outside overlay for notifications -->
    <div v-if="showNotif" @click="showNotif = false" class="fixed inset-0 z-20"></div>
  </div>
</template>

<script setup>
import { ref, computed, provide, watch, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode';

const { isDark, toggle } = useDarkMode();
provide('isDark', isDark);

const sidebarHover = ref(false);
const showNotif = ref(false);
const showProfile = ref(false);
const searchQuery = ref('');
const flashMessage = ref(null);
const flashType = ref('success');

// Header search
function performSearch() {
  const q = searchQuery.value.trim();
  if (q) {
    router.get('/assets', { search: q }, { preserveState: false });
  }
}

function clearSearch() {
  searchQuery.value = '';
  // If we're on /assets, reload without search param
  if (currentUrl.value.startsWith('/assets')) {
    const params = { ...(page.props.filters || {}) };
    delete params.search;
    router.get('/assets', params, { preserveState: false });
  }
}

// Initialize search from current page filters (e.g. when on /assets?search=foo)
onMounted(() => {
  const filters = page.props.filters;
  if (filters?.search) {
    searchQuery.value = filters.search;
  }
});

// Keep search bar in sync when navigating between pages
watch(() => page.props.filters, (filters) => {
  searchQuery.value = filters?.search || '';
}, { deep: true });

// Flash message auto-dismiss
const page = usePage();
watch(() => page.props.flash, (flash) => {
  if (flash?.success) {
    flashMessage.value = flash.success;
    flashType.value = 'success';
    setTimeout(() => { flashMessage.value = null; }, 4000);
  } else if (flash?.error) {
    flashMessage.value = flash.error;
    flashType.value = 'error';
    setTimeout(() => { flashMessage.value = null; }, 6000);
  }
}, { immediate: true, deep: true });

const authUser = computed(() => page.props.auth?.user || {});
const currentUrl = computed(() => page.url);
const pageTitle = computed(() => {
  const map = {
    '/': 'Dashboard', '/assets': 'Asset Browser', '/upload': 'Upload Center',
    '/pipeline': 'Processing Pipeline', '/review': 'Review Queue', '/analytics': 'Analytics',
    '/collections': 'Collections', '/taxonomy': 'Taxonomy Manager', '/settings': 'Settings',
    '/permissions': 'Permissions', '/documents': 'Documents', '/notes': 'Notes',
    '/datasets': 'Datasets', '/preview': 'Asset Preview', '/profile': 'User Profile',
  };
  return map[currentUrl.value] || 'GAM';
});

function isActive(href) {
  return href === '/' ? currentUrl.value === '/' : currentUrl.value.startsWith(href);
}
function navigate(href) {
  router.visit(href);
}
function logout() {
  router.post('/logout');
}

const isAdmin = computed(() => (page.props.auth?.user?.roles || []).includes('Admin'));

const navSections = computed(() => {
  const sections = [
    { title: 'Overview', items: [
      { label: 'Dashboard', icon: 'ri-dashboard-3-line', href: '/' },
      { label: 'Analytics', icon: 'ri-bar-chart-grouped-line', href: '/analytics' },
    ]},
    { title: 'Assets', items: [
      { label: 'Browser', icon: 'ri-image-2-line', href: '/assets' },
      { label: 'Upload', icon: 'ri-upload-cloud-2-line', href: '/upload' },
      { label: 'Collections', icon: 'ri-folders-line', href: '/collections' },
    ]},
    { title: 'Pipeline', items: [
      { label: 'Processing', icon: 'ri-flow-chart', href: '/pipeline' },
      { label: 'Review Queue', icon: 'ri-checkbox-circle-line', href: '/review', badge: String(page.props.pendingReviewCount || 0) },
      { label: 'Taxonomy', icon: 'ri-node-tree', href: '/taxonomy' },
    ]},
    { title: 'Knowledge', items: [
      { label: 'Documents', icon: 'ri-book-open-line', href: '/documents' },
      { label: 'Notes', icon: 'ri-sticky-note-line', href: '/notes' },
      { label: 'Datasets', icon: 'ri-database-2-line', href: '/datasets' },
    ]},
    { title: 'Admin', items: [
      { label: 'Permissions', icon: 'ri-shield-keyhole-line', href: '/permissions' },
      { label: 'Settings', icon: 'ri-settings-4-line', href: '/settings' },
    ]},
  ];
  return isAdmin.value ? sections : sections.filter(s => s.title !== 'Admin');
});

const notifications = computed(() => page.props.notifications || []);

// Track which notifications have been "read" via localStorage
const readNotifTexts = ref(JSON.parse(localStorage.getItem('gam_notif_read') || '[]'));

function markAllRead() {
  const texts = notifications.value.map(n => n.text);
  readNotifTexts.value = texts;
  localStorage.setItem('gam_notif_read', JSON.stringify(texts));
  showNotif.value = false;
}

const unreadNotifications = computed(() => {
  return notifications.value.filter(n => !readNotifTexts.value.includes(n.text));
});
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.fade-slide-enter-active { transition: opacity 0.3s ease, transform 0.3s ease; }
.fade-slide-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.fade-slide-enter-from { opacity: 0; transform: translateX(-8px); }
.fade-slide-leave-to { opacity: 0; transform: translateX(-8px); }
.dropdown-enter-active { transition: opacity 0.25s ease, transform 0.25s cubic-bezier(.22,1,.36,1); }
.dropdown-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.dropdown-enter-from { opacity: 0; transform: translateY(-8px) scale(0.96); }
.dropdown-leave-to { opacity: 0; transform: translateY(-8px) scale(0.96); }
</style>
