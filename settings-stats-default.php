<?php

require_once(BASE.'/classes/Stats.class.php');

$statsSettings['default-view'] = [['chart' => 'line', 'type' => 'action'], ['chart' => 'line', 'type' => 'bandwidth']];

$alpha = 0.65;

$statsSettings['color'] = [
  "rgba(130, 174, 245, $alpha)",
  "rgba(255, 99, 132, $alpha)",
  "rgba(255, 159, 64, $alpha)",
  "rgba(255, 205, 86, $alpha)",
  "rgba(75, 192, 192, $alpha)",
  "rgba(54, 162, 235, $alpha)",
  "rgba(153, 102, 255, $alpha)",
  "rgba(201, 203, 207, $alpha)",
  "rgba(136, 237, 101, $alpha)",
  "rgba(237, 88, 110, $alpha)",
  "rgba(237, 133, 218, $alpha)"
];

$statsSettings['label-color'] = [
  'REJECT' => [
    'bg' => "rgba(255, 99, 132, $alpha)"
  ],
  'QUARANTINE' => [
    'bg' => "rgba(255, 159, 64, $alpha)"
  ],
  'DEFER' => [
    'bg' => "rgba(197, 110, 181, $alpha)"
  ],
  'BOUNCE' => [
    'bg' => "rgba(48, 48, 48, $alpha)"
  ],
  'DELIVER' => [
    'bg' => "rgba(136, 237, 101, $alpha)"
  ],
  'bandwidth' => [
    'bg' => "rgba(134, 52, 113, $alpha)"
  ]
];

$statsAgg['histogram'] = [
  'key' => 'time',
  'type' => 'histogram',
  'field' => 'receivedtime'
];

$statsAgg['listener']['in'] = [
  'key' => 'listener',
  'type' => 'filters',
  'filters' => [
    'inbound' => [
      'type' => 'phrase',
      'field' => 'serverid.keyword',
      'value' => 'mailserver:inbound'
    ]
  ]
];
$statsAgg['listener']['out'] = [
  'key' => 'listener',
  'type' => 'filters',
  'filters' => [
    'outbound' => [
      'type' => 'phrase',
      'field' => 'serverid.keyword',
      'value' => 'mailserver:outbound'
    ]
  ]
];

$statsAgg['action'] = [
  'key' => 'action',
  'type' => 'filters',
  'filters' => [
    'REJECT' => [
      'type' => 'phrase',
      'field' => 'action.keyword',
      'value' => 'REJECT'
    ],
    'QUARANTINE' => [
      'type' => 'phrase',
      'field' => 'action.keyword',
      'value' => 'QUARANTINE'
    ],
    'DEFER' => [
      'type' => 'phrase',
      'field' => 'action.keyword',
      'value' => 'DEFER'
    ],
    'BOUNCE' => [
      'type' => 'phrase',
      'field' => 'queue.action.keyword',
      'value' => 'BOUNCE'
    ],
    'DELIVER' => [
      'type' => 'phrase',
      'field' => 'queue.action.keyword',
      'value' => 'DELIVER'
    ]
  ]
];

$statsAgg['classifications'] = [
  'key' => 'spam',
  'type' => 'filters',
  'filters' => [
    'non-spam' => [
      'type' => 'phrase',
      'field' => 'score_rpd',
      'value' => '0'
    ],
    'suspect' => [
      'type' => 'phrase',
      'field' => 'score_rpd',
      'value' => '10'
    ],
    'valid-bulk' => [
      'type' => 'phrase',
      'field' => 'score_rpd',
      'value' => '40'
    ],
    'bulk' => [
      'type' => 'phrase',
      'field' => 'score_rpd',
      'value' => '50'
    ],
    'spam' => [
      'type' => 'phrase',
      'field' => 'score_rpd',
      'value' => '100'
    ]
  ]
];

$statsAgg['senderip']         = [ 'key' => 'ip', 'type' => 'terms', 'field' => 'senderip' ];
$statsAgg['senderdomain']     = [ 'key' => 'senders', 'type' => 'terms', 'field' => 'senderdomain.keyword' ];
$statsAgg['sender']           = [ 'key' => 'senders', 'type' => 'terms', 'field' => 'sender.keyword' ];
$statsAgg['recipientdomain']  = [ 'key' => 'recipients', 'type' => 'terms', 'field' => 'recipientdomain.keyword' ];
$statsAgg['recipient']        = [ 'key' => 'recipient', 'type' => 'terms', 'field' => 'recipient.keyword' ];

