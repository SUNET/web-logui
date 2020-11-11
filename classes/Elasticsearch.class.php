<?php

use Elasticsearch\ClientBuilder;

class Elasticsearch
{
  private $_client = null;

  private $hosts;
  private $index; // #deprecated, use $pattern
  private $pattern;
  private $type;
  private $timefilter;

  private $textlog_pattern;
  private $textlog_index; // #deprecated, use $textlog_pattern
  private $textlog_type;
  private $textlog_timefilter;
  private $textlog_limit;
  private $textlog_rotatelimit;

  private $username;
  private $password;
  private $tls;
  private $timeout;

  public function client() { return $this->_client; }
  public function getIndex() { return $this->index; } // #deprecated
  public function getPattern() { return $this->pattern; }
  public function getType() { return $this->type; }
  public function getTimefilter() { return $this->timefilter; }

  public function getTextlogPattern() { return $this->textlog_pattern; }
  public function getTextlogIndex() { return $this->textlog_index; } // #deprecated
  public function getTextlogType() { return $this->textlog_type; }
  public function getTextlogTimefilter() { return $this->textlog_timefilter; }
  public function getTextlogLimit() { return $this->textlog_limit; }
  public function getTextlogRotateLimit() { return $this->textlog_rotatelimit; }

  public function __construct($hosts, $index, $username = null, $password = null, $tls = [], $timeout = null)
  {
    $this->hosts = $hosts;
    $this->pattern = $index['mail']['pattern'];
    $this->index = $index['mail']['name']; // #deprecated
    $this->type = $index['mail']['type'] ?? null;
    $this->timefilter = $index['mail']['timefilter'];

    $this->textlog_pattern = $index['textlog']['pattern'];
    $this->textlog_index = $index['textlog']['name'];
    $this->textlog_type = $index['textlog']['type'];
    $this->textlog_timefilter = $index['textlog']['timefilter'];
    $this->textlog_limit = $index['textlog']['limit'] ?? 25;
    $this->textlog_rotatelimit = $index['textlog']['search_rotate_limit'] ?? 10;

    $this->username = $username;
    $this->password = $password;
    $this->tls = $tls;
    $this->timeout = is_numeric($timeout) ? $timeout : 5;

    try {
      $this->_client = ClientBuilder::create()->setHosts($this->hosts)->build();
    } catch(Exception $e) {
      die($e);
    }
  }
}
