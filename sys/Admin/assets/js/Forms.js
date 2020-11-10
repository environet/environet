import {docReady} from './helpers';

window.addEventListener('load', function () {
	Array.prototype.filter.call(document.getElementsByClassName('needs-validation'), function (form) {
		form.addEventListener('submit', function (event) {
			if (form.checkValidity() === false) {
				event.preventDefault();
				event.stopPropagation();
			}
			form.classList.add('was-validated');
		}, false);
	});
}, false);


docReady(function () {
	const filterForm = document.querySelector('form.filterForm');
	if (filterForm) {
		const resetButton = filterForm.querySelector('input[type=reset]');
		const filterFields = filterForm.querySelectorAll('input[type=search], select');
		resetButton.addEventListener('click', function(event) {
			event.preventDefault();
			filterFields.forEach(function (filterField) {
				if (filterField.tagName === 'SELECT') {
					filterField.selectedIndex = 0
				} else {
					filterField.value = '';
				}
			});
			filterForm.submit();
		});
	}
});


const accessRuleForm = document.getElementById('accessRuleForm');
if (accessRuleForm) {

	const operatorSelector = document.getElementById('accessRuleOperatorSelect');
	operatorSelector.addEventListener('change', function (event) {
		Array.prototype.filter.call(document.querySelectorAll('#accessRuleForm select[data-ajaxdefault]'), (select) => {
			const url = select.dataset.ajaxdefault;
			select.setAttribute('data-ajax', url + '?operator=' + operatorSelector.value);

			select.dispatchEvent(new CustomEvent('clear'));
			select.dispatchEvent(new CustomEvent('doSearch'));

			docReady(function () {
			});
		});


	});

	docReady(function () {
		operatorSelector.dispatchEvent(new Event('change'));
		Array.prototype.filter.call(document.querySelectorAll('#accessRuleForm select[data-ajaxdefault]'), (select) => {
			const event = new CustomEvent('initValue', {
				detail: {
					operator: operatorSelector.value
				}
			})
			select.dispatchEvent(event);
		});
	});

}