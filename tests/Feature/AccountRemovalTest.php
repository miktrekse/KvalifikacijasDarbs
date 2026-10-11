<?php

namespace Tests\Feature;

use App\Jobs\RecalculateRatings;
use App\Models\Competition;
use App\Models\RoundRating;
use App\Models\TrainingRound;
use App\Models\User;
use App\Support\AccountRemoval;
use App\Support\RatingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Removing an account must never rewrite anyone else's sport history. */
class AccountRemovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /** Three rated tournaments for the same field, so every player ends up with a rating. */
    private function ratedHistory(User $organizer): array
    {
        $field = User::factory()->count(4)->create();
        foreach (range(1, 3) as $_) {
            $competition = $this->competition(['user_id' => $organizer->id]);
            $field->each(fn (User $player) => $this->registerPlayer($competition, $player));
            $this->travelToMinutesBeforeStart($competition, -1);
            $competition->syncLifecycle();
            $competition->refresh();
            $field->each(fn (User $player, int $i) => $this->finishRound($competition, $player, 2 + $i % 3));
            $competition->update(['status' => 'completed']);
        }
        app(RatingEngine::class)->recalculate();

        return $field->map->fresh()->all();
    }

    public function test_removing_an_organizer_keeps_their_tournaments_and_everyones_ratings(): void
    {
        $organizer = User::factory()->verifiedPlayer()->create();
        $field = $this->ratedHistory($organizer);
        $ratingsBefore = collect($field)->pluck('rating', 'id');
        $roundsBefore = RoundRating::count();

        $this->actingAs($this->admin)->delete("/admin/users/{$organizer->id}")->assertSessionHas('success');

        $this->assertSame(3, Competition::where('user_id', $organizer->id)->count());
        $this->assertSame($roundsBefore, RoundRating::count());
        $this->assertEquals($ratingsBefore, collect($field)->map->fresh()->pluck('rating', 'id'));
    }

    public function test_removing_a_player_anonymises_them_but_keeps_their_results(): void
    {
        Storage::fake('public');
        $field = $this->ratedHistory(User::factory()->verifiedPlayer()->create());
        $player = $field[0];
        $player->update(['avatar' => $avatar = UploadedFile::fake()->image('me.jpg')->store('avatars', 'public')]);
        $othersBefore = collect($field)->slice(1)->pluck('rating', 'id');
        $oldEmail = $player->email;

        $this->actingAs($this->admin)->delete("/admin/users/{$player->id}");

        $player->refresh();
        $this->assertTrue($player->isAnonymized());
        $this->assertSame(AccountRemoval::ANONYMOUS_NAME, $player->name);
        $this->assertNull($player->avatar);
        Storage::disk('public')->assertMissing($avatar);
        $this->assertSame(3, $player->roundRatings()->count());
        $this->assertEquals($othersBefore, collect($field)->slice(1)->map->fresh()->pluck('rating', 'id'));

        // Gone from lists and searches, and the old password no longer signs in
        $this->actingAs($this->admin)->get('/admin/users')->assertDontSee($player->email);
        $this->assertFalse(User::withoutGuests()->whereKey($player->id)->exists());
        auth()->logout();
        $this->post('/login', ['email' => $oldEmail, 'password' => 'password']);
        $this->assertGuest();
    }

    public function test_entries_for_events_not_yet_drawn_are_withdrawn(): void
    {
        $player = User::factory()->create();
        $upcoming = $this->competition();
        $this->registerPlayer($upcoming, $player);

        AccountRemoval::anonymize($player);

        $this->assertSame(0, $upcoming->registrations()->count());
    }

    public function test_a_shared_training_round_survives_its_owner_being_removed(): void
    {
        $owner = User::factory()->create();
        $friend = User::factory()->create();
        $this->actingAs($owner)->post('/training', ['course_name' => 'Test Park', 'holes_count' => 3, 'player_ids' => [$friend->id]]);
        $round = TrainingRound::sole();

        $this->actingAs($this->admin)->delete("/admin/users/{$owner->id}");

        $this->assertTrue($round->fresh()->isVisibleTo($friend->id));
        $this->assertSame(3, $round->holes()->count());
    }

    public function test_if_a_user_row_is_deleted_anyway_history_stays_and_ratings_are_replayed(): void
    {
        Queue::fake();
        $organizer = User::factory()->verifiedPlayer()->create();
        $competition = $this->competition(['user_id' => $organizer->id]);
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/training', ['course_name' => 'Test Park', 'holes_count' => 3]);

        $organizer->delete();
        $owner->delete();

        $this->assertNull($competition->fresh()->user_id);
        $this->assertNull(TrainingRound::sole()->user_id);
        Queue::assertPushed(RecalculateRatings::class);
    }
}
