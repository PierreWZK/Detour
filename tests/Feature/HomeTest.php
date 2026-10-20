<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomeTest extends TestCase
{
    public function test_la_page_vitrine_affiche_les_exemples_calcules(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Le plein moins cher')
            ->assertSee('+1,66 €')                 // verdict d'exemple
            ->assertSee('La station à deux rues')
            ->assertSee('Le meilleur choix')
            ->assertSee('og:image', false);
    }
}
