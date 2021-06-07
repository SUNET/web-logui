<?php

class StatsBucket {
  private $aggs;

  function addAggregation($agg) {
    $this->aggs[] = $agg;
    return $this;
  }

  function toArray() {
    $output = [];
    foreach ($this->aggs as $agg) {
      if (!isset($output['aggregation'])) {
        $output['aggregation'] = $agg;
        $last = &$output['aggregation'];
      } else {
        $last['aggregation'] = $agg;
        $last = &$last['aggregation'];
      }
    }
    return $output;
  }
}
