<?php

namespace Tapp\FilamentMcp\Commands;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Laravel\Sanctum\HasApiTokens;
use ReflectionClass;
use Tapp\FilamentMcp\Discovery\McpServerRegistry;
use Tapp\FilamentMcp\FilamentMcpPlugin;

class InstallCommand extends Command
{
    protected $signature = 'filament-mcp:install
        {--panel= : The ID of the panel to add the API tokens page to}
        {--driver=sanctum : Token driver: sanctum or passport}
        {--no-migrate : Publish the Sanctum migration without running it}';

    protected $description = 'Set up API tokens for your MCP servers in a Filament panel';

    public function handle(Filesystem $files): int
    {
        $driver = $this->driver();

        $this->publishConfig();
        $this->selectDriver($driver, $files);

        // Resolve after the driver is selected. The registry keeps the issuer
        // it was given, and that issuer is chosen from the driver config.
        $registry = $this->laravel->make(McpServerRegistry::class);

        if ($driver === 'sanctum') {
            $this->publishSanctumMigration($files);
            $this->addTraitToUserModel($files);
        } else {
            $this->components->twoColumnDetail('Passport', 'using oauth personal access tokens');
            $this->components->warn('The user model must use Laravel\\Passport\\HasApiTokens. This command does not add it.');
        }

        if (! $this->addPluginToPanel($files)) {
            return self::FAILURE;
        }

        $this->reportServers($registry);

        return self::SUCCESS;
    }

    protected function publishConfig(): void
    {
        if (file_exists(config_path('filament-mcp.php'))) {
            $this->components->twoColumnDetail('Config', 'already published');

            return;
        }

        $this->callSilently('vendor:publish', ['--tag' => 'filament-mcp-config']);
        $this->components->twoColumnDetail('Config', 'config/filament-mcp.php');
    }

    protected function driver(): string
    {
        $driver = $this->option('driver');

        if (! is_string($driver) || ! in_array($driver, ['sanctum', 'passport'], true)) {
            throw new \InvalidArgumentException('The --driver option must be sanctum or passport.');
        }

        return $driver;
    }

    /**
     * Writes the chosen driver into the published config and the running app.
     */
    protected function selectDriver(string $driver, Filesystem $files): void
    {
        config(['filament-mcp.auth.driver' => $driver]);

        $path = config_path('filament-mcp.php');

        if ($driver === 'sanctum' || ! $files->exists($path)) {
            return;
        }

        $source = $files->get($path);
        $patched = preg_replace("/'driver' => 'sanctum'/", "'driver' => 'passport'", $source, 1);

        if (is_string($patched) && $patched !== $source) {
            $files->put($path, $patched);
        }
    }

    protected function publishSanctumMigration(Filesystem $files): void
    {
        $published = collect($files->glob(database_path('migrations/*_create_personal_access_tokens_table.php')))->isNotEmpty();

        if (! $published) {
            $this->callSilently('vendor:publish', ['--tag' => 'sanctum-migrations']);
            $this->components->twoColumnDetail('Sanctum migration', 'published');
        } else {
            $this->components->twoColumnDetail('Sanctum migration', 'already published');
        }

        if (! $this->option('no-migrate')) {
            $this->call('migrate', ['--force' => true]);
        }
    }

    protected function addTraitToUserModel(Filesystem $files): void
    {
        $model = config('auth.providers.users.model');

        if (! is_string($model) || ! class_exists($model)) {
            $this->components->warn('Add Laravel\Sanctum\HasApiTokens to your user model.');

            return;
        }

        if (in_array(HasApiTokens::class, class_uses_recursive($model), true)) {
            $this->components->twoColumnDetail('HasApiTokens', 'already on '.class_basename($model));

            return;
        }

        $path = (new ReflectionClass($model))->getFileName();
        $source = $path === false ? null : $files->get($path);
        $patched = $source === null ? null : $this->withTrait($source);

        if ($path === false || $patched === null) {
            $this->components->warn("Add Laravel\\Sanctum\\HasApiTokens to {$model}.");

            return;
        }

        if ($patched !== $source) {
            $files->put($path, $patched);
        }

        $this->components->twoColumnDetail('HasApiTokens', 'added to '.class_basename($model));
    }

