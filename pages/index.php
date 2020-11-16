<?php
if (!defined('WEB_LOGUI')) die('File not included');

if (isset($_POST['delete']) || isset($_POST['bounce']) || isset($_POST['retry']) || isset($_POST['duplicate'])) {
  $actions = array();
  die();
}

$action_colors = array(
  'DELIVER' => '#5cb85c',
  'QUEUE' => '#00aeef',
  'QUARANTINE' => '#f0ad4e',
  'ARCHIVE' => '#b8b8b8',
  'REJECT' => '#d9534f',
  'DELETE' => '#000',
  'BOUNCE' => '#000',
  'ERROR' => '#000',
  'DEFER' => '#e83e8c',
);

function get_preview_link($m, $opts = [])
{
  return '?'.http_build_query(array(
    'page' => 'preview',
    'id' => $m['doc']->id
  ) + $opts);
}

// Backend
$esBackend = new ElasticsearchBackend($settings->getElasticsearch());

// Default values
$search = isset($_GET['search']) ? $_GET['search'] : '';
$size = isset($_GET['size']) ? intval($_GET['size']) : 50;
$size = !isset($_GET['exportcsv']) && $size > 1000 ? 1000 : $size;

// time partitioning
[$index_start, $index_stop] = valid_date_range($_GET['start'], $_GET['stop']);

// Select box arrays
$pagesize = [25, 50, 100, 500, 1000];

// Initial settings
$results = [];
$prev_button = false;
$next_button = false;
$param = [];
$errors = [];

// Set offset with GET
$param['offset'] = 0;
if (isset($_GET['offset'])) {
  $param['offset'] = (int)$_GET['offset'];
  $prev_button = true; // enable "previous" page button
}

$param['sort'] = 'date';
if (isset($_GET['sort']) && in_array($_GET['sort'], ['to', 'from', 'subject', 'date']))
  $param['sort'] = $_GET['sort'];

$param['sortorder'] = 'DESC';
if (isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['DESC', 'ASC']))
  $param['sortorder'] = strtoupper($_GET['order']);

$param['index_range'] = ['start' => $index_start, 'stop' => $index_stop];

if (isset($_GET['unsetfilter'])) {
  $filters = $_SESSION['filters'] ?? [];
  foreach ($filters as $field => $filter)
    foreach ($filter as $key => $value)
      if ($value['id'] == $_GET['unsetfilter']) {
        unset($filters[$field][$key]);
        if (count($filters[$field]) < 1)
          unset($filters[$field]);
      }
  $_SESSION['filters'] = $filters;
}

// ff = field, fo = operator, fv = value
if (isset($_GET['ff']) && isset($_GET['fo']) && isset($_GET['fv'])) {
  addFilter($_GET['ff'], $_GET['fo'], $_GET['fv']);
} else if (isset($_GET['mf']) && is_array($_GET['mf']['f'])) {
  $_SESSION['filters'] = [];
  $active_view_id = $_GET['mf']['id'];
  foreach ($_GET['mf']['f'] as $filter) {
    if (isset($filter['ff']) && isset($filter['fo']) && isset($filter['fv']))
      addFilter($filter['ff'], $filter['fo'], $filter['fv']);
  }
}

function addFilter($field, $operator, $value) {
  $valid_fields = ['messageid', 'subject', 'from', 'to', 'remoteip', 'status', 'action', 'metadata', 'rpdscore', 'sascore'];
  $valid_operators = ['exact', 'contains', 'not', '=', '<=', '>=', '<', '>'];
  if (in_array($field, $valid_fields) && in_array($operator, $valid_operators)) {
    $duplicate = false;
    foreach ($_SESSION['filters'][$field] ?? [] as $f) {
      if ($f['operator'] == $operator && $f['value'] == $value)
        $duplicate = true;
    }

    if (!$duplicate) {
      $_SESSION['filters-id'] = !isset($_SESSION['filters-id']) ? 1 : ++$_SESSION['filters-id'];
      $_SESSION['filters'][$field][] = [
        'id' => $_SESSION['filters-id'],
        'operator' => $operator,
        'value' => $value
      ];
    }
  }
}

if (isset($_SESSION['filters']))
  $param['filters'] = $_SESSION['filters'];

$mailHistory = $esBackend->loadMailHistory($search, $size, $param, $errors);

$results = $mailHistory['items'];
$total_count = $mailHistory['total'];
if (!$results)
  $results = [];

ksort($errors);

if (count($results) > $size) {
  array_pop($results);
  $next_button = true; // enable "next" page button
}

$mails = array();

