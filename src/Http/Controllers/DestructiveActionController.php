<?php

namespace MehrabHossain\LogLens\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DestructiveActionController extends Controller
{
    /**
     * Handle state-changing / destructive operations.
     */
    public function execute(Request $request): JsonResponse
    {
        // Enforce explicit confirmation parameter for destructive actions
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:clear_logs,reset_metrics'],
            'confirmed' => ['required', 'boolean'],
        ]);

        if (! $validated['confirmed']) {
            return response()->json([
                'success' => false,
                'message' => 'Action was rejected because confirmation was not provided.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'action' => $validated['action'],
            'message' => 'Destructive action authorized and confirmed.',
        ]);
    }
}
