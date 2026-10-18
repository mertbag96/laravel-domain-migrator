<?php

namespace App\Jobs;

use App\Events\PostWasPublished;
use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishPost implements ShouldQueue
{
    use Queueable;

    public function __construct(public Post $post) {}

    public function handle(): void
    {
        PostWasPublished::dispatch($this->post);
    }
}
