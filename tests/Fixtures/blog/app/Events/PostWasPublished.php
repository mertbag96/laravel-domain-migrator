<?php

namespace App\Events;

use App\Models\Post;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostWasPublished implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Post $post) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('posts')];
    }
}
