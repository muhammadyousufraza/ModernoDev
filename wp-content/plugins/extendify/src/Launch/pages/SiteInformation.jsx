import { useEffect, useRef, useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';
import { getOption, updateOption } from '@launch/api/WPApi';
import { LoadingIndicator } from '@launch/components/LoadingIndicator';
import { Title } from '@launch/components/Title';
import { useFetch } from '@launch/hooks/useFetch';
import { PageLayout } from '@launch/layouts/PageLayout';
import { usePagesStore } from '@launch/state/Pages';
import { pageState } from '@launch/state/factory';
import { useUserSelectionStore } from '@launch/state/user-selections';

export const fetcher = async () => ({ title: await getOption('blogname') });
export const fetchData = () => ({ key: 'site-info' });
export const state = pageState('Site Information', () => ({
	ready: false,
	canSkip: false,
	validation: null,
	onRemove: () => {},
}));

export const SiteInformation = () => {
	const { loading } = useFetch(fetchData, fetcher);

	useEffect(() => {
		state.setState({ ready: !loading });
	}, [loading]);

	return (
		<PageLayout>
			<div className="grow overflow-y-scroll px-6 py-8 md:px-32 md:py-16">
				<Title
					title={__("What's the name of your new site?", 'extendify-local')}
					description={__('You can change this later.', 'extendify-local')}
				/>
				<div className="relative mx-auto w-full max-w-xl">
					{loading ? <LoadingIndicator /> : <Info />}
				</div>
			</div>
		</PageLayout>
	);
};

const Info = () => {
	const { siteInformation, setSiteInformation } = useUserSelectionStore();
	const nextPage = usePagesStore((state) => state.nextPage);
	const { data: siteInfoFromDb } = useFetch(fetchData, fetcher);
	const initialFocus = useRef(null);
	const [title, setTitle] = useState(siteInformation?.title);

	useEffect(() => {
		if (siteInformation.title !== undefined) return;
		setTitle(siteInfoFromDb?.title ?? '');
	}, [siteInfoFromDb.title, siteInformation.title]);

	useEffect(() => {
		if (title === undefined) return;
		state.setState({ ready: false });
		const id = setTimeout(() => {
			updateOption('blogname', title);
			setSiteInformation('title', title);
			state.setState({ ready: true });
		}, 750);
		return () => clearTimeout(id);
	}, [setSiteInformation, title]);

	useEffect(() => {
		const raf = requestAnimationFrame(() => initialFocus.current?.focus());
		return () => cancelAnimationFrame(raf);
	}, []);

	if (siteInformation?.title === undefined) {
		return <LoadingIndicator />;
	}

	return (
		<form
			onSubmit={(e) => {
				e.preventDefault();
				if (!state.getState().ready) return;
				nextPage();
			}}>
			<label htmlFor="extendify-site-title-input" className="sr-only">
				{__("What's the name of your website?", 'extendify-local')}
			</label>
			<div className="mb-8">
				<input
					data-test="site-title-input"
					autoComplete="off"
					ref={initialFocus}
					type="text"
					name="site-title-input"
					id="extendify-site-title-input"
					className="input-focus h-12 w-full rounded border border-gray-200 px-4 py-6 ring-offset-0"
					value={decodeEntities(title) ?? ''}
					onChange={(e) => setTitle(e.target.value)}
					placeholder={__('Enter your website name', 'extendify-local')}
				/>
			</div>
		</form>
	);
};
