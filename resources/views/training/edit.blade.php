@extends('layouts.dashboard')

@section('title', 'Edit · ' . $round->course_name)

@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <a href="{{ route('training.show', $round->id) }}" class="inline-flex items-center gap-1 text-sm font-bold text-indigo-700 hover:text-indigo-900">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
        {{ $round->course_name }}
    </a>

    @include('partials.round-editor', [
        'action' => route('training.update', $round->id),
        'backUrl' => route('training.show', $round->id),
        'holes' => $round->holes,
        'players' => $players,
        'scores' => $scores,
        'courseName' => $round->course_name,
        'intro' => 'Anyone who played this round can fix its course, par and scores. Changes show up for every player on the card.',
    ])
</div>
@endsection
