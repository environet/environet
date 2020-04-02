import '../scss/app.scss';
import './sidebar.js';
import './Dropdown.js';
import './Forms.js';
import './FileUpload.js';
import './Multiselect.js';

const logoutLinks = document.querySelectorAll('.logout-link');
logoutLinks.forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        document.getElementById('logout-form').submit();
    });
});
