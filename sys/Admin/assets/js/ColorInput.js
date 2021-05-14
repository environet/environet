import { docReady } from './helpers';

docReady(function () {
	const colorPreviewGroups = document.querySelectorAll('.colorPreviewGroup');
	if (colorPreviewGroups.length) {
		colorPreviewGroups.forEach(function (colorPreviewGroup) {
			const input = colorPreviewGroup.querySelector('input');
			const preview = colorPreviewGroup.querySelector('.colorPreview');

			input.addEventListener('change', function (event) {
				preview.style.backgroundColor = '#' + input.value;
			});
			input.addEventListener('keyup', function (event) {
				input.dispatchEvent(new Event('change'));
			});
			input.dispatchEvent(new Event('change'));
		});
	}
});