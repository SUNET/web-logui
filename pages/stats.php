<?php
if (!defined('WEB_LOGUI')) die('File not included');
if (!$settings->getDisplayStats()) die("The setting display-stats isn't enabled");

require_once BASE.'/inc/twig.php';

[$es_start_ts, $es_stop_ts] = valid_date_range($_GET['start'], $_GET['stop']);

$stats = $settings->getStatsAggregations();

function SortByGroup($a, $b) {
  return strcmp($a['groupby'] ?? '', $b['groupby'] ?? '');
}

uasort($stats['line'], 'SortByGroup');
uasort($stats['bar'], 'SortByGroup');
uasort($stats['pie'], 'SortByGroup');

$twigLocals = [
  'access'      => Session::Get()->getAccess(),
  'es_start_ts'    => $es_start_ts,
  'es_stop_ts'     => $es_stop_ts,
  'stats' => [
    'line' => $stats['line'] ?? [],
    'bar' => $stats['bar'] ?? [],
    'pie' => $stats['pie'] ?? []
  ],
  'default_view' => $settings->getStatsDefaultView()
];

echo $twig->render('stats.twig', $twigGlobals + $twigLocals);
