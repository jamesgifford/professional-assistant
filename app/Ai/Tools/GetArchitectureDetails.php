<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetArchitectureDetails implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Retrieves the detailed technical architecture breakdown of how this AI professional assistant was built. Call this tool when the user asks about the technology, architecture, stack, how the assistant works, how it was built, or any technical implementation details about this assistant itself.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        Log::info('GetArchitectureDetails tool called');

        return file_get_contents(resource_path('prompts/architecture.md'));
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
