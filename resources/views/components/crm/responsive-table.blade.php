{{--
    Wraps a <table> and turns each row into a card below lg (1024px).

    On each <td>:
      data-label="Course"                → label shown above the value in the card
      data-label=""                      → no label
      crm-responsive-table__title        → card header, moved to the top
      crm-responsive-table__actions      → footer buttons, moved to the bottom
      crm-responsive-table__wide         → full card width instead of half (long text)

    Facts sit in a two-column grid, so cells holding prose or form controls should be
    wide (controls are detected automatically).
--}}
<div {{ $attributes->merge(['class' => 'crm-responsive-table']) }}>
    {{ $slot }}
</div>
