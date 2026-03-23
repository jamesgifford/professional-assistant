<?php

it('renders the privacy policy page', function () {
    $response = $this->get('/privacy');

    $response->assertOk();
    $response->assertSee('Privacy Policy');
    $response->assertSee('Progravity LLC');
    $response->assertSee('Back to chat');
});

it('renders the terms and conditions page', function () {
    $response = $this->get('/terms');

    $response->assertOk();
    $response->assertSee('Terms');
    $response->assertSee('Progravity LLC');
    $response->assertSee('Back to chat');
});

it('links to privacy policy from terms page', function () {
    $response = $this->get('/terms');

    $response->assertOk();
    $response->assertSee('/privacy');
});
