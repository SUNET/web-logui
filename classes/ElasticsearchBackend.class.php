<?php

use ONGR\ElasticsearchDSL;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchPhraseQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MultiMatchQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\RangeAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\DateHistogramAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\FiltersAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\TopHitsAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\SumAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\MinAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\MaxAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\AvgAggregation;

class ElasticsearchBackend extends Backend
{
  private $es = null;

  public function __construct($es)
  {
    $this->es = $es;
  }

  public function isValid() { return $this->client() != null; }

  public function supportsHistory() { return true; }

  public function scrollMailHistory($scroll_id, $param) {
    try {
      $results = [];
      $settings = Settings::Get();

      // query elasticsearch with an scroll id
      $response = $this->es->client()->scroll([
        'scroll_id' => $scroll_id,
        'scroll' => '2m'
      ]);

      if (isset($response['hits']['hits']))
        foreach ($response['hits']['hits'] as $m)
          $results[] = es_document_parser($m, $settings->getElasticsearchMappings(), $settings->getElasticsearchMetadataFilter());

      return ['items' => $results, 'total' => $response['hits']['total']['value'] ?? 0, 'scroll_id' => $response['_scroll_id'] ?? null];
    } catch (Exception $e) {
      $errors[] = "Exception code: ".$e->getMessage();
      return [];
    }
  }

