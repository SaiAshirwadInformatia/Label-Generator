<?php

namespace App\Services;

use App\Models\Set;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use PDF;
use Spatie\Browsershot\Browsershot;

class PDFGeneratorService
{
    private int $recordCount = 0;
    private bool $preview = false;
    private int $previewLimit = 100;
    private bool $html = false;

    public function count()
    {
        return $this->recordCount;
    }

    public function html(): PDFGeneratorService
    {
        $this->html = true;

        return $this;
    }

    public function preview(): PDFGeneratorService
    {
        $this->preview = true;

        return $this;
    }

    public function limit(int $limit): PDFGeneratorService
    {
        $this->previewLimit = $limit;

        return $this;
    }

    public function readExcel(Set $set): array
    {
        $label = $set->label;
        $columns = [];
        $records = [];

        $path = Storage::disk('public')->path($label->path);

        $reader = ReaderEntityFactory::createReaderFromFile($path);
        $reader->open($path);

        $previewRecords = 0;
        $onlyOneSheet = 1;
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                // do stuff with the row
                $cells = $row->getCells();
                $record = [];
                foreach ($cells as $cell) {
                    $record[] = $cell->getValue();
                }
                if (count($columns) == 0) {
                    // this is just to skip this row
                    $columns = $record;
                } else {
                    $records[] = $record;
                    $previewRecords++;
                }
                if ($this->preview && $previewRecords >= $this->previewLimit) {
                    break;
                }
            }
            break;
        }
        $reader->close();
        $reader = null;

        return $records;
    }

    public function prepareTables(Set $set, &$records): array
    {
        $incremental = 1;

        $tables = [];

        if (!isset($set->settings['differentPage']) or empty($set->settings['differentPage'])) {
            $data = [];

            foreach ($records as $record) {
                $row = [];
                $emptyRows = 0;
                foreach ($set->fields as $field) {
                    $row[$field->name] = match ($field->type) {
                        'Text' => $record[$field->name] ?? "",
                        'Static' => $field->default,
                        'Incremented' => $incremental++,
                        'Number' => intval($record[$field->name]),
                        'Float' => floatval($record[$field->name]),
                        'Boolean' => boolval($record[$field->name]) ? 'Yes' : 'No',
                        'dd/MM/YYYY' => Carbon::parse($record[$field->name])->format('d/m/Y'),
                        'INR' => 'Rs. ' . $record[$field->name],
                        default => ""
                    };
                    if ($field->type == 'EmptyRow') {
                        $emptyRows++;
                    }
                }

                $emptyCount = 1;
                foreach ($row as $v) {
                    if (empty(trim($v))) {
                        $emptyCount++;
                    }
                }
                if ($emptyCount >= $emptyRows + 3) {
                    continue;
                }
                $data[] = $row;
            }
            $this->recordCount += count($data);
            $tables['General'] = $data;

            return $tables;
        }
        $tableRows = collect($records)->groupBy($set->settings['differentPage']);
        $records = null;

        if ($set->type == Set::GROUPED) {
            foreach ($tableRows as $stateName => $records) {
                $records = $records->groupBy($set->columnName);
                $data = [];
                foreach ($records as $record) {
                    $subCount = count($record);
                    $record = $record->first();
                    $row = [];
                    $emptyRows = 0;
                    foreach ($set->fields as $field) {
                        $row[$field->name] = match ($field->type) {
                            'Text' => $record[$field->name] ?? "",
                            'Static' => $field->default,
                            'SubCount' => $subCount,
                            'Incremented' => $incremental++,
                            'Number' => intval($record[$field->name]),
                            'Float' => floatval($record[$field->name]),
                            'Boolean' => boolval($record[$field->name]) ? 'Yes' : 'No',
                            'dd/MM/YYYY' => Carbon::parse($record[$field->name])->format('d/m/Y'),
                            'INR' => 'Rs. ' . $record[$field->name],
                            default => ""
                        };
                        if ($field->type == 'EmptyRow') {
                            $emptyRows++;
                        }

                    }
                    $emptyCount = 1;
                    foreach ($row as $v) {
                        if (empty(trim($v))) {
                            $emptyCount++;
                        }
                    }
                    if ($emptyCount >= $emptyRows + 3) {
                        continue;
                    }
                    $data[] = $row;
                }
                $this->recordCount += count($data);
                $tables[$stateName] = $data;
            }
        } else {
            foreach ($tableRows as $stateName => $records) {
                $data = [];
                foreach ($records as $record) {
                    $row = [];
                    $emptyRows = 0;
                    foreach ($set->fields as $field) {
                        $row[$field->name] = match ($field->type) {
                            'Text' => $record[$field->name] ?? "",
                            'Static' => $field->default,
                            'Incremented' => $incremental++,
                            'Number' => intval($record[$field->name]),
                            'Float' => floatval($record[$field->name]),
                            'Boolean' => boolval($record[$field->name]) ? 'Yes' : 'No',
                            'dd/MM/YYYY' => Carbon::parse($record[$field->name])->format('d/m/Y'),
                            'INR' => 'Rs. ' . $record[$field->name],
                            default => ""
                        };

                        if ($field->type == 'EmptyRow') {
                            $emptyRows++;
                        }
                    }
                    $emptyCount = 1;
                    foreach ($row as $v) {
                        if (empty(trim($v))) {
                            $emptyCount++;
                        }
                    }
                    if ($emptyCount >= $emptyRows + 3) {
                        continue;
                    }
                    $data[] = $row;
                }
                $this->recordCount += count($data);
                $tables[$stateName] = $data;
            }
        }
        return $tables;
    }

    public function process(Set $set): \Barryvdh\DomPDF\PDF|Browsershot|string
    {
        $label = $set->label;
        $records = $this->readExcel($set);

        $tables = $this->prepareTables($set, $records);

        if ($this->html) {
            return view('pdf.table', compact('set', 'tables'));
        }

//        $browserShot = Browsershot::html(view('pdf.table', compact('set', 'tables'))->render())
//            ->format($label->settings['size']);
//
//        if ($label->settings['orientation'] == 'landscape') {
//            $browserShot->landscape();
//        }
//
//        return $browserShot;

        return PDF::loadView('pdf.table', compact('set', 'tables'))
            ->setPaper($label->settings['size'], $label->settings['orientation']);
    }
}
