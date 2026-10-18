<?php

use App\Models\Post;

it('shows a post', function () {
    $post = Post::factory()->create();

    $this->get("/posts/{$post->id}")->assertOk();
});
