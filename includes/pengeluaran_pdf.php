<?php

function expensePdfEscape($text) {
    $text = utf8_decode((string) $text);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function expensePdfText($x, $y, $text, $size = 8, $color = '0.18 0.12 0.08') {
    return "BT $color rg /F1 $size Tf $x $y Td (" . expensePdfEscape($text) . ") Tj ET\n";
}

function expensePdfTextWidth($text, $size) {
    return strlen((string) $text) * $size * 0.5;
}

function expensePdfCenter($left, $right, $y, $text, $size = 8, $color = '0.18 0.12 0.08') {
    $x = $left + (($right - $left - expensePdfTextWidth($text, $size)) / 2);
    return expensePdfText($x, $y, $text, $size, $color);
}

function expensePdfRight($right, $y, $text, $size = 8, $color = '0.18 0.12 0.08') {
    return expensePdfText($right - expensePdfTextWidth($text, $size), $y, $text, $size, $color);
}

function expensePdfLine($x1, $y1, $x2, $y2, $color = '0 0 0') {
    return "q $color RG 0.5 w $x1 $y1 m $x2 $y2 l S Q\n";
}

function expensePdfRect($x, $y, $width, $height, $color) {
    return "q $color rg $x $y $width $height re f Q\n";
}

function expensePdfLogoData() {
    $path = __DIR__ . '/../images/logo.png';
    if (!function_exists('imagecreatefrompng') || !is_file($path)) {
        return '';
    }

    $source = @imagecreatefrompng($path);
    if (!$source) {
        return '';
    }

    $canvas = imagecreatetruecolor(imagesx($source), imagesy($source));
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);
    imagealphablending($canvas, true);
    imagecopy($canvas, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));
    ob_start();
    imagejpeg($canvas, null, 92);
    $data = ob_get_clean();
    imagedestroy($source);
    imagedestroy($canvas);

    return $data ?: '';
}

function expensePdfTrim($text, $limit) {
    $text = trim(preg_replace('/\s+/', ' ', (string) $text));
    if ($text === '') {
        return '-';
    }
    return strlen($text) > $limit ? substr($text, 0, $limit - 3) . '...' : $text;
}

function expensePdfTableLines($top, $bottom) {
    $content = '';
    foreach ([30, 50, 140, 215, 275, 335, 425, 510, 582] as $x) {
        $content .= expensePdfLine($x, $top, $x, $bottom);
    }
    return $content;
}

function expensePdfTableHeader($y) {
    $columns = [
        [30, 50, 'No'], [50, 140, 'Bahan Baku'], [140, 215, 'Supplier'],
        [215, 275, 'Total Dibeli'], [275, 335, 'Total Satuan'],
        [335, 425, 'Harga / Pembelian'], [425, 510, 'Total Harga'], [510, 582, 'Tanggal'],
    ];
    $content = expensePdfRect(30, $y - 8, 552, 24, '0.37 0.23 0.16');
    $content .= expensePdfTableLines($y + 16, $y - 8);
    $content .= expensePdfLine(30, $y + 16, 582, $y + 16);
    $content .= expensePdfLine(30, $y - 8, 582, $y - 8);
    foreach ($columns as $column) {
        $content .= expensePdfCenter($column[0], $column[1], $y - 2, $column[2], 7, '1 1 1');
    }
    return $content;
}

function expensePdfTableRow($row, $number, $y) {
    $unit_labels = ['gram' => 'g', 'kg' => 'kg', 'ml' => 'ml', 'liter' => 'L', 'pcs' => 'pcs'];
    $jumlah = rtrim(rtrim(number_format((float) ($row['jumlah_masuk'] ?? 0), 2, '.', ''), '0'), '.');
    $satuan = strtolower((string) ($row['satuan'] ?? ''));
    $jumlah_label = $jumlah . ' ' . ($unit_labels[$satuan] ?? $satuan);
    $quantity = (float) ($row['jumlah_input'] ?: ($row['jumlah_masuk'] ?? 0));
    $purchase_unit = $row['satuan_input'] ?: ($row['satuan'] ?? '');
    $cells = [
        [30, 50, (string) $number],
        [50, 140, expensePdfTrim($row['nama_bahan'] ?? '-', 23)],
        [140, 215, expensePdfTrim($row['nama_supplier'] ?? '-', 19)],
        [215, 275, rtrim(rtrim(number_format($quantity, 2, ',', '.'), '0'), ',') . ' ' . $purchase_unit],
        [275, 335, trim($jumlah_label)],
        [335, 425, $quantity > 0 ? 'Rp' . rtrim(rtrim(number_format($row['nominal'] / $quantity, 2, ',', '.'), '0'), ',') . '/' . $purchase_unit : '-'],
        [425, 510, 'Rp' . rtrim(rtrim(number_format($row['nominal'] ?? 0, 2, ',', '.'), '0'), ',')],
        [510, 582, date('d/m/Y H:i', strtotime($row['tanggal']))],
    ];
    $content = '';
    foreach ($cells as $cell) {
        $content .= expensePdfCenter($cell[0], $cell[1], $y - 3, $cell[2], 6);
    }
    $bottom = $y - 11;
    $content .= expensePdfTableLines($y + 7, $bottom);
    return $content . expensePdfLine(30, $bottom, 582, $bottom);
}

