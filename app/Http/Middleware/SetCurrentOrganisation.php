<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentOrganisation
{
    /**
     * Resolve, validate, and share the currently selected organisation
     * for the authenticated user. Every dashboard query is scoped
     * through this so one organisation can never see another's data.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $organisations = $user->organisations()->withCount('stores')->orderBy('name')->get();

        $selectedId = session('organisation_id');
        $current = $organisations->firstWhere('id', $selectedId);

        if (! $current) {
            $current = $organisations->first();

            if ($current) {
                session(['organisation_id' => $current->id]);
            }
        }

        $request->attributes->set('currentOrganisation', $current);
        app()->instance('currentOrganisation', $current);

        View::share('currentOrganisation', $current);
        View::share('availableOrganisations', $organisations);

        if ($current) {
            $unreadCount = $current->notifications()->whereNull('read_at')->count();
            View::share('unreadNotificationsCount', $unreadCount);
            View::share('notificationGroups', $this->groupedNotifications($current));
        } else {
            View::share('unreadNotificationsCount', 0);
            View::share('notificationGroups', []);
        }

        return $next($request);
    }

    private function groupedNotifications($organisation): array
    {
        $notifications = $organisation->notifications()
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        return $notifications
            ->groupBy(fn ($notification) => $notification->created_at->isToday() ? 'Today' : $notification->created_at->format('M j'))
            ->map(function ($items, $label) {
                return [
                    'label' => $label,
                    'items' => $items->map(fn ($n) => [
                        'id' => $n->id,
                        'title' => $n->title,
                        'message' => $n->message,
                        'time' => $n->created_at->diffForHumans(),
                        'read' => $n->is_read,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
