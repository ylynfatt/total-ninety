<?php

use Inertia\Testing\AssertableInertia;

it('returns the welcome page for initial visits and Inertia navigation', function () {
    $response = $this->get(route('home'));

    $response->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Welcome')
            ->where('auth.user', null)
        );

    $this->get(route('home'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $response->inertiaPage()['version'],
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertSuccessful()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'Welcome')
        ->assertJsonPath('props.auth.user', null);
});
