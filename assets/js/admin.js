(function($) {
	'use strict';

	$(document).ready(function() {
		// Toggle logging
		$('#logging_enabled').on('change', function() {
			var enabled = $(this).is(':checked');
			// Optionally auto-save without page reload
			console.log('Logging enabled:', enabled);
		});

		// Log refresh (placeholder for future enhancement)
		var autoRefreshInterval = null;

		$('.refresh-logs').on('click', function() {
			location.reload();
		});
	});
})(jQuery);
