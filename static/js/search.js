$(document).ready(function() {

  var filters = {};
	$.post('?xhr', {
		'page': 'messages',
		'type': 'filters'
	}).done((data) => {
		if (data.error)
			return;
		if (typeof data.filters !== 'object')
			return;

    filters = data.filters;
    Object.keys(filters).map(function (field, index) {
      $('#ff').append(
        $('<option>', {
          value: field,
          text: filters[field].label
        })
      );
    });
  });

  $('#filter-value').attr('disabled', true);

  $('#fo').on('change', function(e) {
    if ($(this).val() == 'exists')
      $('#fv').val('').attr('disabled', true);
    else
      $('#fv').attr('disabled', false);
  });

  $('#ff').on('change', function(e) {
    $('#fo').empty();
    var filter = filters[e.currentTarget.value];
    if (typeof filter == 'object') {
      if (Array.isArray(filter.operators)) {
        filter.operators.map(function (operator) {
          $('#fo').append(
            $('<option>', {
              value: operator,
              text: operator
            })
          );
        });
      }
      if (typeof filter.values === 'object' || Array.isArray(filter.values)) {
        $('#filter-value-field').html('<select class="custom-select" id="fv" name="fv"></select>');
        if (Array.isArray(filter.values)) {
          filter.values.map(function (option) {
            $('#fv').append($('<option>', {
              value: option,
              text: option
            }));
          });
        } else {
          Object.keys(filter.values).map(function (option) {
            $('#fv').append($('<option>', {
              value: filter.values[option],
              text: option
            }));
          });
        }
      } else {
        $('#filter-value-field').html('<input type="text" class="form-control" id="fv" name="fv" size="30">');
      }

      $('#fv').attr('disabled', false);
    }
  });
});