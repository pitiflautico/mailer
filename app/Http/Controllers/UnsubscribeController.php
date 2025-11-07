<?php

namespace App\Http\Controllers;

use App\Models\Unsubscribe;
use App\Models\ComplianceLog;
use Illuminate\Http\Request;

class UnsubscribeController extends Controller
{
    /**
     * Show unsubscribe confirmation page.
     */
    public function show(string $token)
    {
        $unsubscribe = Unsubscribe::where('unsubscribe_token', $token)->firstOrFail();

        return view('unsubscribe.confirm', [
            'email' => $unsubscribe->email,
            'token' => $token,
        ]);
    }

    /**
     * Process unsubscribe request.
     */
    public function process(Request $request, string $token)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $unsubscribe = Unsubscribe::where('unsubscribe_token', $token)->firstOrFail();

        // Update unsubscribe record
        $unsubscribe->update([
            'reason' => $request->input('reason'),
            'unsubscribed_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Log compliance action
        ComplianceLog::logAction(
            'unsubscribe',
            'User unsubscribed from emails',
            $unsubscribe->email,
            'can_spam',
            true,
            'Unsubscribe',
            $unsubscribe->id
        );

        return view('unsubscribe.success', [
            'email' => $unsubscribe->email,
        ]);
    }

    /**
     * One-click unsubscribe (RFC 8058).
     */
    public function oneClick(string $token)
    {
        $unsubscribe = Unsubscribe::where('unsubscribe_token', $token)->firstOrFail();

        $unsubscribe->update([
            'unsubscribed_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'reason' => 'One-click unsubscribe',
        ]);

        ComplianceLog::logAction(
            'unsubscribe_one_click',
            'User unsubscribed via one-click',
            $unsubscribe->email,
            'rfc8058',
            true
        );

        return response('Unsubscribed successfully', 200)
            ->header('Content-Type', 'text/plain');
    }
}
