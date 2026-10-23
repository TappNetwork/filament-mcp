<?php

namespace Tapp\FilamentMcp\Discovery;

use Illuminate\Support\Str;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;
use Tapp\FilamentMcp\Attributes\Ability;

final class ToolAbility
{
    /**
     * The Sanctum ability a token needs to call the tool.
     */
    public static function for(Tool $tool): string
    {
        $attribute = self::attribute($tool);

        return $attribute === null ? $tool->name() : $attribute->name;
    }

    public static function label(Tool $tool): string
    {
        $attribute = self::attribute($tool);

        if ($attribute === null) {
            return $tool->title();
        }

        return $attribute->label ?? Str::headline($attribute->name);
    }

    /**
     * The label the tool's #[Ability] declares, if any.
     */
    public static function declaredLabel(Tool $tool): ?string
    {
        return self::attribute($tool)?->label;
    }

    private static function attribute(Tool $tool): ?Ability
    {
        $attributes = (new ReflectionClass($tool))->getAttributes(Ability::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }
}
