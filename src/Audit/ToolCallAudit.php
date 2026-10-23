<?php

namespace Tapp\FilamentMcp\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/**
 * Writes one activity log entry per tool call when spatie/laravel-activitylog
 * is installed. Tools add their own details with McpAudit::with().
 */
class ToolCallAudit
{
    /** @var array<string, mixed> */
    private array $properties = [];

    public function start(): void
    {
        $this->properties = [];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function with(array $properties): void
    {
        $this->properties = [...$this->properties, ...$properties];
    }

    public static function available(): bool
    {
        return function_exists('activity');
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(string $server, string $tool, string $outcome, array $properties = []): void
    {
        $extra = $this->properties;
        $this->properties = [];

        if (! config('filament-mcp.audit.enabled', true) || ! static::available()) {
            return;
        }

        $user = Auth::user();
        $token = $user !== null && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

        $logger = activity(config('filament-mcp.audit.log_name', 'mcp'));

        if ($user instanceof Model) {
            $logger->causedBy($user);
        }

        $facts = [
            'token_id' => $token instanceof Model ? $token->getKey() : null,
            'tool' => $tool,
            'server' => $server,
            'outcome' => $outcome,
        ];

        // What the call actually did wins over a tool's own details.
        $logger
            ->withProperties([...$facts, ...Arr::except([...$properties, ...$extra], array_keys($facts))])
            ->log($tool);
    }
}
