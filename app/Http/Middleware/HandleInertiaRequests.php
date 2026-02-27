<?php

namespace App\Http\Middleware;

use App\Models\Asset;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Activitylog\Models\Activity;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'auth' => fn () => $user ? [
                'user' => [
                    'id'       => $user->id,
                    'name'     => $user->name,
                    'email'    => $user->email,
                    'initials' => $user->initials,
                    'role'     => $user->primary_role,
                    'roles'    => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'allowed_groups' => $user->allowed_groups,
                    'uploads_count'  => $user->assets()->count(),
                    'reviews_count'  => $user->reviewedAssets()->count(),
                    'approval_rate'  => $user->reviewedAssets()->count() > 0
                        ? round($user->reviewedAssets()->where('review_status', 'approved')->count() / $user->reviewedAssets()->count() * 100, 1)
                        : 0,
                ],
            ] : null,

            'pendingReviewCount' => fn () => $user
                ? Asset::where('review_status', 'pending')->count()
                : 0,

            'notifications' => fn () => $user
                ? Activity::with('causer')
                    ->latest()
                    ->take(5)
                    ->get()
                    ->map(fn ($a) => [
                        'text' => ($a->causer?->name ?? 'System') . ' ' . $a->description,
                        'time' => $a->created_at->diffForHumans(),
                    ])
                : [],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
