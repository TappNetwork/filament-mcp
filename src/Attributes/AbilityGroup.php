<?php

namespace Tapp\FilamentMcp\Attributes;

use Attribute;

/**
 * Names the section a tool's ability appears under in the token form,
 * for example #[AbilityGroup('LMS')]. Tools without one fall under "General".
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AbilityGroup
{
    public function __construct(
        public string $name,
    ) {}
}
