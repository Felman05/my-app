document.addEventListener('DOMContentLoaded', function () {
    var tabs = document.querySelectorAll('.auth-tab');
    var frames = document.querySelectorAll('.auth-frame');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = tab.getAttribute('data-target');

            tabs.forEach(function (item) {
                item.classList.remove('active');
            });

            frames.forEach(function (frame) {
                frame.classList.remove('active');
            });

            tab.classList.add('active');

            var selected = document.getElementById(target);
            if (selected) {
                selected.classList.add('active');
            }
        });
    });
});
