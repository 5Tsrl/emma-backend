<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$date = new DateTime();
$r = $date->format('Y-m-d');
$filename = "$r-offices-export.xls";
header("Content-Type: application/force-download");
header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");
header("Content-Disposition: attachment;filename=$filename");
header("Content-Transfer-Encoding: binary ");

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();


$row = 1;
$col = 'A';

if (empty($rows) || empty($headers)) {
	return;
}

// Write headers
foreach ($headers as $header) {
	$sheet->setCellValue("$col$row", $header);
	$col++;
}

// Write data rows
foreach ($rows as $dataRow) {
	$row++;
	$col = 'A';
	foreach ($dataRow as $value) {
		$sheet->setCellValue("{$col}{$row}", $value);
		$col++;
	}
}

set_time_limit(600); // 10 minutes timeout
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '600');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
//$writer->save(TMP . "offices_export_$r.xlsx");

// Return response object to prevent controller from trying to render a view.
return;
