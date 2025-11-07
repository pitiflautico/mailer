<?php

namespace App\Http\Controllers;

use App\Services\ComplianceService;
use Illuminate\Http\Request;

class ComplianceController extends Controller
{
    public function __construct(
        protected ComplianceService $complianceService
    ) {}

    /**
     * Export user data (GDPR).
     */
    public function exportData(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->input('email');
        $data = $this->complianceService->exportUserData($email);

        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="user_data_' . $email . '.json"');
    }

    /**
     * Delete user data (GDPR).
     */
    public function deleteData(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'confirmation' => 'required|accepted',
            'hard_delete' => 'boolean',
        ]);

        $email = $request->input('email');
        $hardDelete = $request->boolean('hard_delete', false);

        $result = $this->complianceService->deleteUserData($email, $hardDelete);

        return response()->json([
            'success' => true,
            'message' => 'User data has been deleted/anonymized',
            'deleted_counts' => $result,
        ]);
    }

    /**
     * Get compliance report.
     */
    public function report(Request $request, int $domainId)
    {
        $days = $request->input('days', 30);
        $report = $this->complianceService->generateComplianceReport($domainId, $days);

        return response()->json($report);
    }
}
