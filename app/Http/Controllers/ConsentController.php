<?php

namespace App\Http\Controllers;

use App\Models\ConsentRecord;
use App\Models\ComplianceLog;
use Illuminate\Http\Request;

class ConsentController extends Controller
{
    /**
     * Show consent form.
     */
    public function show(string $email)
    {
        return view('consent.form', [
            'email' => $email,
        ]);
    }

    /**
     * Grant consent.
     */
    public function grant(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'consent_types' => 'required|array',
            'consent_types.*' => 'in:marketing,transactional,newsletter,promotional',
        ]);

        $email = $request->input('email');
        $consentTypes = $request->input('consent_types');

        foreach ($consentTypes as $type) {
            $consent = ConsentRecord::grant(
                $email,
                $type,
                'opt_in',
                null,
                'User granted consent via web form',
                ['user_agent' => $request->userAgent()]
            );

            ComplianceLog::logGdpr(
                'consent_granted',
                $email,
                "Consent granted for {$type}",
                ['consent_id' => $consent->id]
            );
        }

        return view('consent.success', [
            'email' => $email,
            'consent_types' => $consentTypes,
        ]);
    }

    /**
     * Verify double opt-in.
     */
    public function verify(string $token)
    {
        $consent = ConsentRecord::where('verification_token', $token)->firstOrFail();

        if ($consent->verified_at) {
            return view('consent.already-verified', [
                'email' => $consent->email,
            ]);
        }

        $consent->verify();

        ComplianceLog::logGdpr(
            'consent_verified',
            $consent->email,
            'Double opt-in consent verified',
            ['consent_id' => $consent->id]
        );

        return view('consent.verified', [
            'email' => $consent->email,
            'consent_type' => $consent->consent_type,
        ]);
    }

    /**
     * Revoke consent.
     */
    public function revoke(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'consent_type' => 'required|in:marketing,transactional,newsletter,promotional,all',
            'reason' => 'nullable|string|max:500',
        ]);

        $email = $request->input('email');
        $consentType = $request->input('consent_type');
        $reason = $request->input('reason');

        if ($consentType === 'all') {
            $consents = ConsentRecord::where('email', $email)
                ->where('granted', true)
                ->get();

            foreach ($consents as $consent) {
                $consent->revoke($reason);
            }
        } else {
            $consent = ConsentRecord::where('email', $email)
                ->where('consent_type', $consentType)
                ->where('granted', true)
                ->first();

            if ($consent) {
                $consent->revoke($reason);
            }
        }

        ComplianceLog::logGdpr(
            'consent_revoked',
            $email,
            "Consent revoked for {$consentType}",
            ['reason' => $reason]
        );

        return response()->json([
            'success' => true,
            'message' => 'Consent revoked successfully',
        ]);
    }
}
