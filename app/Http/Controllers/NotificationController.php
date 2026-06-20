<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function getUnread(Request $request): JsonResponse
    {
        $notifications = $request->user()->unreadNotifications;

        $formatNotifications = $notifications->map(function ($notification) {
            return [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'No Title',
                'message' => $notification->data['message'] ?? '',
                'purchase_order_id' => $notification->data['purchase_order_id'] ?? null,
                'po_code' => $notification->data['po_code'] ?? null,
                'status' => $notification->data['status'] ?? null,
                'type' => $notification->data['type'] ?? 'info',
                'created_at' => $notification->created_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formatNotifications
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->find($id);

        if ($notification) {
            $notification->markAsRead();
            return response()->json([
                'success' => true,
                'message' => 'Notifikasi berhasil ditandai dibaca'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Notifikasi tidak ditemukan'
        ], 404);
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Semua notifikasi berhasil ditandai dibaca'
        ]);
    }
}
