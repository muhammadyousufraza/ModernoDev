import { __ } from '@wordpress/i18n';
import { updateUserMeta } from '@draft/api/WPApi';

export const ConsentSidebar = ({ setUserGaveConsent }) => {
	const { consentTermsHTML } = window.extSharedData;

	const userAcceptsTerms = async () => {
		setUserGaveConsent(true);
		window.extSharedData.userGaveConsent = '1';
		await updateUserMeta('ai_consent', true);
	};

	return (
		<>
			<div className="p-6">
				<h2 className="mb-2 mt-0 text-lg">
					{__('Terms of Use', 'extendify-local')}
				</h2>
				<p
					className="m-0"
					dangerouslySetInnerHTML={{ __html: consentTermsHTML }}></p>
				<button
					className="mt-4 w-full cursor-pointer rounded border-0 bg-wp-theme-main px-4 py-2 text-center text-white"
					type="button"
					onClick={() => userAcceptsTerms()}
					data-test="draft-terms-button">
					{__('Accept', 'extendify-local')}
				</button>
			</div>
		</>
	);
};
