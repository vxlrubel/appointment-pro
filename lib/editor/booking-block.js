(function (blocks, element, editor, components) {
  const h = element.createElement;
  blocks.registerBlockType('px-appointment/booking', {
    apiVersion: 2,
    title: 'Appointment Pro',
    icon: 'calendar-alt',
    category: 'widgets',
    attributes: {
      locationId: { type: 'number', default: 0 },
      serviceId: { type: 'number', default: 0 },
      staffId: { type: 'number', default: 0 }
    },
    edit: function (props) {
      const fields = [['locationId', 'Location ID'], ['serviceId', 'Service ID'], ['staffId', 'Staff ID']];
      return h('div', editor.useBlockProps(),
        h(editor.InspectorControls, {}, h(components.PanelBody, {title: 'Booking presets'},
          fields.map(function (field) {
            return h(components.TextControl, {
              key: field[0], label: field[1], type: 'number', min: 0,
              help: 'Use 0 to let the customer choose.', value: props.attributes[field[0]],
              onChange: function (value) { const update = {}; update[field[0]] = Math.max(0, Number(value) || 0); props.setAttributes(update); }
            });
          })
        )),
        h('strong', {}, 'Appointment Pro'),
        h('p', {}, 'The booking form will appear here on your published page.')
      );
    },
    save: function () { return null; }
  });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components);
