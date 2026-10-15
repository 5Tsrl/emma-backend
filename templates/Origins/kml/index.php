<?php
//https://github.com/bmcbride/PHP-Database-KML/
# Create an array of strings to hold the lines of the KML file.
$kml   = array(
  '<?xml version="1.0" encoding="UTF-8"?>'
);
$kml[] = '<kml xmlns="http://earth.google.com/kml/2.1">';
$kml[] = '<Document>';
$kml[] = '<Style id="generic">';
$kml[] = '<IconStyle>';
$kml[] = '<scale>1.3</scale>';
$kml[] = '<Icon>';
$kml[] = '<href>http://maps.google.com/mapfiles/kml/shapes/man.png</href>';
$kml[] = '</Icon>';
$kml[] = '<hotSpot x="20" y="2" xunits="pixels" yunits="pixels"/>';
$kml[] = '</IconStyle>';
$kml[] = '<LineStyle>';
$kml[] = '<color>ff0000ff</color>';
$kml[] = '<width>2</width>';
$kml[] = '</LineStyle>';
$kml[] = '<PolyStyle>';
$kml[] = '<fill>0</fill>';
$kml[] = '</PolyStyle>';
$kml[] = '</Style>';

$columns = null;
# Loop through rows to build placemarks
foreach ($origins as $data) {
    //Estraggo i nomi delle colonne solo la prima volta
    if ($columns == null) {
        $columns = $data->getVisible();
    }

    $lat = $data['lat'] ?? null;
    $lon = $data['lon'] ?? null;
    if ((!is_numeric($lat) || !is_numeric($lon)) && !empty($data['geohash'])) {
        $decoded = \App\Geocoder\GeohashHelper::decode((string)$data['geohash']);
        if ($decoded !== null) {
            $lat = $decoded['lat'];
            $lon = $decoded['lon'];
        }
    }
    if (!is_numeric($lat) || !is_numeric($lon)) {
        continue;
    }

    # Remove kml and geometry fields from data
    $kml[] = '<Placemark id="placemark' . $data['gid'] . '">';
    $kml[] = '<name>' . htmlentities($data['gid']) . '</name>';

    $kml[] = '<ExtendedData>';
    # Build extended data from fields

    foreach ($columns as $c) {
        $kml[] = '<Data name="' . $c . '">';
        $kml[] = '<value><![CDATA[' . $data[$c] . ']]></value>';
        $kml[] = '</Data>';
    }
    $kml[] = '</ExtendedData>';

    $kml[] = '<styleUrl>#generic</styleUrl>';
    $kml[] = '<Point>';
    $kml[] = "<coordinates>{$lon},{$lat}</coordinates>";
    $kml[] = '</Point>';
    $kml[] = '</Placemark>';
}

$kml[]     = '</Document>';
$kml[]     = '</kml>';
$kmlOutput = join("\n", $kml);

header('Content-Type: application/vnd.google-earth.kml+xml kml');
$date = new DateTime();
$r = $date->format('Y-m-d');
$filename = "$r-origini.kml";
header('Content-Disposition: attachment; filename="' . $filename . '"');
//header ("Content-Type:text/xml");  // For debugging
echo $kmlOutput;
return;
