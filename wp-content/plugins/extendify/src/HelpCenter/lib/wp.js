import apiFetch from '@wordpress/api-fetch';

export const updateUserMeta = (option, value) =>
	apiFetch({
		path: '/extendify/v1/shared/update-user-meta',
		method: 'POST',
		data: { option, value },
	});
