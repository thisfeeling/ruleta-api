<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\AudioPlay;
use App\Models\Player;
use Illuminate\Http\Request;

class AudioController extends Controller
{
    public function approve(Request $request, AudioPlay $audioPlay)
    {
        $this->authorize('control', $audioPlay->show);

        $audioPlay->update(['approved' => true, 'reviewed_by' => $request->user()->id]);

        return response()->json(['audio' => $audioPlay]);
    }

    public function reject(Request $request, AudioPlay $audioPlay)
    {
        $this->authorize('control', $audioPlay->show);

        $audioPlay->update(['approved' => false, 'reviewed_by' => $request->user()->id]);

        return response()->json(['audio' => $audioPlay]);
    }
}
