<template>
  <div>
    <div
      :class="['flex items-center gap-1.5 px-2 py-1 rounded-lg cursor-pointer text-[11px] transition-all duration-200 group',
        isSelected ? 'bg-indigo-50 dark:bg-indigo-500/10 ring-2 ring-indigo-500/40 text-indigo-700 dark:text-indigo-300' : 'hover:bg-slate-50 dark:hover:bg-slate-800/50 text-slate-600 dark:text-slate-400']"
      :style="{ paddingLeft: (depth * 16 + 8) + 'px' }"
      @click="handleClick"
    >
      <i v-if="hasChildren" :class="['text-[10px] text-slate-300 dark:text-slate-600 transition-transform', isExpanded ? 'ri-arrow-down-s-line' : 'ri-arrow-right-s-line']"></i>
      <span v-else class="w-3 inline-block"></span>
      <i :class="[nodeIcon, nodeColor, 'text-xs']"></i>
      <span class="truncate flex-1 font-medium">{{ node.name || node.title }}</span>
    </div>
    <template v-if="hasChildren && isExpanded">
      <DocTreeItem v-for="child in node.children" :key="child.type + '-' + child.id"
        :node="child" :depth="depth + 1"
        :expanded="expanded" :selected="selected"
        @select="$emit('select', $event)"
        @toggle="$emit('toggle', $event)" />
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  node: { type: Object, required: true },
  depth: { type: Number, default: 0 },
  expanded: { type: Object, required: true },
  selected: { type: [Number, null], default: null },
});

const emit = defineEmits(['select', 'toggle']);

const iconMap = {
  shelf: 'ri-folder-shield-2-line',
  book: 'ri-book-open-line',
  chapter: 'ri-bookmark-line',
  page: 'ri-file-text-line',
};
const colorMap = {
  shelf: 'text-amber-500',
  book: 'text-indigo-500',
  chapter: 'text-emerald-500',
  page: 'text-slate-400',
};

const nodeKey = computed(() => props.node.type + '-' + props.node.id);
const hasChildren = computed(() => (props.node.children || []).length > 0);
const isExpanded = computed(() => props.expanded.has(nodeKey.value));
const isSelected = computed(() => props.node.type === 'page' && props.selected === props.node.id);
const nodeIcon = computed(() => iconMap[props.node.type] || 'ri-file-text-line');
const nodeColor = computed(() => colorMap[props.node.type] || 'text-slate-400');

function handleClick() {
  if (hasChildren.value) emit('toggle', nodeKey.value);
  emit('select', props.node);
}
</script>
