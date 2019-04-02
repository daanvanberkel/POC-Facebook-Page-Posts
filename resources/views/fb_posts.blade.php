<h1>Posts</h1>

<hr/>

@foreach($posts as $post)
    <p>Post van {{ $post['page'] }}</p>
    <p>Post message: {{ $post['message'] ?? '' }}</p>
    <p>Post image: <img src="{{ $post['full_picture'] }}" /></p>
    <p>Post link: <a href="{{ $post['link'] }}">{{ $post['link'] }}</a></p>
    <hr/>
@endforeach
