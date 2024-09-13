import { Flex, FlexBlock } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editPostStore } from '@wordpress/edit-post';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { useEffect, useRef } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { Draft } from '@draft/Draft';
import '@draft/app.css';
import { GenerateImageButtons } from '@draft/components/GenerateImageButtons';
import { ToolbarMenu } from '@draft/components/ToolbarMenu';
import { magic } from '@draft/svg';

registerPlugin('extendify-draft', {
	render: () => (
		<ExtendifyDraft>
			<PluginSidebarMoreMenuItem target="draft">
				{__('Draft', 'extendify-local')}
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name="draft"
				icon={magic}
				title={__('AI Tools', 'extendify-local')}
				className="extendify-draft h-full">
				<Flex direction="column" expanded justify="space-between">
					<FlexBlock>
						<Draft />
					</FlexBlock>
				</Flex>
			</PluginSidebar>
		</ExtendifyDraft>
	),
});
const ExtendifyDraft = ({ children }) => {
	const { openGeneralSidebar } = useDispatch(editPostStore);
	const sidebarName = useSelect((select) =>
		select(editPostStore).getActiveGeneralSidebarName(),
	);
	const once = useRef(false);
	useEffect(() => {
		if (once.current) return;
		const id = requestAnimationFrame(() => {
			once.current = true;
			// Silence is golden
			document
				.querySelector(
					'.components-modal__screen-overlay .components-modal__header > button',
				)
				?.click();
			if (sidebarName === 'extendify-draft/draft') return;
			openGeneralSidebar('extendify-draft/draft');
		});
		return () => cancelAnimationFrame(id);
	}, [openGeneralSidebar, sidebarName]);

	return children;
};

// Add the toolbar
addFilter(
	'editor.BlockEdit',
	'extendify-draft/draft-toolbar',
	(CurrentMenuItems) => (props) => ToolbarMenu(CurrentMenuItems, props),
);

// Add the Generate with AI button
addFilter(
	'editor.BlockEdit',
	'extendify-draft/draft-image',
	(CurrentComponents) => (props) =>
		GenerateImageButtons(CurrentComponents, props),
);
