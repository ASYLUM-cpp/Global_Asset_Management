<?php

use App\Http\Controllers\ActionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GoogleDriveController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// ── Guest routes ────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ── Authenticated routes ────────────────────────────
Route::middleware('auth')->group(function () {

    // ── Page routes (GET — render Inertia views) ────
    Route::get('/', [PageController::class, 'dashboard'])->name('dashboard');
    Route::get('/assets', [PageController::class, 'assets'])->name('assets');
    Route::get('/upload', [PageController::class, 'upload'])->name('upload');
    Route::get('/pipeline', [PageController::class, 'pipeline'])->name('pipeline');
    Route::get('/review', [PageController::class, 'review'])->name('review');
    Route::get('/analytics', [PageController::class, 'analytics'])->name('analytics');
    Route::get('/collections', [PageController::class, 'collections'])->name('collections');
    Route::get('/taxonomy', [PageController::class, 'taxonomy'])->name('taxonomy');
    Route::get('/settings', [PageController::class, 'settings'])->name('settings');
    Route::get('/permissions', [PageController::class, 'permissions'])->name('permissions');
    Route::get('/documents', [PageController::class, 'documents'])->name('documents');
    Route::get('/notes', [PageController::class, 'notes'])->name('notes');
    Route::get('/datasets', [PageController::class, 'datasets'])->name('datasets');
    Route::get('/preview/{id?}', [PageController::class, 'preview'])->name('preview');
    Route::get('/profile', [PageController::class, 'profile'])->name('profile');
    Route::get('/search', [ActionController::class, 'search'])->name('search');

    // ── Upload (REQ-04) ─────────────────────────────
    Route::post('/upload', [ActionController::class, 'uploadStore'])->name('upload.store');
    Route::post('/upload/single', [ActionController::class, 'uploadSingle'])->name('upload.single');
    Route::get('/upload/status/{id}', [ActionController::class, 'uploadStatus'])->name('upload.status');
    Route::patch('/upload/{id}/metadata', [ActionController::class, 'updateAssetMetadata'])->name('upload.metadata.update');
    Route::delete('/upload/{id}/cancel', [ActionController::class, 'cancelUpload'])->name('upload.cancel');
    Route::post('/upload/{id}/retry', [ActionController::class, 'retryUpload'])->name('upload.retry');

    // ── File serving (previews & thumbnails) ────────
    Route::get('/serve/preview/{id}', [ActionController::class, 'servePreview'])->name('serve.preview');
    Route::get('/serve/thumbnail/{id}', [ActionController::class, 'serveThumbnail'])->name('serve.thumbnail');

    // ── Review actions (REQ-15) ─────────────────────
    Route::post('/review/{id}/approve', [ActionController::class, 'reviewApprove'])->name('review.approve');
    Route::post('/review/{id}/reject', [ActionController::class, 'reviewReject'])->name('review.reject');
    Route::post('/review/{id}/override', [ActionController::class, 'reviewOverride'])->name('review.override');
    Route::post('/review/{id}/flag', [ActionController::class, 'reviewFlag'])->name('review.flag');

    // ── Tag management (REQ-15) ─────────────────────
    Route::post('/assets/{id}/tags', [ActionController::class, 'addTag'])->name('assets.tags.add');
    Route::delete('/assets/{id}/tags/{tagId}', [ActionController::class, 'removeTag'])->name('assets.tags.remove');

    // ── Asset actions (REQ-16, REQ-19, REQ-20) ──────
    Route::get('/assets/{id}/download', [ActionController::class, 'downloadAsset'])->name('assets.download');
    Route::delete('/assets/{id}', [ActionController::class, 'deleteAsset'])->name('assets.destroy');
    Route::post('/assets/bulk-delete', [ActionController::class, 'bulkDeleteAssets'])->name('assets.bulk-delete');

    // ── Taxonomy CRUD + CSV import (REQ-13) ─────────
    Route::post('/taxonomy', [ActionController::class, 'taxonomyStore'])->name('taxonomy.store');
    Route::put('/taxonomy/{id}', [ActionController::class, 'taxonomyUpdate'])->name('taxonomy.update');
    Route::delete('/taxonomy/{id}', [ActionController::class, 'taxonomyDestroy'])->name('taxonomy.destroy');
    Route::post('/taxonomy/import', [ActionController::class, 'taxonomyImport'])->name('taxonomy.import');

    // ── Collection CRUD (REQ-17) ────────────────────
    Route::post('/collections', [ActionController::class, 'collectionStore'])->name('collections.store');
    Route::put('/collections/{id}', [ActionController::class, 'collectionUpdate'])->name('collections.update');
    Route::delete('/collections/{id}', [ActionController::class, 'collectionDestroy'])->name('collections.destroy');
    Route::post('/collections/bulk-delete', [ActionController::class, 'bulkDeleteCollections'])->name('collections.bulk-delete');
    Route::post('/collections/{id}/assets', [ActionController::class, 'collectionAddAssets'])->name('collections.assets.add');
    Route::delete('/collections/{id}/assets/{assetId}', [ActionController::class, 'collectionRemoveAsset'])->name('collections.assets.remove');
    Route::post('/collections/{id}/permissions', [ActionController::class, 'collectionPermissions'])->name('collections.permissions.update');

    // ── Settings (REQ-27) ───────────────────────────
    Route::post('/settings', [ActionController::class, 'settingsUpdate'])->name('settings.update');

    // ── Permissions / RBAC (REQ-18) ─────────────────
    Route::put('/permissions/users/{id}/role', [ActionController::class, 'assignRole'])->name('permissions.role');
    Route::post('/permissions/users', [ActionController::class, 'createUser'])->name('permissions.users.store');
    Route::patch('/permissions/users/{id}', [ActionController::class, 'updateUser'])->name('permissions.users.update');
    Route::delete('/permissions/users/{id}', [ActionController::class, 'deleteUser'])->name('permissions.users.destroy');
    Route::post('/permissions/invite', [ActionController::class, 'inviteUser'])->name('permissions.invite');

    // ── Profile (REQ-26) ────────────────────────────
    Route::put('/profile', [ActionController::class, 'profileUpdate'])->name('profile.update');
    Route::put('/profile/password', [ActionController::class, 'profilePassword'])->name('profile.password');
    // ── Audit Log (REQ-25) ──────────────────────────────
    Route::get('/audit-log', [PageController::class, 'auditLog'])->name('audit-log');
    Route::get('/audit-log/export', [ActionController::class, 'auditLogExport'])->name('audit-log.export');

    // ── Version Management (REQ-16) ─────────────────────
    Route::post('/assets/{id}/versions', [ActionController::class, 'uploadVersion'])->name('assets.versions.upload');
    Route::patch('/assets/{id}/versions/{versionId}/restore', [ActionController::class, 'restoreVersion'])->name('assets.versions.restore');

    // ── Asset Metadata Edit (REQ-02) ─────────────────────
    Route::patch('/assets/{id}', [ActionController::class, 'updateAssetMetadata'])->name('assets.update');

    // ── API Token Management (REQ-01) ───────────────────
    Route::post('/settings/tokens', [ActionController::class, 'createToken'])->name('settings.tokens.create');
    Route::delete('/settings/tokens/{id}', [ActionController::class, 'revokeToken'])->name('settings.tokens.revoke');

    // ── Backup Management (REQ-16) ──────────────────────
    Route::get('/settings/backups', [ActionController::class, 'listBackups'])->name('settings.backups.list');
    Route::post('/settings/backups', [ActionController::class, 'runBackup'])->name('settings.backups.run');
    Route::delete('/settings/backups/{filename}', [ActionController::class, 'deleteBackup'])->name('settings.backups.delete');

    // ── Google Drive Import (REQ-04) ────────────────────
    Route::get('/import/google-drive', [PageController::class, 'googleDrive'])->name('import.google-drive');
    Route::get('/google-drive/auth', [GoogleDriveController::class, 'auth'])->name('google-drive.auth');
    Route::get('/google-drive/callback', [GoogleDriveController::class, 'callback'])->name('google-drive.callback');
    Route::post('/google-drive/disconnect', [GoogleDriveController::class, 'disconnect'])->name('google-drive.disconnect');
    Route::get('/google-drive/files', [GoogleDriveController::class, 'files'])->name('google-drive.files');
    Route::post('/google-drive/import', [GoogleDriveController::class, 'import'])->name('google-drive.import');

    // ── Master Replacement (REQ-03) ─────────────────────
    Route::post('/assets/{id}/replace', [ActionController::class, 'replaceWithVersion'])->name('assets.replace');

    // ── Documents (BookStack API — REQ-24) ──────────────
    Route::post('/documents', [ActionController::class, 'createDocument'])->name('documents.store');
    Route::post('/documents/books', [ActionController::class, 'createBook'])->name('documents.store-book');
    Route::post('/documents/chapters', [ActionController::class, 'createChapter'])->name('documents.store-chapter');
    Route::get('/documents/{id}/content', [ActionController::class, 'documentContent'])->name('documents.content');
    Route::post('/documents/refresh-cache', [ActionController::class, 'documentsRefreshCache'])->name('documents.refresh-cache');
    Route::get('/documents/pages/{page_id}/assets', [ActionController::class, 'documentPageAssets'])->name('documents.page-assets');
    Route::get('/documents/graph', [ActionController::class, 'documentGraph'])->name('documents.graph');
    Route::post('/documents/link-asset', [ActionController::class, 'linkAssetToDocument'])->name('documents.link-asset');
    Route::delete('/documents/link-asset/{id}', [ActionController::class, 'unlinkAssetFromDocument'])->name('documents.unlink-asset');

    // ── Notes (Trilium ETAPI — REQ-25) ──────────────────
    Route::post('/notes', [ActionController::class, 'createNote'])->name('notes.store');
    Route::post('/notes/{id}/like', [ActionController::class, 'likeNote'])->name('notes.like');
    Route::post('/notes/{id}/reply', [ActionController::class, 'replyToNote'])->name('notes.reply');
    Route::post('/notes/refresh-cache', [ActionController::class, 'notesRefreshCache'])->name('notes.refresh-cache');
    Route::get('/notes/{note_id}/assets', [ActionController::class, 'noteAssets'])->name('notes.note-assets');
    Route::get('/notes/knowledge-graph', [ActionController::class, 'knowledgeGraph'])->name('notes.knowledge-graph');
    Route::post('/notes/link-asset', [ActionController::class, 'linkAssetToNote'])->name('notes.link-asset');
    Route::delete('/notes/link-asset/{id}', [ActionController::class, 'unlinkAssetFromNote'])->name('notes.unlink-asset');
});
