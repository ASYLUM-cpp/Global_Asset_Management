<?php

namespace App\Http\Controllers;

use App\Jobs\DriveImportJob;
use App\Models\GoogleDriveToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GoogleDriveController — handles OAuth 2.0 flow and Google Drive file operations.
 *
 * Endpoints:
 *   GET  /google-drive/auth       → redirect to Google consent screen
 *   GET  /google-drive/callback   → handle OAuth callback, store tokens
 *   POST /google-drive/disconnect → revoke token and delete from DB
 *   GET  /google-drive/files      → list files/folders from user's Drive
 *   POST /google-drive/import     → download selected files into GAM pipeline
 */
class GoogleDriveController extends Controller
{
    // ── OAuth helpers ───────────────────────────────────────────

    private function googleConfig(): array
    {
        return config('services.google');
    }

    /**
     * Build the OAuth 2.0 authorize URL.
     */
    private function buildAuthUrl(string $state): string
    {
        $cfg = $this->googleConfig();

        $params = http_build_query([
            'client_id'     => $cfg['client_id'],
            'redirect_uri'  => url($cfg['redirect_uri']),
            'response_type' => 'code',
            'scope'         => implode(' ', $cfg['scopes']),
            'access_type'   => 'offline',  // get refresh token
            'prompt'        => 'consent',  // always ask so we get refresh_token
            'state'         => $state,
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
    }

    /**
     * Exchange authorization code for tokens.
     */
    private function exchangeCode(string $code): ?array
    {
        $cfg = $this->googleConfig();

        $resp = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'redirect_uri'  => url($cfg['redirect_uri']),
            'grant_type'    => 'authorization_code',
        ]);

        return $resp->successful() ? $resp->json() : null;
    }

