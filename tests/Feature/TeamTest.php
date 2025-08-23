<?php

use App\Models\Team;

it('can load teams page', function () {
    $this->get('/teams')->assertSuccessful();
});

it('can load new team form', function () {
    $this->get('/teams/create')->assertSuccessful();
});

it('can load team details', function () {
    $team = Team::factory()->create();

    $this->get("/teams/{$team->id}")->assertSuccessful();
});

it('can create a new team', function () {
    $teamData = [
        'name' => 'Manchester United',
        'acronym' => 'MUN',
        'year_founded' => 1878,
        'home_ground' => 'Old Trafford',
    ];

    $this->post('/teams', $teamData)
        ->assertRedirect('/teams')
        ->assertSessionHas('status', 'Team added successfully!');

    $this->assertDatabaseHas('teams', $teamData);
});

it('can update a team', function () {
    $team = Team::factory()->create();
    $updatedData = [
        'name' => 'Updated Team Name',
        'acronym' => 'UPD',
        'year_founded' => 2000,
        'home_ground' => 'Updated Stadium',
    ];

    $this->put("/teams/{$team->id}", $updatedData)
        ->assertRedirect("/teams/{$team->id}")
        ->assertSessionHas('status', 'Team updated successfully!');

    $this->assertDatabaseHas('teams', $updatedData);
});

it('can delete a team', function () {
    $team = Team::factory()->create();

    $this->delete("/teams/{$team->id}")
        ->assertRedirect('/teams')
        ->assertSessionHas('status', 'Team deleted successfully!');

    $this->assertDatabaseMissing('teams', ['id' => $team->id]);
});
