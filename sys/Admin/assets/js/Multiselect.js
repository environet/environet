import 'slim-select/dist/slimselect.css';
import SlimSelect from 'slim-select';

window.slimSelects = {
	_storage: new WeakMap(),
	put: function (element, obj) {
		this._storage.set(element, obj);
	},
	get: function (element) {
		return this._storage.get(element);
	},
	has: function (element) {
		return this._storage.has(element);
	},
	remove: function (element) {
		return this._storage.delete(element);
	}
}

Array.prototype.filter.call(document.querySelectorAll('select[multiple]'), (select) => {

	const options = {
		select: select,
	};
	if (typeof select.dataset.ajax !== 'undefined') {

		options.ajax = function (search, callback) {
			let url = select.dataset.ajax;
			if (url.indexOf('?') !== -1) {
				url = url + '&search=' + encodeURIComponent(search)
			} else {
				url = url + '?search=' + encodeURIComponent(search)
			}
			fetch(url)
				.then((response) => response.json())
				.then(function (json) {
					let data = [];
					data.push({value: '*', text: ' - All - '})
					for (let i = 0; i < json.length; i++) {
						data.push({ value: json[i].value, text: json[i].name })
					}
					callback(data);
				})
				.then(function() {
					select.dispatchEvent(new CustomEvent('ajaxLoaded'));
				})
				.catch(function (error) {
					callback(false);
				})
		}
	}
	const sSelect = new SlimSelect(options);
	slimSelects.put(select, sSelect);

	select.addEventListener('doSearch', function() {
		sSelect.search(' '); //Hack to change empty value, and trigger ajax
	});
	select.addEventListener('clear', function() {
		sSelect.set([]); //Hack to change empty value, and trigger ajax
	});
	select.addEventListener('initValue', function(event) {
		select.dispatchEvent(new CustomEvent('clear'));
		select.dispatchEvent(new CustomEvent('doSearch'));

		const onAjaxLoaded =  function() {
			if (typeof select.dataset.value !== 'undefined') {
				sSelect.set(select.dataset.value.split(','));
			}
			select.removeEventListener('ajaxLoaded', onAjaxLoaded);
		}
		select.addEventListener('ajaxLoaded', onAjaxLoaded);

	});

});
