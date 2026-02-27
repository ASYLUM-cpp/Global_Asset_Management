<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto">
      <!-- Header -->
      <div class="text-center mb-8 anim-enter">
        <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 mb-1">Upload Assets</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Drag & drop files or browse — AI will classify and tag automatically</p>
      </div>

      <!-- Drop Zone -->
      <div
        class="glass rounded-3xl p-12 relative overflow-hidden mb-6 anim-enter-scale" data-delay="80"
        :class="isDragging ? 'ring-2 ring-indigo-400 bg-indigo-50/50 dark:bg-indigo-500/10' : ''"
        @dragover.prevent="isDragging = true"
        @dragleave="isDragging = false"
        @drop.prevent="handleDrop"
      >
        <div class="absolute inset-0 rounded-3xl border-2 border-dashed transition-colors duration-300"
          :class="isDragging ? 'border-indigo-400' : 'border-slate-200 dark:border-slate-600'"></div>
        <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-indigo-200/20 dark:bg-indigo-500/10 float"></div>
        <div class="absolute -bottom-8 -left-8 w-32 h-32 rounded-full bg-violet-200/20 dark:bg-violet-500/10 float" style="animation-delay:-2s"></div>

        <div class="relative text-center">
          <div class="mx-auto w-20 h-20 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-500 flex items-center justify-center mb-5 shadow-xl shadow-indigo-200/50 dark:shadow-indigo-500/10 transition-transform duration-500"
            :class="isDragging ? 'scale-110 rotate-3' : ''">
            <i :class="['text-3xl text-white transition-transform duration-300', isDragging ? 'ri-inbox-unarchive-line scale-125' : 'ri-upload-cloud-2-line']"></i>
          </div>
          <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-1">
            {{ isDragging ? 'Release to upload' : 'Drop files here' }}
          </h2>
          <p class="text-sm text-slate-400 dark:text-slate-500 mb-5">or click to browse from your computer</p>
          <input ref="fileInput" type="file" multiple class="hidden" accept=".jpg,.jpeg,.png,.tiff,.tif,.svg,.pdf,.mp4,.mov,.psd,.gif,.webp,.doc,.docx,.xls,.xlsx,.ai,.eps,.bmp,.avi,.mkv" @change="handleFileSelect" />
          <button @click="$refs.fileInput.click()" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-sm font-semibold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:shadow-xl hover:shadow-indigo-300/50 hover:-translate-y-0.5 transition-all duration-300 btn-pulse">
            <i class="ri-folder-open-line mr-1.5"></i> Browse Files
          </button>
          <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-4">Supports JPG, PNG, TIFF, SVG, PDF, PSD, AI, EPS, DOC, XLSX, MP4, MOV · Max 500 MB per file</p>
        </div>
      </div>

      <!-- Upload Queue -->
      <div class="glass rounded-2xl p-5 mb-6 anim-enter" data-delay="160">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Upload Queue</h3>
          <div class="flex items-center gap-2">
            <span class="text-[10px] px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300 font-bold">
              {{ queuedFiles.length + uploadedEntries.length }} files
            </span>
            <span v-if="overallStats.uploading > 0" class="text-[10px] px-2.5 py-1 rounded-full bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300 font-bold animate-pulse">
              <i class="ri-loader-4-line ri-spin mr-0.5"></i> {{ overallStats.uploading }} uploading
            </span>
            <span v-if="overallStats.aiReady > 0" class="text-[10px] px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300 font-bold">
              <i class="ri-robot-line mr-0.5"></i> {{ overallStats.aiReady }} classified
            </span>
          </div>
        </div>
        <div class="space-y-2.5 max-h-[calc(100vh-420px)] overflow-y-auto">
          <!-- Queued (not yet uploaded) files -->
          <div v-for="(qf, i) in queuedFiles" :key="'q-' + i"
            class="flex items-center gap-3.5 px-4 py-3 rounded-xl bg-white/50 dark:bg-white/5 border border-slate-100 dark:border-slate-700/40 group hover:border-indigo-100 dark:hover:border-indigo-500/30 transition-all duration-300">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 overflow-hidden bg-gradient-to-br from-indigo-400 to-violet-500">
              <img v-if="qf.preview" :src="qf.preview" class="w-full h-full object-cover" />
              <i v-else :class="[getFileIcon(qf.file.name), 'text-lg text-white']"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate">{{ qf.file.name }}</p>
              <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ formatSize(qf.file.size) }}</p>
            </div>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-50 text-slate-500 dark:bg-slate-700/50 dark:text-slate-400">Ready</span>
            <button @click="removeQueued(i)" class="opacity-0 group-hover:opacity-100 text-slate-300 hover:text-red-400 transition-all"><i class="ri-close-line"></i></button>
          </div>

          <!-- Uploading / Uploaded files with AI metadata -->
          <div v-for="entry in uploadedEntries" :key="'u-' + entry.clientId" class="transition-all duration-500">
            <!-- File row -->
            <div
              class="flex items-center gap-3.5 px-4 py-3 rounded-xl bg-white/50 dark:bg-white/5 border transition-all duration-300 cursor-pointer group/entry"
              :class="[
                entry.status === 'error' ? 'border-red-200 dark:border-red-500/30' :
                entry.status === 'duplicate' ? 'border-amber-200 dark:border-amber-500/30' :
                entry.aiMetadata ? 'border-emerald-200 dark:border-emerald-500/30' :
                'border-slate-100 dark:border-slate-700/40',
                selectedUploads.has(entry.clientId) ? 'ring-2 ring-indigo-400/60 bg-indigo-50/40 dark:bg-indigo-500/10' : '',
                entry.expanded ? 'rounded-b-none' : ''
              ]"
              @click="entry.aiMetadata && toggleExpand(entry)"
            >
              <!-- Multi-select checkbox -->
              <label v-if="entry.status !== 'done' && entry.status !== 'cancelled'"
                class="flex-shrink-0 opacity-40 group-hover/entry:opacity-100 transition-opacity duration-200"
                :class="selectedUploads.has(entry.clientId) ? '!opacity-100' : ''" @click.stop>
                <input type="checkbox"
                  :checked="selectedUploads.has(entry.clientId)"
                  @change="toggleSelectUpload(entry.clientId)"
                  class="w-4 h-4 rounded-md border-2 border-slate-300 dark:border-slate-600 text-indigo-500 focus:ring-indigo-500/30 cursor-pointer transition-all duration-200 hover:border-indigo-400 hover:shadow-sm hover:shadow-indigo-200/50" />
              </label>
              <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 overflow-hidden"
                :class="statusIconBg(entry.status)">
                <img v-if="entry.thumbnailUrl" :src="entry.thumbnailUrl" class="w-full h-full object-cover" />
                <i v-else :class="[statusIcon(entry.status), 'text-lg text-white']"></i>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate">{{ entry.name }}</p>
                <p class="text-[10px] text-slate-400 dark:text-slate-500">
                  {{ entry.sizeFormatted || formatSize(entry.size) }}
                  <span v-if="entry.message" class="ml-1 text-amber-500">· {{ entry.message }}</span>
                  <!-- AI group badge -->
                  <span v-if="entry.aiMetadata?.group" class="ml-1.5 inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300">
                    <i class="ri-robot-line"></i> {{ entry.aiMetadata.group }}
                    <span v-if="entry.aiMetadata.group_confidence" class="opacity-60">{{ Math.round(entry.aiMetadata.group_confidence) }}%</span>
                  </span>
                </p>
              </div>
              <!-- Upload progress bar -->
              <div v-if="entry.status === 'uploading'" class="w-32 flex-shrink-0">
                <div class="h-1.5 rounded-full bg-slate-100 dark:bg-slate-700/50 overflow-hidden">
                  <div class="h-full rounded-full bg-gradient-to-r from-indigo-400 to-violet-500 transition-all duration-300 ease-out"
                    :style="{ width: entry.progress + '%' }"></div>
                </div>
                <p class="text-[9px] text-slate-400 dark:text-slate-500 mt-0.5 text-right">{{ entry.progress }}%</p>
              </div>
              <!-- Pipeline progress -->
              <div v-else-if="entry.status === 'processing'" class="w-32 flex-shrink-0">
                <div class="h-1.5 rounded-full bg-slate-100 dark:bg-slate-700/50 overflow-hidden">
                  <div class="h-full rounded-full bg-gradient-to-r from-teal-400 to-emerald-500 transition-all duration-500 ease-out"
                    :style="{ width: pipelineProgress(entry.pipelineStatus) + '%' }"></div>
                </div>
                <p class="text-[9px] text-slate-400 dark:text-slate-500 mt-0.5 text-right capitalize">{{ entry.pipelineStatus || 'queued' }}</p>
              </div>
              <!-- AI tag count chip -->
              <span v-if="entry.aiMetadata?.tags?.length" class="text-[9px] px-2 py-0.5 rounded-full bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300 font-bold flex-shrink-0">
                {{ entry.aiMetadata.tags.length }} tags
              </span>
              <!-- Status badge -->
              <span :class="['text-[10px] font-bold px-2 py-0.5 rounded-full whitespace-nowrap', statusBadge(entry.status)]">
                {{ statusLabel(entry.status) }}
              </span>
              <!-- Replace Master button (REQ-03: visible only for duplicates) -->
              <button v-if="entry.status === 'duplicate' && entry.assetId"
                @click.stop="replaceMaster(entry)"
                :disabled="entry.replacing"
                class="flex-shrink-0 px-2.5 py-1.5 rounded-lg text-[10px] font-bold text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 transition-all duration-200"
                title="Replace the existing asset with this file as a new version">
                <i :class="entry.replacing ? 'ri-loader-4-line ri-spin' : 'ri-refresh-line'" class="mr-0.5"></i>
                {{ entry.replacing ? 'Replacing...' : 'Replace Master' }}
              </button>
              <!-- Cancel button (visible during uploading, processing, and for errors/duplicates) -->
              <button v-if="entry.status !== 'done' && entry.status !== 'cancelled'"
                @click.stop="cancelEntry(entry)"
                :disabled="entry.cancelling"
                class="flex-shrink-0 w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-all duration-200"
                :title="entry.status === 'uploading' ? 'Cancel upload' : entry.status === 'processing' ? 'Cancel processing' : 'Remove'">
                <i :class="entry.cancelling ? 'ri-loader-4-line ri-spin text-xs' : 'ri-close-line text-sm'"></i>
              </button>
              <!-- Expand arrow -->
              <i v-if="entry.aiMetadata" class="ri-arrow-down-s-line text-slate-400 dark:text-slate-500 transition-transform duration-300 flex-shrink-0"
                :class="entry.expanded ? 'rotate-180' : ''"></i>
            </div>

            <!-- Expandable AI Metadata Card (auto-fills when AI completes) -->
            <transition name="slide">
              <div v-if="entry.expanded && entry.aiMetadata"
                class="border border-t-0 rounded-b-xl px-4 py-4 bg-gradient-to-br from-slate-50/80 to-indigo-50/30 dark:from-slate-800/50 dark:to-indigo-900/10 border-emerald-200 dark:border-emerald-500/30">

                <!-- AI Classification Header -->
                <div class="flex items-center gap-2 mb-3">
                  <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gradient-to-r from-indigo-500 to-violet-500 text-white">
                    <i class="ri-robot-line text-xs"></i>
                    <span class="text-[10px] font-bold">AI Classified</span>
                  </div>
                  <span v-if="entry.metadataSaved" class="text-[10px] text-emerald-500 font-semibold">
                    <i class="ri-check-line"></i> Saved
                  </span>
                </div>

                <!-- Group selector -->
                <div class="grid grid-cols-2 gap-3 mb-3">
                  <div>
                    <label class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold mb-1 block">Taxonomy Group</label>
                    <select v-model="entry.editForm.group"
                      class="w-full text-xs border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 transition-all">
                      <option value="">None</option>
                      <option v-for="g in groups" :key="g" :value="g">{{ g }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold mb-1 block">Collection</label>
                    <select v-model="entry.editForm.collection"
                      class="w-full text-xs border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 transition-all">
                      <option value="">None</option>
                      <option v-for="(name, id) in collections" :key="id" :value="id">{{ name }}</option>
                    </select>
                  </div>
                </div>

                <!-- Description -->
                <div class="mb-3">
                  <label class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold mb-1 block">AI Description</label>
                  <textarea v-model="entry.editForm.description" rows="2"
                    class="w-full text-xs border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 transition-all resize-none"
                    placeholder="AI-generated description..."></textarea>
                </div>

                <!-- Tags -->
                <div class="mb-3">
                  <label class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold mb-1.5 block">AI Tags</label>
                  <div class="flex flex-wrap gap-1.5">
                    <span v-for="(tag, ti) in entry.editForm.tags" :key="ti"
                      class="inline-flex items-center gap-1 pl-2.5 pr-1 py-1 rounded-full text-[10px] font-semibold transition-all duration-200"
                      :class="tag.source === 'ai' ? 'bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' : 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300'">
                      <span>{{ tag.tag }}</span>
                      <span v-if="tag.confidence" class="text-[8px] opacity-60 ml-0.5">{{ Math.round(tag.confidence * 100) }}%</span>
                      <button @click="removeTag(entry, ti)" class="ml-0.5 w-4 h-4 rounded-full hover:bg-red-100 dark:hover:bg-red-500/20 flex items-center justify-center text-red-400 hover:text-red-500 transition-colors">
                        <i class="ri-close-line text-[10px]"></i>
                      </button>
                    </span>
                    <!-- Add tag input -->
                    <div class="inline-flex items-center">
                      <input
                        v-model="entry.newTagInput"
                        @keydown.enter.prevent="addTag(entry)"
                        @keydown.comma.prevent="addTag(entry)"
                        type="text"
                        placeholder="+ add tag"
                        class="w-20 text-[10px] px-2 py-1 rounded-full border border-dashed border-slate-300 dark:border-slate-600 bg-transparent dark:text-slate-300 focus:outline-none focus:border-indigo-400 focus:w-32 transition-all"
                      />
                    </div>
                  </div>
                </div>

                <!-- Action buttons -->
                <div class="flex items-center justify-between pt-2 border-t border-slate-100/60 dark:border-slate-700/40">
                  <div class="flex items-center gap-2">
                    <span v-if="entry.aiMetadata.group_confidence" class="text-[9px] text-slate-400 dark:text-slate-500">
                      Confidence: {{ Math.round(entry.aiMetadata.group_confidence) }}%
                    </span>
                  </div>
                  <div class="flex items-center gap-2">
                    <button @click="resetToAi(entry)" class="px-3 py-1.5 text-[10px] font-semibold text-slate-500 dark:text-slate-400 hover:text-indigo-500 transition-colors">
                      <i class="ri-refresh-line mr-0.5"></i> Reset to AI
                    </button>
                    <button @click="saveMetadata(entry)" :disabled="entry.saving"
                      class="px-4 py-1.5 rounded-lg bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-[10px] font-bold shadow-sm hover:shadow-md hover:-translate-y-px transition-all disabled:opacity-50">
                      <i :class="entry.saving ? 'ri-loader-4-line ri-spin' : 'ri-save-line'" class="mr-0.5"></i>
                      {{ entry.saving ? 'Saving...' : 'Save Metadata' }}
                    </button>
                  </div>
                </div>
              </div>
            </transition>
          </div>

          <!-- Recently uploaded (from server) -->
          <div v-for="(file, i) in serverUploads" :key="'prev-' + file.id"
            class="flex items-center gap-3.5 px-4 py-3 rounded-xl bg-white/50 dark:bg-white/5 border border-slate-100 dark:border-slate-700/40 group hover:border-indigo-100 dark:hover:border-indigo-500/30 transition-all duration-300">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-gradient-to-br from-indigo-400 to-violet-500">
              <i :class="[getFileIcon(file.name), 'text-lg text-white']"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate">{{ file.name }}</p>
              <p class="text-[10px] text-slate-400 dark:text-slate-500">{{ file.size }} · {{ file.uploaded }}</p>
            </div>
            <span :class="['text-[10px] font-bold px-2 py-0.5 rounded-full', pipelineBadge(file.status)]">
              {{ file.status }}
            </span>
            <!-- Retry button for stuck/failed assets -->
            <button v-if="['queued','failed','hashing','previewing','tagging','classifying','indexing'].includes(file.status)"
              @click.stop="retryServerUpload(file)"
              :disabled="file._retrying"
              class="w-7 h-7 rounded-lg flex items-center justify-center text-teal-400 hover:text-teal-300 hover:bg-teal-500/10 transition-all"
              :class="file.status === 'failed' ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
              title="Retry pipeline">
              <i :class="file._retrying ? 'ri-loader-4-line ri-spin text-xs' : 'ri-refresh-line text-sm'"></i>
            </button>
            <!-- Cancel/remove button -->
            <button v-if="file.status !== 'done'"
              @click.stop="cancelServerUpload(file, i)"
              :disabled="file._cancelling"
              class="opacity-0 group-hover:opacity-100 w-7 h-7 rounded-lg flex items-center justify-center text-red-400 hover:text-red-300 hover:bg-red-500/10 transition-all"
              title="Cancel & remove">
              <i :class="file._cancelling ? 'ri-loader-4-line ri-spin text-xs' : 'ri-close-line text-sm'"></i>
            </button>
          </div>
        </div>
        <p v-if="queuedFiles.length === 0 && uploadedEntries.length === 0 && serverUploads.length === 0"
          class="text-xs text-slate-400 dark:text-slate-500 text-center py-6">
          No files in queue — drag & drop or browse to add files
        </p>
      </div>

      <!-- Batch Defaults + Upload Button -->
      <div class="glass rounded-2xl p-5 anim-enter" data-delay="240">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Batch Defaults</h3>
          <span class="text-[10px] text-slate-400 dark:text-slate-500">Optional — AI will auto-classify each file</span>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold mb-1.5 block">Collection</label>
            <select v-model="form.collection" class="w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 bg-white dark:bg-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 transition-all">
              <option value="">None</option>
              <option v-for="(name, id) in collections" :key="id" :value="id">{{ name }}</option>
            </select>
          </div>
          <div>
            <label class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold mb-1.5 block">Override Group</label>
            <select v-model="form.group" class="w-full text-xs border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 bg-white dark:bg-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 transition-all">
              <option value="">Auto-classify (AI)</option>
              <option v-for="g in groups" :key="g" :value="g">{{ g }}</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end gap-3 mt-5 pt-4 border-t border-slate-100/60 dark:border-slate-700/40">
          <button @click="clearAll" class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all">
            Clear All
          </button>
          <button @click="startUpload" :disabled="queuedFiles.length === 0 || isUploading" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 text-white text-xs font-semibold shadow-lg shadow-indigo-200/50 dark:shadow-indigo-500/10 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed">
            <i class="ri-upload-2-line mr-1.5"></i>
            {{ isUploading ? `Uploading ${overallStats.uploading}/${overallStats.total}...` : 'Start Upload' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Floating Multi-Select Cancel Bar -->
    <transition name="slide-up">
      <div v-if="selectedUploads.size > 0"
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-2xl bg-gradient-to-r from-slate-900 to-slate-800 dark:from-slate-700 dark:to-slate-600 shadow-2xl shadow-slate-900/30 flex items-center gap-4 backdrop-blur-xl border border-slate-700/50">
        <span class="text-sm font-semibold text-white flex items-center">
          <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-500 text-white text-xs font-bold mr-2">{{ selectedUploads.size }}</span>
          selected
        </span>
        <div class="w-px h-6 bg-slate-600"></div>
        <button @click="cancelSelected"
          class="px-4 py-2 rounded-xl bg-gradient-to-r from-red-500 to-rose-500 text-white text-xs font-bold shadow-lg shadow-red-500/25 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
          <i class="ri-close-circle-line mr-1"></i> Cancel Selected
        </button>
        <button @click="selectedUploads = new Set()"
          class="px-3 py-2 rounded-xl text-slate-400 hover:text-white text-xs font-semibold transition-colors">
          Clear
        </button>
      </div>
    </transition>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onBeforeUnmount, nextTick } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useScrollReveal } from '@/composables/useAnimations';
import axios from 'axios';
useScrollReveal();

const props = defineProps({
  recentUploads: Array,
  collections: Object,
  groups: Array,
});

// ── State ──────────────────────────────────────────────
const isDragging = ref(false);
const queuedFiles = ref([]);
const uploadedEntries = ref([]);
const serverUploads = ref((props.recentUploads || []).map(f => ({ ...f, _retrying: false, _cancelling: false })));

// ── Server upload actions (recent uploads from DB) ─────────
const retryServerUpload = async (file) => {
  if (file._retrying) return;
  file._retrying = true;
  try {
    await axios.post(`/upload/${file.id}/retry`);
    file.status = 'queued';
    file._retrying = false;
    // Start polling this asset for status updates
    pollServerUpload(file);
  } catch (e) {
    file._retrying = false;
    alert(e.response?.data?.message || 'Retry failed');
  }
};

const cancelServerUpload = async (file, index) => {
  if (file._cancelling) return;
  file._cancelling = true;
  try {
    await axios.delete(`/upload/${file.id}/cancel`);
    serverUploads.value = serverUploads.value.filter(f => f.id !== file.id);
  } catch (e) {
    if (e.response?.status === 409) {
      // Already done — just remove it from display
      serverUploads.value = serverUploads.value.filter(f => f.id !== file.id);
    } else {
      file._cancelling = false;
      alert(e.response?.data?.message || 'Cancel failed');
    }
  }
};

const pollServerUpload = (file) => {
  const timer = setInterval(async () => {
    try {
      const res = await axios.get(`/upload/status/${file.id}`);
      file.status = res.data.pipeline_status;
      if (['done', 'failed', 'cancelled'].includes(res.data.pipeline_status)) {
        clearInterval(timer);
      }
    } catch { clearInterval(timer); }
  }, 2000);
  // Auto-stop after 10 minutes
  setTimeout(() => clearInterval(timer), 600000);
};

// ── Auto-resume polling for in-progress uploads on page load ──
const inProgressStages = ['queued', 'hashing', 'previewing', 'tagging', 'classifying', 'indexing'];
serverUploads.value.forEach(file => {
  if (inProgressStages.includes(file.status)) {
    pollServerUpload(file);
  }
});

const isUploading = ref(false);
const fileInput = ref(null);
const pollingTimers = ref([]);

const form = reactive({
  collection: '',
  group: '',
});

// ── Computed ───────────────────────────────────────────
const overallStats = computed(() => {
  const uploading = uploadedEntries.value.filter(e => e.status === 'uploading').length;
  const processing = uploadedEntries.value.filter(e => e.status === 'processing').length;
  const done = uploadedEntries.value.filter(e => e.status === 'done').length;
  const error = uploadedEntries.value.filter(e => e.status === 'error').length;
  const aiReady = uploadedEntries.value.filter(e => e.aiMetadata).length;
  return { uploading, processing, done, error, aiReady, total: uploadedEntries.value.length + queuedFiles.value.length };
});

// ── File handling ──────────────────────────────────────
const handleDrop = (e) => { isDragging.value = false; addFiles(Array.from(e.dataTransfer.files)); };
const handleFileSelect = (e) => { addFiles(Array.from(e.target.files)); e.target.value = ''; };

const addFiles = (files) => {
  for (const file of files) {
    const entry = { file, preview: null };
    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = (e) => { entry.preview = e.target.result; };
      reader.readAsDataURL(file);
    }
    queuedFiles.value.push(entry);
  }
};

