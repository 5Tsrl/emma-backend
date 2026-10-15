<?php


$date = new DateTime();
$r = $date->format('Y-m-d');
$filename = "$r-offices-export.csv";

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment;filename=$filename");
header("Pragma: no-cache");
header("Expires: 0");

if (empty($rows) || empty($headers)) {
	return;
}

// Open output stream
$output = fopen('php://output', 'w');

// Write UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write headers
fputcsv($output, $headers, ';');

// Write data rows
foreach ($rows as $row) {
	fputcsv($output, $row, ';');
}

fclose($output);
