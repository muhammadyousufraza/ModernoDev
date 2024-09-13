import { rawHandler, getBlockContent } from '@wordpress/blocks';
import { getLinkSuggestions } from '@launch/api/DataApi';
import { updatePage } from '@launch/api/WPApi';

const buttonRegex = /href="(#extendify-[\w|-]+)"/gi;
const pagesWithButtons = (p) => p?.content?.raw?.match(buttonRegex);

export const updateButtonLinks = async (wpPages) => {
	const contactPageSlug = wpPages.find(({ originalSlug }) =>
		originalSlug.startsWith('contact'),
	)?.slug;

	const patternsToProcess = wpPages
		// Look for pages with links
		.filter(pagesWithButtons)
		.map(({ content }) => {
			// 1. Convert to individual blocks
			return (
				rawHandler({ HTML: content.raw || '' })
					// 2. Convert back to HTML
					.map((b) => getBlockContent(b))
					// 3. Filter only blocks with links
					.filter((b) => b.match(buttonRegex))
					.join('')
				// TODO: Filter out patterns from pages that have identical buttons?
			);
		});

	// Collect the page slugs to share with the server
	const availablePages = wpPages
		.filter(({ slug }) => !slug.startsWith('home'))
		.map(({ slug }) => `/${slug}`);

	// Fetch the links from the server. If a request fails, ignore it.
	const suggestedLinks = (
		await Promise.allSettled(
			patternsToProcess.map(
				(pageContent) => getLinkSuggestions(pageContent, availablePages) || {},
			),
		)
	)
		.filter((r) => r.status === 'fulfilled')
		.map((r) => r.value?.suggestedLinks || [])
		// Combine all suggested links
		.reduce((acc, link) => ({ ...acc, ...link }), {});

	const linkKeys = Object.keys(suggestedLinks)
		.filter((k) =>
			// Remove links sent back that aren't in the availablePages
			availablePages.includes(`/${suggestedLinks[k].replace(/^\//, '')}`),
		)
		.map((v) => `\\"${v}\\"`)
		.join('|');

	// Replace links and update the pages. Failed pages get ignored.
	const newPages = (
		await Promise.allSettled(
			wpPages.filter(pagesWithButtons).map((p) => {
				// We want to match \"extendify-cta\" exactly inside the href
				// So we need to look for the quotes, then replace with the quotes
				const content = linkKeys
					? p.content.raw.replace(new RegExp(linkKeys, 'g'), (match) => {
							if (!match || suggestedLinks.length === 0) return '';

							const link = suggestedLinks[match.replace(/"/g, '')];
							// if the link points to the current page or '/'
							// we should link to the contact page (or default to '/')
							if ([p.slug, `/${p.slug}`, '/'].includes(link))
								return `/${contactPageSlug ?? ''}`;

							// The server once sent back slugs without the /
							// so we need to check
							return link.startsWith('/') ? link : `/${link}`;
						})
					: p.content.raw.replace(new RegExp(buttonRegex, 'g'), (match) => {
							return match ? 'href="#"' : '';
						});
				return updatePage({ id: p.id, content });
			}),
		)
	)
		.filter((r) => r.status === 'fulfilled')
		.map((r) => r.value);

	return (
		wpPages
			// Add the new pages into the wpPages array
			.map((p) => newPages.find(({ id }) => id === p.id) || p)
			// Also include the originalSlug from wpPages
			.map((p) => {
				const { originalSlug } = wpPages.find(({ id }) => id === p.id) || {};
				return { ...p, originalSlug };
			})
	);
};
