@php
    $url = route('support.public.page', ['slug' => $document->slug]);
    $jsonLd = $faqEntries !== []
        ? [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($e) => [
                '@type' => 'Question',
                'name' => $e['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $e['answer']],
            ], $faqEntries),
        ]
        : [
            '@context' => 'https://schema.org',
            '@type' => 'TechArticle',
            'headline' => $document->title,
            'description' => $document->summary(),
            'url' => $url,
            'dateModified' => $document->updated_at?->toAtomString(),
            'inLanguage' => 'en',
            'isPartOf' => ['@type' => 'WebSite', 'name' => "{$app} help", 'url' => route('support.public.home')],
        ];
@endphp

@extends('support::layout', [
    'title' => "{$document->title} · {$app} help",
    'description' => $document->summary(),
    'canonical' => $url,
    'markdown' => route('support.public.doc', ['slug' => $document->slug]),
    'jsonLd' => $jsonLd,
])

@section('content')
    <nav class="side" aria-label="Pages">
        <ul>
            @foreach ($documents as $item)
                <li><a href="{{ route('support.public.page', ['slug' => $item->slug]) }}" @if ($item->slug === $document->slug) aria-current="page" @endif>{{ $item->title }}</a></li>
            @endforeach
        </ul>
    </nav>
    <article>
        <h1>{{ $document->title }}</h1>
        @if ($document->description)<p class="lede">{{ $document->description }}</p>@endif
        {!! $html !!}
        <p class="meta">Updated {{ $document->updated_at?->toFormattedDateString() }} · <a href="{{ route('support.public.doc', ['slug' => $document->slug]) }}">Markdown</a></p>
    </article>
@endsection
