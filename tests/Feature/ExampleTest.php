<?php

it('returns a successful response for homepage', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
});
