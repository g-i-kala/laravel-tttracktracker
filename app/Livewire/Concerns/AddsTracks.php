<?php

namespace App\Livewire\Concerns;

use App\Livewire\Imports\Create;
use App\Services\AiTrackListParserService;
use App\Services\ImportBatchService;
use Livewire\Attributes\Computed;
use Throwable;

/**
 * "Add tracks" modal shared by Parse Review and YouTube Results: paste more tracks,
 * parse them like New Import (AI when enabled, run in the request) and append them
 * to the batch, skipping tracks that are already in it. The component decides what
 * happens next in `tracksAdded()`.
 */
trait AddsTracks
{
    public bool $showAddTracksModal = false;

    public string $addTracksInput = '';

    /**
     * Set when AI parsing failed, to offer the simple parser.
     */
    public bool $addTracksAiFailed = false;

    abstract protected function tracksAdded(int $added, int $skipped): void;

    #[Computed]
    public function addTracksUsesAi(): bool
    {
        return app(AiTrackListParserService::class)->isEnabled();
    }

    public function addTracks(bool $useLineParser = false): void
    {
        $this->validate(
            ['addTracksInput' => ['required', 'string', 'max:'.Create::MAX_AI_INPUT_LENGTH]],
            attributes: ['addTracksInput' => 'track list'],
        );

        $importBatches = app(ImportBatchService::class);
        set_time_limit(240);

        try {
            $parsedLines = $importBatches->parseAdditionalTracks($this->batch, $this->addTracksInput, $useLineParser);
        } catch (Throwable $exception) {
            report($exception);
            $this->addTracksAiFailed = true;
            $this->addError('addTracksInput', 'AI parsing failed. Try again or use the simple parser.');

            return;
        }

        if ($parsedLines === []) {
            $this->addError('addTracksInput', 'No tracks detected. Paste one track per line.');

            return;
        }

        $total = $this->batch->items()->count() + count($parsedLines);

        if ($total > Create::MAX_TRACKS) {
            $this->addError('addTracksInput', 'Too many tracks: the import would have '.$total.'. The maximum is '.Create::MAX_TRACKS.'.');

            return;
        }

        $result = $importBatches->appendParsedLines($this->batch, $parsedLines);

        $this->reset('showAddTracksModal', 'addTracksInput', 'addTracksAiFailed');
        $this->resetErrorBag('addTracksInput');
        $this->batch->refresh();

        $this->tracksAdded($result['added'], $result['skipped']);
    }

    protected function addedTracksNotice(int $added, int $skipped): string
    {
        $notice = "Added {$added} ".str('track')->plural($added).'.';

        if ($skipped > 0) {
            $notice .= " {$skipped} ".str('track')->plural($skipped).' already in this import '.($skipped === 1 ? 'was' : 'were').' skipped.';
        }

        return $notice;
    }
}
