<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Starter library of public practice drills, owned by the admin account.
 * Safe to run repeatedly: drills are matched by title and updated in place.
 */
class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('role', 'admin')->first();
        if (!$author) {
            $this->command?->warn('No admin user found — run AdminUserSeeder first.');
            return;
        }

        $categories = Category::pluck('id', 'name');

        foreach ($this->exercises() as $exercise) {
            $category = $exercise['category'];
            unset($exercise['category']);

            Exercise::updateOrCreate(
                ['title' => $exercise['title'], 'user_id' => $author->id],
                $exercise + [
                    'category_id' => $categories[$category] ?? null,
                    'is_public' => true,
                ]
            );
        }

        $this->command?->info(count($this->exercises()) . ' starter exercises ready.');
    }

    private function exercises(): array
    {
        return [
            // ---------- Putting ----------
            [
                'category' => 'Putting',
                'title' => 'Circle of Confidence',
                'difficulty' => 'beginner',
                'duration_minutes' => 15,
                'equipment' => 'putters',
                'throwing_styles' => [],
                'tags' => ['putting', 'confidence', 'routine'],
                'description' => 'Build a repeatable putting routine from inside the circle, starting where you never miss.',
                'instructions' => "1. Place 5 putters at 3 m from the basket.\n2. Putt all 5. If you make all of them, step back 1 m.\n3. Miss one? Stay at that distance until you hit 5/5.\n4. Work out to 7 m, then finish with 5 makes from 3 m.\n\nFocus on the same pre-putt routine every time: look at the chains, breath, release.",
            ],
            [
                'category' => 'Putting',
                'title' => 'Around the World',
                'difficulty' => 'intermediate',
                'duration_minutes' => 20,
                'equipment' => 'putters',
                'throwing_styles' => [],
                'tags' => ['putting', 'angles', 'consistency'],
                'description' => 'Putt from five angles around the basket so wind and slope never surprise you.',
                'instructions' => "1. Mark 5 spots in a half circle at 6 m around the basket.\n2. Putt 3 discs from each spot, moving clockwise.\n3. Count your makes out of 15.\n4. Repeat at 8 m.\n\nGoal: 12/15 at 6 m and 9/15 at 8 m. Notice which side of the basket you miss on from each angle.",
            ],
            [
                'category' => 'Putting',
                'title' => 'Ladder to C2',
                'difficulty' => 'advanced',
                'duration_minutes' => 25,
                'equipment' => 'putters',
                'throwing_styles' => [],
                'tags' => ['putting', 'circle 2', 'distance control'],
                'description' => 'A pressure ladder that stretches your range from the edge of Circle 1 into Circle 2.',
                'instructions' => "1. Set markers at 8, 10, 12 and 14 m.\n2. You need 2 makes in a row to move up a marker.\n3. Any miss short of the basket sends you back one marker.\n4. Finish by making 1 putt from 14 m.\n\nKeep the disc flat and finish high; a missed run-up putt should stop within 3 m of the basket.",
            ],
            [
                'category' => 'Putting',
                'title' => 'Pressure Putting 21',
                'difficulty' => 'expert',
                'duration_minutes' => 20,
                'equipment' => 'putters',
                'throwing_styles' => [],
                'tags' => ['putting', 'pressure', 'game'],
                'description' => 'A scoring game that rewards streaks and punishes misses — the closest thing to a tournament putt on your own.',
                'instructions' => "1. Putt from 6 m. Each make is +1, each miss is −1.\n2. Three makes in a row earn a bonus +2.\n3. Every 5 points, step back 1 m.\n4. Play to exactly 21 points; going below 0 restarts the game.\n\nTrack how many putts you needed and try to beat it next session.",
            ],

            // ---------- Driving ----------
            [
                'category' => 'Driving',
                'title' => 'Smooth Fairway Lines',
                'difficulty' => 'beginner',
                'duration_minutes' => 20,
                'equipment' => 'midranges, fairway-drivers',
                'throwing_styles' => ['backhand'],
                'tags' => ['driving', 'accuracy', 'form'],
                'description' => 'Throw at 70% power and land on a line — control before distance.',
                'instructions' => "1. Pick a target line about 60 m long (two trees, a path).\n2. Throw 10 discs at 70% power with a midrange, then 10 with a fairway driver.\n3. Score 1 point for every disc that lands within 5 m of the line.\n\nIf you can score 15/20, add 10% power. Smooth beats hard.",
            ],
            [
                'category' => 'Driving',
                'title' => 'X-Step Rhythm Builder',
                'difficulty' => 'intermediate',
                'duration_minutes' => 25,
                'equipment' => 'fairway-drivers',
                'throwing_styles' => ['backhand'],
                'tags' => ['driving', 'x-step', 'footwork'],
                'description' => 'Groove a consistent run-up so your power comes from timing, not muscle.',
                'instructions' => "1. Walk through the x-step 10 times without a disc: step, cross behind, plant.\n2. Add a disc and throw at 50% with the full x-step, 5 throws.\n3. Increase to 75%, then 90%, 5 throws each.\n\nFilm one throw from the side if you can — your plant foot should point slightly away from the target.",
            ],
            [
                'category' => 'Driving',
                'title' => 'Hyzer Flip Distance',
                'difficulty' => 'advanced',
                'duration_minutes' => 30,
                'equipment' => 'fairway-drivers, distance-drivers',
                'throwing_styles' => ['backhand'],
                'tags' => ['driving', 'distance', 'hyzer flip'],
                'description' => 'Turn understable drivers into straight, long flights by releasing on hyzer and letting them flip up.',
                'instructions' => "1. Use an understable fairway or distance driver.\n2. Release on a slight hyzer angle at full power; the disc should flip flat and glide straight.\n3. Throw 10 discs, adjusting the release angle until 7 of them fly straight.\n4. Pace off your three longest throws.\n\nToo much turn? Add more hyzer. Fading early? Release flatter.",
            ],
            [
                'category' => 'Driving',
                'title' => 'Forehand Flex Shots',
                'difficulty' => 'expert',
                'duration_minutes' => 30,
                'equipment' => 'fairway-drivers, distance-drivers',
                'throwing_styles' => ['forehand'],
                'tags' => ['driving', 'forehand', 'shot shaping'],
                'description' => 'Throw forehands on anhyzer that fight back to finish — a big shot around obstacles.',
                'instructions' => "1. Pick an overstable driver.\n2. Release a forehand on a clear anhyzer angle, aimed right of your target.\n3. The disc should hold the anhyzer, then flex back left at the end.\n4. Throw 15 discs and score how many finish within 10 m of the target.\n\nKeep your wrist firm and snap through; a lazy forehand will turn over and never come back.",
            ],

            // ---------- Approaches ----------
            [
                'category' => 'Approches',
                'title' => 'Parked in the Circle',
                'difficulty' => 'beginner',
                'duration_minutes' => 20,
                'equipment' => 'putters, midranges',
                'throwing_styles' => ['backhand'],
                'tags' => ['approach', 'upshot', 'parking'],
                'description' => 'Lay up from 30–50 m so your next putt is a tap-in.',
                'instructions' => "1. Stand 30 m from the basket and throw 10 approaches with a putter.\n2. Count how many stop inside 5 m (parked) and inside 10 m (Circle 1).\n3. Move to 40 m and 50 m and repeat with a midrange.\n\nAim for the disc to land soft and flat; skipping past the basket is worse than coming up short.",
            ],
            [
                'category' => 'Approches',
                'title' => 'Three-Shape Approach',
                'difficulty' => 'intermediate',
                'duration_minutes' => 25,
                'equipment' => 'midranges',
                'throwing_styles' => ['backhand', 'forehand'],
                'tags' => ['approach', 'shot shaping', 'hyzer', 'anhyzer'],
                'description' => 'Hit the same target with a hyzer, a flat shot and an anhyzer so you always have a line.',
                'instructions' => "1. From 50 m, throw 5 hyzers, 5 flat shots and 5 anhyzers with the same midrange.\n2. Score each disc: inside 5 m = 2 points, inside 10 m = 1 point.\n3. Repeat with forehand.\n\nYour weakest shape is the one to practise next week.",
            ],
            [
                'category' => 'Approches',
                'title' => 'Spike Hyzer Landing',
                'difficulty' => 'advanced',
                'duration_minutes' => 20,
                'equipment' => 'midranges, fairway-drivers',
                'throwing_styles' => ['backhand'],
                'tags' => ['approach', 'spike hyzer', 'elevation'],
                'description' => 'Throw high and steep so the disc drops dead next to the basket — no skip, no roll.',
                'instructions' => "1. From 60–80 m, release an overstable disc high and on a steep hyzer.\n2. The disc should climb, stall and dive into the ground near the basket.\n3. Throw 10 and count how many stop within 10 m.\n\nGreat for downhill baskets or when there's OB behind the pin.",
            ],
            [
                'category' => 'Approches',
                'title' => 'Wind Reader',
                'difficulty' => 'expert',
                'duration_minutes' => 30,
                'equipment' => 'putters, midranges, fairway-drivers',
                'throwing_styles' => ['backhand', 'forehand'],
                'tags' => ['approach', 'wind', 'disc selection'],
                'description' => 'Approach into a headwind, tailwind and crosswind and learn which disc to trust in each.',
                'instructions' => "1. On a windy day, pick one basket and throw approaches from 4 directions so the wind is in front, behind and from each side.\n2. For each direction, try a putter, a midrange and a fairway driver.\n3. Note which disc finished closest and why.\n\nRule of thumb: headwind makes discs flip, tailwind makes them fade early — pick more stable into the wind.",
            ],

            // ---------- Scramble ----------
            [
                'category' => 'Scramble',
                'title' => 'Escape the Trees',
                'difficulty' => 'beginner',
                'duration_minutes' => 15,
                'equipment' => 'putters, midranges',
                'throwing_styles' => ['backhand', 'forehand'],
                'tags' => ['scramble', 'recovery', 'woods'],
                'description' => 'Find the smart gap back to the fairway instead of the hero shot.',
                'instructions' => "1. Drop 5 discs in the woods off a fairway.\n2. From each lie, pick the safest gap that gets you back in play and throw a putter or midrange through it.\n3. Score 1 point for every disc that reaches open fairway.\n\nBefore every throw, say your gap out loud. It forces you to commit.",
            ],
            [
                'category' => 'Scramble',
                'title' => 'Standstill Power',
                'difficulty' => 'intermediate',
                'duration_minutes' => 20,
                'equipment' => 'midranges, fairway-drivers',
                'throwing_styles' => ['backhand'],
                'tags' => ['scramble', 'standstill', 'footwork'],
                'description' => 'Generate distance with no run-up for tight tee pads and awkward lies.',
                'instructions' => "1. Stand with your feet planted and your front foot pointed away from the target.\n2. Throw 10 standstill shots with a midrange, focusing on weight shift and hip rotation.\n3. Throw 10 more with a fairway driver.\n4. Compare your distance with your normal run-up throw.\n\nA good standstill reaches 80% of your full distance.",
            ],
            [
                'category' => 'Scramble',
                'title' => 'Low Ceiling Rollers',
                'difficulty' => 'advanced',
                'duration_minutes' => 25,
                'equipment' => 'midranges, fairway-drivers',
                'throwing_styles' => ['backhand', 'forehand'],
                'tags' => ['scramble', 'roller', 'utility'],
                'description' => 'When branches are low, roll it — learn to start and steer a roller.',
                'instructions' => "1. Pick an understable midrange.\n2. Release on an anhyzer so the disc lands on its edge about 10 m in front of you.\n3. Throw 10 backhand rollers, then 10 forehand rollers.\n4. Score rollers that stay upright for more than 20 m.\n\nThe landing angle decides which way it curves; experiment until you can steer it.",
            ],
            [
                'category' => 'Scramble',
                'title' => 'Par Save Challenge',
                'difficulty' => 'expert',
                'duration_minutes' => 40,
                'equipment' => 'putters, midranges, fairway-drivers, distance-drivers',
                'throwing_styles' => ['backhand', 'forehand'],
                'tags' => ['scramble', 'course management', 'game'],
                'description' => 'Play 9 holes from bad lies and fight to save par every time.',
                'instructions' => "1. On each of 9 holes, throw your drive into trouble on purpose (rough, behind a tree, near OB).\n2. Play the hole out from there.\n3. Track how many holes you save par and how many strokes you lose.\n\n6 saves out of 9 is tournament-ready scrambling.",
            ],
        ];
    }
}
