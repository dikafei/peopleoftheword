(function (wp) {
	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { Fragment } = wp.element;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, ToggleControl } = wp.components;

	const withNarrowWidthControl = createHigherOrderComponent(
		(BlockEdit) => {
			return (props) => {
				// Hanya untuk Group block
				if (props.name !== 'core/group') {
					return wp.element.createElement(BlockEdit, props);
				}

				const { attributes, setAttributes } = props;
				const className = attributes.className || '';

				const classes = className
					.split(' ')
					.filter(Boolean);

				const isNarrow = classes.includes('is-narrow');

				const toggleNarrow = (enabled) => {
					let newClasses = classes.filter(
						(classItem) => classItem !== 'is-narrow'
					);

					if (enabled) {
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
						null,

						wp.element.createElement(
							PanelBody,
							{
								title: 'Layout',
								initialOpen: true,
							},

							wp.element.createElement(ToggleControl, {
								label: 'Narrow Width',
								checked: isNarrow,
								onChange: toggleNarrow,
								help: isNarrow
									? 'Group width is limited to 760px.'
									: 'Limit this Group to 760px width.',
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