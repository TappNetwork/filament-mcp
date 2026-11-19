<?php

use Tapp\FilamentMcp\Discovery\ToolAbility;
use Tapp\FilamentMcp\Tests\Fixtures\Tools\CreateCourse;
use Tapp\FilamentMcp\Tests\Fixtures\Tools\ListSitesTool;
use Tapp\FilamentMcp\Tests\Fixtures\Tools\TitledTool;

it('drops the class-name Tool suffix from the label only', function () {
    $tool = new ListSitesTool;

    expect(ToolAbility::label($tool))->toBe('List Sites')
        ->and(ToolAbility::for($tool))->toBe('list_sites')
        ->and($tool->title())->toBe('List Sites Tool');
});

it('keeps a declared title and every ability key as is', function () {
    expect(ToolAbility::label(new TitledTool))->toBe('Run The Tool')
        ->and(ToolAbility::for(new TitledTool))->toBe('titled')
        ->and(ToolAbility::label(new CreateCourse))->toBe('Create Course')
        ->and(ToolAbility::for(new CreateCourse))->toBe('create_course');
});
