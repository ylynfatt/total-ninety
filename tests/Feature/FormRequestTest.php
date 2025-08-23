<?php

use App\Models\Team;

describe('Team validation', function () {
    it('validates team creation with valid data', function () {
        $validData = [
            'name' => 'Manchester United',
            'acronym' => 'MUN',
            'year_founded' => 1878,
            'home_ground' => 'Old Trafford',
        ];

        $this->post('/teams', $validData)
            ->assertRedirect('/teams')
            ->assertSessionHasNoErrors();
    });

    it('fails validation with invalid team data', function ($field, $value, $expectedError) {
        $invalidData = [
            'name' => 'Valid Name',
            'acronym' => 'VAL',
            'year_founded' => 2000,
            'home_ground' => 'Valid Ground',
            $field => $value,
        ];

        $this->post('/teams', $invalidData)
            ->assertSessionHasErrors($field);
    })->with([
        ['name', '', 'required'],
        ['name', str_repeat('a', 256), 'max'],
        ['acronym', '', 'required'],
        ['acronym', 'AB', 'size'],
        ['acronym', 'ABCD', 'size'],
        ['acronym', 'abc', 'uppercase'],
        ['year_founded', '', 'required'],
        ['year_founded', 'not-a-number', 'integer'],
        ['year_founded', 1799, 'min'],
        ['year_founded', date('Y') + 1, 'max'],
        ['home_ground', '', 'required'],
        ['home_ground', str_repeat('a', 256), 'max'],
    ]);
});

describe('Game validation', function () {
    it('validates game creation with valid data', function () {
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        $validData = [
            'home_team' => $homeTeam->id,
            'away_team' => $awayTeam->id,
            'match_date' => now()->addDays(7)->format('Y-m-d'),
            'location' => 'Wembley Stadium',
        ];

        $this->post('/games', $validData)
            ->assertRedirect('/games')
            ->assertSessionHasNoErrors();
    });

    it('fails validation with invalid game data', function ($field, $value, $expectedError) {
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        $invalidData = [
            'home_team' => $homeTeam->id,
            'away_team' => $awayTeam->id,
            'match_date' => now()->addDays(7)->format('Y-m-d'),
            'location' => 'Valid Location',
            $field => $value,
        ];

        $this->post('/games', $invalidData)
            ->assertSessionHasErrors($field);
    })->with([
        ['home_team', '', 'required'],
        ['home_team', 'not-a-number', 'integer'],
        ['home_team', 999999, 'exists'],
        ['away_team', '', 'required'],
        ['away_team', 'not-a-number', 'integer'],
        ['away_team', 999999, 'exists'],
        ['match_date', '', 'required'],
        ['match_date', 'invalid-date', 'date'],
        ['match_date', now()->subDay()->format('Y-m-d'), 'after'],
        ['location', '', 'required'],
        ['location', str_repeat('a', 256), 'max'],
    ]);

    it('prevents team from playing against itself', function () {
        $team = Team::factory()->create();

        $invalidData = [
            'home_team' => $team->id,
            'away_team' => $team->id,
            'match_date' => now()->addDays(7)->format('Y-m-d'),
            'location' => 'Stadium',
        ];

        $this->post('/games', $invalidData)
            ->assertSessionHasErrors('away_team');
    });
});
