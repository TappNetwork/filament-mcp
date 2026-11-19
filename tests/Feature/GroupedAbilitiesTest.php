<?php

use Filament\Facades\Filament;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Sanctum\PersonalAccessToken;
use Tapp\FilamentMcp\Discovery\McpServerRegistry;
use Tapp\FilamentMcp\Pages\McpTokens;
use Tapp\FilamentMcp\Tests\Fixtures\GroupedServer;

use function Pest\Livewire\livewire;

it('groups abilities by declared group with General as the fallback', function () {
    Mcp::web('/mcp/grouped', GroupedServer::class)->middleware('auth:sanctum');
    $registry = app(McpServerRegistry::class);

    expect($registry->find('Grouped')->groupedAbilities())->toBe([
        'LMS' => ['create_course' => 'Create Course', 'create_lesson' => 'Create Lesson'],
        'Webhooks' => ['create_webhook_event' => 'Create Webhook Event', 'delete_webhook_event' => 'Delete Webhook Event'],
        'General' => ['list_lessons' => 'List Lessons'],
    ])->and($registry->groupedAbilities())->toHaveKeys(['LMS', 'Webhooks', 'General'])
        ->and($registry->abilities())->toHaveKeys(['create_course', 'create_webhook_event', 'list_lessons']);
});

it('keeps tools without a group in one flat General group', function () {
    expect(array_keys(app(McpServerRegistry::class)->groupedAbilities()))->toBe(['General']);
});

describe('token form', function () {
    beforeEach(function () {
        skipWithoutSanctum();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->user = $this->makeUser());
        Mcp::web('/mcp/grouped', GroupedServer::class)->middleware('auth:sanctum');
    });

    it('renders a select all and a section per group', function () {
        livewire(McpTokens::class)
            ->mountAction('new')
            ->assertSchemaComponentExists('select_all_abilities', 'mountedActionSchema0')
            ->assertSchemaComponentExists('ability_groups.g0_general', 'mountedActionSchema0')
            ->assertSchemaComponentExists('ability_groups.g1_lms', 'mountedActionSchema0')
            ->assertSchemaComponentExists('ability_groups.g2_webhooks', 'mountedActionSchema0')
            ->assertSchemaComponentExists('ability_groups.g1_lms', 'mountedActionSchema0', fn ($field): bool => $field->getLabel() === 'LMS abilities');
    });

    it('saves the union of every group as one flat list', function () {
        livewire(McpTokens::class)
            ->callAction('new', [
                'name' => 'Agent',
                'expiration' => 'never',
                'ability_groups' => [
                    'g0_general' => ['list_lessons'],
                    'g1_lms' => ['create_course'],
                    'g2_webhooks' => ['create_webhook_event'],
                ],
            ])
            ->assertHasNoActionErrors();

        expect(PersonalAccessToken::query()->sole()->abilities)
            ->toEqualCanonicalizing(['list_lessons', 'create_course', 'create_webhook_event']);
    });

    it('pre-fills each group when editing', function () {
        $this->user->createToken('Agent', ['create_course', 'list_lessons']);

        livewire(McpTokens::class)
            ->mountTableAction('edit', PersonalAccessToken::query()->sole())
            ->assertSchemaStateSet(fn (array $state) => collect($state['ability_groups'])->flatten()->sort()->values()->all() === ['create_course', 'list_lessons']
                && collect($state['ability_groups'])->filter()->count() === 2)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        livewire(McpTokens::class)
            ->callTableAction('edit', PersonalAccessToken::query()->sole(), ['ability_groups' => ['g2_webhooks' => ['create_webhook_event']]]);

        expect(PersonalAccessToken::query()->sole()->abilities)->toEqualCanonicalizing(['list_lessons', 'create_course', 'create_webhook_event']);
    });
});

it('puts config extras in an Other group', function () {
    skipWithoutSanctum();
    config()->set('filament-mcp.abilities.extra', ['export_csv' => 'Export CSV']);

    $page = new McpTokens;
    $groups = (fn () => $this->getGroupedAbilityOptions())->call($page);

    expect($groups['Other'])->toBe(['export_csv' => 'Export CSV'])->and(array_key_last($groups))->toBe('Other');
});
