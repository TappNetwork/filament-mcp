<x-filament::page>
    {{-- Plain CSS so the page looks right without a custom Filament theme. --}}
    <style>
        .fmt-section { margin-bottom: 1.5rem; }
        .fmt-stack { display: flex; flex-direction: column; gap: 1rem; margin: 0; padding: 0; list-style: none; }
        .fmt-stack-sm { gap: 0.5rem; }
        .fmt-stack-lg { gap: 1.5rem; }
        .fmt-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
        .fmt-server-name { font-size: 1rem; font-weight: 600; color: var(--gray-950); }
        .fmt-step-title { font-size: 0.875rem; font-weight: 500; color: var(--gray-950); }
        .fmt-muted { font-size: 0.875rem; color: var(--gray-500); }
        .fmt-prompt { max-height: 10rem; overflow-y: auto; border-radius: 0.5rem; padding: 0.5rem 0.75rem; font-size: 0.875rem; white-space: pre-wrap; overflow-wrap: anywhere; color: var(--gray-600); background: var(--gray-50); box-shadow: 0 0 0 1px rgb(3 7 18 / 0.1); }
        .fmt-note { display: flex; align-items: flex-start; gap: 0.375rem; font-size: 0.875rem; font-weight: 500; color: var(--warning-600); }
        .fmt-note-icon { width: 1rem; height: 1rem; margin-top: 0.125rem; flex-shrink: 0; }
        .fmt-error { font-size: 0.875rem; color: var(--danger-600); }
        .dark .fmt-server-name, .dark .fmt-step-title { color: #fff; }
        .dark .fmt-muted { color: var(--gray-400); }
        .dark .fmt-prompt { color: var(--gray-300); background: rgb(255 255 255 / 0.05); box-shadow: 0 0 0 1px rgb(255 255 255 / 0.1); }
        .dark .fmt-note { color: var(--warning-400); }
        .dark .fmt-error { color: var(--danger-400); }
    </style>
    @if (filled($plainTextToken))
        @php($setupSteps = $this->getSetupSteps())

        <x-filament::section
            icon="heroicon-o-exclamation-triangle"
            icon-color="warning"
            class="fmt-section"
            wire:key="mcp-tokens-plain-text-token"
        >
            <x-slot name="heading">
                {{ __('filament-mcp-tokens::setup.attention') }}
            </x-slot>

            <x-slot name="description">
                {{ __('filament-mcp-tokens::setup.copy_now') }}
            </x-slot>

            <div
                class="fmt-stack fmt-stack-lg"
                x-data="{
                    token: @js($plainTextToken),
                    async writeClipboard(text) {
                        try {
                            await navigator.clipboard.writeText(text)
                            return true
                        } catch (error) {
                            const field = document.createElement('textarea')
                            field.value = text
                            field.setAttribute('readonly', '')
                            field.style.position = 'fixed'
                            field.style.opacity = '0'
                            document.body.appendChild(field)

                            try {
                                field.select()
                                return document.execCommand('copy')
                            } catch (fallbackError) {
                                return false
                            } finally {
                                field.remove()
                            }
                        }
                    },
                }"
            >
                @forelse ($setupSteps as $server)
                    <div
                        class="fmt-stack"
                        data-mcp-server="{{ $server['key'] }}"
                        wire:key="mcp-tokens-server-{{ $server['key'] }}"
                        x-data="{
                            setupPrompt: @js($server['prompt']),
                            mcpConfig: @js($server['config']),
                            promptCopied: false,
                            promptCopiedFailed: false,
                            copied: false,
                            copiedFailed: false,
                            configCopied: false,
                            configCopiedFailed: false,
                            timer: null,
                            async copyText(text, flag) {
                                clearTimeout(this.timer)
                                this[flag + 'Failed'] = false
                                this[flag] = await this.writeClipboard(text)
                                this[flag + 'Failed'] = ! this[flag]

                                this.timer = setTimeout(() => {
                                    this[flag] = false
                                    this[flag + 'Failed'] = false
                                }, 4000)
                            },
                        }"
                    >
                        @if (count($setupSteps) > 1)
                            <h3 class="fmt-server-name">{{ $server['name'] }}</h3>
                        @endif

                        <ol class="fmt-stack fmt-steps">
                            <li class="fmt-stack fmt-stack-sm" data-setup-step="1">
                                <p class="fmt-step-title">{{ __('filament-mcp-tokens::setup.step_1') }}</p>

                                <div
                                    data-setup-prompt
                                    class="fmt-prompt"
                                >{{ $server['prompt'] }}</div>

                                <div class="fmt-row">
                                    <x-filament::button type="button" color="gray" x-on:click="copyText(setupPrompt, 'promptCopied')">
                                        <span x-show="! promptCopied">{{ __('filament-mcp-tokens::setup.copy_prompt') }}</span>
                                        <span x-cloak x-show="promptCopied">{{ __('filament-mcp-tokens::setup.prompt_copied') }}</span>
                                    </x-filament::button>
                                </div>

                                <p x-cloak x-show="promptCopiedFailed" role="status" class="fmt-error">
                                    {{ __('filament-mcp-tokens::setup.prompt_copy_failed') }}
                                </p>
                            </li>

                            <li class="fmt-stack fmt-stack-sm" data-setup-step="2">
                                <p class="fmt-step-title">{{ __('filament-mcp-tokens::setup.step_2') }}</p>

                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        type="text"
                                        readonly
                                        autocomplete="off"
                                        spellcheck="false"
                                        x-bind:value="token"
                                        x-on:focus="$event.target.select()"
                                        style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace"
                                    />
                                </x-filament::input.wrapper>

                                <div class="fmt-row">
                                    <x-filament::button type="button" color="gray" x-on:click="copyText(token, 'copied')">
                                        <span x-show="! copied">{{ __('filament-mcp-tokens::setup.copy_token') }}</span>
                                        <span x-cloak x-show="copied">{{ __('filament-mcp-tokens::setup.token_copied') }}</span>
                                    </x-filament::button>

                                    <span class="fmt-muted">{{ __('filament-mcp-tokens::setup.or') }}</span>

                                    <x-filament::button type="button" color="gray" x-on:click="copyText(mcpConfig, 'configCopied')">
                                        <span x-show="! configCopied">{{ __('filament-mcp-tokens::setup.copy_config') }}</span>
                                        <span x-cloak x-show="configCopied">{{ __('filament-mcp-tokens::setup.config_copied') }}</span>
                                    </x-filament::button>
                                </div>

                                <p role="note" class="fmt-note">
                                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="fmt-note-icon" />
                                    <span>{{ __('filament-mcp-tokens::setup.sensitive') }}</span>
                                </p>

                                <p x-cloak x-show="copiedFailed" role="status" class="fmt-error">
                                    {{ __('filament-mcp-tokens::setup.token_copy_failed') }}
                                </p>

                                <p x-cloak x-show="configCopiedFailed" role="status" class="fmt-error">
                                    {{ __('filament-mcp-tokens::setup.config_copy_failed') }}
                                </p>
                            </li>
                        </ol>
                    </div>
                @empty
                    <div class="fmt-stack fmt-stack-sm" data-setup-step="token" x-data="{ copied: false }">
                        <p class="fmt-muted">{{ __('filament-mcp-tokens::setup.no_servers') }}</p>

                        <x-filament::input.wrapper>
                            <x-filament::input
                                type="text"
                                readonly
                                autocomplete="off"
                                spellcheck="false"
                                x-bind:value="token"
                                x-on:focus="$event.target.select()"
                                style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace"
                            />
                        </x-filament::input.wrapper>

                        <div class="fmt-row">
                            <x-filament::button type="button" color="gray" x-on:click="copied = await writeClipboard(token)">
                                <span x-show="! copied">{{ __('filament-mcp-tokens::setup.copy_token') }}</span>
                                <span x-cloak x-show="copied">{{ __('filament-mcp-tokens::setup.token_copied') }}</span>
                            </x-filament::button>
                        </div>
                    </div>
                @endforelse

                <div class="fmt-row" data-setup-step-end>
                    <x-filament::button type="button" color="gray" wire:click="forgetPlainTextToken">
                        {{ __('filament-mcp-tokens::setup.hide') }}
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament::page>
