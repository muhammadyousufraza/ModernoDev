import { rawHandler, serialize } from '@wordpress/blocks';
import { pageNames } from '@shared/lib/pages';
import { generateCustomPatterns } from '@launch/api/DataApi';
import {
	updateOption,
	createPage,
	updateThemeVariation,
	processPlaceholders,
} from '@launch/api/WPApi';
import { removeBlocks, addIdAttributeToBlock } from '@launch/lib/blocks';

// Currently this only processes patterns with placeholders
// by swapping out the placeholders with the actual code
// returns the patterns as blocks with the placeholders replaced
export const replacePlaceholderPatterns = async (patterns) => {
	const hasPlaceholders = patterns.filter((p) => p.patternReplacementCode);
	if (!hasPlaceholders?.length) return patterns;

	try {
		return await processPlaceholders(patterns);
	} catch (e) {
		// Try one more time (plugins installed may not be fully loaded)
		return await processPlaceholders(patterns)
			// If this fails, just return the original patterns
			.catch(() => patterns);
	}
};

export const createWpPages = async (pages, { stickyNav }) => {
	const pageIds = [];

	for (const page of pages) {
		const HTML = page.patterns.map(({ code }) => code).join('');
		const blocks = removeBlocks(rawHandler({ HTML }), ['core/html']);

		const content = [];
		// Use this to avoid adding duplicate Ids to patterns
		const seenPatternTypes = new Set();
		// Loop over every
		for (const [i, pattern] of blocks.entries()) {
			const patternType = page.patterns[i].patternTypes?.[0];
			const serializedBlock = serialize(pattern);
			// Get the translated slug
			const { slug } =
				Object.values(pageNames).find(({ alias }) =>
					alias.includes(patternType),
				) || {};

			// If we've already seen this slug, or no slug found, return the pattern unchanged
			if (seenPatternTypes.has(slug) || !slug) {
				content.push(serializedBlock);
				continue;
			}
			// Add the slug to the seen list so we don't add it again
			seenPatternTypes.add(slug);

			content.push(addIdAttributeToBlock(serializedBlock, slug));
		}

		let pageData = {
			title: page.name,
			status: 'publish',
			content: content.join(''),
			template: stickyNav ? 'no-title-sticky-header' : 'no-title',
			meta: { made_with_extendify_launch: true },
		};
		let newPage;
		try {
			newPage = await createPage(pageData);
		} catch (e) {
			// The above could fail is they are on extendable < 2.0.12
			// TODO: can remove in a month or so
			pageData.template = 'no-title';
			newPage = await createPage(pageData);
		}
		pageIds.push({ ...newPage, originalSlug: page.slug });
	}

	// When we have home, set reading setting
	const maybeHome = pageIds.find(({ originalSlug }) => originalSlug === 'home');
	if (maybeHome) {
		await updateOption('show_on_front', 'page');
		await updateOption('page_on_front', maybeHome.id);
	}

	// When we have blog, set reading setting
	const maybeBlog = pageIds.find(({ originalSlug }) => originalSlug === 'blog');
	if (maybeBlog) {
		await updateOption('page_for_posts', maybeBlog.id);
	}

	return pageIds;
};

export const generateCustomPageContent = async (pages, userState) => {
	// Either didn't see the ai copy page or skipped it
	if (!userState.businessInformation.description) {
		return pages;
	}

	const { siteId, partnerId, wpLanguage, wpVersion } = window.extSharedData;

	const result = await Promise.allSettled(
		pages.map((page) =>
			generateCustomPatterns(page, {
				...userState,
				siteId,
				partnerId,
				siteVersion: wpVersion,
				language: wpLanguage,
			})
				.then((response) => response)
				.catch(() => page),
		),
	);

	return result?.map((page, i) => page.value || pages[i]);
};

export const updateGlobalStyleVariant = (variation) =>
	updateThemeVariation(window.extSharedData.globalStylesPostID, variation);
