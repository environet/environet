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
		const filterFieldsSelector = 'input[type=search], input[type=date], select';
		const resetButton = filterForm.querySelector('input[type=reset]');

		const disableEmptyFilters = function(form) {
			const filterFields = filterForm.querySelectorAll(filterFieldsSelector + ', input[type=hidden]');
			filterFields.forEach(function (filterField) {
				if (filterField.tagName === 'SELECT') {
					if (filterField.selectedIndex === 0) {
						filterField.disabled = 'disabled';
					}
				} else {
					if (filterField.value === '') {
						filterField.disabled = 'disabled';
					}
				}
			});
		};

		resetButton.addEventListener('click', function(event) {
			event.preventDefault();
			const filterFields = filterForm.querySelectorAll(filterFieldsSelector);
			filterFields.forEach(function (filterField) {
				if (filterField.tagName === 'SELECT') {
					filterField.selectedIndex = 0;
				} else {
					filterField.value = '';
				}
			});
			disableEmptyFilters(filterForm);
			filterForm.submit();
		});

		filterForm.addEventListener('submit', function() {
			disableEmptyFilters(filterForm);
		});
	}
});


const accessRuleForm = document.getElementById('accessRuleForm');
if (accessRuleForm) {

	const operatorSelector = document.getElementById('accessRuleOperatorSelect');
	const pointSelector = document.getElementById('accessRulePointSelect');

	const updateAjaxUrls = function (selector) {
		Array.prototype.filter.call(document.querySelectorAll(selector), (select) => {
			const url = select.dataset.ajaxdefault;
			const concatChar = url.includes('?') ? '&' : '?';

			const operator = operatorSelector.value;

			let hasMeteo, hasHydro, hasAll;
			hasMeteo = hasHydro = hasAll = false;
			slimSelects.get(pointSelector).selected().forEach(function(value) {
				if (value === '*') hasAll = true;
				if (value.startsWith('hydro_')) hasHydro = true;
				if (value.startsWith('meteo_')) hasMeteo = true;
			});
			const type = (hasAll || (hasMeteo && hasHydro)) ? '' : (hasHydro ? 'hydro' : (hasMeteo ? 'meteo' : ''));

			select.setAttribute('data-ajax', url + concatChar + 'operator=' + operator + '&type=' + type);

			select.dispatchEvent(new CustomEvent('doSearch'));

			docReady(function () {
			});
		});
	}
	operatorSelector.addEventListener('change', function (event) {
		updateAjaxUrls('#accessRulePointSelect, #accessRulePropertySelect');
	});

	pointSelector.addEventListener('change', function(event) {
		updateAjaxUrls('#accessRulePropertySelect');
	});

	docReady(function () {
		operatorSelector.dispatchEvent(new Event('change'));
		pointSelector.dispatchEvent(new Event('change'));
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