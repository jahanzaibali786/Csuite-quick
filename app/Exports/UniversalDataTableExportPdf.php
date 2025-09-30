<?php

namespace App\Exports;

use Dompdf\Dompdf;
use Dompdf\Options;

class UniversalDataTableExportPdf
{
    protected $collection;
    protected $columns;
    protected $pageTitle;
    protected $reportPeriod;
    protected $headerFooterAlignment;

    public function __construct($collection, array $columns = [], string $pageTitle = 'Report', string $reportPeriod = '', $headerFooterAlignment = [])
    {
        $this->collection = $collection;
        $this->columns = $columns;
        $this->pageTitle = $pageTitle;
        $this->reportPeriod = $reportPeriod;

        if (is_array($headerFooterAlignment) && count($headerFooterAlignment) === 2) {
            $this->headerFooterAlignment = $headerFooterAlignment;
        } else {
            $this->headerFooterAlignment = ['center', 'center'];
        }
    }

    public function download(string $filename)
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans'); // good Unicode font support
        $dompdf = new Dompdf($options);

        $html = '<html><head><style>
            body { font-family: DejaVu Sans, sans-serif; font-size:12px; }
            h1 { text-align: ' . $this->headerFooterAlignment[0] . '; font-size:24px; margin-bottom:5px; }
            h2 { text-align: ' . $this->headerFooterAlignment[0] . '; font-size:14px; margin-top:0; color:#555; }
            table { width:100%; border-collapse:collapse; margin-top:15px; }
            th, td { border:1px solid #ccc; padding:6px 8px; }
            th { background:#f1f1f1; font-weight:bold; text-align:center; }
            tr.bold td { font-weight:bold; }
            .footer { margin-top:30px; text-align: ' . $this->headerFooterAlignment[1] . '; font-size:11px; color:#6B7280; }
        </style></head><body>';

        // Title + period
        $html .= "<h1>{$this->pageTitle}</h1>";
        if (!empty($this->reportPeriod)) {
            $html .= "<h2>{$this->reportPeriod}</h2>";
        }

        // Table
        $html .= '<table><thead><tr>';
        foreach ($this->columns as $col) {
            $html .= "<th>{$col}</th>";
        }
        $html .= '</tr></thead><tbody>';

        foreach ($this->collection as $row) {
            $isBold = false;
            $rowHtml = '';

            foreach ($row as $cell) {
                $val = trim($cell);
                if (stripos($val, 'subtotal') !== false || stripos($val, 'total') !== false) {
                    $isBold = true;
                }
                $rowHtml .= '<td>' . htmlspecialchars($val) . '</td>';
            }

            $html .= $isBold ? "<tr class='bold'>{$rowHtml}</tr>" : "<tr>{$rowHtml}</tr>";
        }

        $html .= '</tbody></table>';

        // Footer
        $html .= '<div class="footer">'
            . 'Date Prepared: ' . now()->format('d/m/Y') . '<br>'
            . 'Time Prepared: ' . now()->format('H:i:s') . '<br>'
            . 'Report Basis: Cash Basis'
            . '</div>';

        $html .= '</body></html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->stream($filename);
    }
}
