<?php

use App\Ai\Agents\ProfessionalAssistant;

it('runs the ai:health command successfully', function () {
    ProfessionalAssistant::fake(['OK']);

    $this->artisan('ai:health')
        ->assertSuccessful();
});
