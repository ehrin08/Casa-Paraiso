<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * One filtered list ready to download as CSV, Excel, PDF, or a print view.
 */
final class TabularExport
{
    public const TEXT = 'text';

    public const NUMBER = 'number';

    public const MONEY = 'money';

    public const DATETIME = 'datetime';

    public const DATE = 'date';

    /**
     * @param  string  $filename  Download name without the extension.
     * @param  array<string, string>  $columns  Column label => column type.
     * @param  Closure(mixed): array<int, mixed>  $row  Maps one record to one value per column.
     * @param  array<int, string>  $context  Filter summary lines shown on PDF and print documents.
     */
    public function __construct(
        public readonly string $filename,
        public readonly string $title,
        public readonly array $columns,
        public readonly Builder $query,
        public readonly Closure $row,
        public readonly array $context = [],
    ) {}
}
