import { __ } from '@wordpress/i18n';
import classNames from 'classnames';

export const AcceptTerms = ({
	setAcceptTerms,
	acceptTerms,
	consentTermsHTML,
}) => {
	return (
		<div className="flex flex-col">
			<label
				htmlFor="accept-terms"
				className="focus-within:text-design-mains ml-1 flex cursor-pointer items-center text-base">
				<span className="relative">
					<input
						id="accept-terms"
						className="h-4 w-4 rounded-sm focus:ring-0 focus:ring-offset-0"
						type="checkbox"
						onChange={() => setAcceptTerms(!acceptTerms)}
						checked={acceptTerms}
					/>
					<svg
						className={classNames('absolute inset-0 block h-5 w-4', {
							'text-white': acceptTerms,
							'text-transparent': !acceptTerms,
						})}
						viewBox="1 0 20 20"
						fill="none"
						xmlns="http://www.w3.org/2000/svg"
						role="presentation">
						<path
							d="M8.72912 13.7449L5.77536 10.7911L4.76953 11.7899L8.72912 15.7495L17.2291 7.24948L16.2304 6.25073L8.72912 13.7449Z"
							fill="currentColor"
						/>
					</svg>
				</span>
				<span className="m-0 ml-1.5 text-lg font-medium text-gray-900 md:text-base">
					{__('I agree (required to use AI Assistant)', 'extendify-local')}
				</span>
			</label>
			<p
				className="m-0 mx-7 mb-2 mt-1 p-0 text-sm text-gray-700"
				dangerouslySetInnerHTML={{ __html: consentTermsHTML }}
			/>
		</div>
	);
};
