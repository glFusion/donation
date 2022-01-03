$(document).ready(function(){
	$('.donation_datepicker').each(function(i, obj) {
		var id = $(this).attr('id');
		donation_datetimepicker_datepicker(id);
	});
	$('.donation_timepicker').each(function(i, obj) {
		var id = $(this).attr('id');
		donation_datetimepicker_timepicker(id);
	});
});
function donation_datetimepicker_datepicker( selector ) {
	var currentDT = $("#"+selector).val();
	$('#'+selector).val( currentDT );
	$('#'+selector).datetimepicker({
		lazyInit: true,
		value:currentDT,
		format:'Y-m-d',
		timepicker: false,
	});
}
function donation_datetimepicker_timepicker( selector ) {
	var currentDT = $("#"+selector).val();
	$('#'+selector).val( currentDT );
	$('#'+selector).datetimepicker({
		lazyInit: true,
		value:currentDT,
		format:'H:i',
		datepicker: false,
		step: 15,
	});
}
