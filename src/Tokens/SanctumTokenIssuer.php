<?php

namespace Tapp\FilamentMcp\Tokens;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class SanctumTokenIssuer implements IssuesMcpTokens
{
    public function name(): string
    {
        return 'sanctum';
    }

    public function guards(): array
    {
        return $this->configuredGuards() ?? ['sanctum'];
    }

    public function issue(Authenticatable $user, string $name, array $abilities, ?CarbonInterface $expiresAt): string
    {
        if (! method_exists($user, 'createToken')) {
            throw new RuntimeException('The user model must use Laravel\Sanctum\HasApiTokens.');
        }

        return $user->createToken($name, $abilities, $expiresAt)->plainTextToken;
    }

    public function tokensQuery(Authenticatable $user): Builder
    {
        if (! method_exists($user, 'tokens')) {
            throw new RuntimeException('The user model must use Laravel\Sanctum\HasApiTokens.');
        }

        return $user->tokens()->getQuery();
    }

    public function abilities(Model $token): array
    {
        $abilities = $token->getAttribute('abilities');

        return is_array($abilities) ? array_values($abilities) : [];
    }

    public function revoke(Model $token): void
    {
        $token->delete();
    }

    public function tracksLastUsedAt(): bool
    {
        return true;
    }

    /**
     * @return list<string>|null
     */
    protected function configuredGuards(): ?array
    {
        $guards = config('filament-mcp.auth.guards');

        if (! is_array($guards) || $guards === []) {
            return null;
        }

        return array_values($guards);
    }
}
