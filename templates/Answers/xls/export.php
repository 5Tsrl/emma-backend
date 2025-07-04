<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$date = new DateTime();
$r = $date->format('Y-m-d');
$filename = "$r-risultati-questionario.xls";
header("Content-Type: application/force-download");
header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");;
header("Content-Disposition: attachment;filename=$filename");
header("Content-Transfer-Encoding: binary ");

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$row = '1';
$col = 'A';

if (empty($answers)) {
    return;
}

//Scrive il titolo
$columns = $questions;
foreach ($columns as $c => $val) {
    $sheet->setCellValue("$col$row", $val);
    $col++;
}


//Scrive le righe successive
foreach ($answers as $s) {
    $row++;
    $col = 'A';
    foreach ($columns as $c => $val) {
        if (isset($s[$c]) && $s[$c] != '"null"' && $s[$c] != '"undefined"') {
            $value = trim($s[$c], "\"");
        } else {
            $value = 'N/D';
        }

        if (!is_array($value)) {
            $sheet->setCellValue("$col$row", $value);
        }

        $col++;
    }
}

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

// Return response object to prevent controller from trying to render
// a view.
return;
