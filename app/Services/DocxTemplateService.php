<?php

namespace App\Services;

use ZipArchive;

/**
 * Isi template .docx (placeholder {{field}} & baris berulang {{item.x}}) tanpa
 * perlu library tambahan — cukup ZipArchive bawaan PHP, modif word/document.xml.
 *
 * Cara bikin template baru: di Word, ganti teks yang mau diisi otomatis jadi
 * {{nama_field}}. Untuk baris tabel yang jumlahnya dinamis (misal daftar barang),
 * bungkus placeholder baris PERTAMA dengan ##ROWSTART## di depan placeholder sel
 * pertama dan ##ROWEND## di belakang placeholder sel terakhir baris itu, lalu
 * hapus baris contoh lainnya. Baris itu akan digandakan sesuai jumlah data.
 */
class DocxTemplateService
{
    /** @param array<string,string> $values field => isi. @param array<int,array<string,string>> $rows baris tabel (key tanpa prefix "item."). */
    public function render(string $templatePath, array $values, array $rows = []): string
    {
        $outPath = tempnam(sys_get_temp_dir(), 'docx_');
        copy($templatePath, $outPath);

        $zip = new ZipArchive();
        $zip->open($outPath);
        $xml = $zip->getFromName('word/document.xml');

        $xml = $this->expandRows($xml, $rows);

        foreach ($values as $key => $value) {
            $escaped = $this->escape((string) $value);
            $xml = str_replace('{{' . $key . '}}', $escaped, $xml);
        }

        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        return $outPath;
    }

    private function expandRows(string $xml, array $rows): string
    {
        $start = strpos($xml, '##ROWSTART##');
        if ($start === false) {
            return $xml;
        }

        $end = strpos($xml, '##ROWEND##', $start) + strlen('##ROWEND##');
        $trOpen = strrpos(substr($xml, 0, $start), '<w:tr');
        $trClose = strpos($xml, '</w:tr>', $end) + strlen('</w:tr>');
        $rowTemplate = substr($xml, $trOpen, $trClose - $trOpen);

        $cloned = '';
        foreach ($rows as $row) {
            $r = str_replace(['##ROWSTART##', '##ROWEND##'], '', $rowTemplate);
            foreach ($row as $key => $value) {
                $r = str_replace('{{item.' . $key . '}}', $this->escape((string) $value), $r);
            }
            $cloned .= $r;
        }

        return substr($xml, 0, $trOpen) . $cloned . substr($xml, $trClose);
    }

    private function escape(string $value): string
    {
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');

        // dukung newline jadi line break beneran di Word
        return str_replace("\n", '</w:t><w:br/><w:t xml:space="preserve">', $value);
    }
}
