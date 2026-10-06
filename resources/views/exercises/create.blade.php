@extends('layouts.dashboard')

@section('title', 'Create New Exercise')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <a href="{{ route('exercises.index') }}" class="ds-back">
        <svg fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
        Exercises
    </a>

    <section class="ds-hero ds-hero--form">
        <p class="ds-eyebrow">New drill</p>
        <h1 class="mt-4">Share a drill<br><em>worth repeating.</em></h1>
        <p class="mt-3 max-w-lg text-sm sm:text-base">Describe the exercise, set its difficulty and the discs it needs — players can save it straight to their training list.</p>
        <nav class="ds-hero__steps mt-6" aria-label="Form sections">
            <a href="#drill"><b>1</b>The drill</a>
            <a href="#setup"><b>2</b>Setup</a>
            <a href="#sharing"><b>3</b>Tags &amp; sharing</a>
        </nav>
    </section>

    <form action="{{ route('exercises.store') }}" method="POST" class="ds-formstack">
        @csrf
        @include('exercises.partials.form-fields')

        <div class="ds-formbar">
            <p class="ds-formbar__note">Fields marked <span class="font-bold text-flight">*</span> are required.</p>
            <div class="ds-formbar__actions">
                <a href="{{ route('exercises.index') }}" class="ds-btn ds-btn--line">Cancel</a>
                <button type="submit" class="ds-btn ds-btn--flight">
                    Create exercise
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
