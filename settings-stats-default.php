<?php

$statsSettings['default-view'] = [['chart' => 'line', 'type' => 'action'], ['chart' => 'line', 'type' => 'bandwidth']];

$alpha = 0.65;

$statsSettings['color'] = [
  "rgba(130, 174, 245, $alpha)",
  "rgba(255, 99, 132, $alpha)",
  "rgba(255, 159, 64, $alpha)",
  "rgba(54, 162, 235, $alpha)",
  "rgba(153, 102, 255, $alpha)",
  "rgba(255, 205, 86, $alpha)",
  "rgba(201, 203, 207, $alpha)",
  "rgba(136, 237, 101, $alpha)",
  "rgba(75, 192, 192, $alpha)",
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
  ],
  'non-spam' => [
    'bg' => "rgba(136, 237, 101, $alpha)"
  ],
  // reject reason
  'spf' => [
    'bg' => "rgba(172, 99, 255, $alpha)"
  ],
  'dmarc' => [
    'bg' => "rgba(118, 69, 173, $alpha)"
  ],
  'virus' => [
    'bg' => "rgba(94, 68, 36, $alpha)"
  ],
  'spam' => [
    'bg' => "rgba(255, 99, 132, $alpha)"
  ],
  'bulk' => [
    'bg' => "rgba(255, 206, 99, $alpha)"
  ],
  'dlp' => [
    'bg' => "rgba(99, 143, 255, $alpha)"
  ],
  'blockexe' => [
    'bg' => "rgba(52, 75, 133, $alpha)"
  ],
  'ipreputation' => [
    'bg' => "rgba(214, 6, 52, $alpha)"
  ],
  'nxdomain' => [
    'bg' => "rgba(99, 99, 99, $alpha)"
  ]
];

