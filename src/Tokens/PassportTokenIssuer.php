<?php

namespace Tapp\FilamentMcp\Tokens;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\Passport;
use RuntimeException;
use Tapp\FilamentMcp\Discovery\McpServerRegistry;

/**
 * Passport personal access tokens. createToken() has no per-token expiry argument,
 * so the chosen expiry is written onto the token row after it is issued.
 */
class PassportTokenIssuer extends SanctumTokenIssuer
{
    public function name(): string
    {
        return 'passport';
    }

    public function guards(): array
    {
        return $this->configuredGuards() ?? ['api'];
    }

    public function issue(Authenticatable $user, string $name, array $abilities, ?CarbonInterface $expiresAt): string
    {
        if (! class_exists(Passport::class)) {
            throw new RuntimeException('filament-mcp is set to the passport driver, but laravel/passport is not installed.');
        }

        if (! method_exists($user, 'createToken')) {
            throw new RuntimeException('The user model must use Laravel\Passport\HasApiTokens.');
        }

        $this->registerScopes();

        $result = $user->createToken($name, $abilities);
        $token = $result->token;

        if ($token instanceof Model) {
            $token->forceFill(['expires_at' => $expiresAt])->save();
        }

        return (string) $result->accessToken;
    }

    public function tokensQuery(Authenticatable $user): Builder
    {
        if (! method_exists($user, 'tokens')) {
            throw new RuntimeException('The user model must use Laravel\Passport\HasApiTokens.');
        }

        return $user->tokens()
            ->where('revoked', false)
            ->whereHas('client', function (Builder $query): void {
                $query->where('grant_types', 'like', '%personal_access%');
            })
            ->getQuery();
    }

    public function abilities(Model $token): array
    {
        $scopes = $token->getAttribute('scopes');

        return is_array($scopes) ? array_values($scopes) : [];
    }

    public function revoke(Model $token): void
    {
        if (method_exists($token, 'revoke')) {
            $token->revoke();

            return;
        }

        $token->forceFill(['revoked' => true])->save();
    }

    public function tracksLastUsedAt(): bool
    {
        return false;
    }

    /**
     * Passport only issues scopes that the application has defined. Tool abilities are
     * those scopes, registered here so a token can be given the tools the user picked.
     * Existing scopes are kept.
     */
    private function registerScopes(): void
    {
        $defined = [];

        foreach (Passport::scopes() as $scope) {
            $defined[$scope->id] = $scope->description;
        }

        $scopes = ['*' => 'All MCP tools'] + $defined + app(McpServerRegistry::class)->abilities();

        if ($scopes !== $defined) {
            Passport::tokensCan($scopes);
        }
    }
}
