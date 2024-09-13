import {
	useEffect,
	useLayoutEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Icon, arrowRight } from '@wordpress/icons';
import classNames from 'classnames';
import { Title } from '@launch/components/Title';
import { PageLayout } from '@launch/layouts/PageLayout';
import { usePagesStore } from '@launch/state/Pages';
import { pageState } from '@launch/state/factory';
import { useUserSelectionStore } from '@launch/state/user-selections';

export const state = pageState('Site Template Type', () => ({
	ready: false,
	canSkip: false,
	validation: null,
	onRemove: () => {},
}));

export const SiteStructure = () => {
	const { siteStructure, setSiteStructure } = useUserSelectionStore();
	const { removePage, addPage } = usePagesStore();
	const [firstButton, setFirstButton] = useState(siteStructure);

	useLayoutEffect(() => {
		if (firstButton) return;
		// Randomize the order of the buttons
		const random = ['single-page', 'multi-page'].sort(
			() => Math.random() - 0.5,
		);
		setFirstButton(random[0]);
		setSiteStructure(random[0]);
	}, [setSiteStructure, firstButton]);

	useEffect(() => {
		state.setState({ ready: !!siteStructure && firstButton });
	}, [siteStructure, addPage, removePage, firstButton]);

	const buttons = useMemo(
		() => [
			<ButtonSelect
				key="single-page"
				onClick={() => setSiteStructure('single-page')}
				selected={siteStructure === 'single-page'}
				imageSrc="https://assets.extendify.com/launch/single-page-website.webp"
				title={__('Single-Page Website', 'extendify-local')}
				description={__(
					'All content displayed on one scrolling page.',
					'extendify-local',
				)}
			/>,
			<ButtonSelect
				key="multi-page"
				onClick={() => setSiteStructure('multi-page')}
				selected={siteStructure === 'multi-page'}
				imageSrc="https://assets.extendify.com/launch/multi-page-website.webp"
				title={__('Multi-Page Website', 'extendify-local')}
				description={__('Multiple interconnected pages.', 'extendify-local')}
			/>,
		],
		[siteStructure, setSiteStructure],
	);
	const buttonsOrdered =
		firstButton === 'multi-page' ? buttons.toReversed() : buttons;

	return (
		<PageLayout>
			<div className="grow overflow-y-scroll px-6 py-8 md:px-32 md:py-16">
				<Title title={__('Pick Your Site Structure', 'extendify-local')} />
				<div className="relative mx-auto flex w-full max-w-3xl flex-col gap-4 lg:flex-row lg:gap-8">
					{buttonsOrdered}
				</div>
			</div>
		</PageLayout>
	);
};

const ButtonSelect = ({ title, description, onClick, selected, imageSrc }) => (
	<div
		data-test="site-template-type"
		className={classNames(
			'relative flex-1 cursor-pointer overflow-hidden rounded border border-gray-200 ring-offset-2 ring-offset-white focus-within:outline-none focus-within:ring-4 focus-within:ring-design-main focus-within:ring-offset-2 focus-within:ring-offset-white hover:outline-none hover:ring-4',
			{
				'ring-4 ring-design-main ring-offset-2 ring-offset-white hover:ring-design-main':
					selected,
				'hover:ring-gray-300': !selected,
			},
		)}
		role="button"
		tabIndex={0}
		aria-label={__('Press to select', 'extendify-local')}
		aria-selected={selected}
		onKeyDown={(e) => {
			if (!['Enter', 'Space', ' '].includes(e.key)) return;
			e.preventDefault();
			onClick();
		}}
		onClick={onClick}>
		<div className="aspect-none hidden w-full justify-center overflow-hidden bg-gray-100 group-hover:opacity-75 lg:flex lg:h-80">
			<img
				alt=""
				src={imageSrc}
				className="h-full object-cover object-center lg:h-full"
			/>
		</div>
		<div className="p-4 lg:p-6">
			<p className="m-0 mb-4 p-0 text-gray-700">{description}</p>
			<div className="flex items-center justify-between">
				<h1 className="m-0 p-0 text-lg font-semibold">{title}</h1>
				<Icon icon={arrowRight} />
			</div>
		</div>
	</div>
);
