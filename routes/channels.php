<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    return Conversation::where('id', $conversationId)
        ->where(function ($q) use ($user) {
            $q->where('buyer_id', $user->id)
                ->orWhereHas('seller', function ($sellerQuery) use ($user) {
                    $sellerQuery->where('user_id', $user->id);
                });
        })
        ->exists();
});
