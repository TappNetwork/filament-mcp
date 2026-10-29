<?php

namespace Tapp\FilamentMcp\Tokens;

use Carbon\CarbonInterface;
use DateInterval;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;
use RuntimeException;
use Tapp\FilamentMcp\Discovery\McpServerRegistry;

/**
 * Passport personal access tokens. createToken() has no per-token expiry argument,
 * so the chosen lifetime is applied to Passport's personal access token TTL for
 * that one call. The JWT exp and the token row then share it.
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

        $previous = Passport::$personalAccessTokensExpireIn;

        try {
            if ($expiresAt !== null) {
                $seconds = max(0, (int) ceil(Carbon::now()->diffInSeconds($expiresAt, absolute: false)));
                Passport::personalAccessTokensExpireIn(new DateInterval('PT'.$seconds.'S'));
            }

            $result = $user->createToken($name, $abilities);
        } finally {
            Passport::$personalAccessTokensExpireIn = $previous;
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
     * Scopes the app already defined are kept. "*" is left unregistered: Passport
     * already treats it as a defined scope, and a personal access token created
     * with it can call every tool.
     */
    private function registerScopes(): void
    {
        $defined = [];

        foreach (Passport::scopes() as $scope) {
            $defined[$scope->id] = $scope->description;
        }

        $scopes = $defined + app(McpServerRegistry::class)->abilities();

        if ($scopes !== $defined) {
            Passport::tokensCan($scopes);
        }
    }
}
