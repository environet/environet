window.addEventListener('load', function() {
    Array.prototype.filter.call(document.getElementsByClassName('file-upload-form'), function(form) {

        form.querySelector('input[type="file"]').addEventListener('change', function() {
            form.submit();
        });

        form.querySelector('input[type="submit"]').addEventListener('click', function(e) {
            e.preventDefault();
            form.querySelector('input[type="file"]').click();
        });
    });
}, false);