    /**
     * Refresh an expired access token.
     */
    private function refreshAccessToken(GoogleDriveToken $token): bool
    {
        $cfg = $this->googleConfig();

        $resp = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'refresh_token' => $token->refresh_token,
            'client_id'     => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'grant_type'    => 'refresh_token',
        ]);

        if (!$resp->successful()) {
            Log::warning('Google Drive: token refresh failed', ['body' => $resp->body()]);
            return false;
        }

        $data = $resp->json();
        $token->update([
            'access_token' => $data['access_token'],
            'expires_at'   => now()->addSeconds($data['expires_in'] ?? 3600),
        ]);

        return true;
    }

    /**
     * Get a valid access token string, refreshing if needed.
     */
    private function getValidToken(GoogleDriveToken $token): ?string
    {
        if ($token->isExpired()) {
            if (!$token->refresh_token || !$this->refreshAccessToken($token)) {
                return null;
            }
            $token->refresh();
        }

        return $token->access_token;
    }

    // ── Routes ──────────────────────────────────────────────────

    /**
     * GET /google-drive/auth — redirect user to Google consent screen.
     */
    public function auth(Request $request)
    {
        $cfg = $this->googleConfig();

        if (empty($cfg['client_id']) || empty($cfg['client_secret'])) {
            return redirect()
                ->route('import.google-drive')
                ->with('error', 'Google Drive is not configured. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to .env.');
        }

        // CSRF-like state token
        $state = bin2hex(random_bytes(16));
        session(['google_drive_state' => $state]);

        return redirect()->away($this->buildAuthUrl($state));
    }

    /**
     * GET /google-drive/callback — handle OAuth redirect.
     */
    public function callback(Request $request)
    {
        // Verify state
        if ($request->input('state') !== session('google_drive_state')) {
            return redirect()
                ->route('import.google-drive')
                ->with('error', 'Invalid OAuth state. Please try again.');
        }
        session()->forget('google_drive_state');

        if ($request->has('error')) {
            return redirect()
                ->route('import.google-drive')
                ->with('error', 'Google authorization was denied: ' . $request->input('error'));
        }

        $code = $request->input('code');
        if (!$code) {
            return redirect()
                ->route('import.google-drive')
                ->with('error', 'No authorization code received.');
        }

        $tokens = $this->exchangeCode($code);
        if (!$tokens) {
            return redirect()
                ->route('import.google-drive')
                ->with('error', 'Failed to exchange authorization code. Please try again.');
        }

        // Get user's Google email for display
        $email = null;
        try {
            $profile = Http::withToken($tokens['access_token'])
                ->get('https://www.googleapis.com/oauth2/v2/userinfo');
            if ($profile->successful()) {
                $email = $profile->json('email');
            }
        } catch (\Throwable $e) {
            // Non-critical
        }

        // Store / update tokens
        GoogleDriveToken::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'access_token'  => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'expires_at'    => now()->addSeconds($tokens['expires_in'] ?? 3600),
                'email'         => $email,
            ]
        );

        activity()
            ->causedBy($request->user())
            ->log('Connected Google Drive' . ($email ? " ({$email})" : ''));

        return redirect()
            ->route('import.google-drive')
            ->with('success', 'Google Drive connected successfully!');
    }

    /**
     * POST /google-drive/disconnect — revoke token and remove from DB.
     */
    public function disconnect(Request $request)
    {
        $token = GoogleDriveToken::where('user_id', $request->user()->id)->first();

        if ($token) {
            // Best-effort revoke
            try {
                Http::post('https://oauth2.googleapis.com/revoke', [
                    'token' => $token->access_token,
                ]);
            } catch (\Throwable $e) {
                // Ignore revocation errors
            }

            $token->delete();

            activity()
                ->causedBy($request->user())
                ->log('Disconnected Google Drive');
        }

        return redirect()
            ->route('import.google-drive')
            ->with('success', 'Google Drive disconnected.');
    }

    /**
     * GET /google-drive/files — list files from a specific folder.
     *
     * Query params:
     *   ?folder=<folderId>  (default: 'root')
     *   ?mode=browse|recent|shared
     */
    public function files(Request $request)
    {
        $token = GoogleDriveToken::where('user_id', $request->user()->id)->first();
        if (!$token) {
            return response()->json(['error' => 'Not connected'], 401);
        }

        $accessToken = $this->getValidToken($token);
        if (!$accessToken) {
            $token->delete(); // force re-auth
            return response()->json(['error' => 'Token expired. Please reconnect.'], 401);
        }

        $mode     = $request->input('mode', 'browse');
        $folderId = $request->input('folder', 'root');
        $pageToken = $request->input('pageToken');

        try {
            $query = match ($mode) {
                'recent' => "trashed = false",
                'shared' => "sharedWithMe = true and trashed = false",
                default  => "'{$folderId}' in parents and trashed = false",
            };

            $params = [
                'q'       => $query,
                'fields'  => 'nextPageToken,files(id,name,mimeType,size,modifiedTime,iconLink,thumbnailLink)',
                'orderBy' => $mode === 'recent' ? 'viewedByMeTime desc' : 'folder,name',
                'pageSize' => 50,
            ];
            if ($pageToken) $params['pageToken'] = $pageToken;

            $resp = Http::withToken($accessToken)
                ->timeout(10)
                ->get('https://www.googleapis.com/drive/v3/files', $params);

            if (!$resp->successful()) {
                Log::warning('Google Drive API error', ['status' => $resp->status(), 'body' => $resp->body()]);
                return response()->json(['error' => 'Google Drive API error'], $resp->status());
            }

            $data  = $resp->json();
            $files = collect($data['files'] ?? [])->map(function ($f) {
                return [
                    'id'        => $f['id'],
                    'name'      => $f['name'],
                    'isFolder'  => $f['mimeType'] === 'application/vnd.google-apps.folder',
                    'mimeType'  => $f['mimeType'],
                    'size'      => isset($f['size']) ? $this->formatBytes((int) $f['size']) : '',
                    'sizeBytes' => (int) ($f['size'] ?? 0),
                    'modified'  => $f['modifiedTime'] ?? null,
                    'icon'      => $f['iconLink'] ?? null,
                    'thumbnail' => $f['thumbnailLink'] ?? null,
                ];
            });

            return response()->json([
                'files'         => $files,
                'nextPageToken' => $data['nextPageToken'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Google Drive list files error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to list files'], 500);
        }
    }

    /**
     * POST /google-drive/import — download selected files into the GAM pipeline.
     *
     * Body: { fileIds: ['abc123', 'def456', ...] }
     */
    public function import(Request $request)
    {
        $request->validate([
            'fileIds'   => 'required|array|min:1|max:50',
            'fileIds.*' => 'required|string',
        ]);

        $token = GoogleDriveToken::where('user_id', $request->user()->id)->first();
        if (!$token) {
            return back()->with('error', 'Not connected to Google Drive.');
        }

        $accessToken = $this->getValidToken($token);
        if (!$accessToken) {
            $token->delete();
            return back()->with('error', 'Google Drive token expired. Please reconnect.');
        }

        // Dispatch a background job for each file
        $count = 0;
        foreach ($request->input('fileIds') as $fileId) {
            DriveImportJob::dispatch(
                $request->user(),
                $fileId,
                $accessToken,
                $token->refresh_token,
            );
            $count++;
        }

        activity()
            ->causedBy($request->user())
            ->log("Queued {$count} Google Drive file(s) for import");

        return back()->with('success', "{$count} file(s) queued for import from Google Drive.");
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
    }
}
