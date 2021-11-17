<?php
// This should already be included, but reinclude it anyways just to be sure
// that the autoloader and settings are initialized properly
require_once BASE.'/inc/core.php';
require_once BASE.'/inc/utils/rest.inc.php';

function history_parse_scores($mail)
{
  $rpd = array();
  $rpd[0] = 'Unknown';
  $rpd[10] = 'Suspect';
  $rpd[40] = 'Valid bulk';
  $rpd[50] = 'Bulk';
  $rpd[100] = 'Spam';
  $rpdav[0] = 'unknown';
  $rpdav[50] = 'medium';
  $rpdav[100] = 'high';
  $ret = array();
  if (isset($mail->scores['sa']['score']) && isset($mail->scores['sa']['sa_rules'])) {
    $ret['sa']['name'] = 'SpamAssassin';
    $ret['sa']['score'] = floatval($mail->scores['sa']['score']);
    $sa_scores = [];
    foreach ($mail->scores['sa']['sa_rules'] as $key => $value)
      $sa_scores[] = $key.'='.$value;
    $ret['sa']['text'] = implode(', ', $sa_scores);
  }
  if (isset($mail->scores['rpd']['score_rpd']) && isset($mail->scores['rpd']['score_rpd_refid'])) {
    $ret['rpd']['name'] = 'Cyren';
    $ret['rpd']['score'] = $rpd[intval($mail->scores['rpd']['score_rpd'])];
    $ret['rpd']['text'] = $mail->scores['rpd']['score_rpd_refid'];
  }
  if (isset($mail->scores['rsd']['score']) && isset($mail->scores['rsd']['rsd_symbols'])) {
    $ret['rpd']['name'] = 'Rspamd';
    $ret['rpd']['score'] = floatval($mail->scores['rpd']['score']);
    $rsd_symbols = [];
    foreach ($mail->scores['rsd']['rsd_symbols'] as $key => $value)
      $rsd_symbols[] = $key.'='.$value;
    $ret['rsd']['text'] = implode(', ', $rsd_symbols);
  }
  if (isset($mail->scores['rpdav']) && isset($mail->scores['rpd']['score_rpd_refid'])) {
    $ret['rpdav']['name'] = 'Cyren AV';
    if (intval($mail->scores['rpdav']) > 0) {
      $ret['rpdav']['score'] = 'Virus ('.$rpdav[intval($mail->scores['rpdav'])].')';
      $ret['rpdav']['text'] = $mail->scores['rpd']['score_rpd_refid'];
    } else $ret['rpdav']['score'] = 'Ok';
  }
  if (isset($mail->scores['kav'])) {
    $ret['kav']['name'] = 'Sophos';
    if (is_array($mail->scores['kav'])) {
      $ret['kav']['score'] = 'Virus';
      $ret['kav']['text'] = implode(', ', $mail->scores['kav']);
    } else $ret['kav']['score'] = 'Ok';
  }
  if (isset($mail->scores['clam'])) {
    $ret['clam']['name'] = 'ClamAV';
    if (is_array($mail->scores['clam']) && count($mail->scores['clam']) > 0) {
      $ret['clam']['score'] = 'Virus';
      $ret['clam']['text'] = implode(', ', $mail->scores['clam']);
    } else $ret['clam']['score'] = 'Ok';
  }

  return $ret;
}

function merge_2d($a1, $a2)
{
  foreach ($a2 as $k => $v) {
    if (!isset($a1[$k])) {
      $a1[$k] = $v;
    } else {
      $a1[$k] = array_merge($a1[$k], $v);
    }
  }

  return $a1;
}

function format_size($size)
{
  $base = log($size, 1024);
  $suffixes = array('B', 'KiB', 'MiB', 'GiB', 'TiB');
  return round(pow(1024, $base - floor($base)), 0) . ' ' . $suffixes[floor($base)];
}

/**
 * Crossplatform strftime, because PHP for some reason thinks it's a good idea
 * to have platform-specific syntax for functions in a scripting language, and
 * instead of fixing this, they DOCUMENT A WORKAROUND.
 */
function strftime2($timestamp, $format)
{
  if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')
    $format = preg_replace('#(?<!%)((?:%%)*)%e#', '\1%#d', $format);
  return strftime($format, $timestamp != NULL ? $timestamp : time());
}

function emptyspace($str)
{
  if ($str == '')
    return '<br>'; // XXX: empty table-cell hack
  return $str;
}

function extract_domain($address)
{
  return strpos($address, '@') !== false ? substr($address, strrpos($address, '@') + 1) : $address;
}

function sanitize_domain($domain)
{
  $d = preg_replace('/[^a-z0-9\.\-]/i', '', $domain);
  if ($d != $domain)
    $d .= '--'.substr(sha1($domain), 0, 8);
  else
    $d = $domain;

  return $d;
}

