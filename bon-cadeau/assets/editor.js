/* global jQuery, wp, BC_EDITOR */
(function ($) {
	'use strict';

	var cfg = BC_EDITOR;
	var zones = (cfg.zones || []).map(function (z) { return $.extend({}, z); });
	var selected = null;
	var imageUrl = cfg.imageUrl;
	var $canvas = $('#bc-canvas');
	var $img = null;

	// Load fonts so the preview matches the final render.
	var css = '';
	$.each(cfg.fonts, function (id, f) {
		css += '@font-face{font-family:"bc-' + id + '";src:url("' + f.url + '");}';
	});
	$('<style>').text(css).appendTo('head');

	function uid() {
		return 'z' + Math.random().toString(36).slice(2, 8);
	}

	function sampleText(text) {
		return text.replace(/\{([a-z0-9_]+)\}/g, function (m, k) {
			return cfg.samples[k] !== undefined ? cfg.samples[k] : m;
		});
	}

	function scale() {
		return $img && $img[0].naturalWidth ? $img[0].clientWidth / $img[0].naturalWidth : 1;
	}

	function findZone(id) {
		for (var i = 0; i < zones.length; i++) {
			if (zones[i].id === id) { return zones[i]; }
		}
		return null;
	}

	/* ---------- Canvas ---------- */

	function renderCanvas() {
		$canvas.find('.bc-zone, img').remove();
		if (!imageUrl) {
			$canvas.find('.bc-empty').show();
			return;
		}
		$canvas.find('.bc-empty').hide();
		$img = $('<img>', { src: imageUrl, alt: '', draggable: false }).appendTo($canvas);
		if ($img[0].complete) {
			renderZones();
		} else {
			$img.on('load', renderZones);
		}
	}

	function renderZones() {
		$canvas.find('.bc-zone').remove();
		zones.forEach(function (z) {
			var $z = $('<div class="bc-zone"><div class="bc-zone-text"></div><span class="bc-handle" title="Largeur"></span></div>')
				.attr('data-id', z.id)
				.toggleClass('is-selected', z.id === selected);
			styleZone($z, z);
			$canvas.append($z);
		});
	}

	function styleZone($z, z) {
		var s = scale();
		$z.css({ left: z.x + '%', top: z.y + '%', width: z.w + '%' });
		$z.find('.bc-zone-text').text(sampleText(z.text)).css({
			fontFamily: '"bc-' + z.font + '", sans-serif',
			fontSize: (z.size * s) + 'px',
			lineHeight: cfg.lineHeight,
			color: z.color,
			textAlign: z.align,
			textTransform: z.uppercase ? 'uppercase' : 'none'
		});
	}

	function refreshZone(z) {
		styleZone($canvas.find('.bc-zone[data-id="' + z.id + '"]'), z);
	}

	/* ---------- Drag / resize ---------- */

	$canvas.on('pointerdown', '.bc-zone', function (e) {
		e.preventDefault();
		var $z = $(this);
		var z = findZone($z.data('id'));
		select(z.id);

		var resizing = $(e.target).hasClass('bc-handle');
		var rect = $canvas.find('img')[0].getBoundingClientRect();
		var startX = e.clientX, startY = e.clientY;
		var orig = { x: z.x, y: z.y, w: z.w };

		function move(ev) {
			var dx = (ev.clientX - startX) / rect.width * 100;
			var dy = (ev.clientY - startY) / rect.height * 100;
			if (resizing) {
				z.w = round(Math.max(1, Math.min(100 - z.x, orig.w + dx)));
			} else {
				z.x = round(Math.max(0, Math.min(100 - z.w, orig.x + dx)));
				z.y = round(Math.max(0, Math.min(99, orig.y + dy)));
			}
			refreshZone(z);
			fillPanel(z);
		}
		function up() {
			$(document).off('pointermove', move).off('pointerup', up);
		}
		$(document).on('pointermove', move).on('pointerup', up);
	});

	$canvas.on('pointerdown', function (e) {
		if (e.target === $canvas[0] || e.target.tagName === 'IMG') { select(null); }
	});

	function round(v) {
		return Math.round(v * 10) / 10;
	}

	// Arrow keys nudge the selected zone.
	$(document).on('keydown', function (e) {
		if (!selected || $(e.target).is('input, textarea, select')) { return; }
		var z = findZone(selected);
		var step = e.shiftKey ? 1 : 0.1;
		var map = { ArrowLeft: ['x', -step], ArrowRight: ['x', step], ArrowUp: ['y', -step], ArrowDown: ['y', step] };
		if (!map[e.key]) { return; }
		e.preventDefault();
		z[map[e.key][0]] = round(Math.max(0, Math.min(100, z[map[e.key][0]] + map[e.key][1])));
		refreshZone(z);
		fillPanel(z);
	});

	/* ---------- Side panel ---------- */

	var $panel = $('#bc-zone-panel');
	var inputs = {
		text: $('#bc-z-text'), font: $('#bc-z-font'), size: $('#bc-z-size'), color: $('#bc-z-color'),
		align: $('#bc-z-align'), uppercase: $('#bc-z-uppercase'), x: $('#bc-z-x'), y: $('#bc-z-y'), w: $('#bc-z-w')
	};

	function select(id) {
		selected = id;
		$canvas.find('.bc-zone').each(function () {
			$(this).toggleClass('is-selected', $(this).data('id') === id);
		});
		var z = id ? findZone(id) : null;
		$panel.find('.bc-zone-fields').prop('hidden', !z);
		$panel.find('.bc-none').toggle(!z);
		if (z) { fillPanel(z); }
	}

	function fillPanel(z) {
		$.each(inputs, function (key, $in) {
			if ($in.is(':focus')) { return; }
			if (key === 'uppercase') { $in.prop('checked', !!z.uppercase); } else { $in.val(z[key]); }
		});
	}

	$.each(inputs, function (key, $in) {
		$in.on('input change', function () {
			var z = findZone(selected);
			if (!z) { return; }
			if (key === 'uppercase') {
				z[key] = $in.is(':checked');
			} else if (['size', 'x', 'y', 'w'].indexOf(key) !== -1) {
				var v = parseFloat($in.val());
				if (isNaN(v)) { return; }
				z[key] = v;
			} else {
				z[key] = $in.val();
			}
			refreshZone(z);
		});
	});

	$('#bc-z-insert').on('change', function () {
		var key = $(this).val();
		$(this).val('');
		if (!key || !selected) { return; }
		var el = inputs.text[0];
		var token = '{' + key + '}';
		var start = el.selectionStart || el.value.length, end = el.selectionEnd || el.value.length;
		el.value = el.value.slice(0, start) + token + el.value.slice(end);
		el.focus();
		el.setSelectionRange(start + token.length, start + token.length);
		inputs.text.trigger('input');
	});

	$('#bc-add-zone').on('click', function () {
		if (!imageUrl) { return; }
		var z = { id: uid(), text: '{valeur} €', x: 10, y: 10, w: 40, size: Math.round(($img[0].naturalHeight || 800) / 15), font: 'dejavu-sans', color: '#222222', align: 'left', uppercase: false };
		zones.push(z);
		renderZones();
		select(z.id);
	});

	$('#bc-z-duplicate').on('click', function () {
		var z = findZone(selected);
		if (!z) { return; }
		var copy = $.extend({}, z, { id: uid(), y: Math.min(95, z.y + 5) });
		zones.push(copy);
		renderZones();
		select(copy.id);
	});

	$('#bc-z-delete').on('click', function () {
		if (!selected || !window.confirm(cfg.i18n.confirmDel)) { return; }
		zones = zones.filter(function (z) { return z.id !== selected; });
		select(null);
		renderZones();
	});

	/* ---------- Image ---------- */

	var frame;
	$('#bc-choose-image').on('click', function () {
		if (!frame) {
			frame = wp.media({ title: cfg.i18n.chooseImage, button: { text: cfg.i18n.useImage }, library: { type: 'image' }, multiple: false });
			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				$('#bc-image-id').val(att.id);
				imageUrl = att.originalImageURL || att.url;
				renderCanvas();
			});
		}
		frame.open();
	});

	/* ---------- Preview & save ---------- */

	$('#bc-preview').on('click', function () {
		var $btn = $(this).prop('disabled', true);
		$.post(cfg.ajaxUrl, {
			action: 'bc_preview',
			_ajax_nonce: cfg.nonce,
			image_id: $('#bc-image-id').val(),
			zones: JSON.stringify(zones)
		}).done(function (res) {
			if (res && res.success) {
				$('#bc-preview-modal').prop('hidden', false).find('img').attr('src', res.data);
			} else {
				window.alert((res && res.data) || cfg.i18n.previewErr);
			}
		}).fail(function () {
			window.alert(cfg.i18n.previewErr);
		}).always(function () {
			$btn.prop('disabled', false);
		});
	});

	$('#bc-preview-modal').on('click', function (e) {
		if (e.target === this || $(e.target).hasClass('bc-modal-close')) { $(this).prop('hidden', true); }
	});

	$('#bc-template-form').on('submit', function () {
		$('#bc-zones').val(JSON.stringify(zones));
	});

	var resizeTimer;
	$(window).on('resize', function () {
		clearTimeout(resizeTimer);
		resizeTimer = setTimeout(renderZones, 100);
	});

	renderCanvas();
}(jQuery));
