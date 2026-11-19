# Changelog

All notable changes to `filament-mcp` will be documented in this file.

## Unreleased

- Group the token form's abilities into sections. Tag a tool with `#[AbilityGroup('LMS')]` and its ability appears under "LMS abilities" with its own select-all, below a select-all for everything. Untagged tools fall under "General" and `abilities.extra` under "Other". One section renders a flat list as before. `McpServer` and `McpServerRegistry` gain `groupedAbilities()`; `abilities()` is unchanged.

- A tool label derived from a class name drops its trailing " Tool" (`ListSitesTool` shows as "List Sites"). Declared titles and labels are kept, and ability keys don't change, so existing tokens keep working. Each group's checkbox list is labelled with its group name for screen readers.

- The install command adds `HasApiTokens` to `final`, `readonly`, and `abstract` user models, including attributes above the class. A newly published Sanctum migration uses `uuidMorphs` or `ulidMorphs` when the user key is a UUID, a ULID, or a string. Install steps require the package from Packagist.

- Opt-in manage-all for MCP tokens via `FilamentMcpPlugin::canManageAllTokens()` so allowed users see every token (with an Owner column) and can edit or revoke them. Creating a token still issues it for the signed-in user. `FilamentMcpPlugin::canAccess()` limits who can open the page, falling back to `authorization.gate` when unset. `IssuesMcpTokens` gains `allTokensQuery()` and `owner()`.

- Edit a token's abilities from the token page. The token keeps its value and gets the new abilities on its next request, with Sanctum and Passport. `IssuesMcpTokens` gains `updateAbilities()` and `allows()`.

- First release: an MCP token page for Filament (token table, expiry, revoking and setup steps for any AI agent), one Sanctum ability per tool, ability groups, tool-call enforcement, an optional audit log and an install command.
