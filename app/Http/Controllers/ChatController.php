<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Seller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Events\MessageSent;
use App\Events\ChatMessageNotification;

class ChatController extends Controller
{
    public function createConversation(Request $request)
    {
        try {
            $authId = auth()->id();
            
            if (!$authId) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $data = $request->validate([
                'buyer_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
                'seller_id' => ['required', 'integer'], // This is user_id of seller
                'product_id' => ['nullable', 'integer'],
            ]);

            $buyerId = $data['buyer_id'] ?? $authId;
            $sellerUserId = $data['seller_id'];
            $productId = $data['product_id'] ?? null;

        // Find seller by user_id
        $seller = Seller::where('user_id', $sellerUserId)->first();

        if (!$seller) {
            return response()->json(['error' => 'Seller not found'], 404);
        }

        if ($productId !== null) {
            $productExists = \DB::table('products')
                ->where('product_id', $productId)
                ->exists();

            if (!$productExists) {
                return response()->json(['error' => 'Product not found'], 404);
            }
        }

        if ($authId !== $buyerId && $authId !== $sellerUserId) {
            return response()->json(['error' => 'You must be one of the conversation participants.'], 403);
        }

        $conversation = Conversation::where('buyer_id', $buyerId)
            ->where('seller_id', $seller->id) // store seller DB id
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'buyer_id' => $buyerId,
                'seller_id' => $seller->id,
                'product_id' => $productId,
                'last_message_at' => null,
            ]);
        }

            $conversation->load(['latestMessage', 'buyer', 'seller.user']);
            return response()->json([
                'message' => $conversation->wasRecentlyCreated ? 'Conversation created successfully' : 'Conversation fetched successfully',
                'data' => $conversation
            ], $conversation->wasRecentlyCreated ? 201 : 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create conversation',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function sendMessage(Request $request, $conversationId)
    {
        $request->validate([
            'body' => 'required|string',
        ]);

        $conversation = Conversation::with('seller')->findOrFail($conversationId);
        $authId = auth()->id();

        $sellerUserId = $conversation->seller->user_id ?? null;

        if ($authId !== $conversation->buyer_id && $authId !== $sellerUserId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $authId,
            'body' => $request->input('body'),
        ]);

        $conversation->update(['last_message_at' => now()]);

        $conversation->loadMissing(['buyer', 'seller.user', 'product']);
        $message->load('sender');

        // Broadcast to the private conversation channel
        broadcast(new MessageSent($message))->toOthers();

        $recipientUserId = null;
        if ($authId === $conversation->buyer_id) {
            $recipientUserId = $conversation->seller?->user?->id;
        } else {
            $recipientUserId = $conversation->buyer?->id;
        }

        if ($recipientUserId) {
            $senderName = $message->sender->name ?? 'a customer';

            $payload = [
                'type' => 'chat_message',
                'title' => 'New Message! 💬',
                'message' => "You have a new message from {$senderName}",
                'sender' => [
                    'id' => $message->sender_id,
                    'name' => $message->sender->name ?? null,
                ],
                'conversation' => [
                    'id' => $conversation->id,
                    'product_id' => $conversation->product_id,
                    'product_name' => $conversation->product->product_name ?? null,
                ],
                'message_details' => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'conversation_id' => $conversation->id,
                    'created_at' => $message->created_at->toDateTimeString(),
                ],
            ];

            Notification::create([
                'user_id' => $recipientUserId,
                'type' => $payload['type'],
                'title' => $payload['title'],
                'message' => $payload['message'],
                'data' => [
                    'conversation' => $payload['conversation'],
                    'message' => $payload['message_details'],
                    'sender' => $payload['sender'],
                    'redirect_route' => '/tabs/chatpage',
                    'redirect_params' => ['conversationId' => $conversation->id],
                ],
            ]);

            broadcast(new ChatMessageNotification($recipientUserId, $payload))->toOthers();
        }

        return response()->json([
            'message' => 'Message sent successfully',
            'data' => [
                'id' => $message->id,
                'conversation_id' => $conversation->id,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender->name ?? null,
                'body' => $message->body,
                'created_at' => $message->created_at->toDateTimeString(),
            ]
        ], 201);
    }

    public function listConversations()
    {
        $authId = auth()->id();

        try {
            $conversations = Conversation::with(['seller.user', 'buyer', 'latestMessage'])
                ->where('buyer_id', $authId)
                ->orWhereHas('seller.user', function ($q) use ($authId) {
                    $q->where('id', $authId);
                })
                ->orderByDesc('last_message_at')
                ->get();

            // ✅ Filter out conversations with missing sellers
            $validConversations = $conversations->filter(function ($c) {
                return $c->seller && $c->seller->user;
            });

            $conversationsData = $validConversations->map(function ($c) use ($authId) {
                $other = $c->buyer_id == $authId ? $c->seller->user : $c->buyer;

                return [
                    'id' => $c->id,
                    'product_id' => $c->product_id,
                    'other_user' => [
                        'id' => $other->id ?? null,
                        'name' => $other->name ?? $c->seller->shop_name ?? "Unknown Seller",
                    ],
                    'last_message' => optional($c->latestMessage)->body,
                    'updated_at' => $c->updated_at,
                ];
            });

            return response()->json([
                'message' => 'Conversations fetched successfully',
                'data' => $conversationsData
            ]);
        } catch (\Exception $e) {
            // ✅ Return empty array if there are database issues
            return response()->json([
                'message' => 'Conversations fetched successfully',
                'data' => []
            ]);
        }
    }

    public function listMessages(Request $request, $conversationId)
    {
        try {
            $conversation = Conversation::with('seller')->findOrFail($conversationId);
            $authId = auth()->id();
            
            // ✅ Check if seller exists
            if (!$conversation->seller) {
                return response()->json(['error' => 'Conversation seller not found'], 404);
            }
            
            $sellerUserId = $conversation->seller->user_id ?? null;

            if ($authId !== $conversation->buyer_id && $authId !== $sellerUserId) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $messages = $conversation->messages()
                ->with('sender:id,name')
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'message' => 'Messages fetched successfully',
                'data' => $messages
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch messages'], 500);
        }
    }
}
