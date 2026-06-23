@if (! $this->showNameSearch && ! filled($this->search['name'] ?? null) && ! filled($this->searchedName))
    <div class="fi-student-search-name-toggle col-span-full">
        <span class="fi-student-search-name-toggle-line" aria-hidden="true"></span>
        <button
            type="button"
            wire:click="$set('showNameSearch', true)"
            class="fi-student-search-name-toggle-btn"
        >
            <x-filament::icon icon="heroicon-o-user" class="h-4 w-4 shrink-0" />
            Search by name instead
        </button>
        <span class="fi-student-search-name-toggle-line" aria-hidden="true"></span>
    </div>
@endif
