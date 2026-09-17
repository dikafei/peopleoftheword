(function (wp) {
	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { Fragment } = wp.element;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, RadioControl } = wp.components;

	const withNarrowWidthControl = createHigherOrderComponent(
		(BlockEdit) => {
			return (props) => {

				if (props.name !== 'core/group') {
					return wp.element.createElement(BlockEdit, props);
				}

				const { attributes, setAttributes } = props;
				const className = attributes.className || '';

				const classes = className
					.split(' ')
					.filter(Boolean);

				const isNarrow = classes.includes('is-narrow');

				const setWidth = (value) => {

					let newClasses = classes.filter(
						(classItem) => classItem !== 'is-narrow'
					);

					if (value === 'narrow') {
						newClasses.push('is-narrow');
					}

					setAttributes({
						className: newClasses.join(' '),
					});
				};

				return wp.element.createElement(
					Fragment,
					null,

					wp.element.createElement(BlockEdit, props),

					wp.element.createElement(
						InspectorControls,
						{
							group: 'styles',
						},

						wp.element.createElement(
							PanelBody,
							{
								title: 'Width',
								initialOpen: true,
							},

							wp.element.createElement(RadioControl, {
								selected: isNarrow
									? 'narrow'
									: 'default',

								options: [
									{
										label: 'Default',
										value: 'default',
									},
									{
										label: 'Narrow',
										value: 'narrow',
									},
								],

								onChange: setWidth,
							})
						)
					)
				);
			};
		},
		'withNarrowWidthControl'
	);

	addFilter(
		'editor.BlockEdit',
		'potw/group-narrow-width',
		withNarrowWidthControl
	);
})(window.wp);