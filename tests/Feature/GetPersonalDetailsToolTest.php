<?php

use App\Ai\Agents\ProfessionalAssistant;
use App\Ai\Tools\GetArchitectureDetails;
use App\Ai\Tools\GetPersonalDetails;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

it('returns the personal details content from the prompts file', function () {
    $tool = new GetPersonalDetails;

    $result = $tool->handle(new Request([]));

    expect($result)
        ->toBeString()
        ->toContain('Running')
        ->toContain('Travel')
        ->toContain('Hiking & Outdoor Leadership')
        ->toContain('Sierra Club');
});

it('has a descriptive tool description mentioning hobbies and interests', function () {
    $tool = new GetPersonalDetails;

    expect($tool->description())
        ->toBeString()
        ->toContain('hobbies')
        ->toContain('interests');
});

it('has an empty schema since no parameters are needed', function () {
    $tool = new GetPersonalDetails;

    $schema = $tool->schema(Mockery::mock(JsonSchema::class));

    expect($schema)->toBe([]);
});

it('is registered on the ProfessionalAssistant agent alongside GetArchitectureDetails', function () {
    $agent = new ProfessionalAssistant;

    $tools = iterator_to_array($agent->tools());

    expect($tools)->toHaveCount(2)
        ->and($tools[0])->toBeInstanceOf(GetArchitectureDetails::class)
        ->and($tools[1])->toBeInstanceOf(GetPersonalDetails::class);
});

it('has updated privacy rules that reference tools', function () {
    $agent = new ProfessionalAssistant;

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('information retrieved from your available tools')
        ->toContain('use the GetPersonalDetails tool')
        ->toContain('use the GetArchitectureDetails tool')
        ->not->toContain('I only have information about James\'s professional background. For anything else');
});
