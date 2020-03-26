const menuActiveClass = 'active';

const toggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
import { Cookie } from './Cookie';

let init = () => {
	if (toggle && sidebar) {
		let isActive = Cookie.get('menuActive') === 'true';
		if (isActive) {
			sidebar.classList.add(menuActiveClass);
		}
		toggle.addEventListener('click', toggleMenu);
	}

};

let toggleMenu = (e) => {
	e.preventDefault();
	sidebar.classList.toggle(menuActiveClass);

	Cookie.set('menuActive', sidebar.classList.contains(menuActiveClass));

};

init();
