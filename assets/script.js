document.addEventListener('DOMContentLoaded', function() {

    // --- Sidebar search ---
    document.querySelectorAll('.wdh-search').forEach(function(container) {
        var searchUrl = container.dataset.searchUrl;
        var project = container.dataset.project;
        var input = container.querySelector('.wdh-search-input');
        var results = container.querySelector('.wdh-search-results');
        var debounceTimer = null;
        var controller = null;

        function closeResults() {
            results.classList.remove('is-open');
        }

        function openResults() {
            if (results.children.length > 0) {
                results.classList.add('is-open');
            }
        }

        function renderResults(items) {
            results.textContent = '';
            if (items.length === 0) {
                var empty = document.createElement('div');
                empty.className = 'wdh-search-empty';
                empty.textContent = 'No results found';
                results.appendChild(empty);
            } else {
                items.forEach(function(item) {
                    var a = document.createElement('a');
                    a.className = 'wdh-search-result';
                    a.href = item.url;
                    var title = document.createElement('div');
                    title.className = 'wdh-search-result-title';
                    title.textContent = item.title;
                    a.appendChild(title);
                    if (item.excerpt) {
                        var excerpt = document.createElement('div');
                        excerpt.className = 'wdh-search-result-excerpt';
                        excerpt.textContent = item.excerpt;
                        a.appendChild(excerpt);
                    }
                    results.appendChild(a);
                });
            }
            results.classList.add('is-open');
        }

        function doSearch(query) {
            if (controller) controller.abort();
            controller = new AbortController();

            var loading = document.createElement('div');
            loading.className = 'wdh-search-loading';
            loading.textContent = 'Searching...';
            results.textContent = '';
            results.appendChild(loading);
            results.classList.add('is-open');

            fetch(searchUrl + '?q=' + encodeURIComponent(query) + '&project=' + encodeURIComponent(project), {
                signal: controller.signal
            })
                .then(function(r) { return r.json(); })
                .then(function(data) { renderResults(data); })
                .catch(function(err) {
                    if (err.name !== 'AbortError') {
                        results.textContent = '';
                        closeResults();
                    }
                });
        }

        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            var query = input.value.trim();
            if (query.length < 2) {
                if (controller) controller.abort();
                results.textContent = '';
                closeResults();
                return;
            }
            debounceTimer = setTimeout(function() { doSearch(query); }, 300);
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                input.value = '';
                results.textContent = '';
                closeResults();
                input.blur();
            }
        });

        input.addEventListener('focus', function() {
            openResults();
        });

        document.addEventListener('click', function(e) {
            if (!container.contains(e.target)) {
                closeResults();
            }
        });
    });

    // --- Code block copy button ---
    var copyIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
    var checkIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';

    document.querySelectorAll('.wdh-render pre').forEach(function(pre) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'wdh-code-copy';
        btn.innerHTML = copyIcon;
        btn.setAttribute('aria-label', 'Copy code');
        pre.appendChild(btn);

        btn.addEventListener('click', function() {
            var code = pre.querySelector('code');
            var text = (code || pre).textContent;
            navigator.clipboard.writeText(text).then(function() {
                btn.innerHTML = checkIcon;
                btn.classList.add('is-copied');
                setTimeout(function() {
                    btn.innerHTML = copyIcon;
                    btn.classList.remove('is-copied');
                }, 2000);
            });
        });
    });

    // --- Sidebar tree expand/collapse ---
    function toggleTreeItem(li) {
        var btn = li.querySelector(':scope > .wdh-tree-toggle');
        var expanded = li.classList.contains('expanded');

        if (expanded) {
            li.classList.remove('expanded');
            li.classList.add('collapsed');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        } else {
            li.classList.remove('collapsed');
            li.classList.add('expanded');
            if (btn) btn.setAttribute('aria-expanded', 'true');
        }
    }

    document.querySelectorAll('.wdh-tree-toggle').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            toggleTreeItem(btn.closest('li'));
        });
    });

    // Folder items (no content) toggle on click instead of navigating
    document.querySelectorAll('.wdh-tree-folder').forEach(function(span) {
        span.addEventListener('click', function() {
            toggleTreeItem(span.closest('li'));
        });
        span.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleTreeItem(span.closest('li'));
            }
        });
    });

    // --- TOC generation with slug-based IDs ---
    var render = document.querySelector('.wdh-render');
    var toc = document.getElementById('wdh-toc-js');
    if (render && toc) {
        var headings = render.querySelectorAll('h2, h3, h4');
        if (headings.length === 0) {
            var tocContainer = document.querySelector('.wdh-inf-toc');
            if (tocContainer) tocContainer.style.display = 'none';
        } else {
            var slugCounts = {};

            headings.forEach(function(h) {
                var slug = h.textContent.trim()
                    .toLowerCase()
                    .replace(/[^\w\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .replace(/^-|-$/g, '');

                if (!slug) slug = 'section';

                if (slugCounts[slug] !== undefined) {
                    slugCounts[slug]++;
                    slug = slug + '-' + slugCounts[slug];
                } else {
                    slugCounts[slug] = 0;
                }

                h.id = slug;

                // Add anchor link to heading
                var anchor = document.createElement('a');
                anchor.className = 'wdh-heading-anchor';
                anchor.href = '#' + slug;
                anchor.textContent = '#';
                anchor.setAttribute('aria-label', 'Link to this section');
                anchor.addEventListener('click', function(ev) {
                    ev.preventDefault();
                    var t = document.getElementById(this.getAttribute('href').slice(1));
                    if (t) {
                        t.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        history.replaceState(null, '', this.getAttribute('href'));
                    }
                });
                h.appendChild(anchor);

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

            // IntersectionObserver for active section tracking
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
        }
    }

    // --- Theme toggle ---
    var themeBtn = document.getElementById('wdh-btn-theme-toggle');
    if (themeBtn) {
        themeBtn.addEventListener('click', function() {
            var html = document.documentElement;
            var current = html.getAttribute('data-theme');
            var next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('wipress-theme', next);
        });
    }

    // --- Download as Markdown ---
    var downloadBtn = document.getElementById('wdh-btn-download-md');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', function() {
            var apiUrl = downloadBtn.dataset.apiUrl;
            var slug = downloadBtn.dataset.pageSlug;

            fetch(apiUrl)
                .then(function(r) { return r.json(); })
                .then(function(page) {
                    var md = null;

                    // Try extracting raw markdown from wipress/markdown blocks
                    if (page.content_format === 'markdown') {
                        md = extractMarkdownFromBlocks(page.content);
                    }

                    // Fallback: convert rendered HTML on page to markdown
                    if (!md) {
                        var renderEl = document.querySelector('.wdh-render');
                        if (renderEl) {
                            md = htmlToMarkdown(renderEl);
                        } else {
                            md = page.content;
                        }
                    }

                    // Prepend title
                    md = '# ' + page.title + '\n\n' + md;

                    var blob = new Blob([md], { type: 'text/markdown' });
                    var a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = slug + '.md';
                    a.click();
                    URL.revokeObjectURL(a.href);
                })
                .catch(function(err) {
                    console.error('Download failed:', err);
                });
        });
    }

    // --- Copy MCP URL ---
    var copyBtn = document.getElementById('wdh-btn-copy-mcp');
    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            var url = copyBtn.dataset.mcpUrl;
            navigator.clipboard.writeText(url).then(function() {
                copyBtn.classList.add('is-copied');
                var orig = copyBtn.dataset.tooltip;
                copyBtn.dataset.tooltip = 'Copied!';
                setTimeout(function() {
                    copyBtn.classList.remove('is-copied');
                    copyBtn.dataset.tooltip = orig;
                }, 2000);
            });
        });
    }

    // --- Mobile drawer ---
    var hamburger = document.getElementById('wdh-hamburger');
    var drawer = document.getElementById('wdh-mobile-drawer');
    var drawerClose = document.getElementById('wdh-drawer-close');
    var drawerOverlay = drawer ? drawer.querySelector('.wdh-drawer-overlay') : null;
    var drawerBack = document.getElementById('wdh-drawer-back');
    var drawerTreeView = document.getElementById('wdh-drawer-tree-view');
    var drawerSectionsView = document.getElementById('wdh-drawer-sections-view');

    function openDrawer() {
        if (!drawer) return;
        drawer.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        // Always start on tree view
        if (drawerTreeView) drawerTreeView.style.display = '';
        if (drawerSectionsView) drawerSectionsView.style.display = 'none';
    }

    function closeDrawer() {
        if (!drawer) return;
        drawer.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    if (hamburger) hamburger.addEventListener('click', openDrawer);
    if (drawerClose) drawerClose.addEventListener('click', closeDrawer);
    if (drawerOverlay) drawerOverlay.addEventListener('click', closeDrawer);

    if (drawerBack) {
        drawerBack.addEventListener('click', function() {
            if (drawerTreeView) drawerTreeView.style.display = 'none';
            if (drawerSectionsView) drawerSectionsView.style.display = '';
        });
    }

    // In sections view: clicking the current section goes back to tree view
    if (drawerSectionsView) {
        drawerSectionsView.querySelectorAll('a.is-current').forEach(function(a) {
            a.addEventListener('click', function(e) {
                e.preventDefault();
                if (drawerTreeView) drawerTreeView.style.display = '';
                if (drawerSectionsView) drawerSectionsView.style.display = 'none';
            });
        });
    }

    // --- Cmd/Ctrl+K shortcut to focus search ---
    document.addEventListener('keydown', function(e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            var isMobile = window.innerWidth <= 768;
            if (isMobile) {
                openDrawer();
                // Focus mobile drawer search after drawer opens
                setTimeout(function() {
                    var drawerSearch = document.querySelector('#wdh-drawer-tree-view .wdh-search-input');
                    if (drawerSearch) drawerSearch.focus();
                }, 300);
            } else {
                var desktopSearch = document.querySelector('.wdh-inf-sidebar-left .wdh-search-input');
                if (desktopSearch) desktopSearch.focus();
            }
        }
    });

    // --- Helpers ---

    function extractMarkdownFromBlocks(content) {
        var parts = [];
        var blocks = content.split('<!-- wp:wipress/markdown ');
        for (var i = 1; i < blocks.length; i++) {
            var end = blocks[i].indexOf(' -->');
            if (end === -1) continue;
            try {
                var attrs = JSON.parse(blocks[i].substring(0, end));
                if (attrs.content) parts.push(attrs.content);
            } catch(e) {}
        }
        return parts.length > 0 ? parts.join('\n\n') : null;
    }

    function htmlToMarkdown(el) {
        var md = '';
        var nodes = el.childNodes;
        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            if (node.nodeType === 3) {
                var text = node.textContent;
                if (text.trim()) md += text;
            } else if (node.nodeType === 1) {
                md += elementToMarkdown(node);
            }
        }
        return md.replace(/\n{3,}/g, '\n\n').trim();
    }

    function elementToMarkdown(el) {
        var tag = el.tagName.toLowerCase();
        switch (tag) {
            case 'h1': return '# ' + el.textContent.trim() + '\n\n';
            case 'h2': return '## ' + el.textContent.trim() + '\n\n';
            case 'h3': return '### ' + el.textContent.trim() + '\n\n';
            case 'h4': return '#### ' + el.textContent.trim() + '\n\n';
            case 'h5': return '##### ' + el.textContent.trim() + '\n\n';
            case 'h6': return '###### ' + el.textContent.trim() + '\n\n';
            case 'p': return inlineToMarkdown(el) + '\n\n';
            case 'br': return '\n';
            case 'strong': case 'b': return '**' + el.textContent + '**';
            case 'em': case 'i': return '*' + el.textContent + '*';
            case 'a': return '[' + el.textContent + '](' + el.getAttribute('href') + ')';
            case 'code':
                if (el.parentElement && el.parentElement.tagName === 'PRE') return el.textContent;
                return '`' + el.textContent + '`';
            case 'pre':
                var code = el.querySelector('code');
                var lang = '';
                if (code) {
                    var cls = code.className.match(/language-(\w+)/);
                    if (cls) lang = cls[1];
                }
                return '```' + lang + '\n' + (code || el).textContent.trim() + '\n```\n\n';
            case 'ul':
                var uitems = '';
                var ulis = el.querySelectorAll(':scope > li');
                for (var j = 0; j < ulis.length; j++) {
                    uitems += '- ' + inlineToMarkdown(ulis[j]).trim() + '\n';
                }
                return uitems + '\n';
            case 'ol':
                var oitems = '';
                var olis = el.querySelectorAll(':scope > li');
                for (var k = 0; k < olis.length; k++) {
                    oitems += (k + 1) + '. ' + inlineToMarkdown(olis[k]).trim() + '\n';
                }
                return oitems + '\n';
            case 'blockquote':
                return el.textContent.trim().split('\n').map(function(line) {
                    return '> ' + line;
                }).join('\n') + '\n\n';
            case 'img':
                return '![' + (el.alt || '') + '](' + el.src + ')\n\n';
            case 'hr': return '---\n\n';
            case 'table': return tableToMarkdown(el) + '\n\n';
            case 'div': case 'section': case 'article':
                return htmlToMarkdown(el);
            default:
                return htmlToMarkdown(el);
        }
    }

    function inlineToMarkdown(el) {
        var result = '';
        var nodes = el.childNodes;
        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            if (node.nodeType === 3) {
                result += node.textContent;
            } else if (node.nodeType === 1) {
                var tag = node.tagName.toLowerCase();
                if (tag === 'strong' || tag === 'b') result += '**' + node.textContent + '**';
                else if (tag === 'em' || tag === 'i') result += '*' + node.textContent + '*';
                else if (tag === 'code') result += '`' + node.textContent + '`';
                else if (tag === 'a') result += '[' + node.textContent + '](' + node.getAttribute('href') + ')';
                else if (tag === 'br') result += '\n';
                else if (tag === 'img') result += '![' + (node.alt || '') + '](' + node.src + ')';
                else result += node.textContent;
            }
        }
        return result;
    }

    function tableToMarkdown(table) {
        var rows = table.querySelectorAll('tr');
        if (rows.length === 0) return '';
        var md = '';
        for (var i = 0; i < rows.length; i++) {
            var cells = rows[i].querySelectorAll('th, td');
            var row = '|';
            for (var j = 0; j < cells.length; j++) {
                row += ' ' + cells[j].textContent.trim() + ' |';
            }
            md += row + '\n';
            if (i === 0) {
                var sep = '|';
                for (var s = 0; s < cells.length; s++) {
                    sep += ' --- |';
                }
                md += sep + '\n';
            }
        }
        return md;
    }

});