const removeQueued = (index) => { queuedFiles.value.splice(index, 1); };

const clearAll = () => {
  queuedFiles.value = [];
  uploadedEntries.value = [];
  form.collection = '';
  form.group = '';
  pollingTimers.value.forEach(clearInterval);
  pollingTimers.value = [];
};

// ── Upload logic ───────────────────────────────────────
const startUpload = async () => {
  if (queuedFiles.value.length === 0 || isUploading.value) return;
  isUploading.value = true;
  const filesToUpload = [...queuedFiles.value];
  queuedFiles.value = [];
  const concurrent = 3;
  let index = 0;

  const uploadNext = async () => {
    while (index < filesToUpload.length) {
      const i = index++;
      const qf = filesToUpload[i];
      const clientId = Date.now() + '-' + i;

      const entry = reactive({
        clientId,
        file: qf.file,
        name: qf.file.name,
        size: qf.file.size,
        sizeFormatted: formatSize(qf.file.size),
        progress: 0,
        status: 'uploading',
        assetId: null,
        pipelineStatus: null,
        thumbnailUrl: qf.preview || null,
        message: null,
        // AI metadata state
        aiMetadata: null,
        editForm: { group: '', collection: form.collection || '', description: '', tags: [] },
        expanded: false,
        metadataSaved: false,
        saving: false,
        newTagInput: '',
        // Cancel state
        abortController: new AbortController(),
        cancelling: false,
      });
      uploadedEntries.value.push(entry);

      try {
        const formData = new FormData();
        formData.append('file', qf.file);
        if (form.collection) formData.append('collection', form.collection);
        if (form.group) formData.append('group', form.group);

        const response = await axios.post('/upload/single', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
          signal: entry.abortController.signal,
          onUploadProgress: (progressEvent) => {
            if (progressEvent.total) {
              entry.progress = Math.round((progressEvent.loaded / progressEvent.total) * 100);
            }
          },
        });

        entry.status = 'processing';
        entry.assetId = response.data.id;
        entry.sizeFormatted = response.data.sizeFormatted || entry.sizeFormatted;
        entry.pipelineStatus = 'queued';
        startStatusPolling(entry);

      } catch (err) {
        if (axios.isCancel(err) || err.name === 'CanceledError' || err.code === 'ERR_CANCELED') {
          entry.status = 'cancelled';
          entry.message = 'Upload cancelled';
        } else if (err.response?.status === 409) {
          entry.status = 'duplicate';
          entry.message = err.response.data.message || 'Duplicate file';
          entry.assetId = err.response.data.id;
        } else {
          entry.status = 'error';
          entry.message = err.response?.data?.message || err.message || 'Upload failed';
        }
      }
    }
  };

  const workers = [];
  for (let w = 0; w < Math.min(concurrent, filesToUpload.length); w++) {
    workers.push(uploadNext());
  }
  await Promise.all(workers);
  isUploading.value = false;
};

