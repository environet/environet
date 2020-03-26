import {Cookie} from "./Cookie";

export class Dropdown {

	static init() {
		const bootstrapDropdowns = document.querySelectorAll('.dropdown');
		bootstrapDropdowns.forEach((dropdownElement) => {
			const dropdownMenu = dropdownElement.querySelector('.dropdown-menu');
			dropdownElement.addEventListener('click', () => {
				dropdownElement.classList.toggle('show');
				dropdownMenu.classList.toggle('show');
			});
		});

		const sidebarDropdowns = document.querySelectorAll('#sidebar li.sub-menu');
		const sidebarDropdownsOpen = Cookie.get('sidebarDropdownsOpen') && JSON.parse(Cookie.get('sidebarDropdownsOpen')) || {};

		sidebarDropdowns.forEach((dropdownElement) => {
			const toggle = dropdownElement.querySelector('a');
			const id = dropdownElement.getAttribute('data-id')
			const subMenu = dropdownElement.querySelector('ul.sub');

			if (sidebarDropdownsOpen[id]) {
				subMenu.classList.add('show');
			}

			toggle.addEventListener('click', () => {
				subMenu.classList.toggle('show');
				sidebarDropdownsOpen[id] = subMenu.classList.contains('show');

				Cookie.set('sidebarDropdownsOpen', JSON.stringify(sidebarDropdownsOpen));
			});
		});
	}
}

Dropdown.init();