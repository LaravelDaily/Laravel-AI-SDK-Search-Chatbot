<?php

namespace App\Http\Controllers;

use App\Models\AiSearchLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AiSearchLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AiSearchLog::class);

        $logs = AiSearchLog::query()
            ->where('company_id', $request->user()?->company_id)
            ->with('user')
            ->latest()
            ->limit(100)
            ->get();

        return view('admin.ai-logs.index', [
            'logs' => $logs,
        ]);
    }
}
