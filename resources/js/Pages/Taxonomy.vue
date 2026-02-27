<template>
  <AppLayout>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6 anim-enter">
      <div>
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Taxonomy</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Manage classification categories and AI tagging rules</p>
      </div>
      <div class="flex items-center gap-2">
        <input ref="csvInput" type="file" accept=".csv" class="hidden" @change="importCsv" />
        <button @click="$refs.csvInput.click()" class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:-translate-y-0.5 transition-all duration-300">
          <i class="ri-upload-line mr-1"></i> Import CSV
        </button>
        <button @click="showAddModal = true" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-xs font-semibold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
          <i class="ri-add-line mr-1"></i> Add Category
        </button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div v-for="(s, si) in summaryCards" :key="si" class="glass rounded-2xl p-4 hover-lift anim-enter" :data-delay="si * 60">
        <div class="flex items-center gap-2.5 mb-2">
          <span class="text-xl">{{ s.emoji }}</span>
          <span class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold">{{ s.label }}</span>
        </div>
        <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ s.value }}</p>
        <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">{{ s.sub }}</p>
      </div>
    </div>

    <!-- Tab bar -->
    <div class="glass rounded-2xl px-4 py-2 flex items-center gap-1 mb-5 anim-enter" data-delay="240">
      <button v-for="(t, ti) in tabs" :key="ti"
        :class="['px-4 py-2 rounded-xl text-[11px] font-bold transition-all duration-300',
          activeTab === ti ? 'bg-indigo-500 text-white shadow-md shadow-indigo-200 dark:shadow-indigo-500/10' : 'text-slate-500 dark:text-slate-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:text-indigo-600']"
        @click="activeTab = ti"
      >{{ t }}</button>
    </div>

    <!-- Taxonomy Tree -->
    <div class="glass rounded-2xl p-5 anim-enter" data-delay="300">
      <div v-if="categories.length === 0" class="py-8 text-center">
        <i class="ri-node-tree text-4xl text-slate-300 dark:text-slate-600 mb-2"></i>
        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">No taxonomy categories</p>
        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Add taxonomy rules or import via CSV to see categories here.</p>
      </div>
      <div v-else class="space-y-3">
        <div v-for="(cat, ci) in categories" :key="ci"
          class="rounded-xl border border-slate-100 dark:border-slate-700/40 overflow-hidden"
        >
          <!-- Category header -->
          <div class="flex items-center gap-3 px-4 py-3 bg-white/50 dark:bg-white/5 cursor-pointer hover:bg-indigo-50/30 dark:hover:bg-indigo-500/10 transition-all duration-300" @click="cat.open = !cat.open">
            <i :class="['text-xs text-slate-400 dark:text-slate-500 transition-transform duration-300', cat.open ? 'ri-arrow-down-s-line rotate-0' : 'ri-arrow-right-s-line']"></i>
            <span class="text-lg">{{ cat.emoji }}</span>
            <div class="flex-1">
              <p class="text-xs font-bold text-slate-800 dark:text-slate-100">{{ cat.name }}</p>
              <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ cat.count }} assets · {{ cat.ruleCount }} rules · {{ cat.children.length }} subcategories</p>
            </div>
            <div class="w-24">
              <div class="h-1.5 rounded-full bg-slate-100 dark:bg-slate-700/50 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r" :class="cat.barColor" :style="{ width: cat.pct + '%' }"></div>
              </div>
              <p class="text-[9px] text-slate-400 dark:text-slate-500 text-right mt-0.5">{{ cat.pct }}%</p>
            </div>
            <span :class="['text-[9px] font-bold px-2 py-0.5 rounded-full', cat.ai ? 'bg-violet-50 dark:bg-violet-500/15 text-violet-600 dark:text-violet-400' : 'bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400']">
              {{ cat.ai ? 'AI-tagged' : 'Manual' }}
            </span>
          </div>
          <!-- Sub-categories (collapsible) -->
          <transition name="slide">
            <div v-if="cat.open" class="border-t border-slate-50 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-800/30">
              <div v-for="(child, chi) in cat.children" :key="chi"
                class="flex items-center gap-3 px-4 py-2.5 pl-12 hover:bg-white/50 dark:hover:bg-white/5 transition-all duration-200 group"
              >
                <div :class="['w-1.5 h-1.5 rounded-full', child.dot]"></div>
                <p class="text-[11px] text-slate-700 dark:text-slate-200 flex-1">{{ child.name }}</p>
                <span class="text-[10px] text-slate-400 dark:text-slate-500">{{ child.count }}</span>
                <span class="text-[9px] font-semibold text-slate-400 dark:text-slate-500">{{ child.confidence }}%</span>
                <button @click.stop="toggleRule(child)" :class="['opacity-0 group-hover:opacity-100 transition-all text-xs', child.active ? 'text-emerald-400 hover:text-amber-500' : 'text-slate-300 hover:text-emerald-500']" :title="child.active ? 'Deactivate' : 'Activate'"><i :class="child.active ? 'ri-toggle-fill' : 'ri-toggle-line'"></i></button>
                <button @click.stop="deleteRule(child.id)" class="opacity-0 group-hover:opacity-100 text-slate-300 hover:text-rose-500 transition-all"><i class="ri-delete-bin-line text-xs"></i></button>
              </div>
            </div>
          </transition>
        </div>
      </div>
    </div>
    <!-- Add Taxonomy Rule Modal -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="showAddModal = false">
      <div class="glass rounded-2xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-4">Add Taxonomy Rule</h3>
        <div class="space-y-3">
          <input v-model="newRule.raw_term" type="text" placeholder="Raw term (e.g. 'burger')" class="w-full text-sm border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 bg-white dark:bg-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
          <input v-model="newRule.canonical_term" type="text" placeholder="Canonical term (e.g. 'Hamburger')" class="w-full text-sm border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 bg-white dark:bg-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
          <select v-model="newRule.group_hint" class="w-full text-sm border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 bg-white dark:bg-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
            <option value="">Select group</option>
            <option v-for="g in groups" :key="g" :value="g">{{ g }}</option>
          </select>
        </div>
        <div class="flex justify-end gap-3 mt-5">
          <button @click="showAddModal = false" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300">Cancel</button>
          <button @click="createRule" class="px-5 py-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-xs font-bold shadow-lg hover:-translate-y-0.5 transition-all">Add Rule</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useScrollReveal } from '@/composables/useAnimations';
