<?php

namespace App\Services;

use App\Support\TabularExport;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TabularExporter
{
    public const FORMATS = ['csv', 'xlsx', 'pdf', 'print'];

    public function format(Request $request): string
    {
        return $request->validate(['format' => ['nullable', Rule::in(self::FORMATS)]])['format'] ?? 'csv';
    }

    public function download(TabularExport $export, string $format): Response|StreamedResponse
    {
        return match ($format) {
            'xlsx' => $this->xlsx($export),
            'pdf' => $this->pdf($export),
            'print' => response($this->document($export, print: true)),
            default => $this->csv($export),
        };
    }

    private function csv(TabularExport $export): StreamedResponse
    {
        $types = array_values($export->columns);

        return response()->streamDownload(function () use ($export, $types): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, array_keys($export->columns));

            foreach ($export->query->lazy(500) as $record) {
                $values = ($export->row)($record);

                fputcsv($handle, array_map(fn ($value, $type) => $this->csvValue($value, $type), $values, $types));
            }

            fclose($handle);
        }, $this->filename($export, 'csv'), ['Content-Type' => 'text/csv']);
    }

    private function csvValue(mixed $value, string $type): string|int|float|null
    {
        if ($value instanceof DateTimeInterface) {
            return $this->local($value)->format($type === TabularExport::DATE ? 'Y-m-d' : 'Y-m-d H:i:s');
        }

        return $type === TabularExport::TEXT ? $this->formulaSafe($value) : $value;
    }

    private function formulaSafe(string|int|float|null $value): string|int|float|null
    {
        return is_string($value) && preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }

    private function xlsx(TabularExport $export): StreamedResponse
    {
        $types = array_values($export->columns);
        $styles = array_map(fn (string $type) => match ($type) {
            TabularExport::MONEY => (new Style)->setFormat('#,##0.00'),
            TabularExport::DATETIME => (new Style)->setFormat('yyyy-mm-dd hh:mm'),
            TabularExport::DATE => (new Style)->setFormat('yyyy-mm-dd'),
            default => null,
        }, $types);

        return response()->streamDownload(function () use ($export, $types, $styles): void {
            $options = new XlsxOptions;
            foreach ($types as $index => $type) {
                $options->setColumnWidth($type === TabularExport::TEXT ? 24 : 16, $index + 1);
            }

            $writer = new XlsxWriter($options);
            $writer->openToFile('php://output');
            $writer->getCurrentSheet()->setName(mb_substr($export->title, 0, 31));
            $writer->addRow(Row::fromValues(array_keys($export->columns), (new Style)->setFontBold()));

            foreach ($export->query->lazy(500) as $record) {
                $values = ($export->row)($record);
                $cells = [];

                foreach ($types as $index => $type) {
                    $cells[] = $this->xlsxCell($values[$index] ?? null, $type, $styles[$index]);
                }

                $writer->addRow(new Row($cells));
            }

            $writer->close();
        }, $this->filename($export, 'xlsx'), ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function xlsxCell(mixed $value, string $type, ?Style $style): Cell
    {
        if ($value === null || $value === '') {
            return Cell::fromValue(null, $style);
        }

        if ($value instanceof DateTimeInterface) {
            return new Cell\DateTimeCell($this->local($value), $style);
        }

        if (in_array($type, [TabularExport::NUMBER, TabularExport::MONEY], true) && is_numeric($value)) {
            return new Cell\NumericCell($value + 0, $style);
        }

        // Text is always a plain string cell so values such as "=SUM()" never become formulas.
        return new Cell\StringCell((string) $value, $style);
    }

    private function pdf(TabularExport $export): Response
    {
        $fonts = storage_path('fonts');
        File::ensureDirectoryExists($fonts);

        $options = (new DompdfOptions)
            ->setDefaultFont('DejaVu Sans')
            ->setIsRemoteEnabled(false)
            ->setFontDir($fonts)
            ->setFontCache($fonts);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->document($export, print: false));
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->getCanvas()->page_text(760, 570, 'Page {PAGE_NUM} of {PAGE_COUNT}', 'DejaVu Sans', 7, [0.40, 0.40, 0.37]);

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($export, 'pdf').'"',
        ]);
    }

    private function document(TabularExport $export, bool $print): string
    {
        $limit = (int) config('casa.exports.document_row_limit', 1000);
        $total = (clone $export->query)->toBase()->getCountForPagination();
        $types = array_values($export->columns);
        $rows = (clone $export->query)->limit($limit)->get()->map(function ($record) use ($export, $types): array {
            $values = ($export->row)($record);

            return array_map(fn ($value, $type) => $this->display($value, $type), $values, $types);
        });

        return view('exports.document', [
            'export' => $export,
            'types' => $types,
            'rows' => $rows,
            'total' => $total,
            'limit' => $limit,
            'print' => $print,
            'generatedAt' => now(config('casa.business_hours.timezone', 'Asia/Manila')),
        ])->render();
    }

    private function display(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return $this->local($value)->format($type === TabularExport::DATE ? 'M j, Y' : 'M j, Y g:i A');
        }

        if ($type === TabularExport::MONEY && is_numeric($value)) {
            return ((float) $value < 0 ? '-' : '').'₱'.number_format(abs((float) $value), 2);
        }

        if ($type === TabularExport::NUMBER && is_numeric($value)) {
            return number_format((float) $value, fmod((float) $value, 1.0) === 0.0 ? 0 : 2);
        }

        return (string) $value;
    }

    private function local(DateTimeInterface $value): CarbonImmutable
    {
        return CarbonImmutable::instance($value)->setTimezone(config('casa.business_hours.timezone', 'Asia/Manila'));
    }

    private function filename(TabularExport $export, string $extension): string
    {
        return $export->filename.'-'.now(config('casa.business_hours.timezone', 'Asia/Manila'))->format('Ymd-His').'.'.$extension;
    }
}
