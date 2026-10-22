<?php

return [
    'prompt' => "Connect the :name MCP server at :url. It uses HTTP transport and a bearer token in the header 'Authorization: Bearer <token>'. Ask me for the token securely. Don't have me paste it into chat. If you have no secure input, have me set it in an environment variable from my own terminal with hidden input, and reference that variable from your MCP config. Then connect and list the available tools.",
    'title' => 'API tokens',
    'attention' => 'Attention needed',
    'copy_now' => "Make sure to copy your new personal access token now. You won't be able to see it again!",
    'step_1' => 'Step 1: Paste the setup prompt into your agent',
    'step_2' => 'Step 2: Give your agent the token securely',
    'copy_prompt' => 'Copy setup prompt',
    'prompt_copied' => 'Prompt copied',
    'prompt_copy_failed' => "Couldn't copy the setup prompt. Allow clipboard access and try again.",
    'copy_token' => 'Copy token',
    'token_copied' => 'Token copied',
    'or' => 'or',
    'copy_config' => 'Copy JSON config',
    'config_copied' => 'Config copied',
    'config_copy_failed' => "Couldn't copy the JSON config. Allow clipboard access and try again.",
    'token_copy_failed' => "Couldn't copy the token. Select it and copy it yourself.",
    'sensitive' => "Contains your sensitive token. Don't paste it into a chat prompt. Use your agent's secure input or add it to your MCP config file.",
    'no_servers' => 'No MCP server uses Sanctum tokens yet. Copy the token now.',
    'hide' => 'Hide token',
];
