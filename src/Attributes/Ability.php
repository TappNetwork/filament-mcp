<?php

namespace Tapp\FilamentMcpTokens\Attributes;

use Attribute;

/**
 * Puts a tool under a shared Sanctum ability instead of its own.
 * Every tool that names the same ability is allowed by it.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Ability
{
    public function __construct(
        public string $name,
        public ?string $label = null,
    ) {}
}