function expensePdfSummary($top, $summary) {
    $value = 'Rp' . number_format($summary['total_pengeluaran'] ?? 0, 0, ',', '.');
    $content = expensePdfRect(350, $top - 28, 214, 28, '0.96 0.91 0.85');
    $content .= expensePdfText(362, $top - 18, 'Total Pengeluaran', 8);
    $content .= expensePdfRight(552, $top - 18, $value, 9);
    $content .= expensePdfLine(350, $top, 564, $top);
    $content .= expensePdfLine(350, $top - 28, 564, $top - 28);
    $content .= expensePdfLine(350, $top, 350, $top - 28);
    return $content . expensePdfLine(564, $top, 564, $top - 28);
}

function generatePengeluaranPdf($rows, $summary, $periode_label) {
    $pages = array_chunk($rows, 24);
    if (!$pages) {
        $pages = [[]];
    }
    $page_count = count($pages);
    $logo_data = expensePdfLogoData();
    $logo_object_number = $logo_data !== '' ? 4 + ($page_count * 2) : 0;
    $objects = [
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Type /Catalog /Pages 3 0 R >>',
    ];
    $kids = [];
    foreach ($pages as $index => $page_rows) {
        $kids[] = (4 + ($index * 2)) . ' 0 R';
    }
    $objects[] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $page_count . ' >>';

    foreach ($pages as $index => $page_rows) {
        $page_number = $index + 1;
        $page_object = 4 + ($index * 2);
        $content_object = $page_object + 1;
        $content = '';
        if ($logo_object_number > 0) {
            $content .= "q 120 0 0 33 245 754 cm /Logo Do Q\n";
        } else {
            $content .= expensePdfText(273, 763, 'POS OLBEEMI', 10, '0.54 0.35 0.23');
        }
        $content .= expensePdfText(205, 734, 'LAPORAN PENGELUARAN', 16);
        $content .= expensePdfRight(564, 760, 'Halaman ' . $page_number . ' / ' . $page_count, 8, '0.42 0.37 0.33');
        $content .= expensePdfLine(48, 724, 564, 724, '0.84 0.78 0.71');
        $content .= expensePdfText(48, 705, 'Periode: ' . $periode_label, 8, '0.42 0.37 0.33');
        $content .= expensePdfRight(564, 705, 'Dicetak: ' . date('d/m/Y H:i'), 8, '0.42 0.37 0.33');
        $content .= expensePdfTableHeader(670);

        $y = 655;
        if (!$page_rows) {
            $content .= expensePdfCenter(30, 582, 638, 'Belum ada data pengeluaran.', 9, '0.42 0.37 0.33');
        }
        foreach ($page_rows as $row_index => $row) {
            $global_number = ($index * 24) + $row_index + 1;
            $content .= expensePdfTableRow($row, $global_number, $y);
            $y -= 18;
        }
        if ($page_number === $page_count) {
            $content .= expensePdfSummary($y - 8, $summary);
        }
        $content .= expensePdfLine(48, 45, 564, 45, '0.84 0.78 0.71');
        $content .= expensePdfCenter(48, 564, 28, 'Olbeemi Coffee Shop - Laporan Pengeluaran', 7, '0.42 0.37 0.33');

        $image_resource = $logo_object_number > 0 ? " /XObject << /Logo $logo_object_number 0 R >>" : '';
        $objects[] = "<< /Type /Page /Parent 3 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 1 0 R >>$image_resource >> /Contents $content_object 0 R >>";
        $objects[] = '<< /Length ' . strlen($content) . ">>\nstream\n" . $content . 'endstream';
    }

    if ($logo_object_number > 0) {
        $objects[] = '<< /Type /XObject /Subtype /Image /Width 436 /Height 119 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($logo_data) . ">>\nstream\n" . $logo_data . "\nendstream";
    }

    $pdf = "%PDF-1.3\n";
    $offsets = [];
    foreach ($objects as $index => $body) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n$body\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n";
    $pdf .= sprintf("%010d 65535 f \n", 0);
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= 'trailer<< /Size ' . (count($objects) + 1) . " /Root 2 0 R >>\nstartxref\n$xref\n%%EOF";

    $filename = 'laporan_pengeluaran_' . date('Ymd_His') . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}
