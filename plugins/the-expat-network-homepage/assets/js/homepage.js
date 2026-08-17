(function () {
	'use strict';

	function toggleConditional(select, wrapper, input) {
		if (!select || !wrapper || !input) {
			return;
		}

		function sync() {
			var show = select.value === 'other';
			wrapper.hidden = !show;
			input.required = show;
			if (!show) {
				input.value = '';
				input.setCustomValidity('');
			}
		}

		select.addEventListener('change', sync);
		sync();
	}

	function initRevealMotion() {
		var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var homepages = Array.prototype.slice.call(document.querySelectorAll('.ten-homepage'));
		var items = Array.prototype.slice.call(document.querySelectorAll('.ten-homepage [data-ten-reveal]'));

		if (!items.length || reduceMotion || !('IntersectionObserver' in window)) {
			items.forEach(function (item) {
				item.classList.add('is-visible');
			});
			return;
		}

		homepages.forEach(function (homepage) {
			homepage.classList.add('ten-motion-enabled');
		});

		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					entry.target.classList.add('is-visible');
					observer.unobserve(entry.target);
				}
			});
		}, {
			rootMargin: '0px 0px -8% 0px',
			threshold: 0.08
		});

		items.forEach(function (item) {
			observer.observe(item);
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var errorSummary = document.querySelector('[data-ten-error-summary]');
		if (errorSummary) {
			errorSummary.focus();
		}

		document.querySelectorAll('.ten-candidate-form').forEach(function (form) {
			var situation = form.querySelector('#ten-current-situation');
			var wrapper = form.querySelector('[data-ten-other-wrapper]');
			var otherInput = form.querySelector('#ten-current-situation-other');
			var pathways = Array.prototype.slice.call(form.querySelectorAll('input[name="pathways[]"]'));
			var pathwayGroup = form.querySelector('#ten-pathways-group');

			toggleConditional(situation, wrapper, otherInput);

			if (pathways.length) {
				function validatePathways() {
					var checked = pathways.some(function (field) { return field.checked; });
					pathways[0].setCustomValidity(checked ? '' : 'Select at least one interest.');
					if (pathwayGroup) {
						if (checked) {
							pathwayGroup.removeAttribute('aria-invalid');
						} else {
							pathwayGroup.setAttribute('aria-invalid', 'true');
						}
					}
				}

				pathways.forEach(function (field) {
					field.addEventListener('change', validatePathways);
				});
				form.addEventListener('submit', validatePathways);
			}
		});

		document.querySelectorAll('.ten-partner-form').forEach(function (form) {
			var opportunity = form.querySelector('#ten-opportunity-type');
			var wrapper = form.querySelector('[data-ten-partner-other-wrapper]');
			var otherInput = form.querySelector('#ten-opportunity-type-other');
			toggleConditional(opportunity, wrapper, otherInput);
		});

		initRevealMotion();
	});
}());