/*
  Line charts
*/
$statsSettings['aggregations']['line'] = [
  'action' => [
    'label' => 'Action',
    'groupby' => 'Inbound',
    'buckets' => (new StatsBucket())
      ->addAggregation($statsAgg['histogram'])
      ->addAggregation($statsAgg['listener']['in'])
      ->addAggregation($statsAgg['action'])
      ->toArray()
  ],
  'action_out' => [
    'label' => 'Action',
    'groupby' => 'Outbound',
    'buckets' => (new StatsBucket())
      ->addAggregation($statsAgg['histogram'])
      ->addAggregation($statsAgg['listener']['out'])
      ->addAggregation($statsAgg['action'])
      ->toArray()
  ],
  'bandwidth' => [
    'label' => 'Bandwidth usage - MiB',
    'groupby' => 'Inbound',
    'splitseries' => false,
    'legend' => 'bandwidth',
    'buckets' => (new StatsBucket())
      ->addAggregation($statsAgg['histogram'])
      ->addAggregation($statsAgg['listener']['in'])
      ->toArray()
    ,
    'metrics' => [
      'key' => 'bandwidth',
      'type' => 'sum',
      'field' => 'size',
      'format' => function ($v) {
        return round($v / 1024 / 1024, 2);
      }
    ]
  ],
  'bandwidth_out' => [
    'label' => 'Bandwidth usage - MiB',
    'groupby' => 'Outbound',
    'splitseries' => false,
    'legend' => 'bandwidth',
    'buckets' => (new StatsBucket())
      ->addAggregation($statsAgg['histogram'])
      ->addAggregation($statsAgg['listener']['out'])
      ->toArray()
    ,
    'metrics' => [
      'key' => 'bandwidth',
      'type' => 'sum',
      'field' => 'size',
      'format' => function ($v) {
        return round($v / 1024 / 1024, 2);
      }
    ]
  ]
];

/*
  Bar charts
*/
$statsSettings['aggregations']['bar'] = [
  'senderip' => [
    'label' => 'Remote IP\'s',
    'groupby' => 'Top (Inbound)',
    'buckets' => (new StatsBucket())
      ->addAggregation($statsAgg['listener']['in'])
      ->addAggregation(
        array_merge($statsAgg['senderip'], [ 'size' => 10, 'sort' => 'desc' ])
      )
      ->toArray()
  ],
  'senderdomain' => [
    'label' => 'Sender domains',
    'groupby' => 'Top (Inbound)',
    'legend' => 'top',
    'buckets' => (new StatsBucket())
      ->addAggregation($statsAgg['listener']['in'])
      ->addAggregation(
        array_merge($statsAgg['senderdomain'], [ 'size' => 10, 'sort' => 'desc' ])
      )
      ->toArray()
  ],
  'senders' => [
    'label' => 'Senders',
    'groupby' => 'Top (Inbound)',
    'buckets' => (new StatsBucket())
      ->addAggregation($statsAgg['listener']['in'])
      ->addAggregation(
        array_merge($statsAgg['sender'], [ 'size' => 10, 'sort' => 'desc' ])
      )
      ->toArray()
  ],
  'senderdomain_out' => [
    'label' => 'Sender domains',
    'groupby' => 'Top (Outbound)',
    'legend' => 'top',
    'buckets' => (new StatsBucket())
      ->addAggregation($statsAgg['listener']['out'])
      ->addAggregation(
        array_merge($statsAgg['senderdomain'], [ 'size' => 10, 'sort' => 'desc' ])
      )
      ->toArray()
  ],
  'senders_out' => [
    'label' => 'Senders',
    'groupby' => 'Top (Outbound)',
    'buckets' => (new StatsBucket())
      ->addAggregation($statsAgg['listener']['out'])
      ->addAggregation(
        array_merge($statsAgg['sender'], [ 'size' => 10, 'sort' => 'desc' ])
      )
      ->toArray()
  ],
  'recipientdomain' => [
    'label' => 'Recipient domains',
    'groupby' => 'Top (Inbound)',
    'buckets' => (new StatsBucket())
      ->addAggregation($statsAgg['listener']['in'])
      ->addAggregation(
        array_merge($statsAgg['recipientdomain'], [ 'size' => 10, 'sort' => 'desc' ])
      )
      ->toArray()
  ],
  'recipients' => [
    'label' => 'Recipients',
    'groupby' => 'Top (Inbound)',
    'buckets' => (new StatsBucket())
      ->addAggregation($statsAgg['listener']['in'])
      ->addAggregation(
        array_merge($statsAgg['recipient'], [ 'size' => 10, 'sort' => 'desc' ])
      )
      ->toArray()
  ],
  'subject' => [
    'label' => 'Subjects',
    'groupby' => 'Top (Inbound)',
    'buckets' => (new StatsBucket())
      ->addAggregation($statsAgg['listener']['in'])
      ->addAggregation([
        'key' => 'ip', 'type' => 'terms', 'field' => 'subject.keyword'
      ])
      ->toArray()
  ],
  'bandwidth' => [
    'label' => 'Bandwidth usage - MiB',
    'groupby' => 'Inbound',
    'buckets' => (new StatsBucket())
      ->addAggregation($statsAgg['listener']['in'])
      ->toArray()
    ,
    'metrics' => [
      'key' => 'bandwidth',
      'type' => 'sum',
      'field' => 'size',
      'format' => function ($v) {
        return round($v / 1024 / 1024, 2);
      }
    ]
  ]
];

/*
  Pie chart
*/
$statsSettings['aggregations']['pie'] = [
  'action' => [
    'label' => 'Action type',
    'groupby' => 'Inbound',
    'buckets' => (new StatsBucket())
      ->addAggregation($statsAgg['listener']['in'])
      ->addAggregation($statsAgg['action'])
      ->toArray()
  ],
  'classification' => [
    'label' => 'Spam classification',
    'groupby' => 'Inbound',
    'buckets' => (new StatsBucket())
      ->addAggregation($statsAgg['listener']['in'])
      ->addAggregation($statsAgg['classifications'])
      ->toArray()
  ]
];
