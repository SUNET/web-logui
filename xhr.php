<?php

if (!defined('WEB_LOGUI')) die('File not included');
header('Content-Type: application/json; charset=UTF-8');

function checkAccess($perm)
{
  if (Session::Get()->checkAccessAll())
    return true;
  $access = Session::Get()->getAccess();
  foreach ($access as $type)
    foreach ($type as $item)
      if ($item == $perm)
        return true;
  if (strpos($perm, '@') !== false)
    if (Session::Get()->checkAccessMail($perm))
      return true;
  return false;
}

function getDatasets($agg, $data, $metrics) {
  $datasets = [];

  if (isset($data[$agg['key']]['buckets'])) {
    foreach ($data[$agg['key']]['buckets'] as $k => $bucket) {
      $dataset['label'] = $bucket['key'] ?? $k;
      $dataset['data'] = [];

      if (isset($agg['aggregation']) && isset($bucket[$agg['aggregation']['key']])) {
        $dataset['data'] = getMetrics($agg['aggregation'], $bucket[$agg['aggregation']['key']], $metrics);
      } else {
        $dataset['data'] = getMetrics($agg, $bucket, $metrics);
      }

      $datasets[] = $dataset;
    }
  } else {
    $datasets[]['data'] = getMetricValue($data, $metrics);
  }

  return $datasets;
}

function getBuckets($agg, $data, $metrics, $depth = 0) {
  $buckets = [];
  $n = 0;
  foreach ($data[$agg['key']]['buckets'] as $k => $bucket) {
    $key = $bucket['key'] ?? $k;
    $buckets[$key] = [
      'count' => $bucket['doc_count'] ?? null,
      'xaxis' => $agg['xaxis'] ?? false
    ];
    if ($agg['aggregation']['key'] && $bucket[$agg['aggregation']['key']]) {
      $buckets[$key]['data'] = getBuckets($agg['aggregation'], $bucket, $metrics, ++$depth);
    } else {
      $value = getMetricValue($bucket, $metrics);
      if ($depth == 0)
        $buckets[$key]['data'][$key] = ['count' => $value];
      else
        $buckets[$key] = ['count' => $value];
    }
    $n++;
  }
  return $buckets;
}

function getMetrics($agg, $bucket, $metrics) {
  if (isset($agg['aggregation'])) {
    foreach ($bucket['buckets'] as $b) {
      return getMetrics($agg['aggregation'], $b[$agg['aggregation']['key']], $metrics);
    }
  } else {
    $data = [];
    if (isset($bucket['buckets'])) {
      foreach ($bucket['buckets'] as $k => $b) {
        $data[$b['key'] ?? $k] = getMetricValue($b, $metrics);
      }
    } else {
      $data[$bucket['key'] ?? 0] = getMetricValue($bucket, $metrics);
    }
    return $data;
  }
}

function getMetricValue($value, $metrics = null) {
  if (!isset($metrics))
    $v = $value['doc_count'];
  else if ($metrics['type'] == 'sum')
    $v = $value[$metrics['key']]['value'];

  if (isset($metrics) && is_callable($metrics['format']))
    $v = $metrics['format']($v);

  return $v;
}

function getChartData($buckets, $datasets = [], $label = null) {
  foreach ($buckets as $k => $bucket) {
    if (is_array($bucket['data'])) {
      if ($bucket['xaxis'])
        $datasets = getChartData($bucket['data'], $datasets, $k);
      else
        $datasets = getChartData($bucket['data'], $datasets);
    } else {
      if ($label !== null) {
        $datasets[$label]['label'] = $label;
        $datasets[$label]['data'][$k] = $bucket['count'];
      } else {
        $datasets[$k]['label'] = $k;
        $datasets[$k]['data'][] = $bucket['count'];
      }
    }
  }
  return $datasets;
}