// ── Status polling (with AI metadata) ──────────────────
const startStatusPolling = (entry) => {
  if (!entry.assetId) return;

  const timer = setInterval(async () => {
    // Skip if cancelled
    if (entry.status === 'cancelled') {
      clearInterval(timer);
      pollingTimers.value = pollingTimers.value.filter(t => t !== timer);
      return;
    }
    try {
      const res = await axios.get(`/upload/status/${entry.assetId}`);
      entry.pipelineStatus = res.data.pipeline_status;

      if (res.data.thumbnail_path) {
        entry.thumbnailUrl = `/serve/thumbnail/${entry.assetId}`;
      }

      // Auto-fill metadata when AI results arrive
      if (res.data.ai_metadata && !entry.aiMetadata) {
        entry.aiMetadata = res.data.ai_metadata;
        // Populate the editable form from AI results
        entry.editForm.group = res.data.ai_metadata.group || '';
        entry.editForm.description = res.data.ai_metadata.description || '';
        entry.editForm.tags = (res.data.ai_metadata.tags || []).map(t => ({
          tag: t.tag,
          confidence: t.confidence,
          source: t.source || 'ai',
          facet: t.facet || null,
        }));
        // Auto-expand the first file that gets AI results
        entry.expanded = true;
        await nextTick();
      }

      // Stop polling when done, failed, or cancelled
      if (res.data.pipeline_status === 'done' || res.data.pipeline_status === 'failed' || res.data.pipeline_status === 'cancelled') {
        entry.status = res.data.pipeline_status === 'done' ? 'done' : res.data.pipeline_status === 'cancelled' ? 'cancelled' : 'error';
        if (res.data.pipeline_status === 'failed') { entry.message = 'Processing failed'; }
        if (res.data.pipeline_status === 'cancelled') { entry.message = 'Cancelled by server'; }
        clearInterval(timer);
        pollingTimers.value = pollingTimers.value.filter(t => t !== timer);
        entry._pollingTimer = null;
      }
    } catch { /* silently ignore */ }
  }, 2000);

  entry._pollingTimer = timer;
  pollingTimers.value.push(timer);
};

