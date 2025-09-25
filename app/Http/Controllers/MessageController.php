<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Message;
use App\Models\Conversation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{

    /**
     * Fetch all messages for a conversation
     */
    public function index($conversationId)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $conversation = Conversation::findOrFail($conversationId);

        // Ensure the user is part of the conversation
        if (!in_array(Auth::id(), [$conversation->sender_id, $conversation->receiver_id])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $messages = Message::where('conversation_id', $conversationId)
            ->with('sender')
            ->orderBy('created_at', 'asc')
            ->get();

        // Mark messages from other user as read
        Message::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'messages' => $messages
        ]);
    }

    /**
     * Send a message
     */
    public function store(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'message' => 'nullable|string|max:1000',
            'image_url' => 'nullable|string',
        ]);

        if (!$request->message && !$request->image_url) {
            return response()->json(['error' => 'Message or image is required'], 400);
        }

        $conversation = Conversation::findOrFail($request->conversation_id);

        // Ensure the user is part of the conversation
        if (!in_array(Auth::id(), [$conversation->sender_id, $conversation->receiver_id])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $message = Message::create([
            'conversation_id' => $request->conversation_id,
            'sender_id' => Auth::id(),
            'message' => $request->message,
            'image_url' => $request->image_url,
        ]);

        // Update conversation's last message time
        $conversation->update(['last_message_at' => now()]);

        // Load sender relationship
        $message->load('sender');

        return response()->json([
            'success' => true,
            'message' => $message
        ]);
    }

    /**
     * Mark a message as read
     */
    public function markAsRead($messageId)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $message = Message::findOrFail($messageId);
        $conversation = Conversation::findOrFail($message->conversation_id);

        // Only the receiver can mark messages as read
        if (Auth::id() === $message->sender_id) {
            return response()->json(['error' => 'Cannot mark your own message as read'], 400);
        }

        if (!in_array(Auth::id(), [$conversation->sender_id, $conversation->receiver_id])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $message->markAsRead();

        return response()->json([
            'success' => true,
            'message' => $message
        ]);
    }
}