  public function loadMailHistory($search, $size, $param, &$errors = array())
  {
    try {
      $results = [];
      $settings = Settings::Get();

      $schema = $settings->getElasticsearchMappings();

      // sort
      $fieldsort = $this->es->getTimefilter();
      if (isset($param['sort'])) {
        switch ($param['sort']) {
          case 'to':
            $fieldsort = $schema['msgto'].'.keyword';
          break;
          case 'from':
            $fieldsort = $schema['msgfrom'].'.keyword';
          break;
          case 'subject':
            $fieldsort = $schema['msgsubject'].'.keyword';
            break;
        }
      }
      $fieldorder = isset($param['sortorder']) ? $param['sortorder'] : 'DESC';
      $sort = new FieldSort($fieldsort, $fieldorder === 'DESC' ? FieldSort::DESC : FieldSort::ASC);

      // query
      $query = new BoolQuery();

      // time zone
      $query_timezone = [];
      if (isset($_SESSION['timezone_utc'])) {
        $query_timezone = ['time_zone' => trim($_SESSION['timezone_utc'])];
      }

      // offset
      $query->add(new RangeQuery($this->es->getTimefilter(), [
        'gte' => intval($param['index_range']['start_ts']) * 1000,
        'lt' => intval($param['index_range']['stop_ts']) * 1000
      ]));

      if (is_string($search) && strlen($search) > 0) {
        $searchFields = [
          $schema['msgsubject'],
          $schema['msgfromdomain'],
          $schema['msgfrom'],
          $schema['msgtodomain'],
          $schema['msgto'],
          $schema['msgid']
        ];
        if (inet_pton($search))
          array_push($searchFields, $schema['msgfromserver']);
        $query->add(new MultiMatchQuery($searchFields, $search, ['operator' => 'AND']));
      }

      if (isset($param['filters'])) {
        $filterSettings = $settings->getElasticsearchFilters();
        $boolField = new BoolQuery();
        foreach ($param['filters'] as $field => $i) {
          $boolFilter = new BoolQuery();
          foreach ($i as $filter) {
            $boolOperator = BoolQuery::SHOULD;

            if ($filter['operator'] === 'exists' && is_string($filterSettings[$filter['field']]['mapping'])) {
              $boolFilter->add(new ExistsQuery($filterSettings[$filter['field']]['mapping']), $boolOperator);
              continue;
            }

            if ($filter['operator'] == 'contains')
              $operator = 'OR';
            else if ($filter['operator'] == 'not') {
              $operator = 'AND';
              $boolOperator = BoolQuery::MUST_NOT;
            } else
              $operator = 'AND';
            switch ($field) {
              case 'remoteip':
                if (inet_pton($filter['value']))
                  $boolFilter->add(new MatchQuery($filterSettings[$filter['field']]['mapping'], $filter['value'], ['operator' => 'AND']), $boolOperator);
                break;
              case 'action':
                if (strtoupper($filter['value']) == 'QUEUE') {
                  $queue = new BoolQuery();
                  $queue->add(new MatchQuery($schema['msgaction'], $filter['value']), BoolQuery::MUST);
                  $queue->add(new MatchQuery($schema['queue']['key'].'.'.$schema['msgaction'], 'DELIVER'), BoolQuery::MUST_NOT);
                  $queue->add(new MatchQuery($schema['queue']['key'].'.'.$schema['msgaction'], 'BOUNCE'), BoolQuery::MUST_NOT);
                  $queue->add(new MatchQuery($schema['queue']['key'].'.'.$schema['msgaction'], 'DELETE'), BoolQuery::MUST_NOT);

                  $boolFilter->add($queue, $boolOperator);
                } else {
                  $boolFilter->add(new MultiMatchQuery([$schema['msgaction'], $schema['queue']['key'].'.'.$schema['queue']['value']['action']], $filter['value'], ['operator' => 'AND']), $boolOperator);
                }
                break;
              case 'sascore':
                if ($filter['operator'] == '=') {
                  $sa = new MatchQuery($schema['scores']['key'].'.'.$schema['scores']['value']['sa'], $filter['value']);
                } else {
                  $range = [];
                  if ($filter['operator'] == '<')
                    $range += ['lt' => $filter['value']];
                  else if ($filter['operator'] == '<=')
                    $range += ['lte' => $filter['value']];
                  else if ($filter['operator'] == '>')
                    $range += ['gt' => $filter['value']];
                  else if ($filter['operator']  == '>=')
                    $range += ['gte' => $filter['value']];

                  $sa = new RangeQuery($schema['scores']['key'].'.'.$schema['scores']['value']['sa'], $range);
                }
                $boolFilter->add($sa, $boolOperator);
                break;
              case 'msgsize':
                if ($filter['operator'] == '=') {
                  $ms = new MatchQuery($schema['msgsize'], $filter['value']);
                } else {
                  $range = [];
                  if ($filter['operator'] == '<')
                    $range += ['lt' => $filter['value']];
                  else if ($filter['operator'] == '<=')
                    $range += ['lte' => $filter['value']];
                  else if ($filter['operator'] == '>')
                    $range += ['gt' => $filter['value']];
                  else if ($filter['operator']  == '>=')
                    $range += ['gte' => $filter['value']];

                  $ms = new RangeQuery($schema['msgsize'], $range);
                }
                $boolFilter->add($ms, $boolOperator);
                break;
              default:
                if ($filterSettings[$filter['field']]['mapping']) {
                  if (is_array($filterSettings[$filter['field']]['mapping']))
                    $boolFilter->add(new MultiMatchQuery($filterSettings[$filter['field']]['mapping'], $filter['value'], ['operator' => $operator]), $boolOperator);
                  else if (is_string($filterSettings[$filter['field']]['mapping']))
                    $boolFilter->add(new MatchQuery($filterSettings[$filter['field']]['mapping'], $filter['value'], ['operator' => $operator]), $boolOperator);
                }
                continue;
            }
          }
          $boolField->add($boolFilter);
        }
        $query->add($boolField);
      }

      // restrict
      $restrict = new BoolQuery();
      foreach ($this->restrict_query() as $v)
        $restrict->add(new TermsQuery($v['type'], $v['values']), BoolQuery::SHOULD);

      // filter
      $body = new Search();
      $body->addQuery($query);
      $body->addQuery($restrict);
      $body->addSort($sort);

      // index pattern
      if ($this->es->getIndex())
        $index = !preg_match("/\*$/", $this->es->getIndex()) ? $this->es->getIndex().'*' : $this->es->getIndex(); // #deprecated
      else
        $index = $this->es->getPattern();

      // params
      $params = [
        'index' => $index,
        'from' => $param['offset'],
        'size' => $size + 1,
        'body' => $body->toArray()
      ];
      if ($this->es->getType())
        $params['type'] = $this->es->getType();

      // query elasticsearch with given params
      $response = $this->es->client()->search($params);
      if (isset($response['hits']['hits']))
        foreach ($response['hits']['hits'] as $m)
          $results[] = es_document_parser($m, $settings->getElasticsearchMappings(), $settings->getElasticsearchMetadataFilter());

      return ['items' => $results, 'total' => $response['hits']['total']['value'] ?? 0, 'scroll_id' => $response['_scroll_id'] ?? null];
    } catch (Exception $e) {
      $errors[] = "Exception code: ".$e->getMessage();
      return [];
    }
  }

