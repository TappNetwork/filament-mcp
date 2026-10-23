<?php

use Spatie\Activitylog\Models\Activity;

it('logs each tool call with the token, tool, server and outcome', function () {
    $user = $this->makeUser();
    $token = $user->createToken('t', ['list_reports']);

    callTool('/mcp/reports', 'list_reports', [], $token->plainTextToken);

    $entry = Activity::query()->sole();

    expect($entry->log_name)->toBe('mcp')
        ->and($entry->description)->toBe('list_reports')
        ->and($entry->causer->is($user))->toBeTrue()
        ->and($entry->properties->all())->toBe([
            'token_id' => $token->accessToken->getKey(),
            'tool' => 'list_reports',
            'server' => 'Reports Admin',
            'outcome' => 'ok',
            'result_count' => 2,
        ]);
});

it('logs a refusal with the missing ability', function () {
    $token = $this->makeUser()->createToken('t', ['list_reports'])->plainTextToken;

    callTool('/mcp/reports', 'delete_report', [], $token);

    expect(Activity::query()->sole()->properties->only(['outcome', 'ability'])->all())
        ->toBe(['outcome' => 'refused', 'ability' => 'manage_reports']);
});

it('logs a tool error', function () {
    $token = $this->makeUser()->createToken('t', ['manage_reports'])->plainTextToken;

    callTool('/mcp/reports', 'rename_report', [], $token);

    expect(Activity::query()->sole()->properties['outcome'])->toBe('error');
});

it('keeps the recorded tool and outcome when a tool adds details with the same keys', function () {
    $token = $this->makeUser()->createToken('t', ['manage_reports'])->plainTextToken;

    callTool('/mcp/reports', 'rename_report', [], $token);

    expect(Activity::query()->sole()->properties->only(['tool', 'outcome', 'report'])->all())
        ->toBe(['tool' => 'rename_report', 'outcome' => 'error', 'report' => 'missing']);
});

it('logs which catalog tools execute_tools ran', function () {
    $token = $this->makeUser()->createToken('t', ['count_stock'])->plainTextToken;

    callTool('/mcp/warehouse', 'execute_tools', ['calls' => [['name' => 'count_stock', 'arguments' => (object) []]]], $token);

    expect(Activity::query()->sole()->properties->only(['tool', 'calls'])->all())
        ->toBe(['tool' => 'execute_tools', 'calls' => ['count_stock']]);
});

it('uses the configured log name', function () {
    config()->set('filament-mcp.audit.log_name', 'metric_mcp');
    $token = $this->makeUser()->createToken('t', ['list_reports'])->plainTextToken;

    callTool('/mcp/reports', 'list_reports', [], $token);

    expect(Activity::query()->sole()->log_name)->toBe('metric_mcp');
});

it('can be turned off', function () {
    config()->set('filament-mcp.audit.enabled', false);
    $token = $this->makeUser()->createToken('t', ['list_reports'])->plainTextToken;

    callTool('/mcp/reports', 'list_reports', [], $token)->assertJsonPath('result.isError', false);

    expect(Activity::query()->count())->toBe(0);
});
