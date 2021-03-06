/**
 * JS used for AJAX requests on the single license detail page.
 *
 * @author Iron Bound Designs
 * @since 1.0
 */

jQuery(document).ready(function ($) {

	$("#remote-activate-submit").tooltip();

	var status_span = $(".status span");

	status_span.editable({
		type       : 'select',
		pk         : ITELIC.key,
		name       : 'status',
		source     : ITELIC.statuses,
		showbuttons: false,
		placement  : "top",
		title      : ' ',
		mode       : 'inline',
		url        : function (params) {
			return editable_ajax(params);
		},
		success    : function (response, newValue) {
			return editable_success_callback(response, newValue);
		}
	});

	var expiresArgs = {
		type       : 'date',
		pk         : ITELIC.key,
		name       : 'expires',
		placement  : "bottom",
		title      : ' ',
		showbuttons: false,
		clear      : false,
		mode       : 'inline',
		datepicker : {
			prevText   : '',
			nextText   : '',
			minDate    : 0,
			changeMonth: false,
			changeYear : false,
			showOn     : 'both',
			onSelect   : function () {
				$('.expires input.hasDatepicker').focus();
			}
		},
		viewformat : ITELIC.df,
		url        : function (params) {
			return editable_ajax(params);
		},
		success    : function (response, newValue) {

			if (newValue && Modernizr.touchevents) {
				newValue = $.datepicker.formatDate($(".expires h3").data('df'), new Date(newValue));
			}

			return editable_success_callback(response, newValue);
		}
	};

	if (Modernizr.touchevents && Modernizr.inputtypes.date) {
		expiresArgs.type = 'text';
		expiresArgs.tpl = '<input type="date">';

		$(document).on('blur', '.expires .editable-input input[type="date"]', function () {

			$(".expires .editableform").submit();
		});
	}

	$(".expires h3").editable(expiresArgs);


	$(".max-activations h3").editable({
		type       : 'number',
		pk         : ITELIC.key,
		name       : 'max',
		placement  : "bottom",
		title      : ' ',
		showbuttons: false,
		mode       : 'inline',
		clear      : false,
		url        : function (params) {
			return editable_ajax(params);
		},
		success    : function (response, newValue) {

			if (newValue == 0 || newValue == '') {
				newValue = 'Unlimited';
			}

			return editable_success_callback(response, newValue);
		}
	});

	/**
	 * Callback function that parses the WP Ajax response.
	 *
	 * @param response
	 * @param newValue
	 * @returns {*}
	 */
	function editable_success_callback(response, newValue) {
		if (!response.success) {
			alert(response.data.message);
			return false;
		} else {
			return {"newValue": newValue};
		}
	}

	/**
	 * Callback function that processes a change from editable
	 * and posts it to a WP Ajax handle.
	 *
	 * @param params
	 * @returns {$.promise|*}
	 */
	function editable_ajax(params) {
		var data = {
			action: 'itelic_admin_licenses_single_update',
			key   : ITELIC.key,
			prop  : params.name,
			val   : params.value,
			nonce : ITELIC.update_nonce
		};

		return $.post(ITELIC.ajax, data);
	}

	status_span.on('shown', function (e, editable) {
		$(this).closest('.status').addClass('status-hovered');
	});

	status_span.on('hidden', function (e, editable) {
		$(this).closest('.status').removeClass('status-hovered');
	});

	status_span.on('save', function (e, params) {
		var container = $(this).closest('.status');

		$.each($.parseJSON(ITELIC.statuses), function (key, value) {
			container.removeClass('status-' + key);
		});

		container.addClass('status-' + params.newValue);

		var remoteActivate = $("#remote-activate-submit");

		if (params.newValue == 'active') {
			if (remoteActivate.hasClass('button-disabled')) {
				remoteActivate.removeClass('button-disabled');
				remoteActivate.prop('title', '');
				$("#remote-activate-location").prop('disabled', false);
			}
		} else {

			if (!remoteActivate.hasClass('button-disabled')) {
				remoteActivate.addClass('button-disabled');
				remoteActivate.prop('title', remoteActivate.data('tip'));
				/*remoteActivate.tooltip();*/
				$("#remote-activate-location").prop('disabled', true);
			}

		}
	});


	$(document).on('keypress', '#remote-activate-location', function (e) {

		var keycode = (e.keyCode ? e.keyCode : e.which);

		if (keycode == '13') {
			$("#remote-activate-submit").click();
		}
	});

	$(document).on('click', '#remote-activate-submit', function (e) {

		e.preventDefault();

		if ($(this).hasClass('button-disabled')) {
			return;
		}

		var data = {
			action  : 'itelic_admin_licenses_single_activate',
			location: $("#remote-activate-location").val(),
			key     : $("#remote-activate-key").val(),
			nonce   : $("#_wpnonce").val()
		};

		var button = $(this);
		button.prop('disabled', true);

		$.post(ITELIC.ajax, data, function (response) {

			button.prop('disabled', false);

			if (!response.success) {

				var message = response.data.message;

				var $notice = $('<div class="notice notice-error notice-alt"><p>' + message + '</p></div>');
				$notice.css({
					display: 'none'
				});

				$notice.insertAfter("#remote-activate-submit");

				$notice.slideDown();

				setTimeout(function () {
					$notice.slideUp(400, function () {
						$(this).remove();
					})
				}, 5000);

			} else {
				var html = response.data.html;

				$("#activations-table tbody:last-child").append(html);

				$("#remote-activate-location").val("");
			}
		});
	});

	$(document).on('click', '.deactivate', function (e) {

		e.preventDefault();

		var link = $(this);

		var i = 0;
		var originalText = link.text();
		link.text(ITELIC.disabling);

		/**
		 * Animate the working text to append 3 '.'
		 * then revert.
		 *
		 * @type {number}
		 */
		var loading = setInterval(function () {

			link.append(".");
			i++;

			if (i == 4) {
				link.text(ITELIC.disabling);
				i = 0;
			}

		}, 500);


		var data = {
			action: 'itelic_admin_licenses_single_deactivate',
			id    : link.data('id'),
			key   : $("#remote-activate-key").val(),
			nonce : link.data('nonce')
		};

		$.post(ITELIC.ajax, data, function (response) {

			if (!response.success) {
				alert(response.data.message);
			} else {
				var html = response.data.html;

				var row = link.closest('tr');
				row.replaceWith(html);
			}

			clearTimeout(loading);
			link.text(originalText);
		});
	});

	$(document).on('click', '.remove-item', function (e) {

		e.preventDefault();

		var button = $(this);

		var degree = 0, timer;

		rotate();
		function rotate() {

			button.css({WebkitTransform: 'rotate(' + degree + 'deg)'});
			button.css({'-moz-transform': 'rotate(' + degree + 'deg)'});
			timer = setTimeout(function () {
				++degree;
				rotate();
			}, 1);
		}

		var data = {
			action: 'itelic_admin_licenses_single_delete',
			id    : button.data('id'),
			key   : $("#remote-activate-key").val(),
			nonce : button.data('nonce')
		};

		$.post(ITELIC.ajax, data, function (response) {

			if (!response.success) {
				alert(response.data.message);
			} else {
				var row = button.closest('tr');
				row.remove();
			}

			clearTimeout(timer);
			button.css({WebkitTransform: 'rotate(' + 0 + 'deg)'});
			button.css({'-moz-transform': 'rotate(' + 0 + 'deg)'});
		});
	});
});