// ── Cancel logic ───────────────────────────────────────
const cancelEntry = async (entry) => {
  if (entry.cancelling) return;
  entry.cancelling = true;

  try {
    // 1. If still uploading, abort the XHR and remove immediately
    if (entry.status === 'uploading' && entry.abortController) {
      entry.abortController.abort();
      entry.status = 'cancelled';
      entry.message = 'Cancelled';
      scheduleRemoval(entry);
      return;
    }

    // 2. If processing on server, call cancel endpoint
    if (entry.assetId && (entry.status === 'processing' || entry.status === 'error' || entry.status === 'duplicate')) {
      // Stop polling first
      stopPolling(entry);
      try {
        await axios.delete(`/upload/${entry.assetId}/cancel`);
      } catch (e) {
        // 409 = already done, that's fine
        if (e.response?.status !== 409) {
          entry.message = 'Cancel failed';
          entry.cancelling = false;
          return;
        }
      }
    }

    // 3. Remove from the list
    entry.status = 'cancelled';
    entry.message = 'Cancelled';
    scheduleRemoval(entry);
  } finally {
    entry.cancelling = false;
  }
};

const stopPolling = (entry) => {
  // Find and clear the polling timer for this entry
  // We'll track timers by entry now
  if (entry._pollingTimer) {
    clearInterval(entry._pollingTimer);
    pollingTimers.value = pollingTimers.value.filter(t => t !== entry._pollingTimer);
    entry._pollingTimer = null;
  }
};