  public function getMail($index, $id)
  {
    $result = null;
    $access = Session::Get()->getAccess();
    $settings = Settings::Get();

    $params = [
      'index' => $index,
      'id' => $id,
      'type' => $this->es->getType()
    ];
    try {
      $response = $this->es->client()->get($params);
      if ($response) {
        $mail = es_document_parser($response, $settings->getElasticsearchMappings(), $settings->getElasticsearchMetadataFilter())['doc'];
        if (is_array($access['mail']) || is_array($access['domain']) || is_array($access['sasl'])) {
          $access_mail = $access_domain = $access_sasl = false;
          if (is_array($access['mail']) && in_array($mail->owner, $access['mail']))
            $access_mail = true;
          if (is_array($access['domain']) && in_array($mail->ownerdomain, $access['domain']))
            $access_domain = true;
          if (is_array($access['sasl']) && in_array($mail->saslusername, $access['sasl']))
            $access_sasl = true;
          if ($access_mail || $access_domain || $access_sasl)
            $result = $mail;
        } else {
          $result = $mail;
        }
      }
    } catch (Exception $e) {}

    return $result;
  }

  public function getTextlog($msgid, $msgts0, $page = 1)
  {
    $result = [];
    $more = false;
    $page = $page > 0 && $page < 100 ? $page : 1;

    try {
      // sort
      $sort = new FieldSort($this->es->getTextlogTimefilter(), FieldSort::ASC);

      // filter
      $query = new BoolQuery();
      $query->add(new MatchPhraseQuery('message', '"'.$msgid.'"'));

      $body = new Search();
      $body->addQuery($query);
      $body->addSort($sort);

      // index pattern
      if ($this->es->getTextlogIndex())
        $index = !preg_match("/\*$/", $this->es->getTextlogIndex()) ? $this->es->getTextlogIndex().'*' : $this->es->getTextlogIndex(); // #deprecated
      else
        $index = $this->es->getTextlogPattern();

      $params = [
        'index' => $index,
        'type' => $this->es->getTextlogType(),
        'size' => ($this->es->getTextlogLimit() * $page) + 1,
        'body' => $body->toArray()
      ];

      $response = $this->es->client()->search($params);
      if (isset($response['hits']['hits']))
        foreach ($response['hits']['hits'] as $m)
          $result[] = logstash_document_parser($m);

      if (count($result) > ($this->es->getTextlogLimit() * $page)) {
        array_pop($result);
        $more = true;
      }
    } catch (Exception $e) {}

    return ['result' => $result, 'more' => $more];
  }

  public function getAggregation($buckets = [], $param = [], $metrics = null) {
    try {
      $settings = Settings::Get();
      $result = [];
      $f = 0;

      $body = new Search();

      // aggregations
      $aggregation = $this->addBucket($buckets, 0, $metrics, $param['interval'] ?? null);

      if (!$aggregation)
        return [];

      // time zone
      $query_timezone = [];

      // range
      if ($param['start_ts'] && $param['stop_ts']) {
        if ($param['interval'] == 'fixed_interval') {
          $start = new DateTime('now');
          $stop = new DateTime('now');
          $start->modify('-2 hour');
        } else {
          $start = new DateTime();
          $start->setTimestamp($param['start_ts']);
          $stop = new DateTime();
          $stop->setTimestamp($param['stop_ts']);
          $stop->modify('+1 day');

          if (isset($_SESSION['timezone_utc'])) {
            $query_timezone = ['time_zone' => trim($_SESSION['timezone_utc'])];
          }
        }

        // query
        $query = new BoolQuery();
        $query->add(new RangeQuery($this->es->getTimefilter(), [
          'lt' => $param['offset'] ?? $stop->getTimestamp() * 1000,
          'gte' => $start->getTimestamp() * 1000
        ]), BoolQuery::MUST);

        $body->addQuery($query);
      }

      if ($param['target']) {
        $query = new MatchQuery($settings->getElasticsearchMappings()['msgtodomain'], $param['target']);
        $body->addQuery($query);
      }

      // restrict
      $restrict = new BoolQuery();
      foreach ($this->restrict_query() as $v)
        $restrict->add(new TermsQuery($v['type'], $v['values']), BoolQuery::SHOULD);

      $body->addQuery($restrict);
      $body->addAggregation($aggregation);

      // index pattern
      if ($this->es->getIndex())
        $index = !preg_match("/\*$/", $this->es->getIndex()) ? $this->es->getIndex().'*' : $this->es->getIndex(); // #deprecated
      else
        $index = $this->es->getPattern();

      $params = [
        'index' => $index,
        'type' => $this->es->getType(),
        'body' => $body->toArray(),
        'size' => 0
      ];

      $response = $this->es->client()->search($params);
      return $response ?? [];
    } catch (Exception $e) {
      echo $e;
      return [];
    }
  }

