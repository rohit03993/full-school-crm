<?php

namespace App\Support;

final class CrmPagination
{
    public const PER_PAGE = 20;

    /**
     * @return list<int>
     */
    public static function perPageOptions(): array
    {
        return [10, self::PER_PAGE];
    }
}