useScrollReveal();

const props = defineProps({
  rules: Array,
  totalRules: Number,
  groupRuleCounts: Object,
  groupAssetCounts: Object,
  totalAssets: Number,
  groups: Array,
  ruleAssetCounts: Object,
  aiAccuracy: Number,
  unclassified: Number,
});

const showAddModal = ref(false);
const newRule = reactive({ raw_term: '', canonical_term: '', group_hint: '' });

function createRule() {
  router.post('/taxonomy', newRule, {
    preserveScroll: true,
    onSuccess: () => {
      showAddModal.value = false;
      newRule.raw_term = '';
      newRule.canonical_term = '';
      newRule.group_hint = '';
    }
  });
}

function deleteRule(id) {
  if (confirm('Delete this taxonomy rule?')) {
    router.delete('/taxonomy/' + id, { preserveScroll: true });
  }
}

function toggleRule(rule) {
  router.put('/taxonomy/' + rule.id, { is_active: !rule.active }, { preserveScroll: true });
}

function importCsv(e) {
  const file = e.target.files[0];
  if (!file) return;
  const formData = new FormData();
  formData.append('csv', file);
  router.post('/taxonomy/import', formData, { forceFormData: true, preserveScroll: true });
  e.target.value = '';
}

const activeTab = ref(0);
const tabs = ['All Categories', 'AI-Managed', 'Manual', 'Unused'];

const summaryCards = computed(() => [
  { emoji: '📂', label: 'Categories', value: String(props.groups?.length || 0), sub: `${props.groups?.length || 0} taxonomy groups` },
  { emoji: '🤖', label: 'AI Accuracy', value: (props.aiAccuracy || 0) + '%', sub: 'Based on auto-approved tags' },
  { emoji: '🏷️', label: 'Total Rules', value: (props.totalRules || 0).toLocaleString(), sub: `Across ${props.groups?.length || 0} groups` },
  { emoji: '⚡', label: 'Unclassified', value: (props.unclassified || 0).toLocaleString(), sub: `of ${props.totalAssets || 0} total assets` },
]);

const barColors = [
  'from-emerald-400 to-teal-500', 'from-indigo-400 to-violet-500', 'from-sky-400 to-blue-500',
  'from-amber-400 to-orange-500', 'from-lime-400 to-emerald-500', 'from-rose-400 to-pink-500', 'from-violet-400 to-purple-500',
];
const groupEmojis = { FOOD: '🍎', MEDIA: '📸', GENBUS: '🏢', GEO: '📍', NATURE: '🌿', LIFE: '👥', SPEC: '⭐' };
const dotColors = ['bg-emerald-400', 'bg-indigo-400', 'bg-sky-400', 'bg-amber-400', 'bg-lime-400', 'bg-rose-400', 'bg-violet-400'];

const allCategories = reactive((props.groups || []).map((group, i) => {
  const groupRules = (props.rules || []).filter(r => r.group === group);
  const assetCount = props.groupAssetCounts?.[group] || 0;
  const ruleCount = props.groupRuleCounts?.[group] || 0;
  const totalA = props.totalAssets || 1;
  return {
    name: group,
    emoji: groupEmojis[group] || '📂',
    count: String(assetCount),
    ruleCount: String(ruleCount),
    pct: Math.round(assetCount / totalA * 100),
    ai: true,
    barColor: barColors[i % barColors.length],
    open: i === 0,
    children: groupRules.map(r => ({
      id: r.id,
      name: `${r.rawTerm} → ${r.canonical}`,
      count: String(props.ruleAssetCounts?.[r.id] || 0),
      confidence: props.aiAccuracy || 0,
      dot: dotColors[i % dotColors.length],
      active: r.active,
    })),
  };
}));

const categories = computed(() => {
  if (activeTab.value === 0) return allCategories;
  if (activeTab.value === 1) return allCategories.filter(c => c.ai);
  if (activeTab.value === 2) return allCategories.filter(c => !c.ai);
  if (activeTab.value === 3) return allCategories.filter(c => parseInt(c.count) === 0);
  return allCategories;
});
</script>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: all 0.3s ease; }
.slide-enter-from, .slide-leave-to { max-height: 0; opacity: 0; overflow: hidden; }
.slide-enter-to, .slide-leave-from { max-height: 400px; opacity: 1; }
</style>
