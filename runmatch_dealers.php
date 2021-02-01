<?php

require_once('utils/csvreader.php');
require_once('utils/file.php');

function normalize($name) {
  $name = strtolower($name);

  if (strpos($name, "benz") !== false) {
    if (strpos($name, "mercedes") === false) {
      $name = str_replace('benz','mercedesbenz', $name);
    }
  }
  $name = replaceWord($name, 'mb','mercedesbenz');
  $name = preg_replace('!\s+!', ' ', $name);
  $name = replaceWord($name, 'vw','volkswagen');
  $name = str_replace('dodge ram','dodge', $name);
  $name = replaceWord($name, 'inc', '');
  $name = replaceWord($name, 'llc', '');
  $name = str_replace('georgia', 'ga', $name);
  $name = str_replace('chrysler jeep dodge', 'chrysler dodge jeep', $name);
  $name = str_replace('chrysler dodge jeep ram', 'cdjr', $name);
  $name = str_replace('chevcad', 'chevrolet cadillac', $name);
  $name = str_replace('chevcadillac', 'chevrolet cadillac', $name);
  $name = str_replace('loln', 'lincoln', $name);
  $name = str_replace('bmw', 'BMW', $name);
  $name = str_replace('a & l', 'A&L', $name);
  $name = str_replace(' of ', ' ', $name);
  $name = str_replace('online dealer', '', $name);
  $name = str_replace('online only', '', $name);
  $name = str_replace('.', '', $name);
  $name = str_replace(',', '', $name);
  $name = str_replace('(', '', $name);
  $name = str_replace(')', '', $name);

  $name = str_replace('kinion auto sales and service', 'Kinion Auto Sales & Services', $name);
  $name = str_replace('red bank volvo cars', 'red bank volvo', $name);
  $name = str_replace('toyotacolchester', 'toyota of colchester', $name);
  $name = str_replace('mercedes-benzel dorado hills', 'mercedes-benz of el dorado hills', $name);
  $name = str_replace('mercedes-benzhoffman estates', 'mercedes-benz of hoffman estates', $name);
  $name = str_replace('don hattan ford, .', 'don hattan ford inc', $name);
  $name = str_replace('tlc motors, .', 'tlc motors', $name);
  $name = str_replace('bob penkhus volvo', 'bob penkhus volkswagen', $name);
  $name = str_replace('stoltz ford st marys', 'stoltz ford of st marys', $name);
  $name = str_replace("stoltz ford of st  mary s", 'stoltz ford of st marys', $name);
  $name = str_replace("stoltz ford of st  marys", 'stoltz ford of st marys', $name);

  $name = ucwords(trim($name));

  return $name;
}

function replaceWord ($input, $word, $replacement) {
  return preg_replace("/\b$word\b/i", $replacement, $input);
}


function normalizeDomain($website) {
  $url = parse_url($website);
  if ($url && isset($url['host'])) {
    return strtolower(str_replace('www.', '', $url['host']));
  }
	$url = parse_url('http://' . $website);
  if ($url && isset($url['host'])) {
    return strtolower(str_replace('www.', '', $url['host']));
  }
	return null;
}

$targetRows = array();
$fp = fopen('inputs/dealers.csv', 'r');
$csvReader = new CsvReader($fp, ',');
if ($csvReader->readHeader()) {
  while (($row = $csvReader->readRow()) !== false) {
      $targetRows[] = $row;
  }
}
$csvReader->close();

$SOURCES = array('cs', 'mc', 'cdc', 'dma', 'afs', 'crawl');
$sourceRows = array();

