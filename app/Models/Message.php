<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = ['conversation_id', 'sender_id', 'body', 'attachments', 'read_at'];

    // Belongs to a conversation
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    // Sender (always a User)
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
