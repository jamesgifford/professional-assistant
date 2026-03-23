<?php

use App\Ai\Agents\ProfessionalAssistant;
use App\Ai\Tools\GetArchitectureDetails;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

it('returns the architecture content from the prompts file', function () {
    $tool = new GetArchitectureDetails;

    $result = $tool->handle(new Request([]));

    expect($result)
        ->toBeString()
        ->toContain('Laravel AI SDK')
        ->toContain('Multi-provider failover')
        ->toContain('Architecture optimization');
});

it('has a descriptive tool description', function () {
    $tool = new GetArchitectureDetails;

    expect($tool->description())
        ->toBeString()
        ->toContain('architecture');
});

it('has an empty schema since no parameters are needed', function () {
    $tool = new GetArchitectureDetails;

    $schema = $tool->schema(Mockery::mock(JsonSchema::class));

    expect($schema)->toBe([]);
});

it('is registered on the ProfessionalAssistant agent', function () {
    $agent = new ProfessionalAssistant;

    $tools = iterator_to_array($agent->tools());

    expect($tools[0])->toBeInstanceOf(GetArchitectureDetails::class);
});

it('does not include the full architecture content in the agent instructions', function () {
    $agent = new ProfessionalAssistant;

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('GetArchitectureDetails tool')
        ->not->toContain('Multi-provider failover')
        ->not->toContain('Twilio webhook signature validation prevents spoofed SMS requests');
});