const scheduleRemoval = (entry) => {
  setTimeout(() => {
    uploadedEntries.value = uploadedEntries.value.filter(e => e.clientId !== entry.clientId);
    if (selectedUploads.value.has(entry.clientId)) {
      selectedUploads.value.delete(entry.clientId);
      selectedUploads.value = new Set(selectedUploads.value);
    }
  }, 800);
};

// ── Multi-select cancel ────────────────────────────────────────
const selectedUploads = ref(new Set());

const toggleSelectUpload = (clientId) => {
  if (selectedUploads.value.has(clientId)) {
    selectedUploads.value.delete(clientId);
  } else {
    selectedUploads.value.add(clientId);
  }
  selectedUploads.value = new Set(selectedUploads.value);
};

const cancelSelected = async () => {
  const ids = [...selectedUploads.value];
  selectedUploads.value = new Set();
  for (const clientId of ids) {
    const entry = uploadedEntries.value.find(e => e.clientId === clientId);
    if (entry && entry.status !== 'done' && entry.status !== 'cancelled') {
      cancelEntry(entry);
    }
  }
};

// ── Metadata editing ───────────────────────────────────
const toggleExpand = (entry) => { entry.expanded = !entry.expanded; };

const removeTag = (entry, index) => { entry.editForm.tags.splice(index, 1); };

