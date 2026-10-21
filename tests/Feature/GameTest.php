<?php

namespace Tests\Feature;

use App\Detour\Game\DailyPrice;
use App\Detour\Game\DailyQuiz;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GameTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['data.economie.gouv.fr/*' => Http::response(['results' => [
            ['moyenne' => 2.3543567, 'mini' => 2.2, 'maxi' => 2.856, 'n' => 4995],
        ]])]);
    }

    public function test_les_appreciations_du_juste_prix(): void
    {
        $this->assertSame('Dans le mille', DailyPrice::grade(2.354, 2.354)['label']);
        $this->assertSame('exact', DailyPrice::grade(2.354, 2.354)['direction']);
        $this->assertSame('Brûlant', DailyPrice::grade(2.370, 2.354)['label']);   // 1,6 ct
        $this->assertSame('trop haut', DailyPrice::grade(2.370, 2.354)['direction']);
        $this->assertSame('Chaud', DailyPrice::grade(2.310, 2.354)['label']);     // 4,4 ct
        $this->assertSame('trop bas', DailyPrice::grade(2.310, 2.354)['direction']);
        $this->assertSame('Tiède', DailyPrice::grade(2.450, 2.354)['label']);     // 9,6 ct
        $this->assertSame('Froid', DailyPrice::grade(2.000, 2.354)['label']);
        $this->assertSame(35.4, DailyPrice::grade(2.000, 2.354)['diffCents']);
    }

    public function test_les_questions_du_quiz_sont_valides_et_changent_chaque_jour(): void
    {
        $quiz = new DailyQuiz;
        $ids = [];
        foreach ($quiz->all() as $q) {
            $this->assertCount(4, $q['choices'], $q['id']);
            $this->assertContains($q['answer'], [0, 1, 2, 3], $q['id']);
            $this->assertNotEmpty($q['explain'], $q['id']);
            $ids[] = $q['id'];
        }
        $this->assertSame($ids, array_unique($ids));
        $this->assertGreaterThanOrEqual(30, count($ids));

        // Autant de jours que de questions : toutes différentes, et la même pour tout le monde un jour donné
        $start = CarbonImmutable::parse('2026-10-21 08:00', 'Europe/Paris');
        $days = array_map(fn ($i) => $quiz->questionOf($start->addDays($i))['id'], range(0, count($ids) - 1));
        $this->assertCount(count($ids), array_unique($days));
        $this->assertSame($quiz->questionOf($start)['id'], $quiz->questionOf($start->setTime(23, 50))['id']);
    }

    public function test_la_page_ne_contient_pas_les_reponses(): void
    {
        $question = (new DailyQuiz)->questionOf(CarbonImmutable::now('Europe/Paris'));

        $this->get('/jeu')
            ->assertOk()
            ->assertSee('Le juste prix')
            ->assertSee($question['q'])
            ->assertDontSee($question['explain'])
            ->assertDontSee('2,354');
    }

    public function test_le_juste_prix_note_la_proposition(): void
    {
        $this->postJson('/jeu/juste-prix', ['guess' => 2.37])
            ->assertOk()
            ->assertJsonPath('answer', 2.354)
            ->assertJsonPath('count', 4995)
            ->assertJsonPath('grade.label', 'Brûlant');
    }

    public function test_le_quiz_donne_la_reponse_et_l_explication(): void
    {
        $question = (new DailyQuiz)->questionOf(CarbonImmutable::now('Europe/Paris'));

        $this->postJson('/jeu/quiz', ['choice' => $question['answer']])
            ->assertOk()
            ->assertJsonPath('correct', true)
            ->assertJsonPath('explain', $question['explain']);

        $this->postJson('/jeu/quiz', ['choice' => ($question['answer'] + 1) % 4])
            ->assertJsonPath('correct', false);
    }

    public function test_une_reponse_invalide_est_refusee(): void
    {
        $this->postJson('/jeu/quiz', ['choice' => 7])->assertUnprocessable();
        $this->postJson('/jeu/juste-prix', ['guess' => 'beaucoup'])->assertUnprocessable();
    }

    public function test_le_juste_prix_sans_donnees_donne_un_message_clair(): void
    {
        Http::swap(new Factory);
        Http::fake(['data.economie.gouv.fr/*' => Http::response('Service Unavailable', 503)]);

        $this->postJson('/jeu/juste-prix', ['guess' => 2.3])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Le prix du jour est indisponible pour le moment.');
    }
}
