( function() {
    var el = wp.element.createElement;
    var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
    var TextControl = wp.components.TextControl;
    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;
    var registerPlugin = wp.plugins.registerPlugin;

    function OrderPanel() {
        var postType = useSelect(function(select) {
            return select('core/editor').getCurrentPostType();
        });

        var menuOrder = useSelect(function(select) {
            return select('core/editor').getEditedPostAttribute('menu_order') || 0;
        });

        var editPost = useDispatch('core/editor').editPost;

        if (postType !== 'wiki') return null;

        return el(PluginDocumentSettingPanel, {
            name: 'wipress-order',
            title: 'Page Order',
            icon: 'sort'
        },
            el(TextControl, {
                label: 'Menu Order',
                help: 'Lower numbers appear first in the sidebar.',
                type: 'number',
                value: String(menuOrder),
                onChange: function(val) {
                    editPost({ menu_order: parseInt(val, 10) || 0 });
                }
            })
        );
    }

    registerPlugin('wipress-order-panel', {
        render: OrderPanel
    });
} )();
