<?php

use Illuminate\Support\Facades\File;
use Laravel\Passport\ClientRepository;
use Tapp\FilamentMcp\Commands\InstallCommand;

beforeEach(function () {
    $this->sandbox = sys_get_temp_dir().'/mcp-tokens-install-'.uniqid();
    File::ensureDirectoryExists($this->sandbox.'/app/Models');
    File::ensureDirectoryExists($this->sandbox.'/app/Providers/Filament');

    $class = 'InstallUser'.uniqid();
    File::put($this->sandbox."/app/Models/{$class}.php", <<<PHP
    <?php

    namespace Sandbox\\Models;

    use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;
    use Illuminate\\Foundation\\Auth\\User as Authenticatable;
    use Illuminate\\Notifications\\Notifiable;

    class {$class} extends Authenticatable
    {
        use HasFactory, Notifiable;

        protected \$fillable = ['name'];
    }

    PHP);
    require $this->sandbox."/app/Models/{$class}.php";
    config()->set('auth.providers.users.model', "Sandbox\\Models\\{$class}");
    $this->userFile = $this->sandbox."/app/Models/{$class}.php";

    $this->panelFile = $this->sandbox.'/app/Providers/Filament/AdminPanelProvider.php';
    File::put($this->panelFile, <<<'PHP'
    <?php

    namespace App\Providers\Filament;

    use Filament\Panel;
    use Filament\PanelProvider;

    class AdminPanelProvider extends PanelProvider
    {
        public function panel(Panel $panel): Panel
        {
            return $panel
                ->default()
                ->id('admin')
                ->path('admin');
        }
    }

    PHP);

    $this->app->useAppPath($this->sandbox.'/app');
});

afterEach(function () {
    File::deleteDirectory($this->sandbox);
    File::delete(config_path('filament-mcp.php'));
    File::delete(File::glob(database_path('migrations/*_create_personal_access_tokens_table.php')));
});

it('sets up the token page in one command', function () {
    $this->artisan('filament-mcp:install', ['--panel' => 'admin', '--no-migrate' => true])
        ->expectsOutputToContain('Reports Admin')
        ->assertSuccessful();

    expect(File::get($this->userFile))
        ->toContain("use Laravel\\Sanctum\\HasApiTokens;\n")
        ->toContain('use HasApiTokens, HasFactory, Notifiable;')
        ->and(File::get($this->panelFile))
        ->toContain("use Tapp\\FilamentMcp\\FilamentMcpPlugin;\n")
        ->toContain("            ->id('admin')\n            ->plugin(FilamentMcpPlugin::make())\n")
        ->and(config_path('filament-mcp.php'))->toBeFile()
        ->and(File::glob(database_path('migrations/*_create_personal_access_tokens_table.php')))->toHaveCount(1);
});

it('installs the passport driver without adding Sanctum', function () {
    $before = File::get($this->userFile);

    $this->artisan('filament-mcp:install', [
        '--panel' => 'admin',
        '--driver' => 'passport',
        '--no-migrate' => true,
    ])->expectsOutputToContain('php artisan passport:client --personal')
        ->assertSuccessful();

    expect(File::get($this->userFile))->toBe($before)
        ->and(File::get(config_path('filament-mcp.php')))->toContain("'driver' => 'passport'")
        ->and(File::glob(database_path('migrations/*_create_personal_access_tokens_table.php')))->toHaveCount(0);
});

it('reports a passport personal access client when one exists', function () {
    $this->artisan('migrate', [
        '--path' => realpath(__DIR__.'/../../vendor/laravel/passport/database/migrations'),
        '--realpath' => true,
    ])->assertSuccessful();

    app(ClientRepository::class)->createPersonalAccessGrantClient('MCP tests');

    $this->artisan('filament-mcp:install', [
        '--panel' => 'admin',
        '--driver' => 'passport',
        '--no-migrate' => true,
    ])->expectsOutputToContain('Personal access client')
        ->doesntExpectOutputToContain('passport:client --personal')
        ->assertSuccessful();
});

it('rewrites auth.driver when the published line is quoted or spaced differently', function () {
    $this->artisan('filament-mcp:install', ['--panel' => 'admin', '--no-migrate' => true])->assertSuccessful();

    $path = config_path('filament-mcp.php');
    File::put($path, str_replace("'driver' => 'sanctum'", '"driver"  =>  "sanctum"', File::get($path)));

    $this->artisan('filament-mcp:install', [
        '--panel' => 'admin',
        '--driver' => 'passport',
        '--no-migrate' => true,
    ])->assertSuccessful();

    expect(File::get($path))->toMatch('/(["\'])driver\1\s*=>\s*\'passport\'/');
});

it('can run again without changing anything', function () {
    $this->artisan('filament-mcp:install', ['--panel' => 'admin', '--no-migrate' => true])->assertSuccessful();
    $user = File::get($this->userFile);
    $panel = File::get($this->panelFile);

    $this->artisan('filament-mcp:install', ['--panel' => 'admin', '--no-migrate' => true])->assertSuccessful();

    expect(File::get($this->userFile))->toBe($user)
        ->and(File::get($this->panelFile))->toBe($panel)
        ->and(File::glob(database_path('migrations/*_create_personal_access_tokens_table.php')))->toHaveCount(1);
});

it('replaces an existing SanctumPlugin', function () {
    $source = <<<'PHP'
    <?php

    namespace App\Providers\Filament;

    use Devtical\Sanctum\SanctumPlugin;
    use Filament\Panel;

    class AdminPanelProvider
    {
        public function panel(Panel $panel): Panel
        {
            return $panel->id('admin')->plugin(SanctumPlugin::make());
        }
    }
    PHP;

    $patched = app(InstallCommand::class)->withPlugin($source, 'admin');

    expect($patched)
        ->toContain('->plugin(FilamentMcpPlugin::make())')
        ->toContain('use Tapp\FilamentMcp\FilamentMcpPlugin;')
        ->not->toContain('SanctumPlugin;');
});

it('adds the trait to a model with no traits yet', function () {
    $patched = app(InstallCommand::class)->withTrait(<<<'PHP'
    <?php

    namespace App\Models;

    use Illuminate\Foundation\Auth\User as Authenticatable;

    class User extends Authenticatable
    {
        protected $guarded = [];
    }
    PHP);

    expect($patched)->toContain("{\n    use HasApiTokens;\n\n    protected \$guarded = [];");
});

it('fails when the panel does not exist', function () {
    $this->artisan('filament-mcp:install', ['--panel' => 'missing', '--no-migrate' => true])->assertFailed();
});
