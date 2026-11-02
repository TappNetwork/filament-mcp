# Changelog

All notable changes to `filament-mcp` will be documented in this file.

## Unreleased

- Opt-in manage-all for MCP tokens via `FilamentMcpPlugin::canManageAllTokens()` so allowed users see every token (with an Owner column) and can edit or revoke them. Creating a token still issues it for the signed-in user. `FilamentMcpPlugin::canAccess()` limits who can open the page, falling back to `authorization.gate` when unset. `IssuesMcpTokens` gains `allTokensQuery()` and `owner()`.

- Edit a token's abilities from the token page. The token keeps its value and gets the new abilities on its next request, with Sanctum and Passport. `IssuesMcpTokens` gains `updateAbilities()` and `allows()`.

- First release: an MCP token page for Filament (token table, expiry, revoking and setup steps for any AI agent), one Sanctum ability per tool, ability groups, tool-call enforcement, an optional audit log and an install command.
