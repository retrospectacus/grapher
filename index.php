<?php
$d = 1;
if (array_key_exists('d', $_GET)) {
  $d = intval($_GET['d']);
}
if (!$d || $d < 1) {
  $d = 1;
}
$denominator = floor($d / 30) + 1;
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
  return $mapping[$s];
}

ini_set('memory_limit', '2G');

foreach($files as $filestr) {
  $filename = trim(substr($filestr, strpos($filestr, ' ')));
  if ($filename && ($handle = fopen($filename, 'r')) !== FALSE) {
    $headers = Array();
    for ($row = 1; ($line0 = fgetcsv($handle)) !== FALSE; $row++) {
      $line = $line0;
      $num = count($line);
      if ($row == 1) {
        $headers = array_map('trim', $line);
      }
      else if ($row%$denominator==0) {
        for ($c=0; $c < $num; $c++) {
          if ($headers[$c]=='datetime') {
            $datetime = trim($line[$c]);
          }
          else if (array_key_exists($headers[$c], $mapping)) {
            $value = trim($line[$c]);
            $data[m($headers[$c])][$datetime] = ($value=='NaN'?0:$value);
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
echo "
<small style='position:absolute;z-index:1'>
<form>
 Graph <input name='d' value='$d' size='3'/> days <input type='submit' value='Go'/>
</form>";

if ($denominator > 1) {
  echo "<span style='color:red'>Warning: Large data set requested. Resolution has been reduced to $denominator-minute intervals.</span><br>";
}

echo "
Current Readings at $datetime<br/>";
foreach ($headers as $i => $key) {
  if (array_key_exists($key, $mapping) && m($key) !== 'Zero') {
    echo ' | '.m($key).' = '.substr($line[$i],0,strpos($line[$i], '.')+3) . '°C';
  }
}

echo "</small>";

include('plot_lib.html');
// $title = "$d Day Plot";
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
    '"x":["'.implode('","',array_keys($data[$label])).'"],' .
    '"y":['.implode(',',$data[$label]).']}';
}
echo "],";
include('template_line.html');
include('footer.html');
?>
