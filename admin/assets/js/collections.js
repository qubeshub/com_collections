/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

Hubzero.submitbutton = function(task) {
	$(document).trigger('editorSave');

	var frm = document.getElementById('item-form');

	if (frm) {
		if (task == 'cancel' || document.formvalidator.isValid(frm)) {
			Hubzero.submitform(task, frm);
		} else {
			alert(frm.getAttribute('data-invalid-msg'));
		}
	}
}

jQuery(document).ready(function ($) {
	var attach = $("#ajax-uploader");

	$("#ajax-uploader-list").sortable({
		handle: '.asset-handle'
	});

	if (attach.length) {
		var linkr = $('#link-adder');

		$('#ajax-uploader-list')
			.on('click', 'a.delete', function (e){
				e.preventDefault();
				if ($(this).attr('data-id')) {
					$.get($(this).attr('href'), {}, function(data) {});
				}
				$(this).parent().parent().remove();
			});

		if (linkr.length > 0) {
			linkr.append(
				'<div class="linker">' +
					'<div class="linker-button"><span>' + linkr.attr('data-txt-instructions') + '</span></div>' + 
				'</div>'
			);

			$('.linker-button').on('click', function(){
				var i = $('.item-asset').length + 1000;
				$('#ajax-uploader-list').append(
					'<p class="item-asset">' +
						'<span class="asset-handle"></span>' +
						'<span class="asset-file">' +
							'<input type="text" name="assets[' + i + '][filename]" size="35" value="http://" placeholder="http://" />' +
						'</span>' +
						'<span class="asset-description">' +
							'<input type="hidden" name="assets[' + i + '][type]" value="link" />' +
							'<input type="hidden" name="assets[' + i + '][id]" value="0" />' +
							'<a class="delete" href="' + linkr.attr('data-base') + '" data-id="" title="' + linkr.attr('data-txt-delete') + '">' + linkr.attr('data-txt-delete') + '</a>' +
						'</span>' +
					'</p>'
				);
			});
		}

		var uploader = new qq.FileUploader({
			element: attach[0],
			action: attach.attr("data-action"),
			params: {dir: $('#field-dir').val(), i: $('.item-asset').length},
			multiple: true,
			debug: true,
			template: '<div class="qq-uploader">' +
						'<div class="qq-upload-button"><span>' + attach.attr('data-txt-instructions') + '</span></div>' + 
						'<div class="qq-upload-drop-area"><span>' + attach.attr('data-txt-instructions') + '</span></div>' +
						'<ul class="qq-upload-list"></ul>' + 
					'</div>',
			onComplete: function(id, file, response) {
				if (response.id != $('#field-dir').val()) {
					$('#field-id').val(response.id);
					$('#field-dir').val(response.id);

					uploader.setParams({dir: $('#field-dir').val()});
				}

				// HTML entities had to be encoded for the JSON or IE 8 went nuts. So, now we have to decode it.
				response.html = response.html.replace(/&gt;/g, '>');
				response.html = response.html.replace(/&lt;/g, '<');
				$('#ajax-uploader-list').append(response.html);
			}
		});
	}
});
