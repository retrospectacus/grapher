<?php
if ($_GET['d']) {
  $d = intval($_GET['d']);
}
if (!$d || $d < 0) {
  $d = 1;
}
$files = `find /home/adama/retro-house/ -type f -name '*Enviroboard.csv' -mtime -$d -printf "%T@ %p\n" 2>/dev/null | sort -n `;

$files = explode("\n", $files);

$data = Array();
$mapping = Array(
  "T1~Unknown" => "Zero",
  "T2~Unknown" => "Crawlspace",
  "T3~Unknown" => "Outside E",
  "T4~Unknown" => "Outside W",
  "T5~Unknown" => "Living Room",
  "T6~Unknown" => "Sump",
  "T7~Unknown" => "Bedroom",
  "T8~Unknown" => "Garage",
);
function m($s) {
  global $mapping;
  if (array_key_exists($s, $mapping)) {
    return $mapping[$s];
  }
  else {
    return $s;
  }
}

ini_set('memory_limit', '2G');

foreach($files as $filestr) {
  $filename = substr($filestr, strpos($filestr, " "));
  if (trim($filename) && ($handle = fopen(trim($filename), "r")) !== FALSE) {
    $headers = Array();
    for ($row = 1; ($line = fgetcsv($handle)) !== FALSE; $row++) {
      $num = count($line);
      if ($row == 1) {
        $headers = array_map('trim', $line);
      }
      else {
        for ($c=0; $c < $num; $c++) {
          if ($headers[$c]=='datetime' || array_key_exists($headers[$c], $mapping)) {
            $value = trim($line[$c]);
            $data[m($headers[$c])][] = ($value=="NaN"?0:$value);
          }
        }
      }
    }
    fclose($handle);
  }
  else {
   // echo "Could not open $filename ($filestr)<br>";
  }
}

// echo "<pre>";
// print_r(array_keys($data));
// print_r($data);
// echo "</pre>";

include('header.html');
$title = "$d Day Plot";
echo "[";
$first = true;
foreach($mapping as $label) {
  if (!$first) {
    echo ",";
  }
  else {
    $first = false;
  }
  echo '{"mode":"lines","name":"'.$label.'","type":"scatter",' .
    '"x":["'.implode('","',$data['datetime']).'"],' .
    '"y":['.implode(',',$data[$label]).']}';
}
echo "],";
include('template_line.php');
include('footer.html');
?>
