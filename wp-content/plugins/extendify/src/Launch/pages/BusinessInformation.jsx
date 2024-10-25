import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { AcceptTerms } from '@launch/components/BusinessInformation/AcceptTerms';
import { InfoBox } from '@launch/components/BusinessInformation/InfoBox';
import { SiteTones } from '@launch/components/BusinessInformation/Tones';
import { Title } from '@launch/components/Title';
import { PageLayout } from '@launch/layouts/PageLayout';
import { usePagesStore } from '@launch/state/Pages';
import { pageState } from '@launch/state/factory';
import { useUserSelectionStore } from '@launch/state/user-selections';

export const state = pageState('Business Information', () => ({
	ready: true,
	canSkip: true,
	validation: null,
	onRemove: () => {},
}));

export const BusinessInformation = () => (
	<PageLayout>
		<div className="grow overflow-y-scroll px-6 py-8 md:px-32 md:py-16">
			<Title
				title={__(
					'Let us create custom copy for your website',
					'extendify-local',
				)}
				description={__(
					"Our AI Assistant will take your input and create customized copy for each page. Describe your website or business with as much detail as you'd like and we'll use it to create your perfect site.",
					'extendify-local',
				)}
			/>
			<div className="relative mx-auto w-full max-w-xl">
				<BusinessInfo />
			</div>
		</div>
	</PageLayout>
);

const BusinessInfo = () => {
	const { businessInformation, setBusinessInformation } =
		useUserSelectionStore();
	const [desc, setDesc] = useState(businessInformation?.description || '');
	const nextPage = usePagesStore((state) => state.nextPage);
	const consentTermsHTML = window.extSharedData?.consentTermsHTML;
	const showAIConsent = window.extSharedData?.showAIConsent && consentTermsHTML;

	useEffect(() => {
		const timer = setTimeout(() => {
			setBusinessInformation('description', desc);
		}, 500);
		state.setState({ canSkip: !desc });
		return () => clearTimeout(timer);
	}, [desc, setBusinessInformation]);

	useEffect(() => {
		if (!showAIConsent) return;
		if (businessInformation.acceptTerms || !businessInformation.description) {
			state.setState({ validation: null });
			return;
		}
		state.setState({
			validation: {
				message: __('Please accept the terms to continue', 'extendify-local'),
			},
		});
	}, [businessInformation, showAIConsent]);

	return (
		<form
			onSubmit={(e) => {
				e.preventDefault();
				if (!state.getState().ready) return;
				nextPage();
			}}>
			<div className="mb-2">
				<InfoBox description={desc} setDescription={setDesc} />
			</div>
			<div className="mb-8">
				<SiteTones />
			</div>
			{showAIConsent ? (
				<div className="mb-8 flex items-center">
					<AcceptTerms
						consentTermsHTML={consentTermsHTML}
						acceptTerms={businessInformation.acceptTerms}
						setAcceptTerms={(acceptTerms) => {
							setBusinessInformation('acceptTerms', acceptTerms);
						}}
					/>
				</div>
			) : null}
		</form>
	);
};
