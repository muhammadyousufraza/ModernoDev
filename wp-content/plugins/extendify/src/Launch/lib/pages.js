import {
	BusinessInformation,
	state as businessInfoState,
} from '@launch/pages/BusinessInformation';
import {
	Goals,
	goalsFetcher,
	goalsParams as goalsData,
	state as goalsState,
	pluginsFetcher,
	pluginsParams as pluginsData,
} from '@launch/pages/Goals';
import {
	HomeSelect,
	fetcher as homeSelectFetcher,
	fetchData as homeSelectData,
	state as homeSelectState,
} from '@launch/pages/HomeSelect';
import {
	SiteInformation,
	fetcher as siteInfoFetcher,
	fetchData as siteInfoData,
	state as siteInfoState,
} from '@launch/pages/SiteInformation';
import {
	SiteStructure,
	state as siteStructureState,
} from '@launch/pages/SiteStructure';
import {
	SiteTypeSelect,
	state as siteTypeState,
} from '@launch/pages/SiteTypeSelect';

// This is the default pages array
// Pages can be added/removed dynamically, and override partnerSkipSteps
// You can add pre-fetch functions to start fetching data for the next page
// Supports both [] and single fetcher functions
const defaultPages = [
	[
		'site-type',
		{
			component: SiteTypeSelect,
			state: siteTypeState,
		},
	],
	[
		'site-title',
		{
			component: SiteInformation,
			fetcher: siteInfoFetcher,
			fetchData: siteInfoData,
			state: siteInfoState,
		},
	],
	[
		'goals',
		{
			component: Goals,
			fetcher: [goalsFetcher, pluginsFetcher],
			fetchData: [goalsData, pluginsData],
			state: goalsState,
		},
	],
	[
		'site-structure',
		{
			component: SiteStructure,
			state: siteStructureState,
		},
	],
	[
		'layout',
		{
			component: HomeSelect,
			fetcher: homeSelectFetcher,
			fetchData: homeSelectData,
			state: homeSelectState,
		},
	],
	[
		'business-information',
		{
			component: BusinessInformation,
			state: businessInfoState,
		},
	],
];

const pages = defaultPages?.filter(
	(pageKey) => !window.extOnbData?.partnerSkipSteps?.includes(pageKey[0]),
);
export { pages };