if ($_POST['page'] == 'stats')
{
  if (!$settings->getDisplayStats())
    die(json_encode(array('error' => "The setting display-stats isn't enabled")));

  if (!$_POST['start'] || !$_POST['stop'])
    die(json_encode(['error' => 'Missing date range']));

  $name = $_POST['type'];
  [$start, $stop] = valid_date_range($_POST['start'], $_POST['stop']);

  $dt_start = new DateTime($start);
  $dt_stop = new DateTime($stop);
  $days = $dt_stop->diff($dt_start)->format('%a');
  if ($days <= 3)
    $interval = 'hour';
  else if ($days <= 92)
    $interval = 'day';
  else if ($days > 92 && $days < 547)
    $interval = 'month';
  else
    $interval = 'year';

  $fixedInterval = null;
  if (isset($_POST['interval'])) {
    if ($_POST['interval'] == 'fixed_interval')
      $fixedInterval = 'fixed_interval';
  }

  $colorset = $settings->getStatsColor();

  $map = $settings->getStatsAggregation($_POST['chart'], $name);
  if (!$map)
    die(json_encode(['error' => 'Unknown type']));

  if (in_array($_POST['chart'], ['pie', 'bar']) && $name) {
    try {
      $aggs = $map['buckets']['aggregation'];

      $esBackend = new ElasticsearchBackend($settings->getElasticsearch());
      $data = $esBackend->getAggregation(
        $aggs,
        [
          'start' => $start,
          'stop' => $stop,
          'target' => $_POST['target'] ?? null,
          'interval' => $fixedInterval
        ],
        $map['metrics']
      );

      $buckets = getBuckets($aggs, $data['aggregations'], $map['metrics'] ?? null);
      $chartData = getChartData($buckets);
      $labels = array_keys($chartData);
      $legends = [];
      foreach ($chartData as $data)
        if ($data['data'])
          $legends = array_unique($legends + array_keys($data['data']));

      $datasets = [];
      foreach ($legends as $k => $legend) {
        $datasets[$legend]['label'] = $legend;
        foreach ($labels as $label) {
          $datasets[$legend]['data'][] = $chartData[$label]['data'][$legend];
          $bgColor = $settings->getStatsLabelColor()[$legend]['bg'] ?? $settings->getStatsLabelColor()[$label]['bg'];
          if (!$bgColor) {
            if (count($legends) > 1)
              $bgColor =  $colorset[$k % count($colorset)];
            else
              $bgColor = $colorset[$color++ % count($colorset)];
          }
          $datasets[$legend]['backgroundColor'][] = $bgColor;
        }
      }

      die(json_encode([
        'label' => $map['label'] ?? '',
        'group' => $map['groupby'] ?? '',
        'variant' => $map['variant'] ?? '',
        'labels' => $labels,
        'datasets' => array_values($datasets)
      ]));
    } catch (Exception $e) {
      die(json_encode(['error' => ""]));
    }
  }

  if ($_POST['chart'] == 'line' && isset($_POST['type'])) {
    try {
      $aggs = $map['buckets']['aggregation'];

      if ($fixedInterval) {
        $aggs['type'] = 'fixed_interval';
        $interval = $fixedInterval;
      }

      $esBackend = new ElasticsearchBackend($settings->getElasticsearch());
      $data = $esBackend->getAggregation(
        $aggs,
        [
          'start' => $start,
          'stop' => $stop,
          'target' => $_POST['target'] ?? null,
          'interval' => $interval
        ],
        $map['metrics']
      );

      $datasets = getDatasets($aggs, $data['aggregations'], $map['metrics'] ?? null);

      $chartdata = [];
      $items = [];
      foreach ($datasets as $dataset) {
        if (is_array($dataset['data'])) {
          foreach ($dataset['data'] as $k => $v) {
            $items[$k]['data'][] = ['t' => $dataset['label'], 'y' => $v];
          }
        } else {
          if ($map['splitseries'] === false)
            $items[$map['legend'] ?? 'data']['data'][] = ['t' => $dataset['label'], 'y' => $dataset['data']];
          else
            $items[$dataset['label']]['data'][] = ['t' => $dataset['label'], 'y' => $dataset['data']];
        }
      }
      foreach ($items as $k => $item) {
        $i['label'] = $k;
        $i['data'] = $item['data'];
        $i['backgroundColor'] = $settings->getStatsLabelColor()[$k]['bg'] ?? $colorset[$color++ % count($colorset)];
        if ($settings->getStatsLabelColor()[$k]['border'])
          $i['borderColor'] = $settings->getStatsLabelColor()[$k]['border'];
        $i['lineTension'] = 0.25;
        $chartdata[] = $i;
      }

      die(json_encode(['label' => $map['label'] ?? '', 'group' => $map['groupby'] ?? '', 'datasets' => $chartdata, 'interval' => $interval]));
    } catch (Exception $e) {}
  }

  die(json_encode(array('error' => 'Not implemented yet')));
}

if ($_POST['page'] == 'messages') {
  if ($_POST['type'] == 'datepicker') {
    $indices = str_replace($settings->getElasticsearch()->getIndex(), '', Session::Get()->getElasticsearchIndices());
    die(json_encode(['indices' => $indices]));
  }

  if ($_POST['type'] == 'filters') {
    $filterSettings = $settings->getElasticsearchFilters();
    die(json_encode(['filters' => $filterSettings]));
  }
}

die(json_encode(array('error' => 'unsupported request')));
