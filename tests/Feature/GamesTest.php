<?php

namespace Tests\Feature;

use Tests\TestCase;

class GamesTest extends TestCase
{
    public function test_games(): void
    {
        $response = $this->get('/games');

        $response->assertStatus(200);
    }

    public function test_games_create_page(): void
    {
        $response = $this->get('/games/create');

        $response->assertStatus(200);
    }

    public function test_games_show_page(): void
    {
        $response = $this->get('/games/1');

        $response->assertStatus(200);
        $response->assertSeeText('Home Team');
        $response->assertSeeText('Away Team');
    }
}