    /**
     * Adds the import and the trait, or returns null when the class can't be read.
     */
    public function withTrait(string $source): ?string
    {
        if (! preg_match('/^class\s+\w+[^{]*\{\n/m', $source, $class, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $bodyStart = $class[0][1] + strlen($class[0][0]);
        $body = substr($source, $bodyStart);

        if (preg_match('/^\s*use\s+[^;]*\bHasApiTokens\b/m', $body)) {
            return $source;
        }

        if (preg_match('/^(\s*)use\s+([^;]+);/', $body, $traits)) {
            $body = preg_replace('/^(\s*)use\s+/', '$1use HasApiTokens, ', $body, 1);
        } else {
            $body = "    use HasApiTokens;\n\n".$body;
        }

        $head = $this->withImport(substr($source, 0, $bodyStart), HasApiTokens::class);

        return $head === null ? null : $head.$body;
    }

    protected function addPluginToPanel(Filesystem $files): bool
    {
        $panel = $this->panel();

        if ($panel === null) {
            $this->components->error('No Filament panel found. Pass --panel=<id>.');

            return false;
        }

        $path = $this->panelProviderPath($files, $panel);

        if ($path === null) {
            $this->components->warn("Add FilamentMcpPlugin::make() to the [{$panel->getId()}] panel.");

            return true;
        }

        $source = $files->get($path);

        if (str_contains($source, 'FilamentMcpPlugin')) {
            $this->components->twoColumnDetail('Panel plugin', "already on [{$panel->getId()}]");

            return true;
        }

        $patched = $this->withPlugin($source, $panel->getId());

        if ($patched === null) {
            $this->components->warn("Add FilamentMcpPlugin::make() to the [{$panel->getId()}] panel.");

            return true;
        }

        $files->put($path, $patched);
        $this->components->twoColumnDetail('Panel plugin', "added to [{$panel->getId()}]");

        return true;
    }

    /**
     * Swaps SanctumPlugin for the package plugin, or adds it after ->id().
     */
    public function withPlugin(string $source, string $panelId): ?string
    {
        if (str_contains($source, 'SanctumPlugin::make()')) {
            $source = str_replace('SanctumPlugin::make()', 'FilamentMcpPlugin::make()', $source);
            $source = preg_replace('/^use Devtical\\\\Sanctum\\\\SanctumPlugin;\n/m', '', $source) ?? $source;
        } else {
            $pattern = '/^(\s*)->id\([\'"]'.preg_quote($panelId, '/').'[\'"]\)\n/m';

            if (! preg_match($pattern, $source)) {
                return null;
            }

            $source = preg_replace($pattern, "\$0\$1->plugin(FilamentMcpPlugin::make())\n", $source, 1);
        }

        return $this->withImport((string) $source, FilamentMcpPlugin::class);
    }

    protected function withImport(string $source, string $class): ?string
    {
        if (preg_match('/^use '.preg_quote($class, '/').';$/m', $source)) {
            return $source;
        }

        if (preg_match_all('/^use [^;]+;\n/m', $source, $imports, PREG_OFFSET_CAPTURE)) {
            $last = end($imports[0]);
            $at = $last[1] + strlen($last[0]);

            return substr($source, 0, $at)."use {$class};\n".substr($source, $at);
        }

        if (preg_match('/^namespace [^;]+;\n/m', $source, $namespace, PREG_OFFSET_CAPTURE)) {
            $at = $namespace[0][1] + strlen($namespace[0][0]);

            return substr($source, 0, $at)."\nuse {$class};\n".substr($source, $at);
        }

        return null;
    }

    protected function panel(): ?Panel
    {
        $id = $this->option('panel');

        if (is_string($id) && $id !== '') {
            return Filament::getPanels()[$id] ?? null;
        }

        $panels = Filament::getPanels();

        if (count($panels) === 1) {
            return reset($panels);
        }

        try {
            return Filament::getDefaultPanel();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function panelProviderPath(Filesystem $files, Panel $panel): ?string
    {
        $pattern = '/->id\([\'"]'.preg_quote($panel->getId(), '/').'[\'"]\)/';

        foreach ($files->allFiles(app_path('Providers')) as $file) {
            if (preg_match($pattern, $file->getContents())) {
                return $file->getPathname();
            }
        }

        return null;
    }

    protected function reportServers(McpServerRegistry $registry): void
    {
        $servers = $registry->servers();

        if ($servers === []) {
            $guard = config('filament-mcp.auth.driver') === 'passport' ? 'api' : 'sanctum';
            $this->components->info("Done. No MCP server uses the {$guard} guard yet. Register one in routes/ai.php:");
            $this->line("    Mcp::web('/mcp/app', AppServer::class)->middleware('auth:{$guard}');");

            return;
        }

        $this->components->info('Done. Tokens can now be given abilities for these MCP servers:');

        foreach ($servers as $server) {
            $this->components->twoColumnDetail($server->name, $server->url());
        }
    }
}