foreach ($SOURCES as $source) {
  print "SOURCE: $source\n";
  $fp = fopen('output/dealers_extracted_'.$source.'.csv', 'r');
  $csvReader = new CsvReader($fp, ',');
  if ($csvReader->readHeader()) {
    while (($row = $csvReader->readRow()) !== false) {
      $city = normalize($row['city']);
      $state = normalize($row['state']);
      $dealer = normalize($row['dealer']);
      $addr = isset($row['addr']) ? $row['addr'] : null;
      $domain = isset($row['website']) ? normalizeDomain($row['website']) : null;
      if (!isset($sourceRows[$source])) {
        $sourceRows[$source] = array();
      }
      if (!isset($sourceRows[$source][$state])) {
        $sourceRows[$source][$state] = array();
      }
      $sourceRows[$source][$state][$dealer] = $row['dealer'];
      $sourceRows[$source][$state]['ADDR:' . $addr] = $row['dealer'];
      if ($domain) {
        $sourceRows[$source][$state]['DOMAIN:' . $domain] = $row['dealer'];
      }
    }
  }
  $csvReader->close();
}

function findMatch($list, $target) {
	if (isset($list[$target])) {
    $match = $list[$target];
    return $match;
	}
	return false;
}

$i = 0;
$foundCount = 0;

$header = 'match,state,city,website,dealer_id,address,zip,dealer_name_1,dealer_name_2,dealer_name_3';
foreach ($SOURCES as $source) {
  $header .= ',' . $source . '_dealer';
}

$outputLines = array($header);
$unmatchedLines = array('state,city,website,dealer_id,address,zip,dealer_name_1,dealer_name_2,dealer_name_3');

$matchCountBySource = array();
foreach ($SOURCES as $source) {
	$matchCountBySource[$source] = 0;
}

foreach ($targetRows as $row) {
  $i = $i + 1;
  //remove spaces before/after of string
  $row['city'] = trim($row['city']);
  $row['state'] = trim($row['state']);
  $row['addr'] = trim($row['addr']);
  $row['website'] = trim($row['website']);
  $row['zip'] = trim($row['zip']);
  $row['dealer_id'] = trim($row['dealer_id']);

  $city = normalize($row['city']);
  $state = normalize($row['state']);
  $addr = normalize($row['addr']);
  $domain = normalizeDomain($row['website']);

  $targetDealers = array();

  $output = array($row['state'], $row['city'], $row['website'], $row['dealer_id'], $row['addr'], $row['zip']);
  $unmatchedOutput = array($row['state'], $row['city'], $row['website'], $row['dealer_id'], $row['addr'], $row['zip']);
  for ($v = 1; $v <= 3; $v++) {
    $row['dealer_name_' . $v] = trim($row['dealer_name_' . $v]);
    $targetDealer = normalize($row['dealer_name_' . $v]);
    $targetDealers []= $targetDealer;
    $output []= $row['dealer_name_' . $v];
    $unmatchedOutput []= $row['dealer_name_' . $v];
  }

  $matched = false;
  foreach ($SOURCES as $source) {
		$matchBySource = false;
    $match = '';
    if (isset($sourceRows[$source][$state])) {
      $dealers = $sourceRows[$source][$state];
      foreach ($targetDealers as $targetDealer) {
        if ($targetDealer) {
          if (isset($dealers[$targetDealer])) {
            $match = $dealers[$targetDealer];
            $matched = true;
						$matchBySource = true;
          }
        }
      }
      if (isset($dealers['ADDR:'.$addr])) {
        $match = $dealers['ADDR:'.$addr];
        $matched = true;
				$matchBySource = true;
      }
      if (isset($dealers['DOMAIN:'.$domain])) {
        $match = $dealers['DOMAIN:'.$domain];
        $matched = true;
				$matchBySource = true;
      }
    }
		if ($matchBySource) {
			$matchCountBySource[$source] = $matchCountBySource[$source] + 1;
		}
    $output [] = $match;
  }

  $outputLines []= ($matched ? '1' : '0') . ',' . '"' . implode('","', $output) . '"';
  if ($matched) {
    $foundCount = $foundCount + 1;
  } else {
    $unmatchedLines []= implode(',', $unmatchedOutput);
  }
}

File::write('output/dealers_map.csv', implode("\n", $outputLines));
File::write('output/dealers_unmatched.csv', implode("\n", $unmatchedLines));

print_r($matchCountBySource);
print 'FOUND: ' . $foundCount . ' / ' . $i . ': output/dealers_map.csv' . "\n";
print 'UNMATCHED: ' . (count($unmatchedLines) - 1) . ': output/dealers_unmatched.csv' . "\n";
