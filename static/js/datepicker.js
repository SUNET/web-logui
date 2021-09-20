$(document).ready(function() {
	moment.locale($('#datepicker_locale').val());

	var start = moment.unix(parseInt($('#es_start_ts').val()));
	if (!start.isValid())
		start = moment().subtract(2, 'days');
	var stop = moment.unix(parseInt($('#es_stop_ts').val()));
	if (!stop.isValid())
		stop = moment();

	function updateDateRangeLabel(start, stop) {
		$('#es_daterangepicker span').text(start.format('MMM D, YYYY @ HH:mm') + ' - ' + stop.format('MMM D, YYYY @ HH:mm'));
	}

	function submitDateRange(start, stop) {
		$('#es_start_ts').val(start.unix());
		$('#es_stop_ts').val(stop.unix());
		$('#datepicker').submit();
	}

	$('#es_daterangepicker').daterangepicker({
    timePicker: true,
		timePicker24Hour: true,
		timePickerSeconds: true,
    startDate: start,
    endDate: stop,
		opens: 'left',
		maxDate: moment().endOf('day'),
		ranges: {
			'Today': [moment().startOf('day'), moment().endOf('day')],
			'Yesterday': [moment().subtract(1, 'days').startOf('day'), moment().subtract(1, 'days').endOf('day')],
			'Last 7 Days': [moment().subtract(6, 'days').startOf('day'), moment().endOf('day')],
			'Last 30 Days': [moment().subtract(29, 'days').startOf('day'), moment().endOf('day')],
			'Last 60 Days': [moment().subtract(59, 'days').startOf('day'), moment().endOf('day')],
			'This Month': [moment().startOf('month'), moment().endOf('month')],
			'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
	 }
  }, submitDateRange);
	updateDateRangeLabel(start, stop);
});
