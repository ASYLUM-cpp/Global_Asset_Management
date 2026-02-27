<template>
  <div>
    <div
      :class="['flex items-center gap-1.5 px-2 py-1 rounded-lg cursor-pointer text-[11px] transition-all duration-200 group',
        isSelected ? 'bg-amber-50 dark:bg-amber-500/10 ring-2 ring-amber-500/40 text-amber-700 dark:text-amber-300' : 'hover:bg-slate-50 dark:hover:bg-slate-800/50 text-slate-600 dark:text-slate-400']"
      :style="{ paddingLeft: (depth * 16 + 8) + 'px' }"
      @click="handleClick"
    >
      <i v-if="hasChildren" :class="['text-[10px] text-slate-300 dark:text-slate-600 transition-transform', isExpanded ? 'ri-arrow-down-s-line' : 'ri-arrow-right-s-line']"></i>
      <span v-else class="w-3 inline-block"></span>
      <i :class="[nodeIcon, 'text-xs text-amber-500']"></i>
      <span class="truncate flex-1 font-medium">{{ node.title || node.name }}</span>
    </div>
    <template v-if="hasChildren && isExpanded">
      <NoteTreeItem v-for="child in node.children" :key="child.id"
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
  selected: { type: [String, null], default: null },
});

const emit = defineEmits(['select', 'toggle']);

const iconMap = {
  text: 'ri-file-text-line',
  code: 'ri-code-line',
  image: 'ri-image-line',
  file: 'ri-attachment-2',
  search: 'ri-search-line',
  book: 'ri-book-line',
};

const nodeKey = computed(() => 'note-' + props.node.id);
const hasChildren = computed(() => (props.node.children || []).length > 0);
const isExpanded = computed(() => props.expanded.has(nodeKey.value));
const isSelected = computed(() => props.selected === props.node.id);
const nodeIcon = computed(() => props.node.icon || iconMap[props.node.type] || 'ri-file-text-line');

function handleClick() {
  if (hasChildren.value) emit('toggle', nodeKey.value);
  emit('select', props.node);
}
</script>
