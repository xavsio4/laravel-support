@extends('support::layout', [
    'title' => "{$app} help",
    'description' => $summary ?: "Help and documentation for {$app}.",
    'canonical' => route('support.public.home'),
])

@section('content')
    <nav class="side" aria-label="Pages">
        <ul>
            @if ($faq)<li><a href="{{ route('support.public.page', ['slug' => $faq->slug]) }}">{{ $faq->title }}</a></li>@endif
            @foreach ($documents as $document)
                <li><a href="{{ route('support.public.page', ['slug' => $document->slug]) }}">{{ $document->title }}</a></li>
            @endforeach
        </ul>
    </nav>
    <article>
        <h1>{{ $app }} help</h1>
        <p class="lede">{{ $summary ?: "Help and documentation for {$app}." }}</p>
        <ul class="cards">
            @if ($faq)
                <li><a href="{{ route('support.public.page', ['slug' => $faq->slug]) }}"><b>{{ $faq->title }}</b><span>{{ $faq->summary() }}</span></a></li>
            @endif
            @foreach ($documents as $document)
                <li><a href="{{ route('support.public.page', ['slug' => $document->slug]) }}"><b>{{ $document->title }}</b><span>{{ $document->summary() }}</span></a></li>
            @endforeach
        </ul>
    </article>
@endsection
