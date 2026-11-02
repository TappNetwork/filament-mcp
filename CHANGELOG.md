# Changelog

All notable changes to `filament-mcp` will be documented in this file.

## Unreleased

- Edit a token's abilities from the token page. The token keeps its value and gets the new abilities on its next request, with Sanctum and Passport. `IssuesMcpTokens` gains `updateAbilities()` and `allows()`.

- First release: an MCP token page for Filament (token table, expiry, revoking and setup steps for any AI agent), one Sanctum ability per tool, ability groups, tool-call enforcement, an optional audit log and an install command.
