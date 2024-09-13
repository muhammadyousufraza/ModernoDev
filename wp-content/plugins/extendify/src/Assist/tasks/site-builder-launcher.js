import { __ } from '@wordpress/i18n';

const { launchCompleted } = window.extAssistData;
const { themeSlug } = window.extSharedData;

export default {
	slug: 'site-builder-launcher',
	title: __('Continue with site builder', 'extendify-local'),
	description: __(
		'Create a super-fast, beautiful, and fully customized site in minutes with our Site Launcher.',
		'extendify-local',
	),
	buttonLabels: {
		completed: __('Select Site Industry', 'extendify-local'),
		notCompleted: __('Select Site Industry', 'extendify-local'),
	},
	link: 'admin.php?page=extendify-launch',
	type: 'site-launcher-task',
	dependencies: { goals: [], plugins: [] },
	show: () => {
		// This only runs if launch wasn't finished and they have extendable
		return themeSlug === 'extendable' && !launchCompleted;
	},
	backgroundImage:
		'https://assets.extendify.com/assist-tasks/extendify-preview-2.webp',
};
