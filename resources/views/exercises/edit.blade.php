@extends('layouts.dashboard')

@section('title', 'Edit Exercise')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <a href="{{ route('exercises.view', $exercise->id) }}" class="ds-back">
        <svg fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
        Back to exercise
    </a>

    <section class="ds-hero ds-hero--form">
        <p class="ds-eyebrow">Edit drill</p>
        <h1 class="mt-4">{{ $exercise->title }}</h1>
        <p class="mt-3 max-w-lg text-sm sm:text-base">Fine-tune the instructions, difficulty and equipment. Players who saved it will see the update.</p>
        <nav class="ds-hero__steps mt-6" aria-label="Form sections">
            <a href="#drill"><b>1</b>The drill</a>
            <a href="#setup"><b>2</b>Setup</a>
            <a href="#sharing"><b>3</b>Tags &amp; sharing</a>
        </nav>
    </section>

    <form action="{{ route('exercises.update', $exercise->id) }}" method="POST" class="ds-formstack">
        @csrf
        @method('PUT')
        @include('exercises.partials.form-fields', ['exercise' => $exercise])

        <div class="ds-formbar">
            <p class="ds-formbar__note">Fields marked <span class="font-bold text-flight">*</span> are required.</p>
            <div class="ds-formbar__actions">
                <a href="{{ route('exercises.view', $exercise->id) }}" class="ds-btn ds-btn--line">Cancel</a>
                <button type="submit" class="ds-btn ds-btn--flight">Save changes</button>
            </div>
        </div>
    </form>
</div>
@endsection
