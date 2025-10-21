<?php

namespace App\Http\Controllers;

use App\Services\PhpMailerService;
use Illuminate\Http\Request;

class MailController extends Controller
{
    public function send(Request $request, PhpMailerService $mailer)
    {
        $validated = $request->validate([
            'to' => 'required|email',
            'subject' => 'required|string|max:200',
            'html' => 'required|string',
            'text' => 'nullable|string',
        ]);

        $mailer->send($validated['to'], 'OnlyFarms User', $validated['subject'], $validated['html'], $validated['text'] ?? null);

        return response()->json(['status' => 'sent']);
    }
}


