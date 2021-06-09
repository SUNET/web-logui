<?php
if (!defined('WEB_LOGUI')) die('File not included');
if (!$settings->getDisplayStats()) die("The setting display-stats isn't enabled");

require_once BASE.'/inc/twig.php';

[$index_start, $index_stop] = valid_date_range($_GET['start'] ?? null, $_GET['stop'] ?? null);

$stats = $settings->getStatsAggregations();

function SortByGroup($a, $b) {
  return strcmp($a['groupby'] ?? '', $b['groupby'] ?? '');
}

uasort($stats['line'], 'SortByGroup');
uasort($stats['bar'], 'SortByGroup');
uasort($stats['pie'], 'SortByGroup');

$twigLocals = [
  'access'      => Session::Get()->getAccess(),
  'index_start' => $index_start,
  'index_stop'  => $index_stop,
  'stats' => [
    'line' => $stats['line'] ?? [],
    'bar' => $stats['bar'] ?? [],
    'pie' => $stats['pie'] ?? []
  ],
  'default_view' => $settings->getStatsDefaultView()
];

echo $twig->render('stats.twig', $twigGlobals + $twigLocals);
