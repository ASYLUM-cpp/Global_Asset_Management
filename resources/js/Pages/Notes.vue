<template>
  <AppLayout>
    <div class="flex h-[calc(100vh-64px-48px)]">
      <!-- LEFT SIDEBAR - Trilium Note Tree -->
      <aside
        :class="['flex-shrink-0 border-r border-slate-200 dark:border-slate-700/40 flex flex-col bg-white/80 dark:bg-slate-900/80 backdrop-blur-sm overflow-hidden transition-all duration-500 ease-in-out',
        mobileSidebar ? 'fixed inset-y-0 left-0 z-[100] shadow-2xl w-80' : '',
        !mobileSidebar && sidebarOpen ? 'w-80' : '',
        !mobileSidebar && !sidebarOpen ? 'w-0 border-r-0' : '']">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-700/40 min-w-[320px]">
          <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
            <i class="ri-sticky-note-line text-amber-500"></i> Note Tree
          </h3>
          <div class="flex items-center gap-1">
            <button @click="expandAll" title="Expand all" class="w-6 h-6 rounded hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center"><i class="ri-arrow-down-s-line text-xs text-slate-400"></i></button>
            <button @click="collapseAll" title="Collapse all" class="w-6 h-6 rounded hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center"><i class="ri-arrow-up-s-line text-xs text-slate-400"></i></button>
            <button @click="refreshTree" title="Refresh" class="w-6 h-6 rounded hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center"><i class="ri-refresh-line text-xs text-slate-400"></i></button>
            <button @click="sidebarOpen = false; mobileSidebar = false" class="w-6 h-6 rounded hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center md:hidden"><i class="ri-close-line text-xs text-slate-400"></i></button>
          </div>
        </div>
        <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-700/40">
          <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-800/50 rounded-lg px-2.5 py-1.5">
            <i class="ri-search-line text-[10px] text-slate-400"></i>
            <input v-model="treeSearch" type="text" placeholder="Filter notes..." class="flex-1 text-[10px] bg-transparent border-none outline-none placeholder-slate-400 dark:placeholder-slate-500" />
          </div>
        </div>
        <div class="flex-1 overflow-y-auto px-2 py-2 min-w-[320px]">
          <div v-if="!filteredTree.length" class="py-6 text-center">
            <i class="ri-folder-open-line text-2xl text-slate-300 dark:text-slate-600"></i>
            <p class="text-[10px] text-slate-400 mt-1">No notes found</p>
          </div>
          <NoteTreeItem v-for="node in filteredTree" :key="node.id"
            :node="node" :depth="0" :expanded="expandedNodes" :selected="selectedNoteId"
            @select="onTreeNodeSelect" @toggle="toggleNode" />
        </div>
      </aside>
      <div v-if="mobileSidebar" class="fixed inset-0 bg-black/30 z-[99] md:hidden" @click="mobileSidebar = false"></div>

      <!-- MAIN CONTENT -->
      <div class="flex-1 flex flex-col overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/40 flex-shrink-0 flex-wrap gap-2">
          <div class="flex items-center gap-3">
            <button @click="mobileSidebar = true" class="md:hidden w-8 h-8 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center"><i class="ri-menu-line text-slate-500"></i></button>
            <button @click="sidebarOpen = !sidebarOpen" class="hidden md:flex w-8 h-8 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 items-center justify-center"><i :class="sidebarOpen ? 'ri-layout-left-line' : 'ri-layout-right-line'" class="text-slate-500"></i></button>
            <div>
              <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Notes</h1>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Team annotations, comments, and review notes</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <IntegrationHealthBadge service="trilium" :status="props.triliumHealth?.status" :latency-ms="props.triliumHealth?.latencyMs" />
            <div class="flex items-center bg-slate-100 dark:bg-slate-800 rounded-xl p-0.5">
              <button @click="viewMode = 'feed'" :class="['px-3 py-1.5 rounded-lg text-[10px] font-semibold transition-all', viewMode === 'feed' ? 'bg-white dark:bg-slate-700 text-amber-600 shadow-sm' : 'text-slate-400 hover:text-slate-600']"><i class="ri-list-unordered mr-0.5"></i> Feed</button>
              <button @click="viewMode = 'graph'; loadKnowledgeGraph()" :class="['px-3 py-1.5 rounded-lg text-[10px] font-semibold transition-all', viewMode === 'graph' ? 'bg-white dark:bg-slate-700 text-amber-600 shadow-sm' : 'text-slate-400 hover:text-slate-600']"><i class="ri-mind-map mr-0.5"></i> Knowledge Graph</button>
            </div>
          </div>
        </div>

        <div ref="feedContainerRef" class="flex-1 overflow-y-auto p-6">
          <!-- FEED VIEW -->
          <template v-if="viewMode === 'feed'">
            <!-- Quick compose -->
            <div class="glass rounded-2xl p-5 mb-6 anim-enter relative z-10" data-delay="60">
              <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center text-xs font-bold text-white flex-shrink-0">SC</div>
                <div class="flex-1 relative">
                  <textarea ref="composeRef" v-model="noteText" @input="onComposeInput" @keydown="onComposeKeydown" rows="3" placeholder="Write a note... Tag assets with @, mention team with #" class="w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 bg-white dark:bg-slate-800 resize-none focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-300 transition-all"></textarea>
                  <!-- Autocomplete dropdown (opens below textarea) -->
                  <div v-if="autocomplete.show" class="absolute left-0 right-0 z-[200] top-full mt-1 glass rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 max-h-64 flex flex-col">
                    <div class="px-3 pt-2.5 pb-2 border-b border-slate-100 dark:border-slate-700/40 flex-shrink-0">
                      <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">{{ autocomplete.type === '@' ? 'Tag an Asset' : 'Mention Team Member' }}</p>
                      <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-800/50 rounded-lg px-2.5 py-1.5">
                        <i class="ri-search-line text-[10px] text-slate-400"></i>
                        <input ref="acSearchRef" v-model="autocomplete.popupSearch" @input="onPopupSearchInput" @keydown="onPopupSearchKeydown" type="text" :placeholder="autocomplete.type === '@' ? 'Search assets...' : 'Search members...'" class="flex-1 text-[10px] bg-transparent border-none outline-none placeholder-slate-400 dark:placeholder-slate-500" />
                        <span class="text-[8px] text-slate-400">{{ acFilteredItems.length }}</span>
                      </div>
                    </div>
                    <div class="flex-1 overflow-y-auto">
                      <div v-if="autocomplete.loading" class="py-4 text-center"><i class="ri-loader-4-line animate-spin text-amber-400"></i></div>
                      <div v-else-if="acFilteredItems.length === 0" class="py-4 text-center"><p class="text-[10px] text-slate-400">No results</p></div>
                      <div v-else>
                        <div v-for="(item, idx) in acFilteredItems" :key="item.id"
                          :class="['flex items-center gap-2.5 px-3 py-2 cursor-pointer transition-colors', idx === autocomplete.activeIndex ? 'bg-amber-50 dark:bg-amber-500/10' : 'hover:bg-slate-50 dark:hover:bg-slate-800/50']"
                          @click="selectAutocompleteItem(item)" @mouseenter="autocomplete.activeIndex = idx">
                          <template v-if="autocomplete.type === '@'">
                            <div class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center flex-shrink-0">
                              <img v-if="item.thumbnail" :src="'/serve/thumbnail/' + item.id" class="w-full h-full object-cover rounded-lg" />
                              <i v-else class="ri-file-line text-[10px] text-slate-400"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                              <p class="text-[10px] font-semibold text-slate-700 dark:text-slate-200 truncate">{{ item.name }}</p>
                              <span class="text-[8px] px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700/50 text-slate-500">{{ item.group }}</span>
                            </div>
                          </template>
                          <template v-else>
                            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center text-[9px] font-bold text-white flex-shrink-0">{{ item.initials }}</div>
                            <div class="min-w-0 flex-1">
                              <p class="text-[10px] font-semibold text-slate-700 dark:text-slate-200 truncate">{{ item.name }}</p>
                              <span class="text-[8px] text-slate-400">{{ item.email }}</span>
                            </div>
                          </template>
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Selected tag display -->
                  <div v-if="selectedTag" class="flex items-center gap-1.5 mt-2">
                    <span class="text-[9px] text-slate-400">Tag:</span>
                    <span class="text-[9px] font-bold px-2 py-0.5 rounded-full" :class="(props.availableTags.find(t => t.name === selectedTag) || {}).class || 'bg-slate-500/80 text-white'">{{ selectedTag }}</span>
                    <button @click="selectedTag = null" class="text-slate-400 hover:text-red-400 transition-colors"><i class="ri-close-line text-[10px]"></i></button>
                  </div>
                  <div class="flex items-center justify-between mt-2">
                    <div class="flex items-center gap-2">
                      <button @click="triggerAutocomplete('@')" class="text-slate-400 dark:text-slate-500 hover:text-amber-500 transition-colors"><i class="ri-at-line text-sm"></i></button>
                      <button @click="triggerAutocomplete('#')" class="text-slate-400 dark:text-slate-500 hover:text-amber-500 transition-colors"><i class="ri-hashtag text-sm"></i></button>
                      <button @click="insertAtCursor('[file]')" class="text-slate-400 dark:text-slate-500 hover:text-amber-500 transition-colors"><i class="ri-attachment-2 text-sm"></i></button>
                      <button @click="insertAtCursor('😊')" class="text-slate-400 dark:text-slate-500 hover:text-amber-500 transition-colors"><i class="ri-emotion-line text-sm"></i></button>
                      <!-- Tag picker toggle -->
                      <div class="relative">
                        <button @click="showTagPicker = !showTagPicker" :class="['transition-colors', selectedTag ? 'text-amber-500' : 'text-slate-400 dark:text-slate-500 hover:text-amber-500']" title="Add tag"><i class="ri-price-tag-3-line text-sm"></i></button>
                        <div v-if="showTagPicker" class="absolute left-0 bottom-full mb-2 z-50 glass rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 p-2 min-w-[180px]">
                          <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-1.5 px-1">Choose a Tag</p>
                          <div class="flex flex-wrap gap-1">
                            <button v-for="tag in props.availableTags" :key="tag.name"
                              @click="selectedTag = tag.name; showTagPicker = false"
                              :class="['text-[9px] font-bold px-2 py-1 rounded-full transition-all hover:scale-105', tag.class, selectedTag === tag.name ? 'ring-2 ring-offset-1 ring-amber-400' : 'opacity-80 hover:opacity-100']">
                              {{ tag.name }}
                            </button>
                          </div>
                          <button v-if="selectedTag" @click="selectedTag = null; showTagPicker = false" class="mt-1.5 w-full text-[9px] text-slate-400 hover:text-red-400 transition-colors text-center py-0.5">Clear tag</button>
                        </div>
                      </div>
                    </div>
                    <button @click="postNote" class="px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-white text-[10px] font-bold shadow-md shadow-amber-200/50 dark:shadow-amber-500/10 hover:-translate-y-0.5 transition-all duration-300">Post Note</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Error banner -->
            <div v-if="props.error" class="glass rounded-2xl p-5 mb-5 border border-amber-200 dark:border-amber-500/30 anim-enter" data-delay="90">
              <div class="flex items-center gap-3">
                <i class="ri-alert-line text-lg text-amber-500"></i>
                <div>
                  <p class="text-xs font-bold text-slate-700 dark:text-slate-200">Could not load notes</p>
                  <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">{{ props.error }}</p>
                </div>
              </div>
            </div>

            <!-- Empty state -->
            <div v-if="notesList.length === 0 && !props.error" class="glass rounded-2xl p-12 text-center anim-enter" data-delay="120">
              <i class="ri-sticky-note-line text-5xl text-slate-300 dark:text-slate-600 mb-3"></i>
              <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">No notes yet</p>
              <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Start a conversation by posting a note. Tag assets and mention team members.</p>
            </div>

            <!-- Notes Feed — grouped by category -->
            <div v-else class="space-y-6">
              <div v-for="group in groupedNotes" :key="group.tag">
                <!-- Section header -->
                <div class="flex items-center gap-2.5 mb-3">
                  <span :class="['text-[10px] font-bold px-2.5 py-1 rounded-full', group.tagClass]">{{ group.tag }}</span>
                  <div class="flex-1 h-px bg-slate-200 dark:bg-slate-700/50"></div>
                  <span class="text-[9px] text-slate-400 dark:text-slate-500 font-medium">{{ group.notes.length }} note{{ group.notes.length !== 1 ? 's' : '' }}</span>
                </div>
                <!-- Notes in this group -->
                <div class="space-y-4">
              <div v-for="(note, ni) in group.notes" :key="note.id"
                :ref="el => { if (el) noteRefs[note.id] = el; }"
                :class="['glass rounded-2xl p-5 hover-lift transition-all duration-500 note-fade-in', selectedNoteId === note.id ? 'ring-2 ring-amber-400/60 shadow-amber-100 dark:shadow-amber-500/10' : '']"
                :style="{ animationDelay: (ni * 50) + 'ms' }"
              >
                <div class="flex items-start gap-3">
                  <div :class="['w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0', note.avatarBg]">{{ note.initials }}</div>
                  <div class="flex-1">
                    <div class="flex items-center gap-2">
                      <p class="text-xs font-bold text-slate-800 dark:text-slate-100">{{ note.author }}</p>
                      <span class="text-[10px] text-slate-400 dark:text-slate-500">{{ note.time }}</span>
                      <span v-if="note.tag" :class="['text-[9px] font-bold px-2 py-0.5 rounded-full', note.tagClass]">{{ note.tag }}</span>
                    </div>
                    <p class="text-xs text-slate-600 dark:text-slate-300 mt-1.5 leading-relaxed">{{ note.content }}</p>

                    <!-- Linked asset from note data -->
                    <div v-if="note.asset" class="mt-3 px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700/40 flex items-center gap-2.5">
                      <div :class="['w-8 h-8 rounded-lg flex items-center justify-center', note.asset.bg]">
                        <i :class="[note.asset.icon, 'text-xs text-white']"></i>
                      </div>
                      <div>
                        <p class="text-[10px] font-semibold text-slate-700 dark:text-slate-200">{{ note.asset.name }}</p>
                        <p class="text-[9px] text-slate-400 dark:text-slate-500">{{ note.asset.type }}</p>
                      </div>
                    </div>

                    <!-- Linked assets panel (collapsible) -->
                    <div class="mt-2">
                      <button @click="toggleNoteAssets(note.id)" class="text-[10px] text-amber-500 hover:text-amber-600 font-semibold flex items-center gap-1 transition-colors">
                        <i :class="noteAssetsOpen[note.id] ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'"></i>
                        <i class="ri-links-line"></i> Linked Assets
                        <span v-if="noteAssetCounts[note.id]" class="ml-0.5 bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 px-1.5 py-0 rounded-full text-[8px]">{{ noteAssetCounts[note.id] }}</span>
                      </button>
                      <div v-if="noteAssetsOpen[note.id]" class="mt-2">
                        <div v-if="noteAssetsLoading[note.id]" class="py-3 text-center"><i class="ri-loader-4-line animate-spin text-sm text-amber-400"></i></div>
                        <div v-else-if="!noteAssetsData[note.id]?.length" class="flex items-center gap-2 py-2">
                          <p class="text-[10px] text-slate-400">No linked assets</p>
                          <button v-if="isAdmin" @click="openLinkModal(note)" class="text-[10px] text-amber-500 hover:text-amber-600 font-semibold"><i class="ri-add-line"></i> Link</button>
                        </div>
                        <div v-else class="grid grid-cols-2 md:grid-cols-3 gap-2">
                          <div v-for="asset in noteAssetsData[note.id]" :key="asset.id" class="px-2.5 py-2 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700/40 flex items-center gap-2 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" @click="router.visit('/preview/' + asset.id)">
                            <div v-if="asset.thumbnail_path" class="w-7 h-7 rounded bg-slate-100 dark:bg-slate-700 overflow-hidden flex-shrink-0"><img :src="'/serve/thumbnail/' + asset.id" class="w-full h-full object-cover" /></div>
                            <div v-else class="w-7 h-7 rounded bg-slate-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0"><i class="ri-file-line text-[10px] text-slate-400"></i></div>
                            <div class="min-w-0 flex-1">
                              <p class="text-[9px] font-semibold text-slate-700 dark:text-slate-200 truncate">{{ asset.original_filename }}</p>
                              <span class="text-[7px] px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400">{{ asset.group_classification }}</span>
                            </div>
                            <button v-if="isAdmin" @click.stop="unlinkNoteAsset(note.id, asset.link_id)" class="text-[9px] text-rose-400 hover:text-rose-600 flex-shrink-0"><i class="ri-unlink"></i></button>
                          </div>
                          <button v-if="isAdmin" @click="openLinkModal(note)" class="px-2.5 py-2 rounded-lg border-2 border-dashed border-slate-200 dark:border-slate-700 flex items-center justify-center gap-1 text-[10px] text-slate-400 hover:text-amber-500 hover:border-amber-300 transition-all">
                            <i class="ri-add-line"></i> Link
                          </button>
                        </div>
                      </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-4 mt-3">
                      <button @click="likeNote(note)" :class="['text-[10px] transition-colors flex items-center gap-1', note.liked ? 'text-rose-500' : 'text-slate-400 dark:text-slate-500 hover:text-rose-500']">
                        <i :class="note.liked ? 'ri-heart-fill' : 'ri-heart-line'"></i> {{ note.likes }}
                      </button>
                      <button @click="toggleReply(note)" :class="['text-[10px] transition-colors flex items-center gap-1', replyingTo === note.id ? 'text-amber-500' : 'text-slate-400 dark:text-slate-500 hover:text-amber-500']">
                        <i class="ri-reply-line"></i> Reply
                      </button>
                    </div>

                    <!-- Inline reply form -->
                    <div v-if="replyingTo === note.id" class="mt-3 pl-1 border-l-2 border-amber-200 dark:border-amber-500/30">
                      <textarea v-model="replyText" rows="2" placeholder="Write a reply..." class="w-full text-[11px] border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 bg-white dark:bg-slate-800 resize-none focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-300 transition-all"></textarea>
                      <div class="flex items-center justify-end gap-2 mt-1.5">
                        <button @click="replyingTo = null; replyText = ''" class="text-[10px] text-slate-400 hover:text-slate-600 transition-colors">Cancel</button>
                        <button @click="submitReply(note)" :disabled="!replyText.trim() || submittingReply" class="px-3 py-1.5 rounded-lg bg-gradient-to-r from-amber-500 to-orange-500 text-white text-[10px] font-bold shadow-sm hover:-translate-y-0.5 transition-all duration-300 disabled:opacity-40 disabled:hover:translate-y-0">
                          <i v-if="submittingReply" class="ri-loader-4-line animate-spin mr-0.5"></i>
                          {{ submittingReply ? 'Sending...' : 'Reply' }}
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
                </div><!-- /space-y-4 inner group -->
              </div><!-- /v-for group -->
            </div>
          </template>

          <!-- KNOWLEDGE GRAPH VIEW -->
          <template v-if="viewMode === 'graph'">
            <div class="flex items-center gap-3 mb-4">
              <label class="flex items-center gap-1.5 text-[10px] text-slate-500 cursor-pointer"><input type="checkbox" v-model="graphFilters.notes" class="rounded border-slate-300 text-amber-500 focus:ring-amber-500/30 w-3.5 h-3.5" /> Notes</label>
              <label class="flex items-center gap-1.5 text-[10px] text-slate-500 cursor-pointer"><input type="checkbox" v-model="graphFilters.assets" class="rounded border-slate-300 text-green-500 focus:ring-green-500/30 w-3.5 h-3.5" /> Assets</label>
              <label class="flex items-center gap-1.5 text-[10px] text-slate-500 cursor-pointer"><input type="checkbox" v-model="graphFilters.docs" class="rounded border-slate-300 text-indigo-500 focus:ring-indigo-500/30 w-3.5 h-3.5" /> Documents</label>
            </div>
            <div v-if="graphLoading" class="glass rounded-2xl p-12 text-center">
              <i class="ri-loader-4-line animate-spin text-3xl text-amber-400"></i>
              <p class="text-xs text-slate-400 mt-2">Loading knowledge graph...</p>
            </div>
            <div v-else-if="filteredGraphNodes.length === 0" class="glass rounded-2xl p-12 text-center">
              <i class="ri-mind-map text-5xl text-slate-300 dark:text-slate-600 mb-3"></i>
              <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">No relationships yet</p>
              <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Link assets to notes or documents to see the knowledge graph.</p>
            </div>
            <div v-else class="glass rounded-2xl overflow-hidden" style="height: 600px;">
              <VueFlow :nodes="filteredGraphNodes" :edges="filteredGraphEdges" :fit-view-on-init="true" @node-click="onGraphNodeClick">
                <Background />
                <Controls />
              </VueFlow>
            </div>
          </template>
        </div>
      </div>
    </div>

    <AssetLinkModal :show="showLinkModal" context="trilium" :target-id="linkModalTargetId" :target-title="linkModalTargetTitle" @close="showLinkModal = false" @linked="onAssetLinked" />
  </AppLayout>
