@php
    $inputClasses = 'w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm shadow-xs focus:border-accent-500 focus:ring-2 focus:ring-accent-500/30 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900';
    $labelClasses = 'mb-1 block text-sm font-medium';
    $errorClasses = 'mt-1 text-sm text-red-600 dark:text-red-400';
    $cardClasses = 'rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900';
    $month = $this->thisMonth;
    $usagePercent = $month['usage'] === null ? null : min(100, (int) round($month['usage'] * 100));
@endphp

<div class="flex max-w-4xl flex-col gap-6">
    <div>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">Settings</p>
        <h1 class="text-2xl font-semibold">AI</h1>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">AI parses pasted track lists. Costs are estimates based on token counts.</p>
    </div>

    {{-- This month --}}
    <section class="{{ $cardClasses }} flex flex-col gap-4">
        <h2 class="font-semibold">{{ now()->format('F Y') }}</h2>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Spent</p>
                <p class="text-2xl font-semibold tabular-nums">${{ number_format($month['spent'], 4) }}</p>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    @if ($month['budget'] !== null)
                        of ${{ number_format($month['budget'], 2) }} budget
                    @else
                        no budget set
                    @endif
                </p>
            </div>
            <div>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Calls</p>
                <p class="text-2xl font-semibold tabular-nums">{{ $month['calls'] }}</p>
                @if ($month['failed'] > 0)
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $month['failed'] }} failed</p>
                @endif
            </div>
            <div>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Tokens</p>
                <p class="text-2xl font-semibold tabular-nums">{{ number_format($month['inputTokens'] + $month['outputTokens']) }}</p>
                <p class="text-sm text-zinc-500 tabular-nums dark:text-zinc-400">{{ number_format($month['inputTokens']) }} in · {{ number_format($month['outputTokens']) }} out</p>
            </div>
        </div>

        @if ($usagePercent !== null)
            <div>
                <div class="h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800" role="progressbar" aria-valuenow="{{ $usagePercent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Budget used">
                    <div @class([
                        'h-full rounded-full',
                        'bg-red-500' => $month['usage'] >= 1,
                        'bg-amber-500' => $month['usage'] >= 0.8 && $month['usage'] < 1,
                        'bg-accent-600' => $month['usage'] < 0.8,
                    ]) style="width: {{ $usagePercent }}%"></div>
                </div>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $usagePercent }}% used.
                    @if ($month['usage'] >= 1)
                        <span class="font-medium text-red-600 dark:text-red-400">Budget reached: new imports use the simple line parser.</span>
                    @elseif ($month['usage'] >= 0.8)
                        <span class="font-medium text-amber-600 dark:text-amber-400">Almost at the limit.</span>
                    @endif
                </p>
            </div>
        @endif
    </section>

    {{-- API key --}}
    <section class="{{ $cardClasses }} flex flex-col gap-4">
        <div>
            <h2 class="font-semibold">API key</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                Provider: <code>{{ $this->key['provider'] }}</code>.
                @switch ($this->key['source'])
                    @case ('settings')
                        Using the key saved here: <code>{{ $this->key['masked'] }}</code>.
                        @break
                    @case ('env')
                        Using the key from <code>.env</code>: <code>{{ $this->key['masked'] }}</code>. A key saved here takes priority.
                        @break
                    @default
                        <span class="text-amber-600 dark:text-amber-400">No key set: AI parsing is off.</span>
                @endswitch
            </p>
        </div>

        <form wire:submit="saveApiKey" class="flex flex-col gap-2 sm:flex-row sm:items-start">
            <div class="flex-1">
                <label for="apiKey" class="sr-only">New API key</label>
                <input id="apiKey" type="password" wire:model="apiKey" autocomplete="off" placeholder="{{ $this->key['source'] === 'settings' ? 'Replace key…' : 'Paste a key…' }}" class="{{ $inputClasses }} font-mono">
                @error('apiKey') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded-md bg-accent-600 px-4 py-2 text-sm font-semibold text-white hover:bg-accent-500">Save key</button>
        </form>

        <div class="flex flex-wrap items-center gap-3 text-sm">
            @if ($this->key['source'] !== null)
                <button type="button" wire:click="testApiKey" wire:loading.attr="disabled" wire:target="testApiKey" class="rounded-md border border-zinc-300 px-3 py-1.5 font-medium hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800">
                    <span wire:loading.remove wire:target="testApiKey">Test key</span>
                    <span wire:loading wire:target="testApiKey">Testing…</span>
                </button>
            @endif
            @if ($this->key['source'] === 'settings')
                <button type="button" wire:click="removeApiKey" wire:confirm="Remove the saved key? The .env key (if any) will be used." class="rounded-md border border-zinc-300 px-3 py-1.5 font-medium hover:text-red-600 dark:border-zinc-700 dark:hover:text-red-400">
                    Remove saved key
                </button>
            @endif

            @if ($keyTestResult === 'valid')
                <span class="text-accent-600 dark:text-accent-400">Key works.</span>
            @elseif ($keyTestResult === 'invalid')
                <span class="text-red-600 dark:text-red-400">Key was rejected (or the provider could not be reached).</span>
            @elseif ($keyTestResult === 'unsupported')
                <span class="text-zinc-500">Key test is available for OpenAI only.</span>
            @endif
        </div>
    </section>

    {{-- Model and budget --}}
    <form wire:submit="saveSettings" class="{{ $cardClasses }} flex flex-col gap-4">
        <h2 class="font-semibold">Model and budget</h2>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="model" class="{{ $labelClasses }}">Model</label>
                <input id="model" type="text" list="priced-models" wire:model="model" class="{{ $inputClasses }} font-mono">
                <datalist id="priced-models">
                    @foreach ($this->pricedModels as $pricedModel)
                        <option value="{{ $pricedModel }}" wire:key="model-{{ $pricedModel }}"></option>
                    @endforeach
                </datalist>
                @error('model') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                @if (filled($model) && ! in_array($model, $this->pricedModels, true))
                    <p class="mt-1 text-sm text-amber-600 dark:text-amber-400">No price for this model in <code>config/services.php</code>: its cost will not be counted.</p>
                @endif
            </div>

            <div>
                <label for="monthlyBudget" class="{{ $labelClasses }}">Monthly budget (USD) <span class="font-normal text-zinc-500">(optional)</span></label>
                <input id="monthlyBudget" type="number" step="0.01" min="0" wire:model="monthlyBudget" placeholder="No limit" class="{{ $inputClasses }}">
                @error('monthlyBudget') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Warning at 80%. Once reached, new imports use the simple line parser until next month.</p>
            </div>
        </div>

        <div>
            <button type="submit" class="rounded-md bg-accent-600 px-4 py-2 text-sm font-semibold text-white hover:bg-accent-500">Save</button>
        </div>
    </form>

    {{-- Recent calls --}}
    <section class="flex flex-col gap-3">
        <h2 class="font-semibold">Recent calls</h2>

        @if ($this->recentCalls->isEmpty())
            <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-8 text-center text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                No AI calls yet.
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 text-xs font-medium tracking-wide text-zinc-500 uppercase dark:border-zinc-800 dark:text-zinc-400">
                        <tr>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Import</th>
                            <th class="px-3 py-2">Model</th>
                            <th class="px-3 py-2 text-right">Tokens in / out</th>
                            <th class="px-3 py-2 text-right">Cost</th>
                            <th class="px-3 py-2 text-right">Time</th>
                            <th class="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach ($this->recentCalls as $call)
                            <tr wire:key="call-{{ $call->id }}">
                                <td class="px-3 py-2 whitespace-nowrap text-zinc-600 dark:text-zinc-400">{{ $call->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-3 py-2">
                                    @if ($call->importBatch)
                                        <a href="{{ route('imports.review', $call->importBatch) }}" wire:navigate class="hover:text-accent-600 dark:hover:text-accent-400">{{ $call->importBatch->name }}</a>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $call->model }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap tabular-nums">{{ number_format($call->input_tokens) }} / {{ number_format($call->output_tokens) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $call->cost_usd === null ? '—' : '$'.number_format((float) $call->cost_usd, 4) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap tabular-nums">{{ $call->duration_ms === null ? '—' : number_format($call->duration_ms / 1000, 1).' s' }}</td>
                                <td class="px-3 py-2">
                                    @if ($call->status === \App\Enums\AiUsageStatus::Failed)
                                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950 dark:text-red-300" title="{{ $call->error_message }}">Failed</span>
                                    @else
                                        <span class="rounded-full bg-accent-100 px-2 py-0.5 text-xs font-medium text-accent-700 dark:bg-accent-950 dark:text-accent-300">OK</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
