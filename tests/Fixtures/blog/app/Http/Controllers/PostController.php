<?php

namespace App\Http\Controllers;

use App\Jobs\PublishPost;
use App\Models\Post;

class PostController extends Controller
{
    public function show(Post $post)
    {
        return view('posts.show', ['post' => $post]);
    }

    public function publish(Post $post)
    {
        PublishPost::dispatch($post);

        return back();
    }
}
