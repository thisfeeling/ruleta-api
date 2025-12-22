<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\Show;
use Illuminate\Http\Request;

class ShowController extends Controller
{
    public function start(Show $show)
    {
        $this->authorize('control', $show);

        if ($show->isActive()) {
            return response()->json(['message' => 'Show already started'], 422);
        }

        $show->update(['status' => 'in_progress', 'started_at' => now()]);

        return response()->json(['show' => $show]);
    }

    public function pause(Show $show)
    {
        $this->authorize('control', $show);

        if (!$show->isActive()) {
            return response()->json(['message' => 'Show not active'], 422);
        }

        $show->update(['status' => 'paused']);

        return response()->json(['show' => $show]);
    }

    public function end(Show $show)
    {
        $this->authorize('control', $show);

        if (!$show->isActive() && $show->status !== 'paused') {
            return response()->json(['message' => 'Show not in progress or paused'], 422);
        }

        $show->update(['status' => 'completed', 'completed_at' => now()]);

        return response()->json(['show' => $show]);
    }
}
