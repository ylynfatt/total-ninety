<?php

it('can load teams page', function () {
    $response = $this->get('/teams');

    $response->assertStatus(200);
});

it('can load new team form', function () {
    $response = $this->get('/teams/create');

    $response->assertStatus(200);
});

test('can load team details', function () {
    $response = $this->get('/teams/1');

    $response->assertStatus(200);
});
