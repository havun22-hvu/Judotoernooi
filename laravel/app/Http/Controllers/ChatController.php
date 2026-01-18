<?php

namespace App\Http\Controllers;

use App\Events\NewChatMessage;
use App\Models\ChatMessage;
use App\Models\Toernooi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * Get messages for current user/device
     */
    public function index(Request $request, Toernooi $toernooi): JsonResponse
    {
        $type = $request->input('type');
        $id = $request->input('id');

        $messages = ChatMessage::where('toernooi_id', $toernooi->id)
            ->where(function ($query) use ($type, $id) {
                // Messages sent TO this recipient
                $query->where(function ($q) use ($type, $id) {
                    $q->voor($type, $id);
                });
                // Messages sent BY this sender (to show in their own chat)
                $query->orWhere(function ($q) use ($type, $id) {
                    $q->where('van_type', $type);
                    if ($id !== null) {
                        $q->where('van_id', $id);
                    }
                });
            })
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($msg) => [
                'id' => $msg->id,
                'van_type' => $msg->van_type,
                'van_id' => $msg->van_id,
                'van_naam' => $msg->afzender_naam,
                'naar_type' => $msg->naar_type,
                'naar_id' => $msg->naar_id,
                'naar_naam' => $msg->ontvanger_naam,
                'bericht' => $msg->bericht,
                'gelezen' => $msg->gelezen_op !== null,
                'created_at' => $msg->created_at->toIso8601String(),
                'is_eigen' => $msg->van_type === $type && $msg->van_id == $id,
            ]);

        return response()->json($messages);
    }

    /**
     * Send a new message
     */
    public function store(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'van_type' => 'required|string|in:hoofdjury,mat,weging,spreker,dojo',
            'van_id' => 'nullable|integer',
            'naar_type' => 'required|string|in:hoofdjury,mat,weging,spreker,dojo,alle_matten,iedereen',
            'naar_id' => 'nullable|integer',
            'bericht' => 'required|string|max:1000',
        ]);

        $message = ChatMessage::create([
            'toernooi_id' => $toernooi->id,
            'van_type' => $validated['van_type'],
            'van_id' => $validated['van_id'] ?? null,
            'naar_type' => $validated['naar_type'],
            'naar_id' => $validated['naar_id'] ?? null,
            'bericht' => $validated['bericht'],
        ]);

        // Broadcast the message
        broadcast(new NewChatMessage($message))->toOthers();

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'van_naam' => $message->afzender_naam,
                'naar_naam' => $message->ontvanger_naam,
                'bericht' => $message->bericht,
                'created_at' => $message->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(Request $request, Toernooi $toernooi): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'id' => 'nullable|integer',
            'message_ids' => 'nullable|array',
            'message_ids.*' => 'integer',
        ]);

        $query = ChatMessage::where('toernooi_id', $toernooi->id)
            ->whereNull('gelezen_op')
            ->voor($validated['type'], $validated['id'] ?? null);

        if (!empty($validated['message_ids'])) {
            $query->whereIn('id', $validated['message_ids']);
        }

        $query->update(['gelezen_op' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Get unread count for recipient
     */
    public function unreadCount(Request $request, Toernooi $toernooi): JsonResponse
    {
        $type = $request->input('type');
        $id = $request->input('id');

        $count = ChatMessage::where('toernooi_id', $toernooi->id)
            ->voor($type, $id)
            ->ongelezen()
            // Don't count own messages as unread
            ->where(function ($q) use ($type, $id) {
                $q->where('van_type', '!=', $type)
                    ->orWhere('van_id', '!=', $id);
            })
            ->count();

        return response()->json(['count' => $count]);
    }
}
