import { __ } from '@wordpress/i18n';

export const InfoBox = ({ description, setDescription }) => (
	<>
		<label
			htmlFor="extendify-business-info-input"
			className="m-0 text-lg font-medium leading-8 text-gray-900 md:text-base md:leading-10">
			{__('Website description', 'extendify-local')}
		</label>
		<textarea
			data-test="business-info-input"
			autoComplete="off"
			autoFocus={true}
			rows="4"
			name="business-info-input"
			id="extendify-business-info-input"
			className={
				'input-focus placeholder:text-md h-40 w-full rounded-lg border border-gray-300 p-2 ring-offset-0 placeholder:italic placeholder:opacity-50'
			}
			value={description ?? ''}
			onChange={(e) => setDescription(e.target.value)}
			placeholder={__(
				'E.g., We are a yoga studio in London with professionally trained instructors with focus on hot yoga for therapeutic purposes.',
				'extendify-local',
			)}
		/>
	</>
);