</template>

<script setup>
import { ref, computed, reactive, nextTick, watch, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import IntegrationHealthBadge from '@/Components/IntegrationHealthBadge.vue';
import AssetLinkModal from '@/Components/AssetLinkModal.vue';
import NoteTreeItem from '@/Components/NoteTreeItem.vue';
import { router, usePage } from '@inertiajs/vue3';
import { useScrollReveal } from '@/composables/useAnimations';
import { VueFlow } from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';

useScrollReveal();

// Tailwind safelist – tag colours applied dynamically from the backend.
// These must appear in source so Tailwind JIT generates the CSS rules.
// prettier-ignore
const _tagSafelist = [
  'bg-rose-500/80', 'bg-violet-500/80', 'bg-amber-500/80', 'bg-sky-500/80',
  'bg-indigo-500/80', 'bg-emerald-500/80', 'bg-teal-500/80', 'bg-cyan-500/80',
  'bg-fuchsia-500/80', 'bg-slate-500/80',
];

const page = usePage();
const isAdmin = computed(() => (page.props.auth?.user?.roles || []).includes('Admin'));

const props = defineProps({
  notes: { type: Array, default: () => [] },
  error: { type: String, default: null },
  noteTree: { type: [Array, Object], default: () => [] },
  triliumHealth: { type: Object, default: () => ({ status: 'unknown', latencyMs: null }) },
  teamMembers: { type: Array, default: () => [] },
  availableTags: { type: Array, default: () => [] },
});

const sidebarOpen = ref(true);
const mobileSidebar = ref(false);
const treeSearch = ref('');
const expandedNodes = ref(new Set());
const selectedNoteId = ref(null);
const viewMode = ref('feed');
const noteRefs = reactive({});
const composeRef = ref(null);
const feedContainerRef = ref(null);
const selectedTag = ref(null);
const showTagPicker = ref(false);

const notesList = computed(() => {
  return (props.notes || []).map(n => ({
    ...n,
    content: typeof n.content === 'string' ? n.content : (n.content != null ? String(n.content) : ''),
  }));
});

// Group notes by tag for sectioned display
const groupedNotes = computed(() => {
  const groups = {};
  for (const note of notesList.value) {
    const tag = note.tag || 'General';
    if (!groups[tag]) groups[tag] = { tag, tagClass: note.tagClass || 'bg-slate-500/80 text-white', notes: [] };
    groups[tag].notes.push(note);
  }
  // Sort: named categories first (alphabetical), "General" last
  return Object.values(groups).sort((a, b) => {
    if (a.tag === 'General') return 1;
    if (b.tag === 'General') return -1;
    return a.tag.localeCompare(b.tag);
  });
});
const noteText = ref('');
const replyingTo = ref(null);
const replyText = ref('');
const submittingReply = ref(false);

// ── Autocomplete state ──
const autocomplete = reactive({
  show: false,
  type: '', // '@' or '#'
  query: '',
  allItems: [],     // full unfiltered list
  popupSearch: '',  // search bar inside popup
  loading: false,
  activeIndex: 0,
  triggerPos: -1,
});
const acSearchRef = ref(null);
let acDebounce = null;

// Filtered items based on popup search bar
const acFilteredItems = computed(() => {
  const q = autocomplete.popupSearch.trim().toLowerCase();
  if (!q) return autocomplete.allItems;
  if (autocomplete.type === '@') {
    return autocomplete.allItems.filter(a =>
      (a.name || '').toLowerCase().includes(q) || (a.group || '').toLowerCase().includes(q)
    );
  } else {
    return autocomplete.allItems.filter(m =>
      (m.name || '').toLowerCase().includes(q) || (m.email || '').toLowerCase().includes(q)
    );
  }
});

function onComposeInput() {
  const el = composeRef.value;
  if (!el) return;
  const text = noteText.value;
  const cursor = el.selectionStart;

  let triggerChar = null;
  let triggerPos = -1;
  for (let i = cursor - 1; i >= 0; i--) {
    const ch = text[i];
    if (ch === ' ' || ch === '\n') break;
    if (ch === '@' || ch === '#') {
      triggerChar = ch;
      triggerPos = i;
      break;
    }
  }

  if (triggerChar && triggerPos >= 0) {
    autocomplete.type = triggerChar;
    autocomplete.triggerPos = triggerPos;
    autocomplete.query = text.substring(triggerPos + 1, cursor);
    autocomplete.activeIndex = 0;
    clearTimeout(acDebounce);
    acDebounce = setTimeout(() => loadAutocompleteItems(triggerChar), 200);
  } else {
    autocomplete.show = false;
  }
}

function onComposeKeydown(e) {
  if (!autocomplete.show) return;
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    autocomplete.activeIndex = Math.min(autocomplete.activeIndex + 1, acFilteredItems.value.length - 1);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    autocomplete.activeIndex = Math.max(autocomplete.activeIndex - 1, 0);
  } else if (e.key === 'Enter' || e.key === 'Tab') {
    if (acFilteredItems.value.length > 0) {
      e.preventDefault();
      selectAutocompleteItem(acFilteredItems.value[autocomplete.activeIndex]);
    }
  } else if (e.key === 'Escape') {
    autocomplete.show = false;
  }
}

