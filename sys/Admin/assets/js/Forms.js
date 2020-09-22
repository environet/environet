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