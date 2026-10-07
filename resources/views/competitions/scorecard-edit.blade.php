@extends('layouts.dashboard')

@section('title', 'Edit scores · ' . $competition->name)

@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <a href="{{ route('competitions.view', $competition->id) }}" class="inline-flex items-center gap-1 text-sm font-bold text-indigo-700 hover:text-indigo-900">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
        {{ $competition->name }}
    </a>

    @include('partials.round-editor', [
        'action' => route('competitions.scorecard.update', $competition->id),
        'backUrl' => route('competitions.view', $competition->id),
        'holes' => $holes,
        'players' => $players,
        'scores' => $scores,
        'intro' => 'Official scores for every card. Only admins and the tournament director can change them; a changed hole replaces every scorer\'s entry for it'
            . ($competition->status === 'completed' ? ', and ratings are recalculated.' : '.'),
    ])
</div>
@endsection
