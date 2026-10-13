<?php

namespace App\Livewire\Imports;

use App\Models\ImportBatch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Imports list: all batches, newest first, with a link to continue each one.
 */
#[Title('Imports')]
class Index extends Component
{
    use WithPagination;

    public const int PER_PAGE = 25;

    /**
     * @return LengthAwarePaginator<int, ImportBatch>
     */
    #[Computed]
    public function batches(): LengthAwarePaginator
    {
        return ImportBatch::query()
            ->withCount('items')
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE);
    }

    public function render(): View
    {
        return view('livewire.imports.index');
    }
}
