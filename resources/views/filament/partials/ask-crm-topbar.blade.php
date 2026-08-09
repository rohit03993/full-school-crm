@if (\App\Livewire\AskCrmChatWidget::canView())
    <button
        type="button"
        onclick="window.Livewire && window.Livewire.dispatch('open-ask-crm')"
        style="display:inline-flex;align-items:center;gap:0.35rem;margin-right:0.5rem;border:0;border-radius:999px;background:#f59e0b;color:#fff;padding:0.4rem 0.85rem;font-size:0.75rem;font-weight:800;cursor:pointer;box-shadow:0 2px 8px rgba(245,158,11,.35);"
    >
        <span aria-hidden="true">💬</span>
        Ask CRM
    </button>
@endif
