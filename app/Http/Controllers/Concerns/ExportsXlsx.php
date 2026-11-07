<?php

namespace App\Http\Controllers\Concerns;

use App\Services\DocumentArchive;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

trait ExportsXlsx
{
    /**
     * File Excel (.xlsx) asli. CSV dihindari karena Excel dengan setelan regional Indonesia
     * memakai ";" sebagai pemisah, sehingga semua kolom CSV menumpuk di kolom A.
     * Salinannya ikut kesimpan di modul Documentation (kategori Laporan).
     *
     * @param  array<string, float>  $columns  judul kolom => lebar kolom
     */
    protected function downloadXlsx(string $filename, string $sheetName, array $columns, callable $rows)
    {
        $path = tempnam(sys_get_temp_dir(), 'mjg-xlsx');
        $writer = new XlsxWriter();
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName($sheetName);
        $sheet->setSheetView(new SheetView(freezeRow: 2));
        foreach (array_values($columns) as $i => $width) {
            $sheet->setColumnWidth($width, $i + 1);
        }

        $header = new Style(fontBold: true, fontColor: Color::WHITE, backgroundColor: '0066A0');
        $writer->addRow(Row::fromValuesWithStyle(array_keys($columns), $header));

        $dateStyle = new Style(format: 'dd/mm/yyyy');
        $rows(function (array $values) use ($writer, $dateStyle) {
            $styles = array_map(fn ($v) => $v instanceof \DateTimeInterface ? $dateStyle : null, $values);
            $writer->addRow(Row::fromValuesWithStyles($values, array_filter($styles)));
        });

        $sheet->setAutoFilter(new AutoFilter(0, 1, count($columns) - 1, max($writer->getWrittenRowCount(), 1)));
        $writer->close();

        DocumentArchive::storeFile($path, $filename, null, 'Laporan');

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
