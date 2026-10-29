<?php

namespace Tapp\FilamentMcp\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Tapp\FilamentMcp\FilamentMcpServiceProvider;
use Tapp\FilamentMcp\Tests\Fixtures\AdminPanelProvider;
use Tapp\FilamentMcp\Tests\Fixtures\PublicDocsServer;
use Tapp\FilamentMcp\Tests\Fixtures\ReportsServer;
use Tapp\FilamentMcp\Tests\Fixtures\User;
use Tapp\FilamentMcp\Tests\Fixtures\WarehouseServer;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        if (class_exists(Passport::class)) {
            Passport::$registersRoutes = false;
        }

        return array_values(array_filter([
            LivewireServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            class_exists(SchemasServiceProvider::class) ? SchemasServiceProvider::class : null,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            SanctumServiceProvider::class,
            class_exists(PassportServiceProvider::class) ? PassportServiceProvider::class : null,
            McpServiceProvider::class,
            ActivitylogServiceProvider::class,
            FilamentMcpServiceProvider::class,
            AdminPanelProvider::class,
        ]));
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'https://app.test');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('activitylog.table_name', 'activity_log');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->json('attribute_changes')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
        });
    }

    protected function defineRoutes($router): void
    {
        URL::forceRootUrl('https://app.test');
        URL::forceScheme('https');

        Mcp::web('/mcp/reports', ReportsServer::class)->middleware('auth:sanctum')->name('mcp.reports');
        Mcp::web('/mcp/warehouse', WarehouseServer::class)->middleware(['auth:sanctum']);
        Mcp::web('/mcp/docs', PublicDocsServer::class);
    }

    protected function makeUser(): User
    {
        return User::query()->create(['name' => 'Ada', 'email' => 'ada'.uniqid().'@example.com']);
    }
}
