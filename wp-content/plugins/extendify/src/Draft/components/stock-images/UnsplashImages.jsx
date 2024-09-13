import { __ } from '@wordpress/i18n';
import { UnsplashImage } from './UnsplashImage';

export const UnsplashImages = ({
	imageData,
	isInsertingImage,
	onClick,
	loading,
}) => {
	const imageLength = imageData?.images?.length ?? 10;

	if (!loading && !imageData.images.length) {
		return __('No images found.', 'extendify-local');
	}

	return (
		<div className="columns-2 gap-1">
			{Array.from({ length: imageLength }).map((_, idx) => {
				const skeletonHeight = [150, 175, 200];
				return (
					<UnsplashImage
						key={imageData?.images?.[idx]?.id ?? idx}
						image={imageData?.images?.[idx]}
						skeletonHeight={skeletonHeight[idx % skeletonHeight.length]}
						isInsertingImage={isInsertingImage}
						onClick={onClick}
					/>
				);
			})}
		</div>
	);
};
