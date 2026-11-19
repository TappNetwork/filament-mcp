<?php

namespace Tapp\FilamentMcp\Discovery;

use Illuminate\Support\Str;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;
use Tapp\FilamentMcp\Attributes\Ability;
use Tapp\FilamentMcp\Attributes\AbilityGroup;

final class ToolAbility
{
    public const DEFAULT_GROUP = 'General';

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
            return self::title($tool);
        }

        return $attribute->label ?? Str::headline($attribute->name);
    }

    /**
     * The tool's title, without the " Tool" that a class name like
     * ListSitesTool adds when no title is declared.
     */
    private static function title(Tool $tool): string
    {
        $title = $tool->title();

        if ($title === Str::headline(class_basename($tool)) && Str::endsWith($title, ' Tool')) {
            return Str::beforeLast($title, ' Tool');
        }

        return $title;
    }

    /**
     * The label the tool's #[Ability] declares, if any.
     */
    public static function declaredLabel(Tool $tool): ?string
    {
        return self::attribute($tool)?->label;
    }

    /**
     * The group the tool's #[AbilityGroup] declares, else "General".
     */
    public static function group(Tool $tool): string
    {
        $attributes = (new ReflectionClass($tool))->getAttributes(AbilityGroup::class);

        return $attributes === [] ? self::DEFAULT_GROUP : $attributes[0]->newInstance()->name;
    }

    private static function attribute(Tool $tool): ?Ability
    {
        $attributes = (new ReflectionClass($tool))->getAttributes(Ability::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }
}
