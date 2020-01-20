<?php
$debug = 0;
$denominator = 1;
$denominator_cutoff = 10; // # of days to graph before scaling down
$current = "Current";

function files_from_find_command($args) {
  global $debug;
  if ($debug) {
    error_log(">>> find /home/adama/retro-house/ -type f -name '*Enviroboard.csv' ${args} -printf \"%T@ %p\n\" 2>/dev/null | sort -n</pre>");
  }
  return `find /home/adama/retro-house/ -type f -name '*Enviroboard.csv' ${args} -printf "%T@ %p\n" 2>/dev/null | sort -n `;
}

function days_ago($days) {
  return "-mtime -${days}";
}

function hours_ago($hours) {
  $mins = $hours * 60;
  return "-mmin -${mins}";
}

function date_range($fromU, $toU = false) {  // Unix
  if (!$toU) {
    $toU = $fromU + 86400;
  }
  $from = strftime('%F', $fromU);
  $to = strftime('%F', $toU);
  return "-newermt ${from} ! -newermt ${to}";
}

if (array_key_exists('d', $_GET)) {
  $d = $_GET['d'];
  $dn = 0;
  if (is_numeric($d)) {
    $dn = intval($_GET['d']);
  }
  $dat = strtotime($_GET['d']);
  
  if (substr($d, -1) == 'h' && is_numeric(substr($d, 0, -1))) {
    $hn = intval(substr($d, 0, -1));
    if ($hn >= 1) {
      $files = files_from_find_command(hours_ago($hn));
      $denominator = floor($hn / 24 / $denominator_cutoff) + 1;
    }
    else {
      $d = 1;
      $files = files_from_find_command(days_ago(1));
    }
  }
  else if ($dn >= 1) {
    $files = files_from_find_command(days_ago($dn));
    $denominator = floor($dn / $denominator_cutoff) + 1;
  }
  else if ($dat) {
    $files = files_from_find_command(date_range($dat));
    $current = "";
  }
  else {
    $d = 1;
    $files = files_from_find_command(days_ago(1));
  }
}
else {
  $d = 1;
  $files = files_from_find_command(days_ago(1));
// $title = "1 Day Plot";
}

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

function adjust($old) {
  return  $old * 1.04 - 1.16;
  $beta = 3974;
  $a = 0.00128583761237175;
  $b = 0.000236038201072225;
  $c = 0.0000000934658922353101;
  $rp = 5000 * exp(-$beta/298.15);
  $r = $rp * exp($beta/($old+273.15));
  return $a + $b * log($r) + $c * pow(log($r),3);
}

ini_set('memory_limit', '2G');
$cutoff = '20200115-19:23:00';
$adjust = 1;

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
            if ($adjust && (strcmp($datetime, $cutoff) > -1)) {
              $adjust = 0;
            }
          }
          else if (array_key_exists($headers[$c], $mapping)) {
            $value = trim($line[$c]);
            if ($adjust && $value != 'NaN') {
              $value = adjust($value);
            }
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
 Graph <input name='d' value='$d' size='5'/> <input type='submit' value='Go'/>
</form>";

if ($denominator > 1) {
  echo "<span style='color:red'>
    Warning: Large data set requested. 
    Resolution has been reduced to ${denominator}-minute intervals. 
    You can also enter a single date like 2019-11-20 or \"Last Tuesday\".
    </span><br>";
}

if (!$data) {
  echo "<span style='color:red'>
    Warning: No Data found for \"". addslashes($d) ."\". 
    Enter a date in the past, a number of days, or a number with 'h' for hours.</span><br>";
}

function ft($v) { // format temp
  return substr($v,0,strpos($v, '.')+3) . '°C';
}

if ($data) {
  echo "
  <big>". ft(min($line[10], $line[11])) ." /". ft(max($line[10], $line[11])) ." Outside ". ($dat? addslashes($d): '') ."</big>
  <br>${current} Readings at $datetime<br/>";
  foreach ($headers as $i => $key) {
    if (array_key_exists($key, $mapping) && m($key) !== 'Zero') {
      echo ' | '.m($key).' = '.ft($line[$i]);
    }
  }
}
echo "<br><a target='_blank' href='https://weather.gc.ca/city/pages/yt-16_metric_e.html'>Weather from Environment Canada</a>";
echo "</small>";

if ($data) {
  include('plot_lib.html');
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
}
include('footer.html');
?>