foreach ($results as $m) {
  if ($i > $size) { break; }
  $i++;
  if ($m['type'] == 'archive') {
    $m['doc']->msgaction = 'ARCHIVE';
  }
  if ($m['type'] == 'queue' && $m['doc']->msgaction == 'DELIVER') $m['doc']->msgaction = 'QUEUE';

  $mail = array();

  $mail['doc'] = $m['doc'];

  if ($m['doc']->msgts0 + (3600 * 24) > time())
    $mail['today'] = true;
  $mail['time'] = $m['doc']->msgts0 - $_SESSION['timezone'] * 60;

  $mail['preview'] = $preview;
  $mail['previewlink'] = get_preview_link($m, ['index' => $m['index']]);
  $mail['action_text'] = substr($m['doc']->queue['action'] ?? $m['doc']->msgaction, 0, 1);
  $mail['action_color'] = $action_colors[$m['doc']->queue['action'] ?? $m['doc']->msgaction];
  if ($settings->getDisplayScores()) {
    $printscores = array();
    $scores = history_parse_scores($m['doc']);
    foreach ($scores as $engine => $s) {
      if ($engine == 'rpd' && $s['score'] != 'Unknown')
        $printscores[] = strtolower($s['score']);
      if ($engine == 'kav' && $s['score'] != 'Ok')
        $printscores[] = 'virus';
      if ($engine == 'clam' && $s['score'] != 'Ok')
        $printscores[] = 'virus';
      if ($engine == 'rpdav' && $s['score'] != 'Ok')
        $printscores[] = 'virus';
      if ($engine == 'sa')
        $printscores[] = $s['score'];
    }
    $mail['scores'] = implode(', ', array_unique($printscores));
  }
  $mails[] = $mail;
}

// csv export
if (isset($_GET['exportcsv'])) {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename=export.csv');

  $fp = fopen('php://output', 'w');

  $csv_export = $_GET['export'];

  if ($csv_export['headers'] == true) {
    $csv_headers = [];
    if ($csv_export['action'])
      $csv_headers[] = 'action';
    if ($csv_export['from'])
      $csv_headers[] = 'from';
    if ($csv_export['to'])
      $csv_headers[] = 'to';
    if ($csv_export['subject'])
      $csv_headers[] = 'subject';
    if ($csv_export['status'])
      $csv_headers[] = 'status';
    if ($csv_export['date'])
      $csv_headers[] = 'date';
    if ($settings->getDisplayScores() && $csv_export['scores'])
      $csv_headers[] = 'scores';

    fputcsv($fp, $csv_headers);
  }

  foreach ($mails as $mail) {
    $csv_mail = [];
    if ($csv_export['action'])
      $csv_mail[] = $mail['doc']->queue['action'] ?? $mail['doc']->msgaction;
    if ($csv_export['from'])
      $csv_mail[] = $mail['doc']->msgfrom;
    if ($csv_export['to'])
      $csv_mail[] = $mail['doc']->msgto;
    if ($csv_export['subject'])
      $csv_mail[] = $mail['doc']->msgsubject;
    if ($csv_export['status']) {
      if ($mail['doc']->msgaction == 'QUARANTINE')
        $csv_mail[] = 'Quarantine';
      elseif ($mail['doc']->msgaction == 'ARCHIVE')
        $csv_mail[] = 'Archive';
      elseif ($mail['doc']->msgaction == 'QUEUE' && $mail['doc']->queue['action'] != 'DELIVER')
        $csv_mail[] = str_replace('%1', $mail['doc']->msgretries, 'In queue (retry '.$mail['doc']->queue['retry'].')').' '.$mail['doc']->msgdescription;
      else
        $csv_mail[] = $mail['doc']->queue['errormsg'] ?? $mail['doc']->msgdescription;
    }
    if ($csv_export['date'])
      $csv_mail[] = date('Y-m-d H:i:s', $mail['time']);
    if ($settings->getDisplayScores() && $csv_export['scores'])
        $csv_mail[] = $mail['scores'];

    fputcsv($fp, $csv_mail);
  }

  die();
}

$paging['offset'] = $param['offset'] + $size;

$mailAccess = Session::Get()->getAccess('mail') ?? [];
$domainAccess = Session::Get()->getAccess('domain') ?? [];

require_once BASE.'/inc/twig.php';

$twigLocals = [
  'search'                    => $search,
  'size'                      => $size,
  'errors'                    => $errors,
  'mailhasmultipleaddresses'  => count($mailAccess) != 1 || count($domainAccess) > 0,
  'search_domains'            => (count($domainAccess) > 0 && count($domainAccess) < 30) ? Session::Get()->getAccess('domain') : [],
  'feature_scores'            => $settings->getDisplayScores(),
  'index_start'               => $index_start,
  'index_stop'                => $index_stop,
  'mails'                     => $mails,
  'mails_count'               => $total_count,
  'prev_button'               => $prev_button,
  'next_button'               => $next_button,
  'pagesizes'                 => $pagesize,
  'paging'                    => $paging,
  'filters'                   => $_SESSION['filters'] ?? [],
  'sortby'                    => $param['sort'],
  'sortorder'                 => $param['sortorder'],
  'active_view_id'            => $active_view_id ?? false,
  'table_columns'             => $settings->getDisplayIndexColumns()
];

echo $twig->render('index.twig', $twigGlobals + $twigLocals);
