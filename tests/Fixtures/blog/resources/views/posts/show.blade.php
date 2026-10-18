<article>
    <h1>{{ $post->title }}</h1>

    @can('update', $post)
        <a href="#">Edit</a>
    @endcan

    <p>{{ App\Models\Post::count() }} posts</p>
</article>
