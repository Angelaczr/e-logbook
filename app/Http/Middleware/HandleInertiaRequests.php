<?php

namespace App\Http\Middleware;

use App\Models\Logbook;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role->value,
                    'role_label' => $user->role->label(),
                ],
                'can' => [
                    'review' => $user !== null && $user->is_active && $user->role->canReview(),
                    'viewAnalytics' => $user?->can('view-analytics') ?? false,
                    'export' => $user?->can('export-reports') ?? false,
                ],
            ],
            'pendingReviewCount' => fn (): int => $user !== null && $user->is_active && $user->role->canReview()
                ? Logbook::query()->pendingReviewFor($user)->count()
                : 0,
            'notifications' => fn (): array => $user === null
                ? ['unread_count' => 0, 'items' => []]
                : [
                    'unread_count' => $user->unreadNotifications()->count(),
                    'items' => $user->notifications()->latest()->limit(10)->get()->map(fn ($notification) => [
                        'id' => $notification->id,
                        'title' => $notification->data['title'] ?? '',
                        'message' => $notification->data['message'] ?? '',
                        'read' => $notification->read_at !== null,
                        'created_at' => $notification->created_at->toIso8601String(),
                    ])->all(),
                ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
