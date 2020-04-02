import 'slim-select/dist/slimselect.min.css';
import SlimSelect from 'slim-select';

Array.prototype.filter.call(document.querySelectorAll('select[multiple]'), (select) => {
    new SlimSelect({select});
});
