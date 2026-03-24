<?php

it('returns a successful response', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Welcome'));
});
