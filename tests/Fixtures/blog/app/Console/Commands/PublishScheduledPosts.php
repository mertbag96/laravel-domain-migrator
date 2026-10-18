<?php

namespace App\Console\Commands;

use App\Jobs\PublishPost;
use App\Models\Post;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    protected $signature = 'posts:publish';

    public function handle(): void
    {
        Post::query()->whereNull('published_at')->each(fn (Post $post) => PublishPost::dispatch($post));
    }
}
