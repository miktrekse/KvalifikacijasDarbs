<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Drill privacy (one policy for every endpoint) and input limits. */
class ExerciseTest extends TestCase
{
    use RefreshDatabase;

    private function drill(User $author, bool $public, array $attributes = []): Exercise
    {
        return Exercise::create($attributes + [
            'user_id' => $author->id,
            'title' => $public ? 'Public drill' : 'Private drill',
            'difficulty' => 'beginner',
            'is_public' => $public,
        ]);
    }

    public function test_a_private_drill_is_hidden_from_everyone_but_its_author_and_admins(): void
    {
        $author = User::factory()->create();
        $private = $this->drill($author, false);

        $this->actingAs($author)->get("/exercises/view/{$private->id}")->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get("/exercises/view/{$private->id}")->assertOk();

        $this->actingAs(User::factory()->create())->get("/exercises/view/{$private->id}")->assertNotFound();
        $this->actingAs(User::factory()->guest()->create())->get("/exercises/view/{$private->id}")->assertNotFound();
    }

    public function test_public_drills_are_open_to_everyone(): void
    {
        $public = $this->drill(User::factory()->create(), true);

        $this->actingAs(User::factory()->create())->get("/exercises/view/{$public->id}")->assertOk();
    }

    public function test_someone_elses_private_drill_cannot_be_saved_or_commented_on(): void
    {
        $private = $this->drill(User::factory()->create(), false);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->postJson('/exercises/toggle-save', ['exercise_id' => $private->id, 'saved' => true])->assertNotFound();
        $this->actingAs($stranger)->post("/exercises/{$private->id}/comments", ['content' => 'Nice'])->assertNotFound();

        $this->assertSame(0, $stranger->addedExercises()->count());
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_a_saved_drill_made_private_by_its_author_leaves_the_saved_list(): void
    {
        $author = User::factory()->create();
        $drill = $this->drill($author, true, ['title' => 'Circle one ladder']);
        $fan = User::factory()->create();
        $fan->addedExercises()->attach($drill->id);

        $this->actingAs($fan)->get('/exercises/saved')->assertSee('Circle one ladder');

        $drill->update(['is_public' => false]);

        $this->actingAs($fan)->get('/exercises/saved')->assertDontSee('Circle one ladder');
    }

    public function test_only_the_author_can_edit_or_delete_a_drill(): void
    {
        $drill = $this->drill(User::factory()->create(), true);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/exercises/edit/{$drill->id}")->assertRedirect(route('exercises.index'));
        $this->actingAs($stranger)->delete("/exercises/{$drill->id}")->assertRedirect(route('exercises.index'));

        $this->assertModelExists($drill);
    }

    public function test_a_comment_can_only_be_deleted_through_its_own_drill(): void
    {
        $author = User::factory()->create();
        $drill = $this->drill($author, true);
        $otherDrill = $this->drill($author, true, ['title' => 'Other']);
        $commenter = User::factory()->create();
        $comment = Comment::create(['exercise_id' => $drill->id, 'user_id' => $commenter->id, 'content' => 'Hi']);

        $this->actingAs($commenter)->delete("/exercises/{$otherDrill->id}/comments", ['comment_id' => $comment->id])->assertNotFound();
        $this->assertModelExists($comment);

        $this->actingAs($commenter)->delete("/exercises/{$drill->id}/comments", ['comment_id' => $comment->id])->assertRedirect();
        $this->assertModelMissing($comment);
    }

    public function test_drill_text_and_tags_have_limits(): void
    {
        $author = User::factory()->verifiedPlayer()->create();
        $valid = ['title' => 'Putting ladder', 'difficulty' => 'beginner'];

        $this->actingAs($author)->post('/exercises', $valid + ['description' => str_repeat('a', 5001)])->assertSessionHasErrors('description');
        $this->actingAs($author)->post('/exercises', $valid + ['instructions' => str_repeat('a', 10001)])->assertSessionHasErrors('instructions');
        $this->actingAs($author)->post('/exercises', $valid + ['tags_input' => implode(',', range(1, 16))])->assertSessionHasErrors('tags_input');
        $this->actingAs($author)->post('/exercises', $valid + ['tags_input' => str_repeat('x', 31)])->assertSessionHasErrors('tags_input');
        $this->assertDatabaseCount('exercises', 0);

        $this->actingAs($author)->post('/exercises', $valid + ['tags_input' => 'putting, Putting , ,circle 1']);
        $this->assertSame(['putting', 'circle 1'], Exercise::first()->tags);
    }

    public function test_comments_are_rate_limited(): void
    {
        $drill = $this->drill(User::factory()->create(), true);
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->post("/exercises/{$drill->id}/comments", ['content' => "Comment {$i}"])->assertRedirect();
        }
        $this->actingAs($user)->post("/exercises/{$drill->id}/comments", ['content' => 'One too many'])->assertStatus(429);

        $this->assertSame(10, Comment::count());
    }
}
