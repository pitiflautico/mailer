<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SendMailController extends Controller
{
    protected MailService $mailService;

    public function __construct(MailService $mailService)
    {
        $this->mailService = $mailService;
    }

    /**
     * Send a single email.
     */
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from' => 'required|email',
            'to' => 'required|email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'is_html' => 'nullable|boolean',
            'cc' => 'nullable|email',
            'bcc' => 'nullable|email',
            'reply_to' => 'nullable|email',
            'headers' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->mailService->send($request->all());

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Email sent successfully',
                'data' => [
                    'message_id' => $result['message_id'],
                    'send_log_id' => $result['send_log_id'] ?? null,
                ],
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }
    }

    /**
     * Send bulk emails.
     */
    public function sendBulk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'emails' => 'required|array|min:1|max:100',
            'emails.*.from' => 'required|email',
            'emails.*.to' => 'required|email',
            'emails.*.subject' => 'required|string|max:255',
            'emails.*.body' => 'required|string',
            'emails.*.is_html' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->mailService->sendBulk($request->input('emails'));

        return response()->json([
            'success' => true,
            'message' => 'Bulk send completed',
            'data' => [
                'success_count' => $result['success'],
                'failed_count' => $result['failed'],
                'errors' => $result['errors'],
            ],
        ], 200);
    }
}
