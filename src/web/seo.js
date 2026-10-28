// Character counters and the search result preview of the SEO field.
(function () {
	function counter(input) {
		if (input.dataset.mlseoCounted) return;
		input.dataset.mlseoCounted = '1';

		const max = parseInt(input.dataset.mlseoCount, 10);
		const el = document.createElement('div');
		el.className = 'mlseo-count';
		input.insertAdjacentElement('afterend', el);

		const field = input.closest('[data-mlseo-field]');
		const update = () => {
			let len = input.value.length;
			let label = '';
			// the title counts with what the pattern adds
			if (field && 'mlseoTitle' in input.dataset) {
				const title = input.value || field.dataset.defaultTitle || '';
				len = (
					field.dataset.titlePrefix +
					title +
					field.dataset.titleSuffix
				).length;
				label = ' (full title)';
			} else if (!input.value) {
				el.textContent = '';
				return;
			}

			el.textContent = `${len} / ${max}${label}`;
			el.classList.toggle('mlseo-count--over', len > max);
		};

		input.addEventListener('input', update);
		update();
	}

	function preview(field) {
		if (field.dataset.mlseoPreviewed) return;
		field.dataset.mlseoPreviewed = '1';

		const title = field.querySelector('[data-mlseo-title]');
		const description = field.querySelector('[data-mlseo-description]');
		const titleEl = field.querySelector('.mlseo-preview__title');
		const descEl = field.querySelector('.mlseo-preview__description');
		if (!title || !titleEl) return;

		const update = () => {
			titleEl.textContent =
				field.dataset.titlePrefix +
				(title.value || field.dataset.defaultTitle) +
				field.dataset.titleSuffix;
			descEl.textContent =
				description.value || field.dataset.defaultDescription || '';
		};

		title.addEventListener('input', update);
		description.addEventListener('input', update);
		update();
	}

	function init(root) {
		root.querySelectorAll('[data-mlseo-count]').forEach(counter);
		root.querySelectorAll('[data-mlseo-field]').forEach(preview);
	}

	init(document);
	// slideouts and element editors add fields later
	new MutationObserver(() => init(document)).observe(document.body, {
		childList: true,
		subtree: true,
	});
})();
