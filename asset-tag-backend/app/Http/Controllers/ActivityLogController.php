<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    // Get paginated logs
  public function index()
    {
        $logs = ActivityLog::latest()->paginate(20);

        // Transform logs to make old_data and new_data readable
        $logs->getCollection()->transform(function ($log) {

            $formatData = function ($data) {
                $formatted = [];
                if (!$data) return $formatted;

                foreach ($data as $key => $value) {
                    // Format booleans
                    if ($key === 'is_active') {
                        $value = $value ? 'Active' : 'Inactive';
                    }

                    // Format dates
                    if (str_contains($key, 'date') && $value) {
                        $value = date('M d, Y', strtotime($value));
                    }

                    // Keep the key readable
                    $formatted[ucfirst(str_replace('_', ' ', $key))] = $value ?? '-';
                }

                return $formatted;
            };

            return [
                'id' => $log->id,
                'user_name' => $log->user_name,
                'user_role' => $log->user_role,
                'action' => $log->action,
                'module' => $log->module,
                'record_id' => $log->record_id,
                'created_at' => $log->created_at->format('M d, Y h:i A'),
                'old_data' => $formatData($log->old_data),
                'new_data' => $formatData($log->new_data),
            ];
        });

        return response()->json($logs);
    }




    // Optionally get a single log
    public function show(ActivityLog $activityLog)
    {
        return response()->json($activityLog);
    }
}
