import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { pageNames } from '@shared/lib/pages';
import { Axios as api } from './axios';

const wpRoot = window.extOnbData.wpRoot;

export const updateOption = (option, value) =>
	api.post('launch/options', { option, value });

export const getOption = async (option) => {
	const { data } = await api.get('launch/options', {
		params: { option },
	});
	return data;
};

export const createPage = (pageData) =>
	api.post(`${wpRoot}wp/v2/pages`, pageData);

export const updatePage = (pageData) =>
	api.post(`${wpRoot}wp/v2/pages/${pageData.id}`, pageData);

export const getPageById = (pageId) =>
	api.get(`${wpRoot}wp/v2/pages/${pageId}`);

export const installPlugin = async (plugin) => {
	// Fail silently if no slug is provided
	if (!plugin?.wordpressSlug) return;

	try {
		// Install plugin and try to activate it.
		const response = await api.post(`${wpRoot}wp/v2/plugins`, {
			slug: plugin.wordpressSlug,
			status: 'active',
		});
		if (!response.ok) return response;
	} catch (e) {
		// Fail gracefully for now
	}

	try {
		// Try and activate it if the above fails
		return await activatePlugin(plugin);
	} catch (e) {
		// Fail gracefully for now
	}
};

export const activatePlugin = async (plugin) => {
	const endpoint = new URL(`${wpRoot}wp/v2/plugins`);
	const params = new URLSearchParams(endpoint.searchParams);
	params.set('search', plugin.wordpressSlug);
	endpoint.search = params.toString();
	const response = await api.get(endpoint.toString());
	const pluginSlug = response?.[0]?.plugin;
	if (!pluginSlug) {
		throw new Error('Plugin not found');
	}
	// Attempt to activate the plugin with the slug we found
	return await api.post(`${wpRoot}wp/v2/plugins/${pluginSlug}`, {
		status: 'active',
	});
};

export const updateTemplatePart = (part, content) =>
	api.post(`${wpRoot}wp/v2/template-parts/${part}`, {
		slug: `${part}`,
		theme: 'extendable',
		type: 'wp_template_part',
		status: 'publish',
		// See: https://github.com/extendify/company-product/issues/833#issuecomment-1804179527
		// translators: Launch is the product name. Unless otherwise specified by the glossary, do not translate this name.
		description: __('Added by Launch', 'extendify-local'),
		content,
	});

const allowedHeaders = ['header', 'header-with-center-nav-and-social'];
const allowedFooters = [
	'footer',
	'footer-social-icons',
	'footer-with-center-logo-and-menu',
];

export const getHeadersAndFooters = async () => {
	let patterns = await getTemplateParts();
	patterns = patterns?.filter((p) => p.theme === 'extendable');
	const headers = patterns?.filter((p) => allowedHeaders.includes(p?.slug));
	const footers = patterns?.filter((p) => allowedFooters.includes(p?.slug));
	return { headers, footers };
};

const getTemplateParts = () => api.get(wpRoot + 'wp/v2/template-parts');

export const getThemeVariations = async () => {
	const variations = await api.get(
		wpRoot + 'wp/v2/global-styles/themes/extendable/variations',
	);
	if (!Array.isArray(variations)) {
		throw new Error('Could not get theme variations');
	}
	// Randomize
	return [...variations].sort(() => Math.random() - 0.5);
};

export const updateThemeVariation = (id, variation) =>
	api.post(`${wpRoot}wp/v2/global-styles/${id}`, {
		id,
		settings: variation.settings,
		styles: variation.styles,
	});

export const addPatternSectionsToNav = async (homePatterns, headerCode) => {
	// ['about-us', 'services', 'contact-us']
	const sections = homePatterns
		.map(({ patternTypes }) => patternTypes?.[0])
		.filter(Boolean);

	const seen = new Set();
	const pageListItems = sections
		.map((patternType) => {
			const { title, slug } =
				Object.values(pageNames).find(({ alias }) =>
					alias.includes(patternType),
				) || {};
			if (!slug) return '';
			if (seen.has(slug)) return '';
			seen.add(slug);
			return `<!-- wp:navigation-link { "label":"${title}", "type":"custom", "url":"#${slug}", "isTopLevelLink":true } /-->`;
		})
		.join('');

	// Create a custom navigation
	const navigation = await saveNavigation(pageListItems);

	// Add ref to nav attributes
	return updateNavAttributes(headerCode, { ref: navigation.id });
};

export const addPagesToNav = async (pages, wpPages, headerCode) => {
	// We match the original slugs as the new ones could have changed by wp
	const findWpPage = ({ slug }) =>
		wpPages.find(({ originalSlug: s }) => s === slug) || {};

	const pageListItems = pages
		.filter((p) => findWpPage(p)?.id) // make sure its a page
		.filter(({ slug }) => slug !== 'home') // exclude home page
		.map((page) => {
			const { id, title, link, type } = findWpPage(page);
			return `<!-- wp:navigation-link { "label":"${title.rendered}", "type":"${type}", "id":"${id}", "url":"${link}", "kind":"post-type", "isTopLevelLink":true } /-->`;
		})
		.join('');

	// Create a custom navigation
	const navigation = await saveNavigation(pageListItems);

	// Add ref to nav attributes
	return updateNavAttributes(headerCode, { ref: navigation.id });
};

const saveNavigation = (pageItems) =>
	apiFetch({
		path: 'extendify/v1/launch/create-navigation',
		method: 'POST',
		data: {
			title: __('Header Navigation', 'extendify-local'),
			slug: 'site-navigation',
			content: pageItems,
		},
	});

const getNavAttributes = (headerCode) => {
	try {
		return JSON.parse(headerCode.match(/<!-- wp:navigation([\s\S]*?)-->/)[1]);
	} catch (e) {
		return {};
	}
};
const updateNavAttributes = (headerCode, attributes) => {
	const newAttributes = JSON.stringify({
		...getNavAttributes(headerCode),
		...attributes,
	});
	return headerCode.replace(
		/(<!--\s*wp:navigation\b[^>]*>)([^]*?)(<!--\s*\/wp:navigation\s*-->)/gi,
		`<!-- wp:navigation ${newAttributes} /-->`,
	);
};

export const getActivePlugins = () => api.get('launch/active-plugins');

export const prefetchAssistData = async () =>
	await api.get('launch/prefetch-assist-data');

export const updateUserMeta = (option, value) =>
	apiFetch({
		path: '/extendify/v1/shared/update-user-meta',
		method: 'POST',
		data: { option, value },
	});

export const processPlaceholders = (patterns) =>
	apiFetch({
		path: '/extendify/v1/shared/process-placeholders',
		method: 'POST',
		data: { patterns },
	});

export const postLaunchFunctions = () =>
	apiFetch({
		path: '/extendify/v1/launch/post-launch-functions',
		method: 'POST',
	});
