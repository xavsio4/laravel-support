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
        <form class="find" role="search" action="{{ route('support.public.home') }}" method="get">
            <input type="search" name="q" value="{{ $query }}" placeholder="Search the docs…" aria-label="Search the docs">
            <button type="submit">Search</button>
        </form>
        @if ($results !== null)
            <h2 class="results">{{ count($results) }} {{ \Illuminate\Support\Str::plural('result', count($results)) }} for “{{ $query }}”</h2>
            @forelse ($results as $hit)
                <a class="hit" href="{{ $hit['url'] }}">
                    <b>{{ $hit['title'] }}@if ($hit['section']) › {{ $hit['section'] }}@endif</b>
                    <span>{{ $hit['snippet'] }}</span>
                </a>
            @empty
                <p class="lede">Nothing matches. Try other words, or browse the pages below.</p>
            @endforelse
            <h2 class="results">All pages</h2>
        @endif
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
