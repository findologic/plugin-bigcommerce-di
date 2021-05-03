window.bootstrap = require('bootstrap/dist/js/bootstrap.bundle.js');

document.addEventListener('DOMContentLoaded', () => {
    (document.querySelectorAll('.alert .delete') || []).forEach(($delete) => {
        var $notification = $delete.parentNode;

        $delete.addEventListener('click', () => {
            $notification.parentNode.removeChild($notification);
        });
    });
});

document.querySelector("#config-form").addEventListener("submit", function(e){
    document.querySelector('.loader-wrapper').style.display = 'inline';
    document.querySelector('.save-text').style.display = 'none';
});