$statsSettings['aggregations'] = [
  'line' => [
    'action' => [
      'label' => 'Action',
      'groupby' => 'Inbound',
      'buckets' => [
        'aggregation' => [
          'key' => 'time',
          'type' => 'histogram',
          'field' => 'receivedtime',
          'aggregation' => [
            'key' => 'listener',
            'type' => 'filters',
            'filters' => [
              'inbound' => [
                'type' => 'phrase',
                'field' => 'serverid.keyword',
                'value' => 'mailserver:inbound'
              ]
            ],
            'aggregation' => [
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
            ]
          ]
        ]
      ]
    ],
    'bandwidth' => [
      'label' => 'Bandwidth usage - MiB',
      'groupby' => 'Inbound',
      'splitseries' => false,
      'legend' => 'bandwidth',
      'buckets' => [
        'aggregation' => [
          'key' => 'time',
          'type' => 'histogram',
          'field' => 'receivedtime',
          'aggregation' => [
            'key' => 'listener',
            'type' => 'filters',
            'filters' => [
              'inbound' => [
                'type' => 'phrase',
                'field' => 'serverid.keyword',
                'value' => 'mailserver:inbound'
              ]
            ]
          ]
        ]
      ],
      'metrics' => [
        'key' => 'bandwidth',
        'type' => 'sum',
        'field' => 'size',
        'format' => function ($v) {
          return round($v / 1024 / 1024, 2);
        }
      ]
    ],
    'rejectreason' => [
      'label' => 'Reject reason',
      'groupby' => 'Inbound',
      'buckets' => [
        'aggregation' => [
          'key' => 'time',
          'type' => 'histogram',
          'field' => 'receivedtime',
          'aggregation' => [
            'key' => 'listener',
            'type' => 'filters',
            'filters' => [
              'inbound' => [
                'type' => 'phrase',
                'field' => 'serverid.keyword',
                'value' => 'mailserver:inbound'
              ]
            ],
            'aggregation' => [
              'key' => 'reason',
              'type' => 'filters',
              'filters' => [
                'spam' => [
                  'type' => 'phrase',
                  'field' => 'metadata.reject-reason.keyword',
                  'value' => 'spam'
                ],
                'bulk' => [
                  'type' => 'phrase',
                  'field' => 'metadata.reject-reason.keyword',
                  'value' => 'bulk'
                ],
                'spf' => [
                  'type' => 'phrase',
                  'field' => 'metadata.reject-reason.keyword',
                  'value' => 'spf'
                ],
                'dmarc' => [
                  'type' => 'phrase',
                  'field' => 'metadata.reject-reason.keyword',
                  'value' => 'dmarc'
                ],
                'ipreputation' => [
                  'type' => 'phrase',
                  'field' => 'metadata.reject-reason.keyword',
                  'value' => 'ipreputation'
                ],
                'virus' => [
                  'type' => 'phrase',
                  'field' => 'metadata.reject-reason.keyword',
                  'value' => 'virus'
                ],
                'nxdomain' => [
                  'type' => 'phrase',
                  'field' => 'metadata.reject-reason.keyword',
                  'value' => 'nxdomain'
                ],
                'blockexe' => [
                  'type' => 'phrase',
                  'field' => 'metadata.reject-reason.keyword',
                  'value' => 'blockexe'
                ],
                'dlp' => [
                  'type' => 'phrase',
                  'field' => 'metadata.reject-reason.keyword',
                  'value' => 'dlp'
                ],


              ]
//              'type' => 'terms',
//              'field' => 'metadata.reject-reason.keyword'
            ]
          ]
        ]
      ]
    ],
    'action-out' => [
      'label' => 'Action',
      'groupby' => 'Outbound',
      'buckets' => [
        'aggregation' => [
          'key' => 'time',
          'type' => 'histogram',
          'field' => 'receivedtime',
          'aggregation' => [
            'key' => 'listener',
            'type' => 'filters',
            'filters' => [
              'outbound' => [
                'type' => 'phrase',
                'field' => 'serverid.keyword',
                'value' => 'mailserver:outbound'
              ]
            ],
            'aggregation' => [
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
            ]
          ]
        ]
      ]
    ],
    'bandwidth-out' => [
      'label' => 'Bandwidth usage - MiB',
      'groupby' => 'Outbound',
      'splitseries' => false,
      'legend' => 'bandwidth',
      'buckets' => [
        'aggregation' => [
          'key' => 'time',
          'type' => 'histogram',
          'field' => 'receivedtime',
          'aggregation' => [
            'key' => 'listener',
            'type' => 'filters',
            'filters' => [
              'outbound' => [
                'type' => 'phrase',
                'field' => 'serverid.keyword',
                'value' => 'mailserver:outbound'
              ]
            ]
          ]
        ]
      ],
      'metrics' => [
        'key' => 'bandwidth',
        'type' => 'sum',
        'field' => 'size',
        'format' => function ($v) {
          return round($v / 1024 / 1024, 2);
        }
      ]
    ],
  ],
  'bar' => [
    'senderip' => [
      'label' => 'Remote IP\'s',
      'groupby' => 'Top (Inbound)',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'inbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:inbound'
            ]
          ],
          'aggregation' => [
            'key' => 'ip',
            'type' => 'terms',
            'field' => 'senderip',
            'size' => 10,
            'sort' => 'desc',
          ]
        ]
      ]
    ],
    'senderdomain' => [
      'label' => 'Sender domains',
      'groupby' => 'Top (Inbound)',
      'legend' => 'top',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'inbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:inbound'
            ]
          ],
          'aggregation' => [
            'key' => 'senders',
            'type' => 'terms',
            'field' => 'senderdomain.keyword',
            'size' => 10,
            'sort' => 'desc'
          ]
        ]
      ]
    ],
    'senders' => [
      'label' => 'Senders',
      'groupby' => 'Top (Inbound)',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'inbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:inbound'
            ]
          ],
          'aggregation' => [
            'key' => 'senders',
            'type' => 'terms',
            'field' => 'sender.keyword',
            'size' => 10,
            'sort' => 'desc'
          ]
        ]
      ]
    ],
    'recipientdomain' => [
      'label' => 'Recipient domains',
      'groupby' => 'Top (Inbound)',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'inbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:inbound'
            ]
          ],
          'aggregation' => [
            'key' => 'recipients',
            'type' => 'terms',
            'field' => 'recipientdomain.keyword',
            'size' => 10,
            'sort' => 'desc'
          ]
        ]
      ]
    ],
    'recipients' => [
      'label' => 'Recipients',
      'groupby' => 'Top (Inbound)',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'inbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:inbound'
            ]
          ],
          'aggregation' => [
            'key' => 'recipients',
            'type' => 'terms',
            'field' => 'recipient.keyword',
            'size' => 10,
            'sort' => 'desc'
          ]
        ]
      ]
    ],
    'localrelayip' => [
      'label' => 'Local Relay IP\'s',
      'groupby' => 'Top (Outbound)',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'outbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:outbound'
            ]
          ],
          'aggregation' => [
            'key' => 'ip',
            'type' => 'terms',
            'field' => 'senderip',
            'size' => 10,
            'sort' => 'desc',
          ]
        ]
      ]
    ],
    'senders-outbound' => [
      'label' => 'Senders',
      'groupby' => 'Top (Outbound)',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'outbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:outbound'
            ]
          ],
          'aggregation' => [
            'key' => 'senders',
            'type' => 'terms',
            'field' => 'sender.keyword',
            'size' => 10,
            'sort' => 'desc'
          ]
        ]
      ]
    ],
    'recipients-outbound' => [
      'label' => 'Recipients',
      'groupby' => 'Top (Outbound)',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'outbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:outbound'
            ]
          ],
          'aggregation' => [
            'key' => 'recipients',
            'type' => 'terms',
            'field' => 'recipient.keyword',
            'size' => 10,
            'sort' => 'desc'
          ]
        ]
      ]
    ],
  ],
  'pie' => [
    'action' => [
      'label' => 'Action type',
      'groupby' => 'Inbound',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'inbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:inbound'
            ]
          ],
          'aggregation' => [
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
          ]
        ]
      ]
    ],
    'classification' => [
      'label' => 'Spam classification',
      'groupby' => 'Inbound',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'inbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:inbound'
            ]
          ],
          'aggregation' => [
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
          ]
        ]
      ]
    ],
    'rejectreason' => [
      'label' => 'Reject reason',
      'groupby' => 'Inbound',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'inbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:inbound'
            ]
          ],
          'aggregation' => [
            'key' => 'reason',
            'type' => 'terms',
            'field' => 'metadata.reject-reason.keyword'
          ]
        ]
      ]
    ],
  ]
];

