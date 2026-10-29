<?php

namespace Tapp\FilamentMcp\Pages;

use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Tapp\FilamentMcp\Discovery\McpServerRegistry;
use Tapp\FilamentMcp\Setup\SetupInstructions;
use Tapp\FilamentMcp\Tokens\IssuesMcpTokens;

/**
 * Lets a user create personal access tokens for the app's MCP servers, one
 * ability per tool, and shows the steps to connect an AI agent once a token
 * is created. The token system is the configured auth driver (Sanctum or Passport).
 */
class McpTokens extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected string $view = 'filament-mcp::pages.mcp-tokens';

    /**
     * Only set in the response that created the token. Never persisted.
     */
    public ?string $plainTextToken = null;

    public static function setting(string $key, mixed $default = null): mixed
    {
        return config('filament-mcp.'.$key, $default);
    }

    public static function getDefaultSlug(): string
    {
        return static::setting('navigation.slug', 'api-tokens');
    }

    public static function getNavigationIcon(): string
    {
        return static::setting('navigation.icon', 'heroicon-o-key');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-mcp::setup.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return static::setting('navigation.sidebar_menu.group');
    }

    public static function getNavigationSort(): ?int
    {
        return static::setting('navigation.sidebar_menu.sort');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) static::setting('navigation.sidebar_menu.enabled', false) && static::canAccess();
    }

    public static function canAccess(): bool
    {
        $gate = static::setting('authorization.gate');

        if (! static::setting('authorization.enabled', true) || blank($gate)) {
            return true;
        }

        return Gate::allows($gate);
    }

    public function getTitle(): string
    {
        return __('filament-mcp::setup.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->tokens()->tokensQuery($this->tokenOwner()))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-mcp::tokens.name'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('abilities')
                    ->label(__('filament-mcp::tokens.abilities'))
                    ->getStateUsing(fn (Model $record): string => $this->summarizeAbilities($this->tokens()->abilities($record)))
                    ->tooltip(fn (Model $record): ?string => implode(', ', $this->abilityLabels($this->tokens()->abilities($record))) ?: null),
                TextColumn::make('expires_at')
                    ->label(__('filament-mcp::tokens.expires_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder(__('filament-mcp::tokens.never')),
                TextColumn::make('last_used_at')
                    ->label(__('filament-mcp::tokens.last_used_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder(__('filament-mcp::tokens.never'))
                    ->visible(fn (): bool => $this->tokens()->tracksLastUsedAt()),
                TextColumn::make('created_at')
                    ->label(__('filament-mcp::tokens.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make('details')
                    ->label(__('filament-mcp::tokens.details'))
                    ->modalHeading(fn (Model $record): string => (string) $record->getAttribute('name'))
                    ->schema([
                        TextEntry::make('abilities')
                            ->label(__('filament-mcp::tokens.abilities'))
                            ->state(fn (Model $record): array => $this->abilityLabels($this->tokens()->abilities($record)))
                            ->badge()
                            ->placeholder(__('filament-mcp::tokens.none')),
                        TextEntry::make('expires_at')
                            ->label(__('filament-mcp::tokens.expires_at'))
                            ->dateTime()
                            ->placeholder(__('filament-mcp::tokens.never')),
                    ]),
                Action::make('revoke')
                    ->label(__('filament-mcp::tokens.revoke'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Model $record) => $this->tokens()->revoke($record)),
            ])
            ->toolbarActions([
                BulkAction::make('revoke')
                    ->label(__('filament-mcp::tokens.revoke'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => $records->each(fn (Model $record) => $this->tokens()->revoke($record))),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new')
                ->label(__('filament-mcp::tokens.create'))
                ->schema(fn (): array => $this->getCreateTokenSchema())
                ->action(function (array $data): void {
                    $this->plainTextToken = $this->tokens()->issue(
                        $this->tokenOwner(),
                        $data['name'],
                        array_values($data['abilities'] ?? []),
                        $this->resolveExpiration($data),
                    );

                    Notification::make()
                        ->title(__('filament-mcp::tokens.created'))
                        ->success()
                        ->send();

                    // A modal action only re-renders the dialog. Render the page
                    // so the token is in this response, not in a flashed session.
                    $this->forceRender();
                }),
        ];
    }

    protected function tokens(): IssuesMcpTokens
    {
        return app(IssuesMcpTokens::class);
    }

    /**
     * The signed-in user. It must use the HasApiTokens trait for the configured driver.
     */
    protected function tokenOwner(): mixed
    {
        return Auth::user();
    }

    public function forgetPlainTextToken(): void
    {
        $this->plainTextToken = null;

        $this->forceRender();
    }

    /**
     * The abilities a new token can be given: one per MCP tool, plus any extras.
     *
     * @return array<string, string> ability => label
     */
    protected function getAbilityOptions(): array
    {
        return app(McpServerRegistry::class)->abilities() + (array) static::setting('abilities.extra', []);
    }

    /**
     * The setup steps for each server, built only while the new token is shown.
     *
     * @return list<array{key: string, name: string, url: string, prompt: string, config: string}>
     */
    public function getSetupSteps(): array
    {
        if (blank($this->plainTextToken)) {
            return [];
        }

        $instructions = app(SetupInstructions::class);

        return array_map(fn ($server): array => [
            'key' => $server->key,
            'name' => $server->name,
            'url' => $server->url(),
            'prompt' => $instructions->prompt($server),
            'config' => $instructions->config($server, $this->plainTextToken),
        ], app(McpServerRegistry::class)->servers());
    }

    /**
     * @return array<int, Component|TextInput|CheckboxList|DateTimePicker>
     */
    protected function getCreateTokenSchema(): array
    {
        $name = TextInput::make('name')
            ->label(__('filament-mcp::tokens.name'))
            ->required()
            ->maxLength(255);

        $abilities = CheckboxList::make('abilities')
            ->label(__('filament-mcp::tokens.abilities'))
            ->options(fn (): array => $this->getAbilityOptions())
            ->columns(static::setting('abilities.columns', 2))
            ->bulkToggleable();

        if (! static::setting('abilities.allow_expiration', true)) {
            return [$name, $abilities];
        }

        return [
            Grid::make(2)->schema([
                $name,
                Select::make('expiration')
                    ->label(__('filament-mcp::tokens.expiration'))
                    ->options(fn (): array => $this->getExpirationOptions())
                    ->default(fn (): string => $this->getDefaultExpiration())
                    ->required()
                    ->live()
                    ->native(false),
            ]),
            DateTimePicker::make('expires_at')
                ->label(__('filament-mcp::tokens.expires_at'))
                ->minDate(now())
                ->visible(fn (Get $get): bool => $get('expiration') === 'custom')
                ->required(fn (Get $get): bool => $get('expiration') === 'custom'),
            $abilities,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveExpiration(array $data): ?CarbonInterface
    {
        if (! static::setting('abilities.allow_expiration', true)) {
            return null;
        }

        return match ($data['expiration'] ?? 'never') {
            'never' => null,
            'custom' => filled($data['expires_at'] ?? null) ? Carbon::parse($data['expires_at']) : null,
            default => now()->addDays((int) $data['expiration']),
        };
    }

    /**
     * @return array<string, string>
     */
    protected function getExpirationOptions(): array
    {
        $options = [];

        foreach ((array) static::setting('abilities.expiration_presets', [7, 30, 60, 90]) as $days) {
            $options[(string) $days] = __('filament-mcp::tokens.days', [
                'days' => $days,
                'date' => now()->addDays((int) $days)->format('M j, Y'),
            ]);
        }

        $options['custom'] = __('filament-mcp::tokens.custom');
        $options['never'] = __('filament-mcp::tokens.no_expiration');

        return $options;
    }

    protected function getDefaultExpiration(): string
    {
        $days = static::setting('abilities.default_expiration_days');

        if (blank($days)) {
            return 'never';
        }

        $presets = array_map(strval(...), (array) static::setting('abilities.expiration_presets', []));

        return in_array((string) $days, $presets, true) ? (string) $days : 'custom';
    }

    /**
     * @param  array<int, string>  $abilities
     */
    protected function summarizeAbilities(array $abilities): string
    {
        if (in_array('*', $abilities, true)) {
            return __('filament-mcp::tokens.all');
        }

        return $abilities === []
            ? __('filament-mcp::tokens.none')
            : trans_choice('filament-mcp::tokens.ability_count', count($abilities), ['count' => count($abilities)]);
    }

    /**
     * @param  array<int, string>  $abilities
     * @return array<int, string>
     */
    protected function abilityLabels(array $abilities): array
    {
        if (in_array('*', $abilities, true)) {
            return [__('filament-mcp::tokens.all')];
        }

        $options = $this->getAbilityOptions();

        return array_map(fn (string $ability): string => $options[$ability] ?? $ability, $abilities);
    }
}
