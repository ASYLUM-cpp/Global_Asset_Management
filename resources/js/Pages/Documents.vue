<template>
  <AppLayout>
    <div class="flex h-[calc(100vh-64px)]">
      <!-- LEFT SIDEBAR - BookStack Tree -->
      <aside v-show="sidebarOpen || mobileSidebar"
        :class="['flex-shrink-0 border-r border-slate-200 dark:border-slate-700/40 transition-all duration-300 flex flex-col bg-white/80 dark:bg-slate-900/80 backdrop-blur-sm w-80',
        mobileSidebar ? 'fixed inset-y-0 left-0 z-[100] shadow-2xl' : '']">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-700/40">
          <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
            <i class="ri-folder-shield-2-line text-indigo-500"></i> Content Tree
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
            <input v-model="treeSearch" type="text" placeholder="Filter tree..." class="flex-1 text-[10px] bg-transparent border-none outline-none placeholder-slate-400 dark:placeholder-slate-500" />
          </div>
        </div>
        <div class="flex-1 overflow-y-auto px-2 py-2">
          <div v-if="!filteredHierarchy.length" class="py-6 text-center">
            <i class="ri-folder-open-line text-2xl text-slate-300 dark:text-slate-600"></i>
            <p class="text-[10px] text-slate-400 mt-1">No items found</p>
          </div>
          <DocTreeItem v-for="node in filteredHierarchy" :key="node.id + '-' + node.type"
            :node="node" :depth="0" :expanded="expandedNodes" :selected="selectedPageId"
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
              <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Documents</h1>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Access guides, SOPs, and reference materials</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <IntegrationHealthBadge service="bookstack" :status="props.bookstackHealth?.status" :latency-ms="props.bookstackHealth?.latencyMs" />
            <div class="flex items-center bg-slate-100 dark:bg-slate-800 rounded-xl p-0.5">
              <button @click="viewMode = 'cards'" :class="['px-3 py-1.5 rounded-lg text-[10px] font-semibold transition-all', viewMode === 'cards' ? 'bg-white dark:bg-slate-700 text-indigo-600 shadow-sm' : 'text-slate-400 hover:text-slate-600']"><i class="ri-grid-line mr-0.5"></i> Cards</button>
              <button @click="viewMode = 'graph'; loadGraph()" :class="['px-3 py-1.5 rounded-lg text-[10px] font-semibold transition-all', viewMode === 'graph' ? 'bg-white dark:bg-slate-700 text-indigo-600 shadow-sm' : 'text-slate-400 hover:text-slate-600']"><i class="ri-mind-map mr-0.5"></i> Graph</button>
            </div>
            <button @click="showCreateForm = !showCreateForm" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-xs font-semibold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
              <i class="ri-add-line mr-1"></i> {{ showCreateForm ? 'Cancel' : 'New' }}
            </button>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
          <!-- Create Form (Full BookStack features) -->
          <div v-if="showCreateForm" class="glass rounded-2xl p-5 mb-5">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200">Create in BookStack</h3>
              <button @click="showCreateForm = false" class="w-6 h-6 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center"><i class="ri-close-line text-xs text-slate-400"></i></button>
            </div>
            <!-- Type tabs -->
            <div class="flex items-center bg-slate-100 dark:bg-slate-800 rounded-xl p-0.5 mb-4">
              <button @click="createType = 'page'" :class="['flex-1 px-3 py-2 rounded-lg text-[10px] font-semibold transition-all flex items-center justify-center gap-1.5', createType === 'page' ? 'bg-white dark:bg-slate-700 text-indigo-600 shadow-sm' : 'text-slate-400 hover:text-slate-600']"><i class="ri-file-text-line"></i> Page</button>
              <button @click="createType = 'chapter'" :class="['flex-1 px-3 py-2 rounded-lg text-[10px] font-semibold transition-all flex items-center justify-center gap-1.5', createType === 'chapter' ? 'bg-white dark:bg-slate-700 text-amber-600 shadow-sm' : 'text-slate-400 hover:text-slate-600']"><i class="ri-folders-line"></i> Chapter</button>
              <button @click="createType = 'book'" :class="['flex-1 px-3 py-2 rounded-lg text-[10px] font-semibold transition-all flex items-center justify-center gap-1.5', createType === 'book' ? 'bg-white dark:bg-slate-700 text-emerald-600 shadow-sm' : 'text-slate-400 hover:text-slate-600']"><i class="ri-book-2-line"></i> Book</button>
            </div>

            <!-- ─── BOOK FORM ─── -->
            <template v-if="createType === 'book'">
              <div class="space-y-3">
                <div>
                  <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1 block">Book Name <span class="text-rose-400">*</span></label>
                  <input v-model="newBook.name" type="text" placeholder="e.g. Brand Guidelines, SOPs Manual..." class="w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-300 transition-all" />
                </div>
                <div>
                  <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1 block">Description</label>
                  <textarea v-model="newBook.description" rows="3" placeholder="Short description of this book..." class="w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 bg-white dark:bg-slate-800 resize-none focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-300 transition-all"></textarea>
                </div>
                <!-- Tags -->
                <div>
                  <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1.5 block">Tags</label>
                  <div class="flex flex-wrap gap-1.5 mb-2">
                    <span v-for="(tag, ti) in newBook.tags" :key="ti" class="inline-flex items-center gap-1 text-[9px] px-2 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20">
                      <span class="font-semibold">{{ tag.name }}</span><span v-if="tag.value">: {{ tag.value }}</span>
                      <button @click="newBook.tags.splice(ti, 1)" class="ml-0.5 hover:text-rose-500"><i class="ri-close-line text-[9px]"></i></button>
                    </span>
                  </div>
                  <div class="flex items-center gap-2">
                    <input v-model="tagInput.name" type="text" placeholder="Tag name" class="flex-1 text-[10px] border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-300 transition-all" @keydown.enter.prevent="addTag(newBook.tags)" />
                    <input v-model="tagInput.value" type="text" placeholder="Value (optional)" class="flex-1 text-[10px] border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-300 transition-all" @keydown.enter.prevent="addTag(newBook.tags)" />
                    <button @click="addTag(newBook.tags)" class="px-2.5 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 text-[10px] font-semibold hover:bg-emerald-100 transition-colors"><i class="ri-add-line"></i></button>
                  </div>
                </div>
              </div>
              <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/40">
                <p v-if="createError" class="text-[10px] text-rose-500">{{ createError }}</p><span v-else></span>
                <button @click="submitBook" :disabled="creating" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-[11px] font-bold shadow-md shadow-emerald-200/50 dark:shadow-emerald-500/10 hover:-translate-y-0.5 transition-all duration-300 disabled:opacity-50">
                  <i v-if="creating" class="ri-loader-4-line animate-spin mr-1"></i>
                  {{ creating ? 'Creating...' : 'Create Book' }}
                </button>
              </div>
            </template>

            <!-- ─── CHAPTER FORM ─── -->
            <template v-if="createType === 'chapter'">
              <div class="space-y-3">
                <div>
                  <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1 block">Parent Book <span class="text-rose-400">*</span></label>
                  <select v-model="newChapter.book_id" class="w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-300 transition-all">
                    <option value="" disabled>Select a book...</option>
                    <option v-for="book in props.books" :key="book.id" :value="book.id">{{ book.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1 block">Chapter Name <span class="text-rose-400">*</span></label>
                  <input v-model="newChapter.name" type="text" placeholder="e.g. Logo Usage, Emergency Procedures..." class="w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-300 transition-all" />
                </div>
                <div>
                  <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1 block">Description</label>
                  <textarea v-model="newChapter.description" rows="2" placeholder="What this chapter covers..." class="w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 bg-white dark:bg-slate-800 resize-none focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-300 transition-all"></textarea>
                </div>
                <!-- Tags -->
                <div>
                  <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1.5 block">Tags</label>
                  <div class="flex flex-wrap gap-1.5 mb-2">
                    <span v-for="(tag, ti) in newChapter.tags" :key="ti" class="inline-flex items-center gap-1 text-[9px] px-2 py-1 rounded-lg bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20">
                      <span class="font-semibold">{{ tag.name }}</span><span v-if="tag.value">: {{ tag.value }}</span>
                      <button @click="newChapter.tags.splice(ti, 1)" class="ml-0.5 hover:text-rose-500"><i class="ri-close-line text-[9px]"></i></button>
                    </span>
                  </div>
                  <div class="flex items-center gap-2">
                    <input v-model="tagInput.name" type="text" placeholder="Tag name" class="flex-1 text-[10px] border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-300 transition-all" @keydown.enter.prevent="addTag(newChapter.tags)" />
                    <input v-model="tagInput.value" type="text" placeholder="Value (optional)" class="flex-1 text-[10px] border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-300 transition-all" @keydown.enter.prevent="addTag(newChapter.tags)" />
                    <button @click="addTag(newChapter.tags)" class="px-2.5 py-1.5 rounded-lg bg-amber-50 dark:bg-amber-500/10 text-amber-600 text-[10px] font-semibold hover:bg-amber-100 transition-colors"><i class="ri-add-line"></i></button>
                  </div>
                </div>
              </div>
              <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/40">
                <p v-if="createError" class="text-[10px] text-rose-500">{{ createError }}</p><span v-else></span>
                <button @click="submitChapter" :disabled="creating" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-white text-[11px] font-bold shadow-md shadow-amber-200/50 dark:shadow-amber-500/10 hover:-translate-y-0.5 transition-all duration-300 disabled:opacity-50">
                  <i v-if="creating" class="ri-loader-4-line animate-spin mr-1"></i>
                  {{ creating ? 'Creating...' : 'Create Chapter' }}
                </button>
              </div>
            </template>

            <!-- ─── PAGE FORM ─── -->
            <template v-if="createType === 'page'">
              <div class="space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1 block">Book <span class="text-rose-400">*</span></label>
                    <select v-model="newDoc.book_id" @change="newDoc.chapter_id = ''" class="w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 transition-all">
                      <option value="" disabled>Select a book...</option>
                      <option v-for="book in props.books" :key="book.id" :value="book.id">{{ book.name }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1 block">Chapter <span class="text-[9px] text-slate-400">(optional)</span></label>
                    <select v-model="newDoc.chapter_id" :disabled="!newDoc.book_id" class="w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 transition-all disabled:opacity-40">
                      <option value="">Direct in book (no chapter)</option>
                      <option v-for="ch in chaptersForSelectedBook" :key="ch.id" :value="ch.id">{{ ch.name }}</option>
                    </select>
                  </div>
                </div>
                <div>
                  <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1 block">Page Title <span class="text-rose-400">*</span></label>
                  <input v-model="newDoc.title" type="text" placeholder="e.g. Primary Logo Specifications..." class="w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 transition-all" />
                </div>
                <div>
                  <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1 block">Content <span class="text-rose-400">*</span></label>
                  <textarea v-model="newDoc.content" rows="5" placeholder="Write your page content here..." class="w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 bg-white dark:bg-slate-800 resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 transition-all"></textarea>
                </div>
                <!-- Tags -->
                <div>
                  <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-1.5 block">Tags</label>
                  <div class="flex flex-wrap gap-1.5 mb-2">
                    <span v-for="(tag, ti) in newDoc.tags" :key="ti" class="inline-flex items-center gap-1 text-[9px] px-2 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/20">
                      <span class="font-semibold">{{ tag.name }}</span><span v-if="tag.value">: {{ tag.value }}</span>
                      <button @click="newDoc.tags.splice(ti, 1)" class="ml-0.5 hover:text-rose-500"><i class="ri-close-line text-[9px]"></i></button>
                    </span>
                  </div>
                  <div class="flex items-center gap-2">
                    <input v-model="tagInput.name" type="text" placeholder="Tag name" class="flex-1 text-[10px] border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 transition-all" @keydown.enter.prevent="addTag(newDoc.tags)" />
                    <input v-model="tagInput.value" type="text" placeholder="Value (optional)" class="flex-1 text-[10px] border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1.5 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 transition-all" @keydown.enter.prevent="addTag(newDoc.tags)" />
                    <button @click="addTag(newDoc.tags)" class="px-2.5 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 text-[10px] font-semibold hover:bg-indigo-100 transition-colors"><i class="ri-add-line"></i></button>
                  </div>
                </div>
              </div>
              <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/40">
                <p v-if="createError" class="text-[10px] text-rose-500">{{ createError }}</p><span v-else></span>
                <button @click="submitDocument" :disabled="creating" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-[11px] font-bold shadow-md shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:-translate-y-0.5 transition-all duration-300 disabled:opacity-50">
                  <i v-if="creating" class="ri-loader-4-line animate-spin mr-1"></i>
                  {{ creating ? 'Creating...' : 'Create Page' }}
                </button>
              </div>
            </template>
          </div>

          <!-- CARD VIEW -->
          <template v-if="viewMode === 'cards'">
            <div class="glass rounded-2xl px-5 py-3.5 mb-5 anim-enter" data-delay="60">
              <div class="flex items-center gap-3">
                <i class="ri-search-line text-slate-400 dark:text-slate-500"></i>
                <input type="text" v-model="searchQuery" placeholder="Search documents by title, content, or tags..." class="flex-1 text-xs bg-transparent border-none outline-none placeholder-slate-400 dark:placeholder-slate-500" />
                <span class="text-[10px] text-slate-400 dark:text-slate-500">{{ docs.length }} documents</span>
              </div>
            </div>
            <div class="flex gap-2 mb-5 flex-wrap anim-enter" data-delay="100">
              <button v-for="(cat, ci) in docCategories" :key="ci"
                :class="['px-3.5 py-2 rounded-xl text-[11px] font-semibold transition-all duration-300',
                  activeCat === ci ? 'bg-indigo-500 text-white shadow-md shadow-indigo-200 dark:shadow-indigo-500/10' : 'glass text-slate-500 dark:text-slate-400 hover:text-indigo-600']"
                @click="activeCat = ci">{{ cat }}</button>
            </div>
            <div v-if="docs.length === 0" class="glass rounded-2xl p-12 text-center anim-enter" data-delay="140">
              <i class="ri-book-open-line text-5xl text-slate-300 dark:text-slate-600 mb-3"></i>
              <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">No documents yet</p>
              <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Create your first document to share guides, SOPs, and reference materials.</p>
            </div>
            <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <div v-for="(doc, di) in docs" :key="di" class="glass rounded-2xl p-5 hover-lift cursor-pointer group anim-enter" :data-delay="140 + di * 50" @click="openDocument(doc)">
                <div class="flex items-start gap-3.5">
                  <div :class="['w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0', doc.iconBg]">
                    <i :class="[doc.icon, 'text-lg text-white']"></i>
                  </div>
                  <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 group-hover:text-indigo-600 transition-colors">{{ doc.title }}</h3>
                    <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5 line-clamp-2">{{ doc.desc }}</p>
                    <div class="flex items-center gap-3 mt-2.5">
                      <span class="text-[9px] text-slate-400 dark:text-slate-500"><i class="ri-user-line mr-0.5"></i> {{ doc.author }}</span>
                      <span class="text-[9px] text-slate-400 dark:text-slate-500"><i class="ri-time-line mr-0.5"></i> {{ doc.updated }}</span>
                      <span :class="['text-[9px] font-bold px-2 py-0.5 rounded-full', doc.statusClass]">{{ doc.status }}</span>
                    </div>
                    <div class="flex flex-wrap gap-1 mt-2">
                      <span v-for="(tag, ti) in doc.tags" :key="ti" class="text-[8px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400">{{ tag }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- GRAPH VIEW -->
          <template v-if="viewMode === 'graph'">
            <div v-if="graphLoading" class="glass rounded-2xl p-12 text-center">
              <i class="ri-loader-4-line animate-spin text-3xl text-indigo-400"></i>
              <p class="text-xs text-slate-400 mt-2">Loading relation graph...</p>
            </div>
            <div v-else-if="graphNodes.length === 0" class="glass rounded-2xl p-12 text-center">
              <i class="ri-mind-map text-5xl text-slate-300 dark:text-slate-600 mb-3"></i>
              <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">No relationships yet</p>
              <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Link assets to documents to see the relation graph.</p>
            </div>
            <div v-else class="glass rounded-2xl overflow-hidden" style="height: 600px;">
              <VueFlow :nodes="graphNodes" :edges="graphEdges" :fit-view-on-init="true" @node-click="onGraphNodeClick">
                <Background />
                <Controls />
              </VueFlow>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- DOCUMENT VIEWER MODAL -->
    <Teleport to="body">
      <div v-if="viewerDoc" class="fixed inset-0 z-[200] flex items-center justify-center" @click.self="viewerDoc = null">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
        <div class="relative w-full max-w-4xl max-h-[85vh] mx-4 bg-white dark:bg-slate-900 rounded-2xl shadow-2xl flex flex-col overflow-hidden">
          <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/40">
            <div class="flex items-center gap-3 min-w-0">
              <div :class="['w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0', viewerDoc.iconBg || 'bg-indigo-500']">
                <i :class="[viewerDoc.icon || 'ri-file-text-line', 'text-sm text-white']"></i>
              </div>
              <div class="min-w-0">
                <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate">{{ viewerDoc.title }}</h2>
                <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ viewerDoc.author || '' }} · {{ viewerDoc.updated || '' }}</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <div class="flex items-center bg-slate-100 dark:bg-slate-800 rounded-lg p-0.5 mr-2">
                <button @click="viewerTab = 'content'" :class="['px-2.5 py-1 rounded-md text-[10px] font-semibold transition-all', viewerTab === 'content' ? 'bg-white dark:bg-slate-700 text-indigo-600 shadow-sm' : 'text-slate-400']"><i class="ri-file-text-line mr-0.5"></i> Content</button>
                <button @click="viewerTab = 'assets'; loadLinkedAssets(viewerDoc.id)" :class="['px-2.5 py-1 rounded-md text-[10px] font-semibold transition-all', viewerTab === 'assets' ? 'bg-white dark:bg-slate-700 text-indigo-600 shadow-sm' : 'text-slate-400']"><i class="ri-links-line mr-0.5"></i> Assets</button>
              </div>
              <button @click="viewerDoc = null" class="w-8 h-8 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center"><i class="ri-close-line text-lg text-slate-400"></i></button>
            </div>
          </div>
          <div class="flex-1 overflow-y-auto px-6 py-5">
            <template v-if="viewerTab === 'content'">
              <div v-if="viewerLoading" class="py-12 text-center"><i class="ri-loader-4-line animate-spin text-2xl text-indigo-400"></i><p class="text-[11px] text-slate-400 mt-2">Loading document...</p></div>
              <div v-else-if="viewerError" class="py-12 text-center"><i class="ri-error-warning-line text-2xl text-rose-400"></i><p class="text-[11px] text-rose-500 mt-2">{{ viewerError }}</p></div>
              <div v-else class="prose prose-sm dark:prose-invert max-w-none text-xs leading-relaxed" v-html="viewerHtml"></div>
            </template>
            <template v-if="viewerTab === 'assets'">
              <div v-if="linkedAssetsLoading" class="py-12 text-center"><i class="ri-loader-4-line animate-spin text-2xl text-indigo-400"></i><p class="text-[11px] text-slate-400 mt-2">Loading linked assets...</p></div>
              <div v-else-if="linkedAssets.length === 0" class="py-12 text-center">
                <i class="ri-inbox-2-line text-3xl text-slate-300 dark:text-slate-600"></i>
                <p class="text-xs text-slate-400 mt-2">No assets linked to this document</p>
                <button v-if="isAdmin" @click="showLinkModal = true" class="mt-3 px-4 py-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-[10px] font-bold shadow-md hover:-translate-y-0.5 transition-all duration-300"><i class="ri-links-line mr-1"></i> Link an Asset</button>
              </div>
              <div v-else>
                <div class="flex items-center justify-between mb-4">
                  <p class="text-[11px] font-semibold text-slate-500">{{ linkedAssets.length }} linked asset{{ linkedAssets.length === 1 ? '' : 's' }}</p>
                  <button v-if="isAdmin" @click="showLinkModal = true" class="px-3 py-1.5 rounded-lg bg-indigo-500 text-white text-[10px] font-bold hover:-translate-y-0.5 transition-all duration-300"><i class="ri-add-line mr-0.5"></i> Link Asset</button>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                  <div v-for="asset in linkedAssets" :key="asset.id" class="glass rounded-xl p-3 hover-lift cursor-pointer group" @click="router.visit('/preview/' + asset.id)">
                    <div v-if="asset.thumbnail_path" class="w-full h-20 rounded-lg bg-slate-100 dark:bg-slate-800 overflow-hidden mb-2"><img :src="'/serve/thumbnail/' + asset.id" class="w-full h-full object-cover" /></div>
                    <div v-else class="w-full h-20 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-2"><i class="ri-file-line text-xl text-slate-300 dark:text-slate-600"></i></div>
                    <p class="text-[10px] font-semibold text-slate-700 dark:text-slate-200 truncate group-hover:text-indigo-500 transition-colors">{{ asset.original_filename }}</p>
                    <div class="flex items-center justify-between mt-1">
                      <span class="text-[8px] px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400">{{ asset.group_classification }}</span>
                      <button v-if="isAdmin" @click.stop="unlinkDocAsset(asset.link_id)" class="text-[9px] text-rose-400 hover:text-rose-600"><i class="ri-unlink"></i></button>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>
    </Teleport>

    <AssetLinkModal :show="showLinkModal" context="bookstack" :target-id="viewerDoc?.id ?? 0" :target-title="viewerDoc?.title ?? ''" @close="showLinkModal = false" @linked="loadLinkedAssets(viewerDoc?.id)" />
  </AppLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import IntegrationHealthBadge from '@/Components/IntegrationHealthBadge.vue';
import AssetLinkModal from '@/Components/AssetLinkModal.vue';
import DocTreeItem from '@/Components/DocTreeItem.vue';
import { router, usePage } from '@inertiajs/vue3';
import { useScrollReveal } from '@/composables/useAnimations';
import { VueFlow } from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';

useScrollReveal();

const page = usePage();
const isAdmin = computed(() => (page.props.auth?.user?.roles || []).includes('Admin'));

const props = defineProps({
  documents: { type: Array, default: () => [] },
  books: { type: Array, default: () => [] },
  chapters: { type: Array, default: () => [] },
  hierarchy: { type: Array, default: () => [] },
  bookstackHealth: { type: Object, default: () => ({ status: 'unknown', latencyMs: null }) },
});

const sidebarOpen = ref(true);
const mobileSidebar = ref(false);
const treeSearch = ref('');
const expandedNodes = ref(new Set());
const selectedPageId = ref(null);
const viewMode = ref('cards');
const activeCat = ref(0);
const searchQuery = ref('');
const docCategories = ['All', 'Guides', 'SOPs', 'Policies', 'References'];
const showCreateForm = ref(false);
const creating = ref(false);
const createError = ref('');
const createType = ref('page');
const newDoc = ref({ title: '', book_id: '', chapter_id: '', content: '', tags: [] });
const newBook = ref({ name: '', description: '', tags: [] });
const newChapter = ref({ name: '', book_id: '', description: '', tags: [] });
const tagInput = reactive({ name: '', value: '' });
const viewerDoc = ref(null);
const viewerLoading = ref(false);
const viewerError = ref('');
const viewerHtml = ref('');
const viewerTab = ref('content');
const linkedAssets = ref([]);
const linkedAssetsLoading = ref(false);
const showLinkModal = ref(false);
const graphNodes = ref([]);
const graphEdges = ref([]);
const graphLoading = ref(false);

// Chapters for the currently selected book in the page creation form
const chaptersForSelectedBook = computed(() => {
  if (!newDoc.value.book_id) return [];
  return (props.chapters || []).filter(ch => ch.book_id === newDoc.value.book_id);
});

const docs = computed(() => {
  let items = props.documents || [];
  if (activeCat.value > 0) {
    const cat = docCategories[activeCat.value].toLowerCase();
    items = items.filter(d => (d.category || '').toLowerCase() === cat);
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    items = items.filter(d =>
      (d.title || '').toLowerCase().includes(q) ||
      (d.desc || '').toLowerCase().includes(q) ||
      (d.tags || []).some(t => t.toLowerCase().includes(q))
    );
  }
  return items;
});

function filterTree(nodes, query) {
  if (!query.trim()) return nodes;
  const q = query.toLowerCase();
  return nodes.reduce((acc, node) => {
    const match = (node.name || '').toLowerCase().includes(q);
    const fc = node.children ? filterTree(node.children, query) : [];
    if (match || fc.length > 0) acc.push({ ...node, children: fc.length > 0 ? fc : (node.children || []) });
    return acc;
  }, []);
}

const filteredHierarchy = computed(() => filterTree(props.hierarchy || [], treeSearch.value));

function toggleNode(nodeKey) {
  const s = new Set(expandedNodes.value);
  s.has(nodeKey) ? s.delete(nodeKey) : s.add(nodeKey);
  expandedNodes.value = s;
}

function expandAll() {
  const keys = new Set();
  (function walk(ns) { for (const n of ns) { if (n.children?.length) { keys.add(n.type + '-' + n.id); walk(n.children); } } })(props.hierarchy || []);
  expandedNodes.value = keys;
}
function collapseAll() { expandedNodes.value = new Set(); }

function onTreeNodeSelect(node) {
  if (node.type === 'page') {
    selectedPageId.value = node.id;
    const doc = (props.documents || []).find(d => d.id === node.id);
    openDocument(doc || { id: node.id, title: node.name, icon: 'ri-file-text-line', iconBg: 'bg-indigo-500' });
  }
}

function refreshTree() {
  router.post('/documents/refresh-cache', {}, { preserveScroll: true, onSuccess: () => router.visit('/documents', { preserveScroll: true }) });
}

function openDocument(doc) {
  viewerDoc.value = doc;
  viewerTab.value = 'content';
  viewerLoading.value = true;
  viewerError.value = '';
  viewerHtml.value = '';
  linkedAssets.value = [];
  fetch('/documents/' + doc.id + '/content', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(data => { data.error ? (viewerError.value = data.error) : (viewerHtml.value = data.html || '<p>No content</p>'); })
    .catch(() => { viewerError.value = 'Failed to load document'; })
    .finally(() => { viewerLoading.value = false; });
}

function loadLinkedAssets(pageId) {
  if (!pageId) return;
  linkedAssetsLoading.value = true;
  fetch('/documents/pages/' + pageId + '/assets', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(data => { linkedAssets.value = data || []; })
    .catch(() => { linkedAssets.value = []; })
    .finally(() => { linkedAssetsLoading.value = false; });
}

function unlinkDocAsset(linkId) {
  router.delete('/documents/link-asset/' + linkId, { preserveScroll: true, onSuccess: () => loadLinkedAssets(viewerDoc.value?.id) });
}

function submitDocument() {
  createError.value = '';
  if (!newDoc.value.title.trim()) { createError.value = 'Page title is required'; return; }
  if (!newDoc.value.book_id && !newDoc.value.chapter_id) { createError.value = 'Please select a book'; return; }
  if (!newDoc.value.content.trim()) { createError.value = 'Content is required'; return; }
  creating.value = true;
  const payload = {
    title: newDoc.value.title,
    content: newDoc.value.content,
    book_id: newDoc.value.chapter_id ? null : newDoc.value.book_id,
    chapter_id: newDoc.value.chapter_id || null,
    tags: newDoc.value.tags.length > 0 ? newDoc.value.tags : null,
  };
  router.post('/documents', payload, {
    preserveScroll: true,
    onSuccess: () => { newDoc.value = { title: '', book_id: '', chapter_id: '', content: '', tags: [] }; showCreateForm.value = false; creating.value = false; },
    onError: (errors) => { createError.value = errors.message || Object.values(errors).flat().join(', ') || 'Failed'; creating.value = false; },
  });
}

function submitBook() {
  createError.value = '';
  if (!newBook.value.name.trim()) { createError.value = 'Book name is required'; return; }
  creating.value = true;
  const payload = {
    name: newBook.value.name,
    description: newBook.value.description || null,
    tags: newBook.value.tags.length > 0 ? newBook.value.tags : null,
  };
  router.post('/documents/books', payload, {
    preserveScroll: true,
    onSuccess: () => { newBook.value = { name: '', description: '', tags: [] }; showCreateForm.value = false; creating.value = false; },
    onError: (errors) => { createError.value = errors.message || Object.values(errors).flat().join(', ') || 'Failed'; creating.value = false; },
  });
}

function submitChapter() {
  createError.value = '';
  if (!newChapter.value.book_id) { createError.value = 'Please select a book'; return; }
  if (!newChapter.value.name.trim()) { createError.value = 'Chapter name is required'; return; }
  creating.value = true;
  const payload = {
    name: newChapter.value.name,
    book_id: newChapter.value.book_id,
    description: newChapter.value.description || null,
    tags: newChapter.value.tags.length > 0 ? newChapter.value.tags : null,
  };
  router.post('/documents/chapters', payload, {
    preserveScroll: true,
    onSuccess: () => { newChapter.value = { name: '', book_id: '', description: '', tags: [] }; showCreateForm.value = false; creating.value = false; },
    onError: (errors) => { createError.value = errors.message || Object.values(errors).flat().join(', ') || 'Failed'; creating.value = false; },
  });
}

function addTag(tagsArray) {
  if (!tagInput.name.trim()) return;
  tagsArray.push({ name: tagInput.name.trim(), value: tagInput.value.trim() });
  tagInput.name = '';
  tagInput.value = '';
}

function loadGraph() {
  graphLoading.value = true;
  fetch('/documents/graph', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(data => { graphNodes.value = data.nodes || []; graphEdges.value = data.edges || []; })
    .catch(() => { graphNodes.value = []; graphEdges.value = []; })
    .finally(() => { graphLoading.value = false; });
}

function onGraphNodeClick({ node }) {
  if (node.data?.nodeType === 'page') {
    const doc = (props.documents || []).find(d => d.id === node.data.pageId);
    openDocument(doc || { id: node.data.pageId, title: node.data.label, icon: 'ri-file-text-line', iconBg: 'bg-indigo-500' });
  } else if (node.data?.nodeType === 'asset') {
    router.visit('/preview/' + node.data.assetId);
  }
}
</script>