function es_document_parser($m, $schema, $metadata_filters = null) {
  $mail = [];
  $mail['index'] = $m['_index'];
  $mail['receivedtime'] = $m['_source']['receivedtime'];
  $mail['doc'] = (object) [
    'id' => $m[$schema['id']],
    'owner' => $m['_source'][$schema['owner']],
    'ownerdomain' => $m['_source'][$schema['ownerdomain']],
    'msgid' => $m['_source'][$schema['msgid']],
    'msgaction' => $m['_source'][$schema['msgaction']],
    'msglistener' => $m['_source'][$schema['msglistener']],
    'msgtransport' => $m['_source'][$schema['msgtransport']],
    'msgsasl' => $m['_source'][$schema['msgsal']],
    'msgfromserver' => $m['_source'][$schema['msgfromserver']],
    'msgsenderhelo' => $m['_source'][$schema['msgsenderhelo']],
    'msgtlsstarted' => $m['_source'][$schema['msgtlsstarted']],
    'msgfrom' => $m['_source'][$schema['msgfrom']],
    'msgfromdomain' => $m['_source'][$schema['msgfromdomain']],
    'msgto' => $m['_source'][$schema['msgto']],
    'msgtodomain' => $m['_source'][$schema['msgtodomain']],
    'msgsubject' => $m['_source'][$schema['msgsubject']],
    'msgsize' => $m['_source'][$schema['msgsize']],
    'msgdescription' => $m['_source'][$schema['msgdescription']],
    'msgactionid' => $m['_source'][$schema['msgactionid']],
    'msgts0' => (int)substr($m['_source'][$schema['msgts0']], 0, -3),
    'serialno' => $m['_source'][$schema['serialno']],
    'scores' => [
      'rpd' => [
        'score_rpd' => $m['_source'][$schema['score_rpd']],
        'score_rpd_refid' => $m['_source'][$schema['score_rpd_refid']]
      ],
      'rpdav' => $m['_source'][$schema['score_rpdav']],
      'sa' => [
        'score' => $m['_source'][$schema['scores']['key']][$schema['scores']['value']['sa']],
        'sa_rules' => $m['_source'][$schema['scores']['key']][$schema['scores']['value']['sa_rules']]
      ],
      'rsd' => [
        'score' => $m['_source'][$schema['scores']['key']][$schema['scores']['value']['rsd']],
        'rsd_symbols' => $m['_source'][$schema['scores']['key']][$schema['scores']['value']['rsd_symbols']]
      ],
      'kav' => $m['_source'][$schema['scores']['key']][$schema['scores']['value']['kav']],
      'clam' => $m['_source'][$schema['scores']['key']][$schema['scores']['value']['clam']]
    ],
    'queue' => [
      'action' => $m['_source'][$schema['queue']['key']][$schema['queue']['value']['action']],
      'retry' => $m['_source'][$schema['queue']['key']][$schema['queue']['value']['retry']],
      'retries' => $m['_source'][$schema['queue']['key']][$schema['queue']['value']['retries']],
      'errormsg' => $m['_source'][$schema['queue']['key']][$schema['queue']['value']['errormsg']]
    ],
    'metadata' => $m['_source'][$schema['metadata']]
  ];
  if (isset($metadata_filters) && is_array($mail['doc']->metadata)) {
    $filtered = [];
    foreach ($mail['doc']->metadata as $k => $v)
      foreach ($metadata_filters as $pattern)
        if (preg_match($pattern, $k))
          $filtered += [$k => $v];
    $mail['doc']->metadata = $filtered;
  }
  return $mail;
}

function logstash_document_parser($m) {
  return [
    'message' => $m['_source']['message']
  ];
}

function valid_date_range($start_ts = null, $stop_ts = null, $strtime = '-6 days') {
  if ($start_ts && $stop_ts) {
    $_SESSION['es_start_ts'] = $start_ts;
    $_SESSION['es_stop_ts'] = $stop_ts;
  }

  $es_start_ts = isset($_SESSION['es_start_ts']) ? $_SESSION['es_start_ts'] : strtotime(date('Y-m-d', strtotime($strtime))) + $_SESSION['timezone'] * 60;
  $es_stop_ts = isset($_SESSION['es_stop_ts']) ? $_SESSION['es_stop_ts'] : strtotime(date('Y-m-d')) + 86399 + $_SESSION['timezone'] * 60;

  return [$es_start_ts, $es_stop_ts];
}

function addFilter($field, $operator, $value) {
  global $settings;
  $filterSettings = $settings->getElasticsearchFilters();

  if (!$filterSettings[$field] || !in_array($operator, $filterSettings[$field]['operators'] ?? []))
    return;
  if (is_array($filterSettings[$field]['values']) && !in_array($value, $filterSettings[$field]['values']))
    return;

  $duplicate = false;
  foreach ($_SESSION['filters'][$field] ?? [] as $f) {
    if ($f['operator'] == $operator && $f['value'] == $value)
      $duplicate = true;
  }

  if (!$duplicate) {
    $_SESSION['filters-id'] = !isset($_SESSION['filters-id']) ? 1 : ++$_SESSION['filters-id'];
    $_SESSION['filters'][$field][] = [
      'field' => $field,
      'id' => $_SESSION['filters-id'],
      'operator' => $operator,
      'value' => $value
    ];
  }
}