const addTag = (entry) => {
  const val = (entry.newTagInput || '').trim();
  if (!val) return;
  // Don't add duplicates
  if (entry.editForm.tags.some(t => t.tag.toLowerCase() === val.toLowerCase())) {
    entry.newTagInput = '';
    return;
  }
  entry.editForm.tags.push({ tag: val, confidence: 1.0, source: 'manual', facet: null });
  entry.newTagInput = '';
};

const resetToAi = (entry) => {
  if (!entry.aiMetadata) return;
  entry.editForm.group = entry.aiMetadata.group || '';
  entry.editForm.description = entry.aiMetadata.description || '';
  entry.editForm.tags = (entry.aiMetadata.tags || []).map(t => ({
    tag: t.tag, confidence: t.confidence, source: t.source || 'ai', facet: t.facet || null,
  }));
  entry.metadataSaved = false;
};

const saveMetadata = async (entry) => {
  if (!entry.assetId || entry.saving) return;
  entry.saving = true;
  try {
    await axios.patch(`/upload/${entry.assetId}/metadata`, {
      group: entry.editForm.group || null,
      description: entry.editForm.description || null,
      tags: entry.editForm.tags.map(t => t.tag),
    });
    entry.metadataSaved = true;
    // Brief flash then collapse
    setTimeout(() => { entry.expanded = false; }, 1200);
  } catch (err) {
    entry.message = 'Failed to save metadata';
  } finally {
    entry.saving = false;
  }
};

