( function() {
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var registerBlockType = wp.blocks.registerBlockType;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var PlainText = wp.blockEditor.PlainText;

    registerBlockType('wipress/markdown', {
        edit: function(props) {
            var content = props.attributes.content;
            var setAttributes = props.setAttributes;
            var ref = useState(false);
            var preview = ref[0];
            var setPreview = ref[1];
            var ref2 = useState('');
            var html = ref2[0];
            var setHtml = ref2[1];
            var blockProps = useBlockProps();

            useEffect(function() {
                if (!preview || !content) { setHtml(''); return; }
                var controller = new AbortController();
                wp.apiFetch({
                    path: '/wipress/v1/render-markdown',
                    method: 'POST',
                    data: { content: content },
                    signal: controller.signal
                }).then(function(res) {
                    setHtml(res.html || '');
                }).catch(function(err) {
                    if (err.name !== 'AbortError') {
                        setHtml('<p style="color:#d63638">Preview failed: ' + (err.message || 'Unknown error') + '</p>');
                    }
                });
                return function() { controller.abort(); };
            }, [preview, content]);

            return el('div', blockProps,
                el('div', { className: 'wipress-markdown-toolbar' },
                    el('button', {
                        onClick: function() { setPreview(false); },
                        className: !preview ? 'is-active' : ''
                    }, 'Write'),
                    el('button', {
                        onClick: function() { setPreview(true); },
                        className: preview ? 'is-active' : ''
                    }, 'Preview')
                ),
                preview
                    ? el('div', {
                        className: 'wipress-markdown-preview',
                        dangerouslySetInnerHTML: { __html: html }
                    })
                    : el(PlainText, {
                        className: 'wipress-markdown-editor',
                        value: content,
                        onChange: function(val) { setAttributes({ content: val }); },
                        placeholder: 'Write markdown here...'
                    })
            );
        },
        save: function() {
            return null; // Server-side render
        }
    });
} )();
