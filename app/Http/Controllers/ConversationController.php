<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conversation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ConversationController extends Controller
{
    
    public function checkOrCreate(Request $request)
    {
        try {
            if (!Auth::check()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $request->validate([
                'receiver_id' => 'required|integer|exists:users,id',
                'product_id' => 'nullable|integer|exists:products,id',
            ]);

            $senderId = Auth::id();
            $receiverId = $request->receiver_id;
            $productId = $request->product_id;

            Log::info('Creating conversation', [
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'product_id' => $productId
            ]);

            if ($senderId == $receiverId) {
                return response()->json(['error' => 'Cannot start conversation with yourself'], 400);
            }

            $conversation = Conversation::where(function ($q) use ($senderId, $receiverId) {
                $q->where('sender_id', $senderId)
                  ->where('receiver_id', $receiverId);
            })->orWhere(function ($q) use ($senderId, $receiverId) {
                $q->where('sender_id', $receiverId)
                  ->where('receiver_id', $senderId);
            });

            if ($productId) {
                $conversation = $conversation->where('product_id', $productId);
            }

            $conversation = $conversation->first();

            if (!$conversation) {
                $conversation = Conversation::create([
                    'sender_id' => $senderId,
                    'receiver_id' => $receiverId,
                    'product_id' => $productId,
                    'last_message_at' => now(),
                ]);
            }

            $conversation->load(['sender', 'receiver']);
            if ($productId) {
                $conversation->load('product');
            }

            return response()->json([
                'success' => true,
                'conversation' => $conversation
            ]);

        } catch (\Exception $e) {
            Log::error('=== CHAT ERROR ===', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to create conversation',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        try {
            if (!Auth::check()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $userId = Auth::id();

            $conversations = Conversation::where('sender_id', $userId)
                ->orWhere('receiver_id', $userId)
                ->with(['sender', 'receiver', 'latestMessage'])
                ->orderBy('last_message_at', 'desc')
                ->get()
                ->map(function ($conversation) use ($userId) {
                    $otherUser = $conversation->getOtherParticipant($userId);
                    return [
                        'id' => $conversation->id,
                        'name' => $otherUser->name,
                        'product_name' => $conversation->product ? $conversation->product->product_name : 'General Chat',
                        'last_message' => $conversation->latestMessage ? $conversation->latestMessage->message : '',
                        'last_message_at' => $conversation->last_message_at,
                        'updated_at' => $conversation->updated_at,
                    ];
                });

            return response()->json($conversations);
        } catch (\Exception $e) {
            Log::error('Error fetching conversations: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch conversations'], 500);
        }
    }
}
