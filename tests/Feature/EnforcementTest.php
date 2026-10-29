<?php

use Laravel\Sanctum\Sanctum;
use Tapp\FilamentMcp\Tests\Fixtures\PublicDocsServer;
use Tapp\FilamentMcp\Tests\Fixtures\ReportsServer;
use Tapp\FilamentMcp\Tests\Fixtures\Tools\CountStock;
use Tapp\FilamentMcp\Tests\Fixtures\Tools\DeleteReport;
use Tapp\FilamentMcp\Tests\Fixtures\Tools\ListReports;

it('runs a tool when the token has its ability', function () {
    $token = $this->makeUser()->createToken('t', ['list_reports'])->plainTextToken;

    callTool('/mcp/reports', 'list_reports', [], $token)
        ->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.content.0.text', 'report-1, report-2');
});

it('refuses a tool when the token lacks its ability', function () {
    $token = $this->makeUser()->createToken('t', ['list_reports'])->plainTextToken;

    callTool('/mcp/reports', 'delete_report', [], $token)
        ->assertOk()
        ->assertJsonPath('result.isError', true)
        ->assertJsonPath('result.content.0.text', 'Token lacks ability manage_reports.');
});

it('lets one group ability cover every tool in the group', function () {
    $token = $this->makeUser()->createToken('t', ['manage_reports'])->plainTextToken;

    callTool('/mcp/reports', 'delete_report', [], $token)->assertJsonPath('result.content.0.text', 'deleted');
    callTool('/mcp/reports', 'list_reports', [], $token)->assertJsonPath('result.isError', true);
});

it('lets a wildcard token call every tool', function () {
    $token = $this->makeUser()->createToken('t')->plainTextToken;

    callTool('/mcp/reports', 'delete_report', [], $token)->assertJsonPath('result.content.0.text', 'deleted');
});

it('rejects a request with no token', function () {
    callTool('/mcp/reports', 'list_reports')->assertUnauthorized();
});

it('lists only the tools the token can call', function () {
    $token = $this->makeUser()->createToken('t', ['manage_reports'])->plainTextToken;

    $names = collect(mcp('/mcp/reports', 'tools/list', [], $token)->json('result.tools'))->pluck('name')->all();

    expect($names)->toBe(['delete_report', 'rename_report']);
});

it('checks every call that execute_tools makes', function () {
    $token = $this->makeUser()->createToken('t', ['count_stock'])->plainTextToken;

    callTool('/mcp/warehouse', 'execute_tools', ['calls' => [['name' => 'count_stock', 'arguments' => (object) []]]], $token)
        ->assertJsonPath('result.isError', false)
        ->assertSee('42');

    callTool('/mcp/warehouse', 'execute_tools', ['calls' => [
        ['name' => 'count_stock', 'arguments' => (object) []],
        ['name' => 'restock_item', 'arguments' => (object) []],
    ]], $token)
        ->assertJsonPath('result.isError', true)
        ->assertJsonPath('result.content.0.text', 'Token lacks ability restock_item.');
});

it('keeps search and execute visible on a catalog server', function () {
    $token = $this->makeUser()->createToken('t', ['count_stock'])->plainTextToken;

    $names = collect(mcp('/mcp/warehouse', 'tools/list', [], $token)->json('result.tools'))->pluck('name')->all();

    expect($names)->toBe(['search_tools', 'execute_tools']);
});

it('leaves servers that are not behind Sanctum alone', function () {
    $this->actingAs($this->makeUser());

    callTool('/mcp/docs', 'count_stock')->assertJsonPath('result.content.0.text', '42');
});

it('enforces abilities in server tests that act as a Sanctum user', function () {
    Sanctum::actingAs($this->makeUser(), ['list_reports']);

    ReportsServer::tool(ListReports::class)->assertOk();
    ReportsServer::tool(DeleteReport::class)->assertHasErrors(['Token lacks ability manage_reports.']);
});

it('does not guard a server test with no signed-in user', function () {
    ReportsServer::tool(DeleteReport::class)->assertOk()->assertSee('deleted');
    PublicDocsServer::tool(CountStock::class)->assertSee('42');
});

it('lets a signed-in user with no access token call tools', function () {
    $this->actingAs($this->makeUser());

    ReportsServer::tool(DeleteReport::class)->assertOk()->assertSee('deleted');
});

it('lets a signed-in browser session call tools, like Sanctum does for first-party requests', function () {
    $this->actingAs($this->makeUser(), 'web');

    callTool('/mcp/reports', 'delete_report')->assertJsonPath('result.content.0.text', 'deleted');
});
