import { useCallback, useEffect, useState, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import classNames from 'classnames';
import { AnimatePresence, motion } from 'framer-motion';
import { getHomeTemplates } from '@launch/api/DataApi';
import { getThemeVariations } from '@launch/api/WPApi';
import { LoadingIndicator } from '@launch/components/LoadingIndicator';
import { SmallPreview } from '@launch/components/SmallPreview';
import { Title } from '@launch/components/Title';
import { useFetch } from '@launch/hooks/useFetch';
import { useIsMountedLayout } from '@launch/hooks/useIsMounted';
import { PageLayout } from '@launch/layouts/PageLayout';
import { pageState } from '@launch/state/factory';
import { useUserSelectionStore } from '@launch/state/user-selections';
import { Checkmark } from '@launch/svg';

export const fetcher = getHomeTemplates;
export const fetchData = (siteType) => ({
	key: 'home-pages-list',
	siteType: siteType ?? useUserSelectionStore?.getState().siteType,
});

export const state = pageState('Layout', () => ({
	ready: false,
	canSkip: false,
	validation: null,
	onRemove: () => {},
}));

export const HomeSelect = () => {
	const { loading, data: homeTemplate } = useFetch(fetchData, fetcher);

	return (
		<PageLayout>
			<div className="grow overflow-y-scroll px-6 py-8 md:px-32 md:py-16">
				<Title
					title={__('Pick a design for your website', 'extendify-local')}
					description={__('You can personalize this later.', 'extendify-local')}
				/>
				<div className="relative mx-auto w-full max-w-6xl">
					{loading ? (
						<LoadingIndicator />
					) : (
						<DesignSelector homeTemplate={homeTemplate} />
					)}
				</div>
			</div>
		</PageLayout>
	);
};

const DesignSelector = ({ homeTemplate }) => {
	const { data: variations } = useFetch('variations', getThemeVariations);
	const isMounted = useIsMountedLayout();
	const [styles, setStyles] = useState([]);
	const { setStyle, style: currentStyle } = useUserSelectionStore();
	const onSelect = useCallback((style) => setStyle(style), [setStyle]);
	const wrapperRef = useRef();
	const once = useRef(false);

	useEffect(() => {
		state.setState({ ready: !!currentStyle?.variation?.title });
	}, [currentStyle]);

	useEffect(() => {
		if (!homeTemplate || !variations) return;
		if (styles.length) return;
		setStyle(null);
		(async () => {
			const slicedEntries = Array.from(homeTemplate.entries());
			for (const [index, style] of slicedEntries) {
				if (!isMounted.current) return;

				setStyles((styles) => [
					...styles,
					{
						...style,
						variation: variations[index % variations.length],
					},
				]);

				// Delay between 350ms and 1s to make it less rigid
				const random = Math.floor(Math.random() * (1000 - 150 + 1)) + 150;
				await new Promise((resolve) => setTimeout(resolve, random));
			}
		})();
	}, [homeTemplate, isMounted, variations, styles.length, setStyle]);

	useEffect(() => {
		if (!currentStyle || !styles || once.current) return;
		const currentButton = wrapperRef.current?.querySelector(
			`#layout-style-${currentStyle.slug} [role="button"]`,
		);
		if (!currentButton) return;
		once.current = true;
		currentButton.focus();
	}, [currentStyle, styles]);

	return (
		<div
			className="grid gap-8 md:grid-cols-2 lg:grid-cols-3"
			data-test="layout-preview-wrapper"
			ref={wrapperRef}>
			{styles?.map((style) => (
				<div className="relative" key={style.id}>
					<AnimatePresence>
						<motion.div
							initial={{ opacity: 0 }}
							animate={{ opacity: 1 }}
							duration={0.7}
							className={classNames(
								'relative cursor-pointer overflow-hidden rounded border border-gray-200 ring-offset-2 ring-offset-white focus-within:outline-none focus-within:ring-4 focus-within:ring-design-main focus-within:ring-offset-2 focus-within:ring-offset-white hover:outline-none hover:ring-4',
								{
									'ring-4 ring-design-main ring-offset-2 ring-offset-white hover:ring-design-main':
										currentStyle?.id === style.id,
									'hover:ring-gray-300': currentStyle?.id !== style.id,
								},
							)}
							style={{ aspectRatio: '1.55' }}>
							<SmallPreview
								style={style}
								onSelect={onSelect}
								selected={currentStyle?.id === style.id}
							/>
						</motion.div>
					</AnimatePresence>
					<span aria-hidden="true">
						{currentStyle?.id === style.id ? (
							<Checkmark className="absolute right-0 top-0 z-50 m-2 h-6 w-6 -translate-y-5 translate-x-5 rounded-full bg-design-main text-design-text" />
						) : null}
					</span>
				</div>
			))}
			{homeTemplate?.slice(styles?.length).map((_, i) => (
				<AnimatePresence key={i}>
					<motion.div
						initial={{ opacity: 1 }}
						animate={{ opacity: 1 }}
						exit={{ opacity: 0 }}
						duration={0.7}
						className="relative bg-gray-50"
						style={{
							aspectRatio: '1.55',
							backgroundImage:
								'linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.5) 50%, rgba(255,255,255,0) 100%)',
							backgroundSize: '600% 600%',
							animation: 'extendify-loading-skeleton 10s ease-in-out infinite',
						}}
					/>
				</AnimatePresence>
			))}
		</div>
	);
};
