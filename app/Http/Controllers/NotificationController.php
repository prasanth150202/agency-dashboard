<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function read(Request $request, Notification $notification): JsonResponse
    {
        abort_unless(
            $notification->organisation_id === (int) session('organisation_id'),
            403
        );

        if (! $notification->is_read) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $organisationId = session('organisation_id');

        Notification::where('organisation_id', $organisationId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