  private function addBucket($bucket, $i = 0, $metrics, $interval = null) {
    $agg = $this->addAggregation(
      $bucket['type'],
      $bucket['key'] ?? ++$i,
      $bucket['field'] ?? null,
      $bucket['filters'] ?? null,
      [
        'size' => $bucket['size'] ?? null,
        'sort' => $bucket['sort'] ?? null,
        'interval' => $interval,
        'exclude' => $bucket['exclude'] ?? null
      ]
    );

    if (isset($bucket['aggregation']))
      $agg->addAggregation($this->addBucket($bucket['aggregation'], $i, $metrics));
    else if (isset($metrics))
      $agg->addAggregation($this->addBucket($metrics, $i, null));

    return $agg;
  }

  private function addAggregation($type, $name, $field, $filters, $opts = []) {
    switch ($type) {
      case 'terms':
        $termsAggregation = new TermsAggregation($name ?? ++$f, $field);
        if (isset($opts['sort']))
          $termsAggregation->addParameter('order', ['_count' => $opts['sort']]);
        if (isset($opts['size']))
          $termsAggregation->addParameter('size', $opts['size']);
        if (is_string($opts['exclude']) || is_array($opts['exclude']))
          $termsAggregation->addParameter('exclude', $opts['exclude']);
        return $termsAggregation;
      case 'filters':
        $filterAgg = [];
        foreach ($filters ?? [] as $filter_name => $filter) {
          if ($filter['type'] == 'phrase')
            $filterAgg[$filter_name] = new MatchPhraseQuery($filter['field'], $filter['value']);
        }
        return new FiltersAggregation($name ?? ++$f, $filterAgg);
      case 'sum':
        return new SumAggregation($name ?? ++$f, $field);
      case 'min':
        return new MinAggregation($name ?? ++$f, $field);
      case 'max':
        return new MaxAggregation($name ?? ++$f, $field);
      case 'avg':
        return new AvgAggregation($name ?? ++$f, $field);
      case 'histogram':
        $agg = new DateHistogramAggregation($name ?? ++$f, $field, $opts['interval'] ?? 'day');
        if (isset($_SESSION['timezone_utc']))
          $agg->addParameter('time_zone', trim($_SESSION['timezone_utc']));
        return $agg;
      case 'fixed_interval':
        $agg = new DateHistogramAggregation($name ?? ++$f, $field, '1m');
        if (isset($_SESSION['timezone_utc']))
          $agg->addParameter('time_zone', trim($_SESSION['timezone_utc']));
        return $agg;
      default:
        return null;
    }
  }

  public function restrict_query()
  {
    $access = Session::Get()->getAccess();
    $restrict = [];
    if (is_array($access['domain']))
      $restrict[] = ['type' => 'ownerdomain', 'values' => $access['domain']];

    if (is_array($access['mail']))
      $restrict[] = ['type' => 'owner', 'values' => $access['mail']];

    if (is_array($access['sasl']))
      $restrict[] = ['type' => 'saslusername', 'values' => $access['sasl']];

    return $restrict;
  }
}
