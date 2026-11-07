<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;

class PdfRenderService
{
    /** @param Model|null $archiveFor dokumen sumber (SO/WO/DO/PO): kalau diisi, PDF-nya ikut kesimpan di modul Documentation. */
    public function render(string $view, array $data, string $filename, bool $download = false, ?Model $archiveFor = null): Response
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        // share() dipakai (bukan taruh di $data) karena pdf.sales-order/work-order/delivery-order/purchase-order
        // nge-wrap pakai @component('pdf.layout', [...]) yang bikin scope sendiri — gak otomatis kebawa dari $data.
        view()->share('logoBase64', $this->logoDataUri());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $data)->render());
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        $output = $dompdf->output();
        if ($archiveFor) {
            DocumentArchive::store($output, $filename, $archiveFor);
        }

        $disposition = $download ? 'attachment' : 'inline';

        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }

    /** Logo di-embed base64 sekali di sini (bukan di tiap view) biar gampang di-debug & gak bikin halaman gagal total kalau filenya gak kebaca. */
    private function logoDataUri(): string
    {
        $path = public_path('images/logo-mjg.png');

        if (! is_file($path)) {
            return '';
        }

        $data = @file_get_contents($path);

        return $data ? 'data:image/png;base64,' . base64_encode($data) : '';
    }
}
