import '../scss/app.scss';
import './sidebar.js';
import './Dropdown.js';

const logoutLinks = document.querySelectorAll('.logout-link');
logoutLinks.forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        document.getElementById('logout-form').submit();
    });
});