// ── Cleanup ────────────────────────────────────────────
onBeforeUnmount(() => { pollingTimers.value.forEach(clearInterval); });

// ── Helpers ────────────────────────────────────────────
const formatSize = (bytes) => {
  if (!bytes) return '0 B';
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
  if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB';
  return (bytes / 1073741824).toFixed(1) + ' GB';
};

const pipelineProgress = (status) => {
  const stages = { queued: 10, hashing: 25, previewing: 45, tagging: 60, classifying: 75, indexing: 90, done: 100 };
  return stages[status] || 5;
};

const getFileIcon = (name) => {
  const ext = (name || '').split('.').pop()?.toLowerCase();
  const map = {
    jpg: 'ri-image-line', jpeg: 'ri-image-line', png: 'ri-image-line', gif: 'ri-image-line', webp: 'ri-image-line', svg: 'ri-image-line', bmp: 'ri-image-line',
    mp4: 'ri-video-line', mov: 'ri-video-line', avi: 'ri-video-line', mkv: 'ri-video-line',
    pdf: 'ri-file-pdf-line', doc: 'ri-file-word-line', docx: 'ri-file-word-line',
    xls: 'ri-file-excel-line', xlsx: 'ri-file-excel-line',
    psd: 'ri-brush-line', tiff: 'ri-image-line', tif: 'ri-image-line',
    ai: 'ri-pen-nib-line', eps: 'ri-pen-nib-line',
  };
  return map[ext] || 'ri-file-line';
};

