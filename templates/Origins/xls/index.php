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
$columns = [];

// Define the column headers we want to display
$headers = [
    'id' => 'ID',    
    'postal_code' => 'Postal Code',
    'city' => 'City',
    'province' => 'Province',
    'district' => 'District',
    'geohash' => 'Geohash',
    'lat' => 'Approx Latitude',
    'lon' => 'Approx Longitude',    
    'modified' => 'Modified',
    'user_id' => 'User ID',
    'company_id' => 'Company ID',
    'company.name' => 'Company Name',
    'company.id' => 'Company DB ID',
    'company.company_code' => 'Company Code',
    'company.city' => 'Company City',
    'company.province' => 'Company Province',
    'company.type' => 'Company Type',
    'user.email' => 'User Email',
    'user.id' => 'User DB ID',
    'employee.user_id' => 'Employee User ID',
    'employee.gender' => 'Gender',
    'employee.dob' => 'Date of Birth',
    'employee.shift' => 'Shift',
    'employee.office_id' => 'Office ID',
    'employee.role_description' => 'Role Description'
];

foreach ($origins as $p) {
    if ($row == 1) {
        // Write headers
        foreach ($headers as $key => $header) {
            $sheet->setCellValue("$col$row", $header);
            $col++;
        }
        $columns = array_keys($headers);
    }
    $row++;
    $col = 'A';
    
    foreach ($columns as $columnKey) {
        $value = '';
        
        // First, try direct key access (for flattened keys like 'company.name')
        if (isset($p[$columnKey])) {
            $value = $p[$columnKey];
        }
        // If not found and key contains dot, try nested access
        elseif (strpos($columnKey, '.') !== false) {
            $parts = explode('.', $columnKey);
            $tempValue = $p;
            
            foreach ($parts as $part) {
                if (is_array($tempValue) && isset($tempValue[$part])) {
                    $tempValue = $tempValue[$part];
                } else {
                    $tempValue = '';
                    break;
                }
            }
            $value = $tempValue;
        }

        // Convert objects to string representation
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                $value = (string) $value;
            } else {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }
        
        // Ensure the value is a scalar for Excel
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        
        // Convert null to empty string
        if (is_null($value)) {
            $value = '';
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
