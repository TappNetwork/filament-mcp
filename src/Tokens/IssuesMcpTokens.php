<?php

namespace Tapp\FilamentMcp\Tokens;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Creates, lists, and revokes the personal access tokens this package manages.
 */
interface IssuesMcpTokens
{
    public function name(): string;

    /**
     * Auth guards that mark an MCP route as managed by this package.
     *
     * @return list<string>
     */
    public function guards(): array;

    /**
     * @param  list<string>  $abilities
     */
    public function issue(Authenticatable $user, string $name, array $abilities, ?CarbonInterface $expiresAt): string;

    public function tokensQuery(Authenticatable $user): Builder;

    /**
     * @return list<string>
     */
    public function abilities(Model $token): array;

    /**
     * Replaces the token's abilities in place. The plain-text token stays the same.
     *
     * @param  list<string>  $abilities
     */
    public function updateAbilities(Model $token, array $abilities): void;

    /**
     * Whether the user's current token grants the ability.
     */
    public function allows(Authenticatable $user, string $ability): bool;

    public function revoke(Model $token): void;

    public function tracksLastUsedAt(): bool;
}
