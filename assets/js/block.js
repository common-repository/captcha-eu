function captchaOptions(settings, name) {
    if (typeof settings.attributes !== 'undefined') {
        settings.attributes = Object.assign(settings.attributes, {
            captchaProtect: {
                type: 'boolean',
            },
            captchaGateText: {
                type: 'string',
            }
        });
    }
    return settings;
}

wp.hooks.addFilter(
    'blocks.registerBlockType',
    'captcha/custom-attribute',
    captchaOptions
);

const captchaProtectControls = wp.compose.createHigherOrderComponent((BlockEdit) => {
    return (props) => {
        const { createElement } = wp.element;
        const { ToggleControl, TextControl, PanelBody, PanelRow } = wp.components;
        const { InspectorAdvancedControls } = wp.blockEditor || wp.editor; // Fallback for wp.editor if wp.blockEditor is not available.
        const { attributes, setAttributes, isSelected } = props;

        // Toggle control element for captchaProtect
        const toggleControlElement = createElement(PanelRow, {}, 
            createElement(ToggleControl, {
                label: wp.i18n.__('Protect Block', 'captcha-eu'),
                checked: !!attributes.captchaProtect,
                onChange: () => setAttributes({ captchaProtect: !attributes.captchaProtect })
            })
        );

        // Text control element for captchaGateText
        const textControlElement = createElement(PanelRow, {},
            createElement(TextControl, {
                label: wp.i18n.__('Gate Text', 'captcha-eu'),
                value: attributes.captchaGateText,
                onChange: (value) => setAttributes({ captchaGateText: value })
            })
        );

        // Conditionally render InspectorAdvancedControls with both controls inside a PanelBody for grouping
        const inspectorAdvancedControlsElement = isSelected ? createElement(InspectorAdvancedControls, {},
            createElement(PanelBody, { title: wp.i18n.__('captcha.eu Settings', 'captcha-eu'), initialOpen: true },
                toggleControlElement,
                attributes.captchaProtect ? textControlElement : null // Conditionally display captchaGateText control
            )
        ) : null;

        // Render BlockEdit and conditionally rendered InspectorAdvancedControls
        return createElement(wp.element.Fragment, {},
            createElement(BlockEdit, props),
            inspectorAdvancedControlsElement
        );
    };
}, 'captchaProtectControls');

wp.hooks.addFilter(
	'editor.BlockEdit',
	'captcha/captcha-advanced-control',
	captchaProtectControls
);