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
    skipWithoutSanctum();

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
        ->and(File::glob(database_path('migrations/*_create_personal_access_tokens_table.php')))->toHaveCount(1)
        ->and(File::get(File::glob(database_path('migrations/*_create_personal_access_tokens_table.php'))[0]))
        ->toContain("\$table->morphs('tokenable');");
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
    skipWithoutSanctum();

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
    skipWithoutSanctum();

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

it('adds HasApiTokens on a class with modifiers and an existing trait line', function (string $declaration) {
    $source = <<<PHP
    <?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Foundation\Auth\User as Authenticatable;
    use Illuminate\Notifications\Notifiable;

    {$declaration}
    {
        use HasFactory, Notifiable;

        protected \$guarded = [];
    }
    PHP;

    $patched = app(InstallCommand::class)->withTrait($source, 'User');
    $again = app(InstallCommand::class)->withTrait((string) $patched, 'User');

    expect($patched)
        ->toContain("use Laravel\\Sanctum\\HasApiTokens;\n")
        ->toContain('use HasApiTokens, HasFactory, Notifiable;')
        ->toContain($declaration)
        ->and(substr_count((string) $patched, 'HasApiTokens'))->toBe(2)
        ->and($again)->toBe($patched);
})->with([
    'class' => 'class User extends Authenticatable',
    'final' => 'final class User extends Authenticatable',
    'readonly' => 'readonly class User extends Authenticatable',
    'abstract' => 'abstract class User extends Authenticatable',
    'final readonly' => 'final readonly class User extends Authenticatable',
    'readonly final' => 'readonly final class User extends Authenticatable',
    'abstract readonly' => 'abstract readonly class User extends Authenticatable',
]);

it('adds the trait when attributes sit above the class', function () {
    $source = <<<'PHP'
    <?php

    declare(strict_types=1);

    namespace App\Models;

    use Illuminate\Database\Eloquent\Attributes\Hidden;
    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Foundation\Auth\User as Authenticatable;
    use Illuminate\Notifications\Notifiable;

    /**
     * @property-read array{name: string} $id
     */
    #[Hidden([
        'password',
        'remember_token',
    ])]
    final class User extends Authenticatable
    {
        use HasFactory, Notifiable;

        protected $guarded = [];
    }
    PHP;

    $patched = app(InstallCommand::class)->withTrait($source, 'User');
    $again = app(InstallCommand::class)->withTrait((string) $patched, 'User');

    expect($patched)
        ->toContain("use Laravel\\Sanctum\\HasApiTokens;\n")
        ->toContain("#[Hidden([\n    'password',\n    'remember_token',\n])]\nfinal class User extends Authenticatable")
        ->toContain('use HasApiTokens, HasFactory, Notifiable;')
        ->and(substr_count((string) $patched, 'HasApiTokens'))->toBe(2)
        ->and($again)->toBe($patched);
});

it('leaves a model that already uses HasApiTokens alone', function (string $source) {
    expect(app(InstallCommand::class)->withTrait($source, 'User'))->toBe($source);
})->with([
    'own trait line' => <<<'PHP'
    <?php

    namespace App\Models;

    use Laravel\Sanctum\HasApiTokens;

    final class User extends Authenticatable
    {
        use HasApiTokens;
    }
    PHP,
    'trait already in the list' => <<<'PHP'
    <?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Notifications\Notifiable;
    use Laravel\Sanctum\HasApiTokens;

    final readonly class User extends Authenticatable
    {
        use HasFactory, HasApiTokens, Notifiable;
    }
    PHP,
]);

it('does not patch a different class when the model name is given', function () {
    $source = <<<'PHP'
    <?php

    namespace App\Models;

    class Username extends Authenticatable
    {
        protected $guarded = [];
    }
    PHP;

    expect(app(InstallCommand::class)->withTrait($source, 'User'))->toBeNull();
});

