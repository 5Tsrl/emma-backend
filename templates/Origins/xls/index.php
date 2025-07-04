<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$date = new DateTime();
$r = $date->format('Y-m-d');
$filename = "$r-origini.xls";
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
foreach ($origins as $p) {
    if ($row == 1) {
        //https://api.cakephp.org/4.0/trait-Cake.Datasource.EntityTrait.html#getVisible
        //Restituisce l'elenco dei campi visibili della query
        $columns = $p->getVisible();
        foreach ($columns as $c) {
            $sheet->setCellValue("$col$row", $c);
            $col++;
        }
    }
    $row++;
    $col = 'A';
    foreach ($columns as $c) {
        //Se il nome contiene un punto devo generare un'array
        $ex = explode('.', $c);
        if (isset($ex[2])) {
            $value = $p[$ex[0]][$ex[1]][$ex[2]];
        } elseif (isset($ex[1])) {
            $value = $p[$ex[0]][$ex[1]];
        } else {
            $value = $p[$c];
        }

        $sheet->setCellValue("$col$row", $value);

        $col++;
    }
}

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

// Return response object to prevent controller from trying to render
// a view.
return;
