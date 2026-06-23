@if ($this->showNameSearch || filled($this->search['name'] ?? null) || filled($this->searchedName))
    <div class="fi-student-search-name-header col-span-full">
        <p class="fi-student-search-name-label">Search by name</p>
        <p class="fi-student-search-name-hint">At least 2 letters — shows a list when several students match</p>
    </div>
@endif