const statusIcon = (s) => ({ uploading: 'ri-upload-cloud-2-line', processing: 'ri-loader-4-line ri-spin', done: 'ri-check-line', error: 'ri-error-warning-line', duplicate: 'ri-file-copy-line', cancelled: 'ri-close-circle-line' }[s] || 'ri-file-line');
const statusIconBg = (s) => ({ uploading: 'bg-gradient-to-br from-indigo-400 to-violet-500', processing: 'bg-gradient-to-br from-teal-400 to-cyan-500', done: 'bg-gradient-to-br from-emerald-400 to-teal-500', error: 'bg-gradient-to-br from-red-400 to-rose-500', duplicate: 'bg-gradient-to-br from-amber-400 to-orange-500', cancelled: 'bg-gradient-to-br from-slate-400 to-slate-500' }[s] || 'bg-gradient-to-br from-indigo-400 to-violet-500');
const statusBadge = (s) => ({ uploading: 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300', processing: 'bg-teal-50 text-teal-600 dark:bg-teal-500/15 dark:text-teal-300', done: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300', error: 'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-300', duplicate: 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300', cancelled: 'bg-slate-50 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300' }[s] || 'bg-slate-50 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300');
const statusLabel = (s) => ({ uploading: 'Uploading', processing: 'Processing', done: 'Complete', error: 'Failed', duplicate: 'Duplicate', cancelled: 'Cancelled' }[s] || s);
const pipelineBadge = (s) => ({ queued: 'bg-slate-50 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300', hashing: 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300', previewing: 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300', tagging: 'bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300', classifying: 'bg-purple-50 text-purple-600 dark:bg-purple-500/15 dark:text-purple-300', indexing: 'bg-teal-50 text-teal-600 dark:bg-teal-500/15 dark:text-teal-300', done: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300', failed: 'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-300', cancelled: 'bg-slate-50 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300' }[s] || 'bg-slate-50 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300');

// ── Master Replacement (REQ-03) ─────────────────────
const replaceMaster = async (entry) => {
  if (!entry.assetId || !entry.file) return;
  entry.replacing = true;
  try {
    const formData = new FormData();
    formData.append('file', entry.file);
    const res = await axios.post(`/assets/${entry.assetId}/replace`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    entry.status = 'processing';
    entry.message = res.data.message || 'Replaced – processing new version';
    entry.pipelineStatus = 'queued';
    startStatusPolling(entry);
  } catch (err) {
    entry.message = err.response?.data?.message || 'Replacement failed';
    entry.status = 'error';
  } finally {
    entry.replacing = false;
  }
};
</script>

<style scoped>
.slide-enter-active { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
.slide-leave-active { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.slide-enter-from, .slide-leave-to { opacity: 0; max-height: 0; padding-top: 0; padding-bottom: 0; overflow: hidden; }
.slide-enter-to, .slide-leave-from { opacity: 1; max-height: 500px; }
.slide-up-enter-active { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
.slide-up-leave-active { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.slide-up-enter-from, .slide-up-leave-to { opacity: 0; transform: translateY(20px) translateX(-50%); }
.slide-up-enter-to, .slide-up-leave-from { opacity: 1; transform: translateY(0) translateX(-50%); }
</style>
