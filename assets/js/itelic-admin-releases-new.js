(function ($) {
	'use strict';

	Dropzone.autoDiscover = false

	var file_frame, image_data;

	var product = $("#product");

	/**
	 * When a release type is selected, display the main release editor.
	 */
	$(".release-types input").change(function () {

		var $main = $(".main-editor");
		var $securityMessage = $("#security-message-row");

		if ($(this).is(':checked')) {

			var type = $(this).val();

			$('.release-help').css('opacity', 1);
			$('.release-help p').slideUp();
			$('.release-help .release-help-' + type).slideDown();

			if ($(this).val() == 'security') {

				if ($main.css('opacity') == 0) {
					$securityMessage.show();
				} else {
					$securityMessage.slideDown();
				}
			} else {
				$securityMessage.slideUp();
			}

			$main.css({
				opacity: 1
			});
		}
	});

	product.select2();

	/**
	 * When a product is selected, display the previous version on the version input.
	 */
	product.change(function () {

		var $this = $(this);

		if ($this.val().length > 0) {

			var $selected = $(this[this.selectedIndex]);

			var prevVersionText = ITELIC.prevVersion;

			var prevVersion = $selected.data('version');

			$("#version").data('prev', prevVersion);

			if (prevVersion.length == 0) {
				prevVersion = '–';
			}

			prevVersionText = prevVersionText.replace('%s', prevVersion);

			$("#prev-version").text(prevVersionText).css('opacity', 1);
		} else {
			$("#prev-version").css('opacity', 0);
		}
	});

	$("#version").change(function () {

		var newVersion = $(this).val();
		var currentVersion = $(this).data('prev');

		if (currentVersion) {

			if ($.versioncompare(newVersion, currentVersion) != 1) {
				$(this).css({
					color: '#dd3d36'
				});

				disable_buttons();
			} else {
				enable_buttons();

				$(this).css({
					color: '#3d4142'
				});
			}
		}
	});

	$('.tip').tooltip();

	/**
	 * Disable the save buttons.
	 *
	 * @since 1.0
	 */
	function disable_buttons() {
		$("#draft").attr('disabled', true);
		$("#release").attr('disabled', true);
	}

	/**
	 * Enable the save buttons.
	 *
	 * @since 1.0
	 */
	function enable_buttons() {
		$("#draft").attr('disabled', false);
		$("#release").attr('disabled', false);
	}

	var uploadContainer = $(".upload-container");
	var uploadProgress = $(".upload-inputs progress");
	var uploadLabel = $(".upload-inputs label");

	/**
	 * When the trash file icon is clicked,
	 * remove the currently listed file.
	 */
	$('.trash-file').click(function (e) {

		uploadLabel.text(ITELIC.uploadLabel);
		$("#upload-file").val('');
		$(".trash-file").css('display', 'none');
		$(".dz-message").show();

		uploadProgress.hide();
		dropzoneFile.removeAllFiles(true);
		dropzoneFile.options.maxFiles = 1;
	});

	var dropzoneFile;

	uploadContainer.dropzone({
		url            : ajaxurl,
		params         : {
			action: 'itelic_handle_release_file_upload',
			nonce : ITELIC.nonce
		},
		paramName      : "file",
		maxFiles       : 1,
		acceptedFiles  : 'application/zip,application/octet-stream',
		clickable      : false,
		previewTemplate: '<div class="dz-preview hidden" style="display:none!important;"></div>',
		init           : function () {

			dropzoneFile = this;

			this.on('addedfile', function (file) {

				if (!$("#upload-file").val().length) {
					uploadLabel.text(file.name);
				}

				$(".dz-message").css('display', 'none');
				uploadProgress.show();
			});

			this.on('uploadprogress', function (file, progress) {
				uploadProgress.val(progress);
			});

			this.on('error', function (file, errorMessage) {

				this.removeFile(file);

				if (!$("#upload-file").val().length) {
					uploadLabel.text(ITELIC.uploadLabel);
				}

				uploadProgress.hide();
				uploadProgress.val(0);

				alert(errorMessage);
			});

			this.on('complete', function (file) {

				if (file.status == 'success') {

					var ID = file.xhr.responseText;

					$("#upload-file").val(ID);

					$(".trash-file").css('display', 'inline');
				}
			});
		}

	});

	/**
	 * When the upload inputs link is clicked, launch the media uploader.
	 */
	$(".upload-inputs a").click(function (e) {

		e.preventDefault();

		/**
		 * If an instance of file_frame already exists, then we can open it
		 * rather than creating a new instance.
		 */
		if (undefined !== file_frame) {

			file_frame.open();
			return;

		}

		/**
		 * If we're this far, then an instance does not exist, so we need to
		 * create our own.
		 *
		 * Here, use the wp.media library to define the settings of the Media
		 * Uploader implementation by setting the title and the upload button
		 * text. We're also not allowing the user to select more than one image.
		 */
		file_frame = wp.media.frames.file_frame = wp.media({
			title   : ITELIC.uploadTitle,
			button  : {
				text: ITELIC.uploadButton
			},
			multiple: false,
			library : {
				type: ['application/zip', 'application/octet-stream']
			}
		});

		/**
		 * Setup an event handler for what to do when an image has been
		 * selected.
		 */
		file_frame.on('select', function () {

			image_data = file_frame.state().get('selection').first().toJSON();

			$("#upload-file").val(image_data.id);
			$(".upload-inputs label").text(image_data.filename);
			$(".trash-file").css('display', 'inline');

			$(".dz-message").hide();
			dropzoneFile.options.maxFiles = 0;
		});

		// Now display the actual file_frame
		file_frame.open();

	});
})(jQuery);

/*
 *  jQuery version compare plugin
 *
 *  Usage:
 *    $.versioncompare(version1[, version2 = jQuery.fn.jquery])
 *
 *  Example:
 *    console.log($.versioncompare("1.4", "1.6.4"));
 *
 *  Return:
 *    0 if two params are equal
 *    1 if the second is lower
 *   -1 if the second is higher
 *
 *  Licensed under the MIT:
 *  http://www.opensource.org/licenses/mit-license.php
 *
 *  Copyright (c) 2011, Nobu Funaki @zuzara
 */
(function ($) {
	$.versioncompare = function (version1, version2) {

		if ('undefined' === typeof version1) {
			throw new Error("$.versioncompare needs at least one parameter.");
		}
		version2 = version2 || $.fn.jquery;
		if (version1 == version2) {
			return 0;
		}
		var v1 = normalize(version1);
		var v2 = normalize(version2);
		var len = Math.max(v1.length, v2.length);

		for (var i = 0; i < len; i++) {
			v1[i] = v1[i] || 0;
			v2[i] = v2[i] || 0;

			if (v1[i] == v2[i]) {
				continue;
			}

			return v1[i] > v2[i] ? 1 : -1;
		}
		return 0;
	};
	function normalize(version) {
		return $.map(version.split('.'), function (value) {
			return parseInt(value, 10);
		});
	}
}(jQuery));