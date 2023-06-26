import 'tom-select/dist/css/tom-select.min.css';
import TomSelect from "tom-select";

window.tomSelects = {
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
	},
}

Array.prototype.filter.call(document.querySelectorAll('select[multiple]'), (select) => {

	const options = {
		plugins: {
			remove_button: {
				title: 'Remove this item',
			},
		},
	};
	if (typeof select.dataset.ajax !== 'undefined') {
		options.load = function (query, callback) {
			let url = select.dataset.ajax;
			if (url.indexOf('?') !== -1) {
				url = url + '&search=' + encodeURIComponent(query)
			} else {
				url = url + '?search=' + encodeURIComponent(query)
			}
			fetch(url)
				.then(response => response.json())
				.then(function (json) {
					let data = [];
					data.push({ value: '*', text: ' - All - ' })
					for (let i = 0; i < json.length; i++) {
						data.push({ value: json[i].value, text: json[i].name })
					}
					callback(data);
				})
				.then(json => {
					select.dispatchEvent(new CustomEvent('ajaxLoaded'));
				})
				.catch(() => {
					callback();
				});
		}
	}
	const tSelect = new TomSelect(select, options);
	tomSelects.put(select, tSelect);

	tSelect.on('item_add', function (newValue, item) {
		const currentValue = tSelect.getValue();
		if (currentValue instanceof Array && currentValue.length > 1) {
			let removeItems = [];
			currentValue.forEach(function (v) {
				if ((v !== '*' && newValue === '*') || (v === '*' && newValue !== '*')) {
					removeItems.push(v)
				}
			});

			removeItems.forEach(v => tSelect.removeItem(v));
		}
	})

	select.addEventListener('doSearch', function () {
		tSelect.load(''); //Hack to change empty value, and trigger ajax
	});
	select.addEventListener('clear', function () {
		tSelect.clear(); //Hack to change empty value, and trigger ajax
		tSelect.clearOptions(); //Hack to change empty value, and trigger ajax
	});
	select.addEventListener('initValue', function (event) {
		select.dispatchEvent(new CustomEvent('clear'));
		select.dispatchEvent(new CustomEvent('doSearch'));

		const onAjaxLoaded = function () {
			if (typeof select.dataset.value !== 'undefined') {
				tSelect.setValue(select.dataset.value.split(','));
			}
			select.removeEventListener('ajaxLoaded', onAjaxLoaded);
		}
		select.addEventListener('ajaxLoaded', onAjaxLoaded);

	});

});
