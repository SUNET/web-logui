$(document).ready(function() {
  var cache = {};

  var fields = [
    {
      label: 'From',
      name: 'from',
      operator: ['exact', 'contains', 'not']
    },
    {
      label: 'To',
      name: 'to',
      operator: ['exact', 'contains', 'not']
    },
    {
      label: 'Subject',
      name: 'subject',
      operator: ['exact', 'contains', 'not']
    },
    {
      label: 'Status',
      name: 'status',
      operator: ['exact', 'contains', 'not']
    },
    {
      label: 'Remote IP',
      name: 'remoteip',
      operator: ['exact', 'not']
    },
    {
      label: 'Message ID',
      name: 'messageid',
      operator: ['exact', 'not']
    },
    {
      label: 'Action',
      name: 'action',
      operator: ['exact', 'not'],
      type: 'select',
      options: [
        'DELIVER',
        'QUEUE',
        'QUARANTINE',
        'ARCHIVE',
        'REJECT',
        'DELETE',
        'BOUNCE',
        'ERROR',
        'DEFER'
      ]
    },
    {
      label: 'Metadata',
      name: 'metadata',
      operator: ['exact', 'contains', 'not']
    },
    {
      label: 'RPD score',
      name: 'rpdscore',
      operator: ['exact', 'not'],
      type: 'select',
      options: [
        'spam',
        'bulk',
        'valid-bulk',
        'suspect',
        'non-spam'
      ]
    },
    {
      label: 'SA score',
      name: 'sascore',
      operator: ['=', '<=', '>=', '<', '>']
    }
  ];

  $('#filter-value').attr('disabled', true);

  fields.map(function (field, index) {
    $('#ff').append(
      $('<option>', {
        value: field.name,
        text: field.label
      })
    );
  });

  $('#ff').on('change', function(e) {
    $('#fo').empty();
    var field = fields.find(i => i.name == e.currentTarget.value);
    if (typeof field == 'object') {
      field.operator.map(function (operator) {
        $('#fo').append(
          $('<option>', {
            value: operator,
            text: operator
          })
        );
      });

      if (field.type == 'select') {
        $('#filter-value-field').html('<select class="custom-select" id="fv" name="fv"></select');
        field.options.map(function (option) {
          $('#fv').append('<option value="' + option + '">' + option + '</option>');
        });
      } else {
        $('#filter-value-field').html('<input type="text" class="form-control" id="fv" name="fv" size="30">');
      }

      $('#fv').attr('disabled', false);
    }
  });
});