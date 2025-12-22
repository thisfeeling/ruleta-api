<?php

namespace App\Events\Traits;

use Illuminate\Broadcasting\Channel;

trait BroadcastsToShow
{
    public function broadcastOn(): array
    {
        return [
            new Channel("show.{$this->show->id}"),
        ];
    }
}
