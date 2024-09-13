import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useTasksStore } from '@assist/state/tasks';

const launchSteps = {
	'site-type': {
		step: __('Site Industry', 'extendify-local'),
		title: __("Let's Start Building Your Website", 'extendify-local'),
		description: __(
			'Create a super-fast, beautiful, and fully customized site in minutes with our Site Launcher.',
			'extendify-local',
		),
		buttonText: __('Select Site Industry', 'extendify-local'),
	},
	'site-title': {
		step: __('Site Title', 'extendify-local'),
		title: __('Continue Building Your Website', 'extendify-local'),
		description: __(
			'Create a super-fast, beautiful, and fully customized site in minutes with our Site Launcher.',
			'extendify-local',
		),
		buttonText: __('Set Site Title', 'extendify-local'),
	},
	goals: {
		step: __('Goals', 'extendify-local'),
		title: __('Continue Building Your Website', 'extendify-local'),
		description: __(
			'Create a super-fast, beautiful, and fully customized site in minutes with our Site Launcher.',
			'extendify-local',
		),
		buttonText: __('Select Site Goals', 'extendify-local'),
	},
	layout: {
		step: __('Design', 'extendify-local'),
		title: __('Continue Building Your Website', 'extendify-local'),
		description: __(
			'Create a super-fast, beautiful, and fully customized site in minutes with our Site Launcher.',
			'extendify-local',
		),
		buttonText: __('Select Site Design', 'extendify-local'),
	},
	pages: {
		step: __('Pages', 'extendify-local'),
		title: __('Continue Building Your Website', 'extendify-local'),
		description: __(
			'Create a super-fast, beautiful, and fully customized site in minutes with our Site Launcher.',
			'extendify-local',
		),
		buttonText: __('Select Site Pages', 'extendify-local'),
	},
};

const getCurrentLaunchStep = () => {
	const pageData = JSON.parse(
		localStorage.getItem(`extendify-pages-${window.extSharedData.siteId}`),
	) || { state: {} };
	const currentPageSlug = pageData?.state?.currentPageSlug;

	// If their last step doesn't exist in our options, just use step 1
	if (!Object.keys(launchSteps).includes(currentPageSlug)) {
		return 'site-type';
	}

	return currentPageSlug;
};

export const LaunchCard = ({ task }) => {
	const [currentStep, setCurrentStep] = useState();
	const { dismissTask } = useTasksStore();

	useEffect(() => {
		if (currentStep) return;
		setCurrentStep(getCurrentLaunchStep());
	}, [currentStep]);

	return (
		<div className="h-full justify-center overflow-hidden bg-design-main text-base">
			<div className="mx-11 my-16">
				<img
					alt="preview"
					className="block w-full object-cover"
					src={task.backgroundImage}
				/>
				<div className="w-full text-center">
					<h2 className="mb-4 mt-8 text-2xl text-white">
						{launchSteps[currentStep]?.title}
					</h2>
					<p className="my-4 text-base text-gray-50">
						{launchSteps[currentStep]?.description}
					</p>
					<div>
						<a
							href={`${window.extSharedData.adminUrl}admin.php?page=extendify-launch`}
							className="mt-4 inline-block cursor-pointer rounded border-none bg-white px-4 py-2.5 text-gray-900 no-underline">
							{launchSteps[currentStep]?.buttonText}
						</a>
						<button
							type="button"
							id="dismiss"
							onClick={() => {
								dismissTask('site-builder-launcher');
							}}
							className="mx-3 cursor-pointer bg-transparent px-2 py-2 text-center text-sm text-design-text">
							{__('Dismiss', 'extendify-local')}
						</button>
					</div>
				</div>
			</div>
		</div>
	);
};