// Keyboard navigation inside the popup search input
function onPopupSearchKeydown(e) {
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    autocomplete.activeIndex = Math.min(autocomplete.activeIndex + 1, acFilteredItems.value.length - 1);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    autocomplete.activeIndex = Math.max(autocomplete.activeIndex - 1, 0);
  } else if (e.key === 'Enter') {
    if (acFilteredItems.value.length > 0) {
      e.preventDefault();
      selectAutocompleteItem(acFilteredItems.value[autocomplete.activeIndex]);
    }
  } else if (e.key === 'Escape') {
    autocomplete.show = false;
    composeRef.value?.focus();
  }
}

function onPopupSearchInput() {
  autocomplete.activeIndex = 0;
}

async function loadAutocompleteItems(type) {
  autocomplete.loading = true;
  autocomplete.show = true;
  autocomplete.popupSearch = '';
  try {
    if (type === '@') {
      const resp = await fetch('/search?q=', {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await resp.json();
      autocomplete.allItems = (data.data || data || []).slice(0, 50).map(a => ({
        id: a.id,
        name: a.name || a.original_filename,
        group: a.group || a.group_classification,
        thumbnail: a.thumbnail || a.thumbnail_path,
      }));
    } else {
      // # → show all team members immediately
      autocomplete.allItems = (props.teamMembers || []).map(m => ({
        id: m.id,
        name: m.name,
        email: m.email,
        initials: m.initials || (m.name ? m.name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase() : '??'),
      }));
    }
  } catch {
    autocomplete.allItems = [];
  } finally {
    autocomplete.loading = false;
    // Focus the popup search bar after items load
    nextTick(() => acSearchRef.value?.focus());
  }
}

function selectAutocompleteItem(item) {
  const text = noteText.value;
  const before = text.substring(0, autocomplete.triggerPos);
  const after = text.substring(composeRef.value?.selectionStart || autocomplete.triggerPos + autocomplete.query.length + 1);
  const insertText = autocomplete.type === '@' ? '@' + item.name + ' ' : '#' + item.name + ' ';
  noteText.value = before + insertText + after;
  autocomplete.show = false;
  nextTick(() => {
    const pos = before.length + insertText.length;
    composeRef.value?.setSelectionRange(pos, pos);
    composeRef.value?.focus();
  });
}

function triggerAutocomplete(char) {
  const cursorPos = composeRef.value?.selectionStart ?? noteText.value.length;
  const before = noteText.value.substring(0, cursorPos);
  const after = noteText.value.substring(cursorPos);
  noteText.value = before + char + after;
  autocomplete.type = char;
  autocomplete.triggerPos = cursorPos;
  autocomplete.query = '';
  autocomplete.activeIndex = 0;
  nextTick(() => {
    const pos = cursorPos + 1;
    composeRef.value?.focus();
    composeRef.value?.setSelectionRange(pos, pos);
    loadAutocompleteItems(char);
  });
}

const noteAssetsOpen = reactive({});
const noteAssetsData = reactive({});
const noteAssetsLoading = reactive({});
const noteAssetCounts = reactive({});

const showLinkModal = ref(false);
const linkModalTargetId = ref('');
const linkModalTargetTitle = ref('');

const graphNodes = ref([]);
const graphEdges = ref([]);
const graphLoading = ref(false);
const graphFilters = reactive({ notes: true, assets: true, docs: true });

function filterTree(nodes, query) {
  if (!query.trim()) return nodes;
  const q = query.toLowerCase();
  return nodes.reduce((acc, node) => {
    const match = (node.title || '').toLowerCase().includes(q);
    const fc = node.children ? filterTree(node.children, query) : [];
    if (match || fc.length > 0) acc.push({ ...node, children: fc.length > 0 ? fc : (node.children || []) });
    return acc;
  }, []);
}

// Normalize noteTree: backend may return a single root object or an array
const normalizedTree = computed(() => {
  const raw = props.noteTree;
  if (!raw) return [];
  if (Array.isArray(raw)) return raw;
  // Single root object — use its children as top-level items
  if (raw.children && Array.isArray(raw.children)) return raw.children;
  return [raw];
});

const filteredTree = computed(() => filterTree(normalizedTree.value, treeSearch.value));

function toggleNode(nodeKey) {
  const s = new Set(expandedNodes.value);
  s.has(nodeKey) ? s.delete(nodeKey) : s.add(nodeKey);
  expandedNodes.value = s;
}

function expandAll() {
  const keys = new Set();
  (function walk(ns) { for (const n of ns) { if (n.children?.length) { keys.add('note-' + n.id); walk(n.children); } } })(normalizedTree.value);
  expandedNodes.value = keys;
}
function collapseAll() { expandedNodes.value = new Set(); }

function onTreeNodeSelect(node) {
  selectedNoteId.value = node.id;
  if (viewMode.value === 'feed') {
    nextTick(() => {
      const raw = noteRefs[node.id];
      const el = raw?.$el || raw;
      const container = feedContainerRef.value;
      if (el && container) {
        const elRect = el.getBoundingClientRect();
        const cRect = container.getBoundingClientRect();
        const offset = elRect.top - cRect.top + container.scrollTop - container.clientHeight / 2 + el.offsetHeight / 2;
        container.scrollTo({ top: Math.max(0, offset), behavior: 'smooth' });
      }
    });
  }
}

function refreshTree() {
  router.post('/notes/refresh-cache', {}, { preserveScroll: true, onSuccess: () => router.visit('/notes', { preserveScroll: true }) });
}

function postNote() {
  if (!noteText.value.trim()) return;
  const payload = { content: noteText.value };
  if (selectedTag.value) payload.tag = selectedTag.value;
  router.post('/notes', payload, {
    preserveScroll: true,
    onSuccess: () => { noteText.value = ''; selectedTag.value = null; showTagPicker.value = false; },
  });
}

function insertAtCursor(text) {
  noteText.value += text;
}

function likeNote(note) {
  router.post('/notes/' + note.id + '/like', {}, { preserveScroll: true });
}

function toggleReply(note) {
  if (replyingTo.value === note.id) {
    replyingTo.value = null;
    replyText.value = '';
  } else {
    replyingTo.value = note.id;
    replyText.value = '';
  }
}

function submitReply(note) {
  if (!replyText.value.trim()) return;
  submittingReply.value = true;
  router.post('/notes/' + note.id + '/reply', { content: replyText.value }, {
    preserveScroll: true,
    onSuccess: () => { replyingTo.value = null; replyText.value = ''; submittingReply.value = false; },
    onError: () => { submittingReply.value = false; },
  });
}

function toggleNoteAssets(noteId) {
  noteAssetsOpen[noteId] = !noteAssetsOpen[noteId];
  if (noteAssetsOpen[noteId] && !noteAssetsData[noteId]) {
    fetchNoteAssets(noteId);
  }
}

function fetchNoteAssets(noteId) {
  noteAssetsLoading[noteId] = true;
  fetch('/notes/' + noteId + '/assets', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(data => { noteAssetsData[noteId] = data || []; noteAssetCounts[noteId] = (data || []).length; })
    .catch(() => { noteAssetsData[noteId] = []; })
    .finally(() => { noteAssetsLoading[noteId] = false; });
}

function openLinkModal(note) {
  linkModalTargetId.value = note.id;
  linkModalTargetTitle.value = note.title || note.content?.substring(0, 40) || 'Note';
  showLinkModal.value = true;
}

function onAssetLinked() {
  showLinkModal.value = false;
  if (linkModalTargetId.value) fetchNoteAssets(linkModalTargetId.value);
}

function unlinkNoteAsset(noteId, linkId) {
  router.delete('/notes/link-asset/' + linkId, { preserveScroll: true, onSuccess: () => fetchNoteAssets(noteId) });
}

function loadKnowledgeGraph() {
  graphLoading.value = true;
  fetch('/notes/knowledge-graph', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(data => { graphNodes.value = data.nodes || []; graphEdges.value = data.edges || []; })
    .catch(() => { graphNodes.value = []; graphEdges.value = []; })
    .finally(() => { graphLoading.value = false; });
}

const filteredGraphNodes = computed(() => {
  return graphNodes.value.filter(n => {
    if (n.data?.nodeType === 'note') return graphFilters.notes;
    if (n.data?.nodeType === 'asset') return graphFilters.assets;
    if (n.data?.nodeType === 'page') return graphFilters.docs;
    return true;
  });
});

const filteredGraphEdges = computed(() => {
  const visibleIds = new Set(filteredGraphNodes.value.map(n => n.id));
  return graphEdges.value.filter(e => visibleIds.has(e.source) && visibleIds.has(e.target));
});

function onGraphNodeClick({ node }) {
  if (node.data?.nodeType === 'note') {
    selectedNoteId.value = node.data.noteId || node.data.id;
    viewMode.value = 'feed';
    nextTick(() => {
      const el = noteRefs[node.data.noteId || node.data.id];
      if (el?.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  } else if (node.data?.nodeType === 'asset') {
    router.visit('/preview/' + node.data.assetId);
  } else if (node.data?.nodeType === 'page') {
    router.visit('/documents');
  }
}
</script>

<style scoped>
.note-fade-in {
  animation: noteFadeIn 0.5s cubic-bezier(.22, 1, .36, 1) both;
}
@keyframes noteFadeIn {
  from { opacity: 0; transform: translateY(16px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
