<x-filament::page>
    @if (filled($plainTextToken))
        @php($setupSteps = $this->getSetupSteps())

        <x-filament::section
            icon="heroicon-o-exclamation-triangle"
            icon-color="warning"
            class="mb-6"
            wire:key="mcp-tokens-plain-text-token"
        >
            <x-slot name="heading">
                {{ __('filament-mcp-tokens::setup.attention') }}
            </x-slot>

            <x-slot name="description">
                {{ __('filament-mcp-tokens::setup.copy_now') }}
            </x-slot>

            <div
                class="mt-4 space-y-6"
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
                        class="space-y-4"
                        data-mcp-server="{{ $server['key'] }}"
                        wire:key="mcp-tokens-server-{{ $server['key'] }}"
                        x-data="{
                            setupPrompt: @js($server['prompt']),
                            mcpConfig: @js($server['config']),
                            promptCopied: false,
                            promptCopyFailed: false,
                            copied: false,
                            copyFailed: false,
                            configCopied: false,
                            configCopyFailed: false,
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
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $server['name'] }}</h3>
                        @endif

                        <ol class="space-y-4">
                            <li class="space-y-2" data-setup-step="1">
                                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('filament-mcp-tokens::setup.step_1') }}</p>

                                <div
                                    data-setup-prompt
                                    class="max-h-40 overflow-y-auto rounded-lg bg-gray-50 px-3 py-2 text-sm whitespace-pre-wrap break-words text-gray-600 ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10"
                                >{{ $server['prompt'] }}</div>

                                <x-filament::button type="button" color="gray" x-on:click="copyText(setupPrompt, 'promptCopied')">
                                    <span x-show="! promptCopied">{{ __('filament-mcp-tokens::setup.copy_prompt') }}</span>
                                    <span x-cloak x-show="promptCopied">{{ __('filament-mcp-tokens::setup.prompt_copied') }}</span>
                                </x-filament::button>

                                <p x-cloak x-show="promptCopiedFailed" role="status" class="text-sm text-danger-600 dark:text-danger-400">
                                    {{ __('filament-mcp-tokens::setup.prompt_copy_failed') }}
                                </p>
                            </li>

                            <li class="space-y-2" data-setup-step="2">
                                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('filament-mcp-tokens::setup.step_2') }}</p>

                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        type="text"
                                        readonly
                                        autocomplete="off"
                                        spellcheck="false"
                                        x-bind:value="token"
                                        x-on:focus="$event.target.select()"
                                        class="font-mono"
                                    />
                                </x-filament::input.wrapper>

                                <div class="flex flex-wrap items-center gap-2">
                                    <x-filament::button type="button" color="gray" x-on:click="copyText(token, 'copied')">
                                        <span x-show="! copied">{{ __('filament-mcp-tokens::setup.copy_token') }}</span>
                                        <span x-cloak x-show="copied">{{ __('filament-mcp-tokens::setup.token_copied') }}</span>
                                    </x-filament::button>

                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('filament-mcp-tokens::setup.or') }}</span>

                                    <x-filament::button type="button" color="gray" x-on:click="copyText(mcpConfig, 'configCopied')">
                                        <span x-show="! configCopied">{{ __('filament-mcp-tokens::setup.copy_config') }}</span>
                                        <span x-cloak x-show="configCopied">{{ __('filament-mcp-tokens::setup.config_copied') }}</span>
                                    </x-filament::button>
                                </div>

                                <p role="note" class="flex items-start gap-1.5 text-sm font-medium text-warning-600 dark:text-warning-400">
                                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>{{ __('filament-mcp-tokens::setup.sensitive') }}</span>
                                </p>

                                <p x-cloak x-show="copiedFailed" role="status" class="text-sm text-danger-600 dark:text-danger-400">
                                    {{ __('filament-mcp-tokens::setup.token_copy_failed') }}
                                </p>

                                <p x-cloak x-show="configCopiedFailed" role="status" class="text-sm text-danger-600 dark:text-danger-400">
                                    {{ __('filament-mcp-tokens::setup.config_copy_failed') }}
                                </p>
                            </li>
                        </ol>
                    </div>
                @empty
                    <div class="space-y-2" data-setup-step="token" x-data="{ copied: false }">
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('filament-mcp-tokens::setup.no_servers') }}</p>

                        <x-filament::input.wrapper>
                            <x-filament::input
                                type="text"
                                readonly
                                autocomplete="off"
                                spellcheck="false"
                                x-bind:value="token"
                                x-on:focus="$event.target.select()"
                                class="font-mono"
                            />
                        </x-filament::input.wrapper>

                        <x-filament::button type="button" color="gray" x-on:click="copied = await writeClipboard(token)">
                            <span x-show="! copied">{{ __('filament-mcp-tokens::setup.copy_token') }}</span>
                            <span x-cloak x-show="copied">{{ __('filament-mcp-tokens::setup.token_copied') }}</span>
                        </x-filament::button>
                    </div>
                @endforelse

                <div data-setup-step-end>
                    <x-filament::button type="button" color="gray" wire:click="forgetPlainTextToken">
                        {{ __('filament-mcp-tokens::setup.hide') }}
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament::page>
