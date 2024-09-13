import useSWRImmutable from 'swr/immutable';
import { searchUnsplash } from '@draft/api/Data';

export const useUnsplashImages = (search) => {
	const { data, error } = useSWRImmutable(search || 'unsplash', searchUnsplash);

	return { data, error, loading: !data && !error };
};
