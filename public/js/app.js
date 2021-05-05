window.bootstrap = require('bootstrap/dist/js/bootstrap.bundle.js');

const configForm = document.querySelector("#config-form");
if(configForm) {
    configForm.addEventListener("submit", function(e){
        document.querySelector('.loader-wrapper').style.display = 'inline';
        document.querySelector('.save-text').style.display = 'none';
    });
}
