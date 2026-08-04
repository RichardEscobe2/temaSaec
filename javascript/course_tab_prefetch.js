// theme_saec — javascript/course_tab_prefetch.js
//
// Hover/focus prefetch for the course hero tab bar (Curso/Participantes/
// Calificaciones/Competencias — templates/components/course_view_header.mustache).
// Those tabs are real navigations to separate native Moodle pages
// (participants list, grade report, competencies tool), not in-page
// sections, so they can't be made "instant" without duplicating core
// functionality inside the theme. What this can safely do is warm the
// connection/start the fetch as soon as intent is likely (mouse enters or
// keyboard focus lands on the link), shaving the perceived wait on click —
// without touching core or requiring a build step (plain script, no jQuery,
// no AMD).
(function() {
    'use strict';

    var prefetched = {};

    function prefetch(url) {
        if (!url || prefetched[url]) {
            return;
        }
        prefetched[url] = true;
        var link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url;
        document.head.appendChild(link);
    }

    function onIntent(event) {
        var tab = event.target.closest ? event.target.closest('.saec-course-tabs__tab') : null;
        if (tab && tab.href) {
            prefetch(tab.href);
        }
    }

    document.addEventListener('mouseover', onIntent, {passive: true});
    document.addEventListener('focusin', onIntent, {passive: true});
})();
