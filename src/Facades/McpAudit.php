<?php

namespace Tapp\FilamentMcpTokens\Facades;

use Illuminate\Support\Facades\Facade;
use Tapp\FilamentMcpTokens\Audit\ToolCallAudit;

/**
 * Adds details to the audit log entry for the current tool call.
 *
 * @method static void with(array<string, mixed> $properties)
 *
 * @see ToolCallAudit
 */
class McpAudit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ToolCallAudit::class;
    }
}
