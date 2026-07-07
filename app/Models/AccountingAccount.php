<?php

namespace App\Models;

use App\Enums\AccountingAccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingAccount extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'is_system',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountingAccountType::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(AccountingJournalLine::class, 'account_id');
    }
}
