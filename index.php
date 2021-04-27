<?php
$debug = 0;
$denominator = 1;
$denominator_cutoff = 10; // # of days to graph before scaling down
$history = false;

function files_from_find_command($args) {
  global $debug;
  
  $command = "find /home/adama/retro-house/ -type f -name '*Enviroboard.csv' ${args} -printf \"%T@ %p\n\" 2>/dev/null | sort -n";
  if ($debug) {
    error_log(">>> $command");
  }
  return `${command}`;
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

function set_denominator($days) {
  global $denominator, $denominator_cutoff;
  $denominator = floor($days / $denominator_cutoff) + 1;
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
      set_denominator($hn / 24);
    }
    else {
      $d = 1;
      $files = files_from_find_command(days_ago(1));
    }
  }
  else if ($dn >= 1) {
    $files = files_from_find_command(days_ago($dn));
    set_denominator($dn);
  }
  else if ($dat && preg_match('/^\d{4}-\d{2}$/', $d)) {
    $end_of_month = strtotime("$d +1 month -1 day");
    $files = files_from_find_command(date_range($dat, $end_of_month));
    set_denominator(30);
    $history = true;
  }
  else if ($dat) {
    $files = files_from_find_command(date_range($dat));
    $history = true;
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
  "T1~Unknown" => Array(
    '20181228-09:23:18' => "TODO",
    '20200115-19:23:00' => "Zero",
  ),
  "T1~Unknown" => "Sewer Pipe",
  "T2~Unknown" => "Crawlspace",
  "T3~Unknown" => "Outside E",
  "T4~Unknown" => "Outside W",
  "T5~Unknown" => "Living Room",
  "T6~Unknown" => "Studio Floor",
  "T7~Unknown" => "Bedroom",
  "T8~Unknown" => "Garage",
  "T9~Unknown" => "Sump Inlet",
  "T10~Unknown" => "Sump Recirc",
  "T11~Unknown" => "Sump Floor",
  "T12~Unknown" => "Studio Bathroom",
//  "A5-Unknown" => "A5",
//  "A6~Unknown" => "House Humidity",
  "A7~Unknown" => "Studio Humidity",
  "A8~Unknown" => "Zero",
);


if (array_key_exists('h', $_GET)) {
  $house = '&h';
  unset(
    $mapping['T1~Unknown'],
    $mapping['T6~Unknown'],
    $mapping['T9~Unknown'],
    $mapping['T10~Unknown'],
    $mapping['T11~Unknown'],
    $mapping['T12~Unknown'],
    $mapping['A7~Unknown']
  );
} else if (array_key_exists('b', $_GET)) {
  $house = '&b';
} else {
  $house = '';
  unset(
    $mapping['T1~Unknown'],
    $mapping['T2~Unknown'],
    $mapping['T5~Unknown'],
    $mapping['T7~Unknown'],
    $mapping['T8~Unknown']
  );
}

function m($s, $ts = 0) {
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
      if ($row == 1) {
        $headers = array_map('trim', $line);
      }
      else if ($row%$denominator==0) {
        $num = count($line);
        for ($c=0; $c < $num; $c++) {
          if ($headers[$c]=='timestamp') {
            $datetime = date('Ymd-H:i:s', (int)(intval($line[$c])/1000));
            if ($adjust && (strcmp($datetime, $cutoff) > -1)) {
              $adjust = 0;
            }
          }
          else if (array_key_exists($headers[$c], $mapping)) {
            $value = trim($line[$c]);
            if ($adjust && $value != 'NaN' && strpos($headers[$c], "A") != 1) {
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
 Graph <input name='d' value='$d' size='9'/> <input type='submit' value='Go'/>
 " . ($house === '&b' ? '<input type="hidden" name="b" />' : ($house ? '<input type="hidden" name="h" />': '')) . "
 <small>
  <b>Eg</b>
  • <a href='?d=2020-01$house'>2020-01</a>
  • <a href='?d=2019-11-20$house'>2019-11-20</a>
  • <a href='?d=Last+Tuesday$house'>Last Tuesday</a>
  • <a href='?d=7$house'>7d</a>
  • <a href='?d=24h$house'>24h</a>
 </small>
</form>";

if ($denominator > 1) {
  echo "<span style='color:red'>
    Warning: Large data set requested. 
    Resolution has been reduced to ${denominator}-minute intervals.
    </span><br>";
}

if (!$data) {
  echo "<span style='color:red'>
    Sorry: No Data found for \"". htmlentities($d) ."\".</span><br>";
}

function ft($v) { // format temp
  return substr($v,0,strpos($v, '.')+3) . '°C';
}

function fh($v) { // format humidity
  return substr($v,0,strpos($v, '.')+3) . '%H';
}

if ($data) {
  echo "
  <big>". ft($line[10]) ." /". ft($line[11]) ." Outside ". ($dat? addslashes($d): '') ."</big>
  <br>" . ($history? 'Readings': 'Current readings') . " at $datetime<br/>";
  foreach ($headers as $i => $key) {
    if (array_key_exists($key, $mapping)) {
      $m = m($key);
      if ($m == 'Zero') {
        continue;
      }
      if (strpos($key, 'A') === 0) {
        echo " | $m = ".fh($line[$i]);
      }
      else {
        echo " | $m = ".ft($line[$i]);
      }
    }
  }
}
if (!$history) {
  echo "<br><a target='_blank' href='https://weather.gc.ca/city/pages/yt-16_metric_e.html'>Weather from Environment Canada</a>";
}
echo "</small>";

if ($data) {
  include('plot_lib.html');
  echo "[";
  $first = true;
  foreach($mapping as $label) {
    if (!array_key_exists($label, $data)) {
      continue;
    }
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
