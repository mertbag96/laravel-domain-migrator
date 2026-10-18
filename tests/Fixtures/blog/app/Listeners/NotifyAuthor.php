<?php

namespace App\Listeners;

use App\Events\PostWasPublished;
use App\Notifications\PostPublished;

class NotifyAuthor
{
    public function handle(PostWasPublished $event): void
    {
        $event->post->author->notify(new PostPublished($event->post));
    }
}