it('rewrites morphs(tokenable) and leaves a string morph in place', function () {
    $command = app(InstallCommand::class);

    expect($command->withTokenableMorph('        $table->morphs(\'tokenable\');', 'uuidMorphs'))
        ->toBe('        $table->uuidMorphs(\'tokenable\');')
        ->and($command->withTokenableMorph('        $table->morphs("tokenable");', 'ulidMorphs'))
        ->toBe('        $table->ulidMorphs("tokenable");')
        ->and($command->withTokenableMorph('        $table->uuidMorphs(\'tokenable\');', 'ulidMorphs'))
        ->toBe('        $table->uuidMorphs(\'tokenable\');')
        ->and($command->withTokenableMorph('        $table->id();', 'uuidMorphs'))
        ->toBeNull();
});

it('publishes a string tokenable morph for a string user key', function (string $imports, string $body, string $modifiers, string $method) {
    skipWithoutSanctum();

    $class = 'KeyedUser'.uniqid();
    $path = $this->sandbox."/app/Models/{$class}.php";
    File::put($path, <<<PHP
    <?php

    namespace Sandbox\\Models;

    {$imports}
    use Illuminate\\Foundation\\Auth\\User as Authenticatable;

    {$modifiers}class {$class} extends Authenticatable
    {
        {$body}
    }

    PHP);
    require $path;
    config()->set('auth.providers.users.model', "Sandbox\\Models\\{$class}");

    $this->artisan('filament-mcp:install', ['--panel' => 'admin', '--no-migrate' => true])
        ->assertSuccessful();

    $migrations = File::glob(database_path('migrations/*_create_personal_access_tokens_table.php'));
    $migration = File::get($migrations[0]);

    expect($migrations)->toHaveCount(1)
        ->and($migration)->toContain("\$table->{$method}('tokenable');")
        ->and($migration)->not->toMatch('/->morphs\s*\(\s*[\'"]tokenable[\'"]\)/')
        ->and(File::get($path))->toContain('use HasApiTokens')
        ->and(substr_count(File::get($path), 'HasApiTokens'))->toBe(2);

    $this->artisan('filament-mcp:install', ['--panel' => 'admin', '--no-migrate' => true])
        ->assertSuccessful();

    expect(File::get($migrations[0]))->toBe($migration)
        ->and(substr_count(File::get($path), 'HasApiTokens'))->toBe(2);
})->with([
    'HasUuids on a final class' => [
        'use Illuminate\Database\Eloquent\Concerns\HasUuids;',
        'use HasUuids;',
        'final ',
        'uuidMorphs',
    ],
    'HasUlids' => [
        'use Illuminate\Database\Eloquent\Concerns\HasUlids;',
        'use HasUlids;',
        'final ',
        'ulidMorphs',
    ],
    'string keyType' => [
        '',
        'protected $keyType = \'string\';',
        'final ',
        'uuidMorphs',
    ],
]);

it('leaves an already published personal access tokens migration unchanged', function () {
    skipWithoutSanctum();

    $class = 'UuidUser'.uniqid();
    $user = $this->sandbox."/app/Models/{$class}.php";
    File::put($user, <<<PHP
    <?php

    namespace Sandbox\\Models;

    use Illuminate\\Database\\Eloquent\\Concerns\\HasUuids;
    use Illuminate\\Foundation\\Auth\\User as Authenticatable;

    final class {$class} extends Authenticatable
    {
        use HasUuids;
    }

    PHP);
    require $user;
    config()->set('auth.providers.users.model', "Sandbox\\Models\\{$class}");

    $path = database_path('migrations/2019_12_14_000001_create_personal_access_tokens_table.php');
    File::ensureDirectoryExists(dirname($path));
    $original = "<?php\n\n\$table->morphs('tokenable');\n";
    File::put($path, $original);

    $this->artisan('filament-mcp:install', ['--panel' => 'admin', '--no-migrate' => true])
        ->assertSuccessful();

    expect(File::get($path))->toBe($original)
        ->and(File::glob(database_path('migrations/*_create_personal_access_tokens_table.php')))->toHaveCount(1);
});
