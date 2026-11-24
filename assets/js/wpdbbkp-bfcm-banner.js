(function ($) {
	'use strict';

	var root = document.getElementById('wpdbbkp-bfcm-banner');
	if (!root || typeof wpdbbkpBfcm === 'undefined') {
		return;
	}

	var end = parseInt(wpdbbkpBfcm.end, 10) * 1000;
	var units = {
		days: root.querySelector('[data-unit="days"]'),
		hours: root.querySelector('[data-unit="hours"]'),
		mins: root.querySelector('[data-unit="mins"]'),
		secs: root.querySelector('[data-unit="secs"]')
	};

	function pad(value) {
		return value < 10 ? '0' + value : String(value);
	}

	function tick() {
		var remaining = Math.max(0, end - Date.now());
		var total = Math.floor(remaining / 1000);
		var days = Math.floor(total / 86400);
		var hours = Math.floor((total % 86400) / 3600);
		var mins = Math.floor((total % 3600) / 60);
		var secs = total % 60;

		if (units.days) {
			units.days.textContent = pad(days);
		}
		if (units.hours) {
			units.hours.textContent = pad(hours);
		}
		if (units.mins) {
			units.mins.textContent = pad(mins);
		}
		if (units.secs) {
			units.secs.textContent = pad(secs);
		}

		if (remaining <= 0) {
			if (timer) {
				window.clearInterval(timer);
			}
			if (root.parentNode) {
				root.parentNode.removeChild(root);
			}
		}
	}

	var timer = window.setInterval(tick, 1000);
	tick();

	var codeButton = root.querySelector('.wpdbbkp-bfcm__code');
	if (codeButton) {
		codeButton.addEventListener('click', function () {
			var code = wpdbbkpBfcm.code || 'BFCM2026';
			var markCopied = function () {
				codeButton.classList.add('is-copied');
				window.setTimeout(function () {
					codeButton.classList.remove('is-copied');
				}, 1600);
			};

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(code).then(markCopied).catch(function () {
					fallbackCopy(code, markCopied);
				});
				return;
			}

			fallbackCopy(code, markCopied);
		});
	}

	function fallbackCopy(code, done) {
		var field = document.createElement('textarea');
		field.value = code;
		field.setAttribute('readonly', 'readonly');
		field.style.position = 'fixed';
		field.style.left = '-9999px';
		document.body.appendChild(field);
		field.select();
		try {
			document.execCommand('copy');
			done();
		} catch (error) {
			// Leave the code visible in the banner if the browser blocks the copy.
		}
		document.body.removeChild(field);
	}

	var dismiss = root.querySelector('.wpdbbkp-bfcm__dismiss');
	if (dismiss) {
		dismiss.addEventListener('click', function () {
			window.clearInterval(timer);
			$.post(wpdbbkpBfcm.ajaxUrl, {
				action: 'wpdbbkp_bfcm_dismiss_banner',
				nonce: wpdbbkpBfcm.nonce
			});
			if (root.parentNode) {
				root.parentNode.removeChild(root);
			}
		});
	}
})(jQuery);
