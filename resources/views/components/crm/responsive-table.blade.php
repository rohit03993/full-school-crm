{{-- Wraps a <table> — stacks rows as cards below md (768px). Add data-label on each <td>. --}}
<div {{ $attributes->merge(['class' => 'crm-responsive-table']) }}>
    {{ $slot }}
</div>
