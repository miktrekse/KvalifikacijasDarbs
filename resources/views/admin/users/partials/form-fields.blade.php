{{-- Shared fields for adding and editing a user; pass $editing (the user) when editing --}}
@php
    $editing = $editing ?? null;
    $check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>';
    $selectedRole = old('role', $editing->role ?? 'user');
@endphp

<section id="account" class="ds-section">
    <div class="ds-section__head">
        <span class="ds-section__num" style="--accent: #22a268">01</span>
        <div>
            <h2 class="ds-section__title">Account</h2>
            <p class="ds-section__text">{{ $editing ? 'Leave the password blank to keep the current one.' : 'The player signs in with this email and password.' }}</p>
        </div>
    </div>
    <div class="ds-fieldgrid">
        <div class="ds-field">
            <label for="name" class="ds-field__label">Name <i>*</i></label>
            <input type="text" name="name" id="name" required value="{{ old('name', $editing->name ?? '') }}"
                class="ds-field__input @error('name') is-invalid @enderror" placeholder="Full name">
            @error('name') <p class="ds-field__error">{{ $message }}</p> @enderror
        </div>
        <div class="ds-field">
            <label for="email" class="ds-field__label">Email <i>*</i></label>
            <input type="email" name="email" id="email" required value="{{ old('email', $editing->email ?? '') }}"
                class="ds-field__input @error('email') is-invalid @enderror" placeholder="email@example.com">
            @error('email') <p class="ds-field__error">{{ $message }}</p> @enderror
        </div>
        <div class="ds-field">
            <label for="password" class="ds-field__label">{{ $editing ? 'New password' : 'Password' }} @unless($editing)<i>*</i>@endunless</label>
            <input type="password" name="password" id="password" @required(!$editing) autocomplete="new-password"
                class="ds-field__input @error('password') is-invalid @enderror" placeholder="Minimum 8 characters">
            @error('password') <p class="ds-field__error">{{ $message }}</p> @enderror
        </div>
        <div class="ds-field">
            <label for="password_confirmation" class="ds-field__label">Confirm {{ $editing ? 'new ' : '' }}password @unless($editing)<i>*</i>@endunless</label>
            <input type="password" name="password_confirmation" id="password_confirmation" @required(!$editing) autocomplete="new-password"
                class="ds-field__input" placeholder="Repeat the password">
        </div>
    </div>
</section>

<section id="role" class="ds-section">
    <div class="ds-section__head">
        <span class="ds-section__num" style="--accent: #f26b3a">02</span>
        <div>
            <h2 class="ds-section__title">Role</h2>
            <p class="ds-section__text">Controls what this user can publish and manage.</p>
        </div>
    </div>
    <div class="ds-choices sm:!grid-cols-3">
        @foreach([
            'user' => ['Player', 'Track rounds, save drills, enter events.'],
            'verified' => ['Verified', 'Can also publish exercises and competitions.'],
            'admin' => ['Admin', 'Full access, including approvals and users.'],
        ] as $value => [$label, $text])
            <label class="ds-choice">
                <input type="radio" name="role" value="{{ $value }}" @checked($selectedRole === $value) @required($loop->first)>
                <span class="ds-choice__check !rounded-full">{!! $check !!}</span>
                <span class="ds-choice__body"><strong>{{ $label }}</strong><small>{{ $text }}</small></span>
            </label>
        @endforeach
    </div>
    @error('role') <p class="ds-field__error">{{ $message }}</p> @enderror
</section>
