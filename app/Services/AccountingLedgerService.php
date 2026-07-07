<?php

namespace App\Services;

use App\Enums\AccountingAccountType;
use App\Enums\AccountingReferenceType;
use App\Enums\EnrollmentStatus;
use App\Enums\PaymentMode;
use App\Models\AccountingAccount;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingJournalLine;
use App\Models\FeeInstallment;
use App\Models\FeePenalty;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingLedgerService
{
    public const CODE_CASH = '1000';

    public const CODE_BANK = '1010';

    public const CODE_FEES_RECEIVABLE = '1100';

    public const CODE_TUITION_INCOME = '4000';

    public const CODE_LATE_FEE_INCOME = '4100';

    public function ensureDefaultAccounts(): void
    {
        $defaults = [
            [self::CODE_CASH, 'Cash', AccountingAccountType::Asset],
            [self::CODE_BANK, 'Bank / UPI', AccountingAccountType::Asset],
            [self::CODE_FEES_RECEIVABLE, 'Fees receivable', AccountingAccountType::Asset],
            [self::CODE_TUITION_INCOME, 'Tuition fee income', AccountingAccountType::Income],
            [self::CODE_LATE_FEE_INCOME, 'Late fee income', AccountingAccountType::Income],
        ];

        foreach ($defaults as [$code, $name, $type]) {
            AccountingAccount::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
        }
    }

    public function postPayment(Payment $payment, float $feePortion, float $penaltyPortion, ?User $postedBy = null): ?AccountingJournalEntry
    {
        $this->ensureDefaultAccounts();

        if (AccountingJournalEntry::query()
            ->where('reference_type', AccountingReferenceType::Payment)
            ->where('reference_id', $payment->id)
            ->exists()) {
            return null;
        }

        $amount = round((float) $payment->amount, 2);
        $feePortion = round($feePortion, 2);
        $penaltyPortion = round($penaltyPortion, 2);

        if ($amount <= 0) {
            return null;
        }

        $payment->loadMissing('feeInstallment');

        $cashAccount = $this->accountForPaymentMode($payment->payment_mode);
        $lines = [
            ['account' => $cashAccount, 'debit' => $amount, 'credit' => 0.0, 'memo' => 'Receipt '.$payment->receipt_number],
        ];

        if ($feePortion > 0) {
            $lines[] = [
                'account' => $this->account(self::CODE_TUITION_INCOME),
                'debit' => 0.0,
                'credit' => $feePortion,
                'memo' => $payment->feeInstallment?->label ?? 'Tuition fees',
            ];
        }

        if ($penaltyPortion > 0) {
            $lines[] = [
                'account' => $this->account(self::CODE_LATE_FEE_INCOME),
                'debit' => 0.0,
                'credit' => $penaltyPortion,
                'memo' => 'Late fees',
            ];
        }

        return $this->createEntry(
            entryDate: Carbon::parse($payment->payment_date),
            description: 'Fee receipt '.$payment->receipt_number,
            referenceType: AccountingReferenceType::Payment,
            referenceId: $payment->id,
            lines: $lines,
            postedBy: $postedBy ?? $payment->addedBy,
        );
    }

    public function postPenaltyAccrual(FeePenalty $penalty, ?User $postedBy = null): ?AccountingJournalEntry
    {
        $this->ensureDefaultAccounts();

        if (AccountingJournalEntry::query()
            ->where('reference_type', AccountingReferenceType::FeePenalty)
            ->where('reference_id', $penalty->id)
            ->exists()) {
            return null;
        }

        $amount = round((float) $penalty->penalty_amount, 2);

        if ($amount <= 0) {
            return null;
        }

        return $this->createEntry(
            entryDate: Carbon::parse($penalty->penalty_date),
            description: 'Late fee accrued — '.$penalty->description,
            referenceType: AccountingReferenceType::FeePenalty,
            referenceId: $penalty->id,
            lines: [
                [
                    'account' => $this->account(self::CODE_FEES_RECEIVABLE),
                    'debit' => $amount,
                    'credit' => 0.0,
                    'memo' => 'Accrued late fee',
                ],
                [
                    'account' => $this->account(self::CODE_LATE_FEE_INCOME),
                    'debit' => 0.0,
                    'credit' => $amount,
                    'memo' => 'Late fee income',
                ],
            ],
            postedBy: $postedBy,
        );
    }

    /**
     * @return array{
     *     total_debits: float,
     *     total_credits: float,
     *     entry_count: int,
     *     accounts: Collection<int, array{code: string, name: string, type: string, debit: float, credit: float, balance: float}>
     * }
     */
    public function summary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $this->ensureDefaultAccounts();

        $query = AccountingJournalLine::query()
            ->join('accounting_journal_entries', 'accounting_journal_entries.id', '=', 'accounting_journal_lines.journal_entry_id')
            ->join('accounting_accounts', 'accounting_accounts.id', '=', 'accounting_journal_lines.account_id');

        if ($from) {
            $query->whereDate('accounting_journal_entries.entry_date', '>=', $from->toDateString());
        }

        if ($to) {
            $query->whereDate('accounting_journal_entries.entry_date', '<=', $to->toDateString());
        }

        $rows = $query
            ->selectRaw('accounting_accounts.code, accounting_accounts.name, accounting_accounts.type')
            ->selectRaw('SUM(accounting_journal_lines.debit) as total_debit')
            ->selectRaw('SUM(accounting_journal_lines.credit) as total_credit')
            ->groupBy('accounting_accounts.id', 'accounting_accounts.code', 'accounting_accounts.name', 'accounting_accounts.type')
            ->orderBy('accounting_accounts.code')
            ->get();

        $accounts = $rows->map(function ($row): array {
            $debit = round((float) $row->total_debit, 2);
            $credit = round((float) $row->total_credit, 2);
            $type = (string) $row->type;
            $balance = in_array($type, [AccountingAccountType::Asset->value, AccountingAccountType::Expense->value], true)
                ? round($debit - $credit, 2)
                : round($credit - $debit, 2);

            return [
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'type' => $type,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
            ];
        });

        $entryQuery = AccountingJournalEntry::query();

        if ($from) {
            $entryQuery->whereDate('entry_date', '>=', $from->toDateString());
        }

        if ($to) {
            $entryQuery->whereDate('entry_date', '<=', $to->toDateString());
        }

        $totals = AccountingJournalLine::query()
            ->whereIn('journal_entry_id', $entryQuery->select('id'))
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        return [
            'total_debits' => round((float) ($totals->total_debit ?? 0), 2),
            'total_credits' => round((float) ($totals->total_credit ?? 0), 2),
            'entry_count' => (int) $entryQuery->count(),
            'accounts' => $accounts,
        ];
    }

    /**
     * @return Collection<int, AccountingJournalEntry>
     */
    public function recentEntries(int $limit = 50, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $query = AccountingJournalEntry::query()
            ->with(['lines.account', 'postedBy'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if ($from) {
            $query->whereDate('entry_date', '>=', $from->toDateString());
        }

        if ($to) {
            $query->whereDate('entry_date', '<=', $to->toDateString());
        }

        return $query->limit($limit)->get();
    }

    /**
     * @param  list<array{account: AccountingAccount, debit: float, credit: float, memo?: string|null}>  $lines
     */
    protected function createEntry(
        Carbon $entryDate,
        string $description,
        AccountingReferenceType $referenceType,
        int $referenceId,
        array $lines,
        ?User $postedBy = null,
    ): AccountingJournalEntry {
        return DB::transaction(function () use ($entryDate, $description, $referenceType, $referenceId, $lines, $postedBy): AccountingJournalEntry {
            $entry = AccountingJournalEntry::query()->create([
                'entry_date' => $entryDate->toDateString(),
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'posted_by_user_id' => $postedBy?->id,
            ]);

            foreach ($lines as $line) {
                $debit = round((float) $line['debit'], 2);
                $credit = round((float) $line['credit'], 2);

                if ($debit <= 0 && $credit <= 0) {
                    continue;
                }

                AccountingJournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account']->id,
                    'debit' => $debit,
                    'credit' => $credit,
                    'memo' => $line['memo'] ?? null,
                ]);
            }

            return $entry->load('lines.account');
        });
    }

    protected function account(string $code): AccountingAccount
    {
        return AccountingAccount::query()->where('code', $code)->firstOrFail();
    }

    protected function accountForPaymentMode(PaymentMode $mode): AccountingAccount
    {
        return match ($mode) {
            PaymentMode::Cash => $this->account(self::CODE_CASH),
            PaymentMode::Online, PaymentMode::Upi => $this->account(self::CODE_BANK),
        };
    }
}
