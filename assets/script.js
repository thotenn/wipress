document.addEventListener('DOMContentLoaded', function() {

    // --- Sidebar tree expand/collapse ---
    document.querySelectorAll('.wdh-tree-toggle').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var li = btn.closest('li');
            var expanded = li.classList.contains('expanded');

            if (expanded) {
                li.classList.remove('expanded');
                li.classList.add('collapsed');
                btn.setAttribute('aria-expanded', 'false');
            } else {
                li.classList.remove('collapsed');
                li.classList.add('expanded');
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    });

    // --- TOC generation with slug-based IDs ---
    var render = document.querySelector('.wdh-render');
    var toc = document.getElementById('wdh-toc-js');
    if (!render || !toc) return;

    var headings = render.querySelectorAll('h2, h3, h4');
    if (headings.length === 0) {
        var tocContainer = document.querySelector('.wdh-inf-toc');
        if (tocContainer) tocContainer.style.display = 'none';
        return;
    }

    var slugCounts = {};

    headings.forEach(function(h) {
        // Generate slug from heading text
        var slug = h.textContent.trim()
            .toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');

        if (!slug) slug = 'section';

        // Handle duplicates
        if (slugCounts[slug] !== undefined) {
            slugCounts[slug]++;
            slug = slug + '-' + slugCounts[slug];
        } else {
            slugCounts[slug] = 0;
        }

        h.id = slug;

        var a = document.createElement('a');
        a.href = '#' + slug;
        a.textContent = h.textContent;
        a.className = 'toc-link';

        var tag = h.tagName;
        if (tag === 'H3') a.classList.add('toc-indent-1');
        if (tag === 'H4') a.classList.add('toc-indent-2');

        a.addEventListener('click', function(e) {
            e.preventDefault();
            var target = document.getElementById(slug);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                history.replaceState(null, '', '#' + slug);
            }
        });

        toc.appendChild(a);
    });

    // --- IntersectionObserver for active section tracking ---
    var tocLinks = toc.querySelectorAll('.toc-link');
    var headingMap = {};
    tocLinks.forEach(function(link) {
        var id = link.getAttribute('href').slice(1);
        headingMap[id] = link;
    });

    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                tocLinks.forEach(function(l) { l.classList.remove('toc-active'); });
                var link = headingMap[entry.target.id];
                if (link) link.classList.add('toc-active');
            }
        });
    }, {
        rootMargin: '0px 0px -70% 0px',
        threshold: 0
    });

    headings.forEach(function(h) { observer.observe(h); });
});
