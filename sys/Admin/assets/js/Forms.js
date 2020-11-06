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
	Array.prototype.filter.call(document.querySelectorAll('.listSearch'), (searchContainer) => {
		const searchInput = searchContainer.querySelector('input')
		const button = searchContainer.querySelector('.clearSearch');
		if (button) {
			searchInput.addEventListener('change', function() {
				if (searchInput.value !== '') {
					button.classList.remove('d-none')
				} else {
					button.classList.add('d-none');
				}
			});
		}
		searchInput.addEventListener('keyup', function() {
			searchInput.dispatchEvent(new Event('change'));
		});
		searchInput.dispatchEvent(new Event('change'));

		button.addEventListener('click', function() {
			searchInput.value = '';
			searchInput.dispatchEvent(new Event('change'));
			searchInput.closest('form').submit();
		});
	});
})


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