<template>
  <AppLayout>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6 anim-enter">
      <div>
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Permissions</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Manage users, roles, and access control</p>
      </div>
      <div class="flex gap-2">
        <button @click="showCreateModal = true" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-xs font-semibold shadow-lg shadow-emerald-200/50 dark:shadow-emerald-500/10 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
          <i class="ri-user-add-line mr-1"></i> Create User
        </button>
        <button @click="showInviteModal = true" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-xs font-semibold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
          <i class="ri-mail-send-line mr-1"></i> Invite User
        </button>
      </div>
    </div>

    <!-- Roles Overview -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div v-for="(role, ri) in roles" :key="ri"
        class="glass rounded-2xl p-4 hover-lift anim-enter"
        :data-delay="ri * 60"
      >
        <div class="flex items-center gap-2.5 mb-3">
          <div :class="['w-9 h-9 rounded-xl flex items-center justify-center', role.bg]">
            <i :class="[role.icon, 'text-sm text-white']"></i>
          </div>
          <div>
            <p class="text-xs font-bold text-slate-800 dark:text-slate-100">{{ role.name }}</p>
            <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ role.count }} users</p>
          </div>
        </div>
        <div class="flex flex-wrap gap-1">
          <span v-for="(perm, pi) in role.perms" :key="pi"
            class="text-[8px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-700/50 dark:text-slate-400"
          >{{ perm }}</span>
        </div>
      </div>
    </div>

    <!-- Users Table -->
    <div class="glass rounded-2xl overflow-hidden anim-enter" data-delay="240">
      <div class="px-5 py-3.5 border-b border-slate-100/60 dark:border-slate-700/40 flex items-center justify-between">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Team Members</h3>
        <div class="flex items-center gap-2">
          <input type="text" v-model="searchQuery" placeholder="Search users..." class="text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 w-48 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:w-64 transition-all" />
        </div>
      </div>
      <div class="divide-y divide-slate-50 dark:divide-slate-800">
        <div v-for="(user, ui) in filteredUsers" :key="ui"
          class="flex items-center gap-4 px-5 py-3.5 hover:bg-indigo-50/20 dark:hover:bg-indigo-500/10 transition-all duration-200 group"
        >
          <div :class="['w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold text-white', user.avatarBg]">{{ user.initials }}</div>
          <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-slate-800 dark:text-slate-100">{{ user.name }}</p>
            <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ user.email }}</p>
          </div>
          <span :class="['text-[9px] font-bold px-2.5 py-1 rounded-full', user.roleBadge]">{{ user.role }}</span>
          <span class="text-[10px] text-slate-400 dark:text-slate-500 w-24 text-right">{{ user.lastActive }}</span>
          <div :class="['w-2 h-2 rounded-full', user.online ? 'bg-emerald-400' : 'bg-slate-300']"></div>
          <select
            :value="user.role"
            @change="changeRole(user.id, $event.target.value)"
            class="opacity-0 group-hover:opacity-100 text-[10px] border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all cursor-pointer"
          >
            <option v-for="r in roleNames" :key="r" :value="r">{{ r }}</option>
          </select>
          <button @click="openEditUser(user)" class="opacity-0 group-hover:opacity-100 w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center text-slate-400 hover:text-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition-all" title="Edit user">
            <i class="ri-pencil-line text-xs"></i>
          </button>
          <button @click="confirmDeleteUser(user)" class="opacity-0 group-hover:opacity-100 w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-all" title="Delete user">
            <i class="ri-delete-bin-line text-xs"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Permission Matrix -->
    <div class="glass rounded-2xl p-5 mt-5 anim-enter" data-delay="300">
      <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-3">Permission Matrix</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-[10px]">
          <thead>
            <tr class="border-b border-slate-100 dark:border-slate-700/40">
              <th class="text-left py-2 px-3 font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Permission</th>
              <th v-for="r in roleNames" :key="r" class="text-center py-2 px-3 font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">{{ r }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(perm, pi) in permMatrix" :key="pi" class="border-b border-slate-50 dark:border-slate-800 hover:bg-indigo-50/20 dark:hover:bg-indigo-500/10 transition-colors">
              <td class="py-2.5 px-3 text-xs text-slate-700 dark:text-slate-200 font-medium">{{ perm.label }}</td>
              <td v-for="(val, vi) in perm.values" :key="vi" class="text-center py-2.5 px-3">
                <span v-if="val" class="inline-flex w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400 items-center justify-center"><i class="ri-check-line text-xs"></i></span>
                <span v-else class="inline-flex w-5 h-5 rounded-full bg-slate-100 text-slate-300 dark:bg-slate-700/50 dark:text-slate-400 items-center justify-center"><i class="ri-close-line text-xs"></i></span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Invite User Modal -->
    <div v-if="showInviteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="showInviteModal = false">
      <div class="glass rounded-2xl p-6 w-[400px]">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-4"><i class="ri-user-add-line mr-1.5 text-indigo-500"></i> Invite User</h3>
        <div v-if="errors.email || errors.role" class="mb-3 p-2.5 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20">
          <p v-for="(msg, field) in errors" :key="field" class="text-[10px] text-red-600 dark:text-red-400">{{ msg }}</p>
        </div>
        <div class="space-y-3">
          <div>
            <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Email</label>
            <input v-model="inviteForm.email" type="email" placeholder="user@example.com" class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
          </div>
          <div>
            <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Role</label>
            <select v-model="inviteForm.role" class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
              <option value="" disabled>Select a role</option>
              <option v-for="r in roleNames" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-5">
          <button @click="showInviteModal = false" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all">Cancel</button>
          <button @click="sendInvite" class="px-5 py-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-xs font-semibold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:-translate-y-0.5 transition-all duration-300">Send Invite</button>
        </div>
      </div>
    </div>

    <!-- Edit User Modal -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="showEditModal = false">
      <div class="glass rounded-2xl p-6 w-[400px]">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-4"><i class="ri-pencil-line mr-1.5 text-indigo-500"></i> Edit User</h3>
        <div v-if="errors.name || errors.email" class="mb-3 p-2.5 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20">
          <p v-for="(msg, field) in errors" :key="field" class="text-[10px] text-red-600 dark:text-red-400">{{ msg }}</p>
        </div>
        <div class="space-y-3">
          <div>
            <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Name</label>
            <input v-model="editForm.name" type="text" class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
          </div>
          <div>
            <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Email</label>
            <input v-model="editForm.email" type="email" class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-5">
          <button @click="showEditModal = false" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all">Cancel</button>
          <button @click="saveEditUser" class="px-5 py-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-xs font-semibold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:-translate-y-0.5 transition-all duration-300">Save Changes</button>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="showDeleteModal = false">
      <div class="glass rounded-2xl p-6 w-[380px]">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-500/15 flex items-center justify-center">
            <i class="ri-error-warning-line text-lg text-red-500"></i>
          </div>
          <div>
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Delete User</h3>
            <p class="text-[10px] text-slate-400">This action cannot be undone</p>
          </div>
        </div>
        <p class="text-xs text-slate-600 dark:text-slate-300 mb-5">Are you sure you want to delete <strong>{{ deleteTarget?.name }}</strong>? All their data will be permanently removed.</p>
        <div class="flex justify-end gap-2">
          <button @click="showDeleteModal = false" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all">Cancel</button>
          <button @click="deleteUser" class="px-5 py-2 rounded-xl bg-gradient-to-r from-red-500 to-rose-500 text-white text-xs font-semibold shadow-lg shadow-red-200/50 dark:shadow-red-500/10 hover:-translate-y-0.5 transition-all duration-300">Delete</button>
        </div>
      </div>
    </div>

    <!-- Create User Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm" @click.self="showCreateModal = false">
      <div class="glass rounded-2xl p-6 w-[400px]">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 mb-4"><i class="ri-user-add-line mr-1.5 text-emerald-500"></i> Create User</h3>
        <div v-if="errors.name || errors.email || errors.password || errors.role" class="mb-3 p-2.5 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20">
          <p v-for="(msg, field) in errors" :key="field" class="text-[10px] text-red-600 dark:text-red-400">{{ msg }}</p>
        </div>
        <div class="space-y-3">
          <div>
            <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Name</label>
            <input v-model="createForm.name" type="text" placeholder="John Doe" class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
          </div>
          <div>
            <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Email</label>
            <input v-model="createForm.email" type="email" placeholder="user@example.com" class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
          </div>
          <div>
            <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Password</label>
            <input v-model="createForm.password" type="password" placeholder="Minimum 8 characters" class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
          </div>
          <div>
            <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Role</label>
            <select v-model="createForm.role" class="mt-1 w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
              <option value="" disabled>Select a role</option>
              <option v-for="r in roleNames" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-5">
          <button @click="showCreateModal = false" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all">Cancel</button>
          <button @click="submitCreateUser" class="px-5 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-xs font-semibold shadow-lg shadow-emerald-200/50 dark:shadow-emerald-500/10 hover:-translate-y-0.5 transition-all duration-300">Create User</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { useScrollReveal } from '@/composables/useAnimations';
useScrollReveal();

const page = usePage();
const errors = computed(() => page.props.errors || {});

const props = defineProps({
  users: Array,
  allRoles: Array,
  allPermissions: Array,
});

const searchQuery = ref('');
const showInviteModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const showCreateModal = ref(false);
const inviteForm = ref({ email: '', role: '' });
const editForm = ref({ id: null, name: '', email: '' });
const deleteTarget = ref(null);
const createForm = ref({ name: '', email: '', password: '', role: '' });

function changeRole(userId, newRole) {
  router.put('/permissions/users/' + userId + '/role', { role: newRole }, { preserveScroll: true });
}

function sendInvite() {
  if (!inviteForm.value.email || !inviteForm.value.role) return;
  router.post('/permissions/invite', inviteForm.value, {
    preserveScroll: true,
    onSuccess: () => { showInviteModal.value = false; inviteForm.value = { email: '', role: '' }; },
  });
}

function openEditUser(user) {
  editForm.value = { id: user.id, name: user.name, email: user.email };
  showEditModal.value = true;
}

function saveEditUser() {
  if (!editForm.value.name || !editForm.value.email) return;
  router.patch('/permissions/users/' + editForm.value.id, {
    name: editForm.value.name,
    email: editForm.value.email,
  }, {
    preserveScroll: true,
    onSuccess: () => { showEditModal.value = false; },
  });
}

function confirmDeleteUser(user) {
  deleteTarget.value = user;
  showDeleteModal.value = true;
}

function deleteUser() {
  router.delete('/permissions/users/' + deleteTarget.value.id, {
    preserveScroll: true,
    onSuccess: () => { showDeleteModal.value = false; deleteTarget.value = null; },
  });
}

function submitCreateUser() {
  if (!createForm.value.name || !createForm.value.email || !createForm.value.password || !createForm.value.role) return;
  router.post('/permissions/users', createForm.value, {
    preserveScroll: true,
    onSuccess: () => { showCreateModal.value = false; createForm.value = { name: '', email: '', password: '', role: '' }; },
  });
}

const roleMeta = {
  'Admin':          { icon: 'ri-shield-star-line', bg: 'bg-gradient-to-br from-red-400 to-rose-500', roleBadge: 'bg-red-50 text-red-600' },
  'Food Team':      { icon: 'ri-restaurant-line', bg: 'bg-gradient-to-br from-emerald-400 to-teal-500', roleBadge: 'bg-emerald-50 text-emerald-600' },
  'Media Team':     { icon: 'ri-camera-line', bg: 'bg-gradient-to-br from-sky-400 to-blue-500', roleBadge: 'bg-sky-50 text-sky-600' },
  'Marketing Team': { icon: 'ri-megaphone-line', bg: 'bg-gradient-to-br from-amber-400 to-orange-500', roleBadge: 'bg-amber-50 text-amber-600' },
};
const defaultRoleMeta = { icon: 'ri-user-line', bg: 'bg-gradient-to-br from-slate-400 to-gray-500', roleBadge: 'bg-slate-100 text-slate-500 dark:bg-slate-700/50 dark:text-slate-400' };

const roles = computed(() => (props.allRoles || []).map(name => {
  const meta = roleMeta[name] || defaultRoleMeta;
  const usersInRole = (props.users || []).filter(u => (u.roles || []).includes(name));
  return {
    name,
    count: usersInRole.length,
    icon: meta.icon,
    bg: meta.bg,
    perms: usersInRole.length > 0 ? [...new Set(usersInRole.flatMap(u => u.permissions || []))].slice(0, 3) : [],
  };
}));

const avatarBgs = ['bg-indigo-500', 'bg-emerald-500', 'bg-rose-500', 'bg-amber-500', 'bg-pink-500', 'bg-sky-500'];
const users = computed(() => (props.users || []).map((u, i) => ({
  ...u,
  initials: u.initials || u.name?.split(' ').map(n => n[0]).join('').slice(0, 2) || '?',
  avatarBg: avatarBgs[i % avatarBgs.length],
  role: (u.roles || [])[0] || 'No Role',
  roleBadge: (roleMeta[(u.roles || [])[0]] || defaultRoleMeta).roleBadge,
  lastActive: u.lastActive || '—',
  online: u.lastLoginAt ? (Date.now() - new Date(u.lastLoginAt).getTime()) < 15 * 60 * 1000 : false,
})));

const filteredUsers = computed(() => {
  if (!searchQuery.value) return users.value;
  const q = searchQuery.value.toLowerCase();
  return users.value.filter(u => u.name?.toLowerCase().includes(q) || u.email?.toLowerCase().includes(q));
});

const roleNames = computed(() => props.allRoles || []);

const permMatrix = computed(() => {
  const perms = props.allPermissions || [];
  const rns = props.allRoles || [];
  return perms.map(perm => ({
    label: perm,
    values: rns.map(role => {
      const usersInRole = (props.users || []).filter(u => (u.roles || []).includes(role));
      return usersInRole.some(u => (u.permissions || []).includes(perm));
    }),
  }));
});
</script>
